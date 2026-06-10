<?php
/**
 * Ambient Documentation — Main API Endpoint
 *
 * Actions (pass via ?action=xxx):
 *   ping             — health check
 *   create_session   — start a new recording session
 *   get_session      — get session status
 *   upload_chunk     — upload audio blob → transcribe → accumulate transcript
 *   process_session  — de-identify → AI SOAP note → save to DB
 *   log_section_action — log clinician interaction with a SOAP section
 *   save_clinician_note — save final clinician-edited note
 *   check_setup      — probe Whisper / Ollama / OpenAI connectivity
 */

// ── Load OpenEMR core ─────────────────────────────────────────
$globalsPath = realpath(__DIR__ . '/../../../../../../../../interface/globals.php')
    ?: realpath(__DIR__ . '/../../../../../interface/globals.php')
    ?: realpath(__DIR__ . '/../../../../../../interface/globals.php');

if (!$globalsPath || !file_exists($globalsPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot find OpenEMR globals.php — check module installation path']);
    exit;
}
require_once $globalsPath;

// ── Load Composer autoloader ──────────────────────────────────
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Run composer install in module directory']);
    exit;
}
require_once $autoloadPath;

// ── Load .env ─────────────────────────────────────────────────
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ── Security: must be logged in ──────────────────────────────
if (!isset($_SESSION['authUser'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated. Please log in to OpenEMR.']);
    exit;
}

$isLocal = ($_ENV['APP_ENV'] ?? 'production') === 'local';
$isAjax  = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isAjax && !$isLocal) {
    http_response_code(403);
    echo json_encode(['error' => 'Direct access not allowed.']);
    exit;
}

// ── Output buffer: intercept die() / SQL errors ──────────────
// OpenEMR's sqlStatement() calls die("SQL Statement Failed") on
// any query error. die() flushes the ob buffer at PHP shutdown,
// so ob_end_clean() never runs. Using ob_start($callback) means
// PHP passes the buffer through our callback on every flush —
// including the implicit flush triggered by die().
// If the buffered content isn't valid JSON we replace it with a
// JSON error object so the browser always gets parseable output.
ob_start(function (string $buf): string {
    $trimmed = ltrim($buf);
    if ($trimmed === '') {
        return $buf;
    }
    // Valid JSON starts with { [ " t f n or a digit
    $first = $trimmed[0];
    if ($first === '{' || $first === '[' || $first === '"'
        || $first === 't' || $first === 'f' || $first === 'n'
        || ($first >= '0' && $first <= '9')
    ) {
        return $buf;
    }
    // Non-JSON leaked (SQL error, PHP notice, HelpfulDie HTML, etc.)
    error_log('AmbientDocs output leak: ' . substr($trimmed, 0, 400));
    return json_encode([
        'error'  => 'Internal server error',
        'detail' => 'A server-side error occurred. Check PHP error log.',
    ]);
});

// ── Parse request ─────────────────────────────────────────────
header('Content-Type: application/json');

// Action always comes from query string (?action=xxx) so the router
// never has to read php://input before the switch — body stays intact.
$action = $_GET['action'] ?? null;

// Parse JSON body once (for non-multipart requests)
$body = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

// Fallback: if action still missing, look in body
if (!$action) {
    $action = $body['action'] ?? null;
}

// ── Services ──────────────────────────────────────────────────
use Clinic\OeModuleAmbientDocs\Service\AuditService;
$audit = new AuditService();

// ── Route ─────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── PING (health check) ────────────────────────────────────
        case 'ping':
            echo json_encode([
                'status'  => 'ok',
                'module'  => 'oe-module-ambient-docs',
                'version' => '1.0.0',
                'php'     => PHP_VERSION,
                'user'    => $_SESSION['authUser'] ?? 'unknown',
                'env'     => $_ENV['APP_ENV'] ?? 'production',
            ]);
            break;

        // ── CREATE SESSION ─────────────────────────────────────────
        case 'create_session':
            $encounterId = (int)($body['encounter_id'] ?? 0);
            $patientId   = (int)($body['patient_id']   ?? 0);
            $providerId  = (int)($_SESSION['authUserID'] ?? 0);

            if (!$encounterId || !$patientId) {
                http_response_code(400);
                echo json_encode(['error' => 'encounter_id and patient_id are required']);
                exit;
            }

            // Resume existing active session for this encounter
            $existing = sqlQuery(
                "SELECT id, status FROM ambient_recording_sessions
                 WHERE encounter_id = ? AND status = 'recording'
                 ORDER BY id DESC LIMIT 1",
                [$encounterId]
            );
            if ($existing) {
                echo json_encode([
                    'session_id' => (int)$existing['id'],
                    'status'     => $existing['status'],
                    'resumed'    => true,
                    'message'    => 'Resumed existing recording session',
                ]);
                break;
            }

            $sessionId = sqlInsert(
                "INSERT INTO ambient_recording_sessions
                 (encounter_id, patient_id, provider_id, started_at, status)
                 VALUES (?, ?, ?, NOW(), 'recording')",
                [$encounterId, $patientId, $providerId]
            );

            $audit->recordingStarted((int)$sessionId, $encounterId, $patientId);

            echo json_encode([
                'session_id' => (int)$sessionId,
                'status'     => 'recording',
                'resumed'    => false,
                'message'    => 'Recording session created successfully',
            ]);
            break;

        // ── GET SESSION STATUS ─────────────────────────────────────
        case 'get_session':
            $sessionId = (int)($body['session_id'] ?? $_GET['session_id'] ?? 0);
            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id is required']);
                exit;
            }
            $session = sqlQuery(
                "SELECT s.*, n.id as ai_note_id
                 FROM ambient_recording_sessions s
                 LEFT JOIN ambient_ai_notes n ON n.session_id = s.id
                 WHERE s.id = ?",
                [$sessionId]
            );
            if (!$session) {
                http_response_code(404);
                echo json_encode(['error' => 'Session not found']);
                exit;
            }
            echo json_encode([
                'session_id'   => (int)$session['id'],
                'encounter_id' => (int)$session['encounter_id'],
                'patient_id'   => (int)$session['patient_id'],
                'status'       => $session['status'],
                'started_at'   => $session['started_at'],
                'ended_at'     => $session['ended_at'],
                'has_ai_note'  => !empty($session['ai_note_id']),
            ]);
            break;

        // ── UPLOAD AUDIO CHUNK → SAVE ONLY ───────────────────────
        //
        // We do NOT transcribe here. Browser MediaRecorder chunks are
        // fragments of a WebM stream — only the full concatenated file
        // is a valid, decodable audio file. Each chunk is appended to
        // a single session recording file and transcription happens once
        // in process_session when the complete audio is available.
        case 'upload_chunk':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $isFinal   = ($_POST['is_final'] ?? '0') === '1';

            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id is required']);
                exit;
            }

            $session = sqlQuery(
                "SELECT * FROM ambient_recording_sessions WHERE id = ?",
                [$sessionId]
            );
            if (!$session) {
                http_response_code(404);
                echo json_encode(['error' => 'Session not found']);
                exit;
            }

            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                $uploadError = $_FILES['audio']['error'] ?? 'no file';
                http_response_code(400);
                echo json_encode([
                    'error'        => 'No valid audio file uploaded',
                    'upload_error' => $uploadError,
                ]);
                exit;
            }

            // Create session storage directory
            $chunkDir      = __DIR__ . '/../storage/chunks/session_' . $sessionId;
            $assembledFile = $chunkDir . '/recording.webm';

            if (!is_dir($chunkDir)) {
                if (!mkdir($chunkDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create storage directory — check permissions']);
                    exit;
                }
            }

            // Append this chunk's bytes to the cumulative recording file.
            // Chunk 1 contains the WebM EBML header; subsequent chunks add
            // Cluster data. Appending in order produces a complete, valid file.
            $chunkBytes = file_get_contents($_FILES['audio']['tmp_name']);
            if ($chunkBytes === false || file_put_contents($assembledFile, $chunkBytes, FILE_APPEND | LOCK_EX) === false) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save audio chunk — check storage directory permissions']);
                exit;
            }

            // Track chunk count for the UI (no transcription here)
            if (!isset($_SESSION['ambient_chunks'])) {
                $_SESSION['ambient_chunks'] = [];
            }
            if (!isset($_SESSION['ambient_chunks'][$sessionId])) {
                $_SESSION['ambient_chunks'][$sessionId] = ['count' => 0, 'bytes' => 0];
            }
            $_SESSION['ambient_chunks'][$sessionId]['count']++;
            $_SESSION['ambient_chunks'][$sessionId]['bytes'] += strlen($chunkBytes);

            $chunkCount = $_SESSION['ambient_chunks'][$sessionId]['count'];
            $totalBytes = $_SESSION['ambient_chunks'][$sessionId]['bytes'];

            if ($isFinal) {
                sqlStatement(
                    "UPDATE ambient_recording_sessions
                     SET status = 'processing', ended_at = NOW() WHERE id = ?",
                    [$sessionId]
                );
            }

            echo json_encode([
                'success'      => true,
                'session_id'   => $sessionId,
                'chunks_saved' => $chunkCount,
                'bytes_saved'  => $totalBytes,
                'is_final'     => $isFinal,
            ]);
            break;

        // ── PROCESS SESSION → SOAP NOTE ────────────────────────────
        case 'process_session':
            $sessionId = (int)($body['session_id'] ?? 0);

            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id is required']);
                exit;
            }

            $session = sqlQuery(
                "SELECT * FROM ambient_recording_sessions WHERE id = ?",
                [$sessionId]
            );
            if (!$session) {
                http_response_code(404);
                echo json_encode(['error' => 'Session not found']);
                exit;
            }

            // ── Step 0: Transcribe assembled audio (if not already done) ─
            //
            // upload_chunk saves all audio fragments to storage/chunks/session_N/recording.webm.
            // We transcribe the complete file here, once, so faster-whisper gets
            // a valid WebM stream rather than an undecodable fragment.
            $chunkDir      = __DIR__ . '/../storage/chunks/session_' . $sessionId;
            $assembledFile = $chunkDir . '/recording.webm';
            $accumulated   = $_SESSION['ambient_transcripts'][$sessionId] ?? null;

            if (!$accumulated || empty(trim($accumulated['raw_text'] ?? ''))) {
                // No in-session transcript yet — transcribe the assembled file
                if (!file_exists($assembledFile) || filesize($assembledFile) < 100) {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'No audio recording found for this session. '
                            . 'Please record audio first.',
                    ]);
                    exit;
                }

                $whisper          = new \Clinic\OeModuleAmbientDocs\Service\WhisperService();
                $transcriptResult = $whisper->transcribe($assembledFile);

                // Remove the assembled audio immediately after transcription — PHI
                @unlink($assembledFile);
                @rmdir($chunkDir);
                unset($_SESSION['ambient_chunks'][$sessionId]);

                if ($transcriptResult['error']) {
                    $audit->errorOccurred($sessionId, $transcriptResult['error'], 'process_session_transcribe');
                    http_response_code(500);
                    echo json_encode([
                        'error'  => 'Transcription failed',
                        'detail' => $isLocal ? $transcriptResult['error'] : 'Check server error log',
                    ]);
                    exit;
                }

                $accumulated = [
                    'raw_text'    => $transcriptResult['raw_transcript'],
                    'diarization' => $transcriptResult['diarized_transcript'] ?? [],
                    'word_count'  => $transcriptResult['word_count'],
                ];

                $audit->sttCompleted($sessionId, 0, $transcriptResult['word_count']);
            }

            if (empty(trim($accumulated['raw_text'] ?? ''))) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'The recording produced no transcribable speech. '
                        . 'Please check your microphone and try again.',
                ]);
                exit;
            }

            $startTime = microtime(true);

            // Step 1 — De-identify
            $phi       = new \Clinic\OeModuleAmbientDocs\Service\PhiDeidentifyService();
            $deIdResult = $phi->deidentify(trim($accumulated['raw_text']), (string)$sessionId);
            $audit->phiDeidentified($sessionId, $deIdResult['token_count']);

            // Step 2 — Build patient context from OpenEMR DB
            // (sent to FastAPI /generate_soap alongside the transcript)
            $patientId   = (int)$session['patient_id'];
            $encounterId = (int)$session['encounter_id'];

            // Patient demographics
            $demographics = [];
            try {
                $patientRow = sqlQuery(
                    "SELECT fname, lname, DOB, sex, pubpid FROM patient_data WHERE pid = ?",
                    [$patientId]
                );
                if ($patientRow) {
                    $dob = $patientRow['DOB'] ?? '';
                    $age = $dob
                        ? (new \DateTime())->diff(new \DateTime($dob))->y
                        : null;
                    $demographics = [
                        'patient_id'    => $patientId,
                        'name'          => trim(($patientRow['fname'] ?? '') . ' ' . ($patientRow['lname'] ?? '')),
                        'date_of_birth' => $dob,
                        'age'           => $age,
                        'sex'           => $patientRow['sex']    ?? '',
                        'mrn'           => $patientRow['pubpid'] ?? (string)$patientId,
                    ];
                }
            } catch (\Exception $e) {
                error_log('AmbientDocs: demographics query failed — ' . $e->getMessage());
            }

            // Vitals for this encounter via the forms join table.
            // form_vitals has no direct encounter column; the link is:
            //   forms.form_id = form_vitals.id  WHERE forms.formdir = 'vitals'
            // Falls back to the most-recent vitals for the patient if none
            // are recorded for this specific encounter.
            $vitals = [];
            try {
                $vitalsRow = sqlQuery(
                    "SELECT v.*
                     FROM form_vitals v
                     JOIN forms f ON f.form_id = v.id
                                 AND f.formdir  = 'vitals'
                                 AND f.deleted  = 0
                     WHERE f.pid = ? AND f.encounter = ?
                     ORDER BY v.date DESC LIMIT 1",
                    [$patientId, $encounterId]
                );
                // Fallback: no vitals for this encounter — get most recent overall
                if (!$vitalsRow) {
                    $vitalsRow = sqlQuery(
                        "SELECT * FROM form_vitals WHERE pid = ?
                         ORDER BY date DESC LIMIT 1",
                        [$patientId]
                    );
                }
                if ($vitalsRow) {
                    // bps / bpd are string in the FastAPI schema (e.g. "120", "80")
                    $bps = $vitalsRow['bps'] ? (string)(int)$vitalsRow['bps'] : '';
                    $bpd = $vitalsRow['bpd'] ? (string)(int)$vitalsRow['bpd'] : '';
                    $vitals = [
                        'bps'               => $bps,
                        'bpd'               => $bpd,
                        'bp_display'        => ($bps !== '' && $bpd !== '') ? "{$bps}/{$bpd}" : '',
                        'pulse'             => $vitalsRow['pulse']             ? (int)$vitalsRow['pulse']               : 0,
                        'respiration'       => $vitalsRow['respiration']       ? (int)$vitalsRow['respiration']         : 0,
                        'temperature'       => $vitalsRow['temperature']       ? (float)$vitalsRow['temperature']       : 0.0,
                        'weight'            => $vitalsRow['weight']            ? (int)$vitalsRow['weight']              : 0,
                        'weight_unit'       => 'lbs',
                        'height'            => $vitalsRow['height']            ? (int)$vitalsRow['height']              : 0,
                        'height_unit'       => 'in',
                        'oxygen_saturation' => $vitalsRow['oxygen_saturation'] ? (int)$vitalsRow['oxygen_saturation']   : 0,
                        'oxygen_delivery'   => $vitalsRow['oxygen_delivery']   ?? 'room air',
                        'BMI'               => $vitalsRow['BMI']               ? (float)$vitalsRow['BMI']               : 0.0,
                        'BMI_status'        => $vitalsRow['BMI_status']        ?? '',
                        'captured'          => $vitalsRow['date']              ?? '',
                    ];
                }
            } catch (\Exception $e) {
                error_log('AmbientDocs: vitals query failed — ' . $e->getMessage());
            }

            // Problem list
            $problemList = [];
            try {
                // lists table has no severity column for medical_problem rows.
                // Allergy severity is in severity_al — medical problems simply
                // don't store a severity, so we default to an empty string.
                $problemRes = sqlStatement(
                    "SELECT title, diagnosis, begdate FROM lists
                     WHERE pid = ? AND type = 'medical_problem' AND activity = 1",
                    [$patientId]
                );
                while ($row = sqlFetchArray($problemRes)) {
                    $onsetYear = null;
                    if (!empty($row['begdate']) && strpos($row['begdate'], '0000') === false) {
                        $onsetYear = (int)date('Y', strtotime($row['begdate']));
                    }
                    $problemList[] = [
                        'title'      => $row['title']     ?? '',
                        'display'    => $row['title']     ?? '',
                        'icd10'      => $row['diagnosis'] ?? '',
                        'onset_year' => $onsetYear,
                        'severity'   => '',
                        'status'     => 'active',
                    ];
                }
            } catch (\Exception $e) {
                error_log('AmbientDocs: problem list query failed — ' . $e->getMessage());
            }

            // Active medications
            // lists table stores dose/frequency in the `comments` column
            $medications = [];
            try {
                $medRes = sqlStatement(
                    "SELECT title, comments FROM lists
                     WHERE pid = ? AND type = 'medication' AND activity = 1",
                    [$patientId]
                );
                while ($row = sqlFetchArray($medRes)) {
                    // Field names must match ActiveMedication schema:
                    // drug_name, dosage, frequency, route, indication
                    // OpenEMR stores dose/sig in the `comments` column
                    $medications[] = [
                        'drug_name'  => $row['title']    ?? '',
                        'dosage'     => $row['comments'] ?? '',
                        'frequency'  => '',
                        'route'      => '',
                        'indication' => '',
                    ];
                }
            } catch (\Exception $e) {
                error_log('AmbientDocs: medications query failed — ' . $e->getMessage());
            }

            // Allergies
            $allergies = [];
            try {
                // severity_al is the actual column name for allergy severity in lists
                $allergyRes = sqlStatement(
                    "SELECT title, reaction, severity_al FROM lists
                     WHERE pid = ? AND type = 'allergy' AND activity = 1",
                    [$patientId]
                );
                while ($row = sqlFetchArray($allergyRes)) {
                    // Field names must match Allergy schema:
                    // allergen, allergen_type, reaction, severity
                    $allergies[] = [
                        'allergen'      => $row['title']       ?? '',
                        'allergen_type' => 'medication',
                        'reaction'      => $row['reaction']    ?? '',
                        'severity'      => $row['severity_al'] ?? '',
                    ];
                }
            } catch (\Exception $e) {
                error_log('AmbientDocs: allergies query failed — ' . $e->getMessage());
            }

            // Encounter metadata
            $encounterRow = [];
            try {
                $encounterRow = sqlQuery(
                    "SELECT date, reason FROM form_encounter
                     WHERE encounter = ? AND pid = ? LIMIT 1",
                    [$encounterId, $patientId]
                ) ?: [];
            } catch (\Exception $e) {
                error_log('AmbientDocs: encounter query failed — ' . $e->getMessage());
            }

            $patientContext = [
                'demographics'      => $demographics,
                'problem_list'      => $problemList,
                'active_medications' => $medications,
                'allergies'         => $allergies,
                'vitals_current'    => $vitals,
                'labs_recent'       => [],   // populated if lab module is active
                'diagnostic_tests'  => [],
                'social_history'    => (object)[],
                'family_history'    => [],
                'intake_form'       => (object)[],
                'last_visit'        => (object)[],
            ];

            $encounterContext = [
                'session_id'           => $sessionId,
                'encounter_id'         => $encounterId,
                'encounter_date'       => $encounterRow['date']   ?? date('Y-m-d'),
                'encounter_time'       => date('H:i:s'),
                'encounter_type'       => $encounterRow['reason'] ?? 'office_visit',
                'provider_credentials' => $_SESSION['userTitle']  ?? 'MD',
                'clinic_name'          => $GLOBALS['openemr_name'] ?? 'Clinic',
                'location'             => $GLOBALS['facility']    ?? 'Main Office',
            ];

            // Step 3 — Generate SOAP note via FastAPI
            $noteService = new \Clinic\OeModuleAmbientDocs\Service\NoteStructuringService();
            $aiResult    = $noteService->structure(
                $deIdResult['deidentified_text'],
                $accumulated['diarization'] ?? [],
                $patientContext,
                $encounterContext
            );

            if ($aiResult['error']) {
                $audit->errorOccurred($sessionId, $aiResult['error'], 'process_session');
                http_response_code(500);
                ob_end_clean();
                echo json_encode([
                    'error'  => 'AI note generation failed',
                    'detail' => $isLocal ? $aiResult['error'] : 'Check error log',
                ]);
                exit;
            }

            // Step 4 — Re-insert PHI tokens into note
            $soapJson         = json_encode($aiResult['soap_note']);
            $reidentifiedJson = $phi->reidentify($soapJson, (string)$sessionId);
            $finalSoapNote    = json_decode($reidentifiedJson, true);
            $phi->clearSession((string)$sessionId);

            $processingMs = (int)((microtime(true) - $startTime) * 1000);

            // Step 5 — Save to DB
            $aiNoteId = 0;
            try {
                $aiNoteId = sqlInsert(
                    "INSERT INTO ambient_ai_notes
                     (session_id, encounter_id, raw_transcript,
                      diarized_transcript, ai_soap_note,
                      confidence_scores, icd10_codes,
                      model_version, prompt_hash, processing_ms, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,NOW())",
                    [
                        $sessionId,
                        $session['encounter_id'],
                        $accumulated['raw_text'],
                        json_encode($accumulated['diarization'] ?? []),
                        json_encode($finalSoapNote),
                        json_encode($finalSoapNote['confidence_scores'] ?? []),
                        json_encode($finalSoapNote['extracted_codes']['icd10'] ?? []),
                        $aiResult['model_version'],
                        $aiResult['prompt_hash'],
                        $processingMs,
                    ]
                );
            } catch (\Exception $e) {
                error_log('AmbientDocs: ambient_ai_notes INSERT failed — ' . $e->getMessage());
            }

            // Step 6 — Mark session complete
            try {
                sqlStatement(
                    "UPDATE ambient_recording_sessions
                     SET status = 'complete', ended_at = NOW(),
                         duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                     WHERE id = ?",
                    [$sessionId]
                );
            } catch (\Exception $e) {
                error_log('AmbientDocs: session status UPDATE failed — ' . $e->getMessage());
            }

            $audit->noteGenerated(
                $sessionId,
                $aiResult['model_version'],
                $aiResult['prompt_hash'],
                $processingMs
            );

            // Step 7 — Clean up accumulated transcript
            unset($_SESSION['ambient_transcripts'][$sessionId]);

            ob_end_clean();
            echo json_encode([
                'success'           => true,
                'session_id'        => $sessionId,
                'ai_note_id'        => (int)$aiNoteId,
                'soap_note'         => $finalSoapNote,
                'confidence_scores' => $finalSoapNote['confidence_scores'] ?? [],
                'visit_type'        => $finalSoapNote['visit_type'] ?? 'unknown',
                'icd10_codes'       => $finalSoapNote['extracted_codes']['icd10'] ?? [],
                'processing_ms'     => $processingMs,
                'model_version'     => $aiResult['model_version'],
            ]);
            break;

        // ── LOG SECTION ACTION ─────────────────────────────────────
        case 'log_section_action':
            $sessionId       = (int)($body['session_id']       ?? 0);
            $section         = htmlspecialchars($body['section']          ?? '');
            $clinicianAction = htmlspecialchars($body['clinician_action'] ?? '');

            if ($sessionId && $section) {
                $audit->log([
                    'session_id'       => $sessionId,
                    'event_type'       => 'section_action',
                    'clinician_action' => $clinicianAction,
                    'event_details'    => ['section' => $section],
                ]);
            }
            echo json_encode(['success' => true]);
            break;

        // ── SAVE CLINICIAN NOTE ────────────────────────────────────
        case 'save_clinician_note':
            $sessionId   = (int)($body['session_id']  ?? 0);
            $aiNoteId    = (int)($body['ai_note_id']  ?? 0);
            $finalNote   = $body['final_note']         ?? [];
            $userEdits   = $body['user_edits']         ?? [];
            $actionType  = $body['action_type']        ?? 'partial_edit';
            $clinicianId = (int)($_SESSION['authUserID'] ?? 0);

            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id is required']);
                exit;
            }

            // Look up AI note if not provided
            if (!$aiNoteId) {
                $aiNote = sqlQuery(
                    "SELECT id, ai_soap_note FROM ambient_ai_notes
                     WHERE session_id = ? ORDER BY id DESC LIMIT 1",
                    [$sessionId]
                );
                $aiNoteId = (int)($aiNote['id'] ?? 0);
            } else {
                $aiNote = sqlQuery(
                    "SELECT id, ai_soap_note FROM ambient_ai_notes WHERE id = ?",
                    [$aiNoteId]
                );
            }

            $sessionRow  = sqlQuery(
                "SELECT encounter_id FROM ambient_recording_sessions WHERE id = ?",
                [$sessionId]
            );
            $encounterId = (int)($sessionRow['encounter_id'] ?? 0);

            // Measure how much the clinician changed
            $aiText      = $aiNote['ai_soap_note'] ?? '{}';
            $cliText     = json_encode($finalNote);
            $editDistance = levenshtein(substr($aiText, 0, 2000), substr($cliText, 0, 2000));
            $maxLen       = max(strlen($aiText), strlen($cliText), 1);
            $editPercent  = round(($editDistance / $maxLen) * 100, 2);

            $clinicianNoteId = sqlInsert(
                "INSERT INTO ambient_clinician_notes
                 (ai_note_id, encounter_id, clinician_id,
                  final_note, action, section_actions,
                  edit_distance, edit_percent, saved_at)
                 VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    $aiNoteId,
                    $encounterId,
                    $clinicianId,
                    json_encode($finalNote),
                    $actionType,
                    json_encode($userEdits),
                    $editDistance,
                    $editPercent,
                ]
            );

            $audit->clinicianActed($sessionId, $encounterId, $actionType, $editDistance, $editPercent);

            echo json_encode([
                'success'           => true,
                'clinician_note_id' => (int)$clinicianNoteId,
                'edit_distance'     => $editDistance,
                'edit_percent'      => $editPercent,
                'action_type'       => $actionType,
            ]);
            break;

        // ── CHECK SETUP — pre-flight probe ────────────────────────
        // Probes every dependency and returns a per-check status so the
        // UI can show an actionable error before recording starts.
        case 'check_setup':
            $useOpenAI  = ($_ENV['USE_OPENAI'] ?? 'false') === 'true';
            $fastApiUrl = rtrim($_ENV['FASTAPI_URL'] ?? 'http://localhost:8000', '/');
            $checks     = [];

            // ── Check 1: DB tables ────────────────────────────
            $missingTables = [];
            foreach (['ambient_recording_sessions', 'ambient_ai_notes',
                      'ambient_clinician_notes', 'ambient_audit_log'] as $tbl) {
                $exists = sqlQuery(
                    "SELECT 1 FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = ?",
                    [$tbl]
                );
                if (!$exists) {
                    $missingTables[] = $tbl;
                }
            }
            $checks['database'] = [
                'ok'      => empty($missingTables),
                'message' => empty($missingTables)
                    ? 'All 4 DB tables found'
                    : 'Missing tables: ' . implode(', ', $missingTables)
                      . '. Run sql/install.sql in phpMyAdmin.',
            ];

            // ── Check 2: Storage writable ─────────────────────
            $storageDir = __DIR__ . '/../storage/chunks';
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0755, true);
            }
            $storageOk = is_dir($storageDir) && is_writable($storageDir);
            $checks['storage'] = [
                'ok'      => $storageOk,
                'message' => $storageOk
                    ? 'Storage directory is writable'
                    : 'Storage directory not writable: ' . $storageDir,
            ];

            // ── Check 3: FastAPI backend reachable ────────────
            // The Python server handles both transcription and SOAP generation.
            // It is the single dependency for AI functionality.
            $http = new \GuzzleHttp\Client(['timeout' => 900, 'connect_timeout' => 75]);
            try {
                $resp       = $http->get($fastApiUrl . '/', ['http_errors' => false]);
                $status     = $resp->getStatusCode();
                $body       = json_decode($resp->getBody()->getContents(), true);
                $apiOk      = ($status === 200);
                $engine     = $body['active_engine'] ?? 'unknown';
                $checks['fastapi'] = [
                    'ok'      => $apiOk,
                    'service' => "FastAPI ({$fastApiUrl})",
                    'message' => $apiOk
                        ? "FastAPI running — engine: {$engine}"
                        : "FastAPI returned HTTP {$status}. Start with: uvicorn main:app --reload --port 8000",
                ];
            } catch (\Exception $e) {
                $checks['fastapi'] = [
                    'ok'      => false,
                    'service' => "FastAPI ({$fastApiUrl})",
                    'message' => "Cannot reach FastAPI server at {$fastApiUrl}. "
                        . "In your project directory run: uvicorn main:app --reload --port 8000",
                ];
            }

            // ── Check 4: OpenAI key (only if USE_OPENAI=true) ─
            if ($useOpenAI) {
                $key   = $_ENV['OPENAI_API_KEY'] ?? '';
                $keyOk = !empty($key) && str_starts_with($key, 'sk-');
                $checks['openai_key'] = [
                    'ok'      => $keyOk,
                    'message' => $keyOk
                        ? 'OPENAI_API_KEY is set'
                        : 'OPENAI_API_KEY missing or invalid — must start with sk-. Set it in .env',
                ];
            }

            $allOk = !in_array(false, array_column($checks, 'ok'), true);

            echo json_encode([
                'ok'         => $allOk,
                'use_openai' => $useOpenAI,
                'mode'       => $useOpenAI ? 'openai' : 'local_ollama',
                'fastapi_url' => $fastApiUrl,
                'checks'     => $checks,
                'message'    => $allOk
                    ? 'All systems ready.'
                    : 'One or more services are not ready. See checks for details.',
            ]);
            break;

        // ── DEFAULT ────────────────────────────────────────────────
        default:
            http_response_code(400);
            echo json_encode([
                'error'             => 'Unknown action: ' . htmlspecialchars($action ?? 'none'),
                'available_actions' => [
                    'ping', 'create_session', 'get_session',
                    'upload_chunk', 'process_session',
                    'log_section_action', 'save_clinician_note',
                    'check_setup',
                ],
            ]);
    }

} catch (\Exception $e) {
    error_log('AmbientDocs API Error: ' . $e->getMessage());

    if (isset($sessionId) && $sessionId) {
        try {
            $audit->errorOccurred((int)$sessionId, $e->getMessage(), $action ?? 'unknown');
        } catch (\Exception $ignored) {}
    }

    http_response_code(500);
    ob_end_clean();   // discard any stray output before sending JSON
    echo json_encode([
        'error'  => 'Server error',
        'detail' => $isLocal ? $e->getMessage() : 'Check error log',
    ]);
}
