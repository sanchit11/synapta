<?php

namespace Clinic\OeModuleAmbientDocs\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * NoteStructuringService
 *
 * Sends a de-identified transcript to the Python FastAPI backend
 * (/generate_soap) and returns a structured SOAP note.
 *
 * The FastAPI server routes to OpenAI or Ollama based on its own
 * USE_OPENAI env var — PHP just needs to send the payload.
 *
 * Required FastAPI payload structure:
 *   action          = "generate_soap"
 *   ambient_details = { session_id, encounter_id, encounter_date, ... }
 *   patient_context = { demographics, problem_list, medications, ... }
 *   options         = { include_icd10_codes, include_snomed_codes, ... }
 */
class NoteStructuringService
{
    private Client $http;
    private string $fastApiUrl;

    public function __construct()
    {
        $timeout = (int)($_ENV['FASTAPI_TIMEOUT'] ?? 1800); // override via .env FASTAPI_TIMEOUT=N
        $this->http = new Client([
            'timeout'         => $timeout, // SOAP generation can take a while on CPU
            'connect_timeout' => 10,
        ]);

        $this->fastApiUrl = rtrim($_ENV['FASTAPI_URL'] ?? 'http://localhost:8000', '/');
    }

    /**
     * Generate a SOAP note from a de-identified transcript.
     *
     * @param  string $deidentifiedTranscript  Transcript with PHI replaced by tokens
     * @param  array  $diarization             [{speaker, text, start_ms, end_ms}]
     * @param  array  $patientContext          Pre-fetched patient data from OpenEMR
     * @param  array  $encounterContext        Encounter metadata (date, type, provider)
     * @return array{
     *   soap_note:     array|null,
     *   model_version: string|null,
     *   prompt_hash:   string|null,
     *   processing_ms: int,
     *   error:         string|null
     * }
     */
    public function structure(
        string $deidentifiedTranscript,
        array  $diarization      = [],
        array  $patientContext   = [],
        array  $encounterContext = []
    ): array {
        if (empty(trim($deidentifiedTranscript))) {
            return $this->errorResult('Transcript is empty — nothing to structure.');
        }

        $startTime  = microtime(true);
        $sessionId  = $encounterContext['session_id']   ?? 'unknown';
        $encounterId = $encounterContext['encounter_id'] ?? 0;

        // ── Build FastAPI payload ──────────────────────────────
        $payload = [
            'action'          => 'generate_soap',
            'ambient_details' => $this->buildAmbientDetails(
                $deidentifiedTranscript,
                $diarization,
                $sessionId,
                $encounterId,
                $encounterContext
            ),
            'patient_context' => $this->buildPatientContext($patientContext),
            'options'         => [
                'include_icd10_codes'      => true,
                'include_snomed_codes'     => false,
                'include_confidence_scores' => true,
                'include_safety_checks'    => true,
                'temperature'              => 0.1,
                'output_format'            => 'structured_json',
            ],
        ];

        try {
            $response = $this->http->post($this->fastApiUrl . '/generate_soap', [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => $payload,
            ]);

            $data         = json_decode($response->getBody()->getContents(), true);
            $processingMs = (int)((microtime(true) - $startTime) * 10000);

            // FastAPI returns:
            // { status, session_id, engine_used, data: { ...SOAP note... } }
            if (($data['status'] ?? '') !== 'success') {
                $msg = $data['message'] ?? $data['detail'] ?? 'Unknown FastAPI error';
                return $this->errorResult("FastAPI generate_soap failed: {$msg}");
            }

            $soapNote = $data['data'] ?? [];
            $soapNote = $this->validateAndClean($soapNote);

            return [
                'soap_note'     => $soapNote,
                'model_version' => 'fastapi-' . ($data['engine_used'] ?? 'unknown'),
                'prompt_hash'   => hash('sha256', json_encode($payload['ambient_details'])),
                'processing_ms' => $processingMs,
                'error'         => null,
            ];

        } catch (RequestException $e) {
            $status  = $e->getResponse()?->getStatusCode() ?? 0;
            $body    = $e->getResponse()?->getBody()->getContents() ?? '';
            $errData = json_decode($body, true);
            $message = $errData['detail']['message']
                ?? $errData['detail']
                ?? $errData['message']
                ?? $e->getMessage();

            // Unpack nested detail from FastAPI's 422 validation errors
            if (is_array($message)) {
                $message = json_encode($message);
            }

            $friendly = $status === 0
                ? "Cannot reach FastAPI server at {$this->fastApiUrl}. "
                  . "Start it with: uvicorn main:app --reload --port 8000"
                : "FastAPI /generate_soap error (HTTP {$status}): {$message}";

            error_log("NoteStructuringService error: {$friendly}");
            return $this->errorResult($friendly);

        } catch (\Exception $e) {
            error_log("NoteStructuringService unexpected error: " . $e->getMessage());
            return $this->errorResult($e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════
    //  PAYLOAD BUILDERS
    // ══════════════════════════════════════════════════════════

    private function buildAmbientDetails(
        string $transcript,
        array  $diarization,
        mixed  $sessionId,
        int    $encounterId,
        array  $ctx
    ): array {
        $now = new \DateTime();

        return [
            'session_id'           => (string)$sessionId,
            'encounter_id'         => (int)$encounterId,
            'encounter_date'       => $ctx['encounter_date'] ?? $now->format('Y-m-d'),
            'encounter_time'       => $ctx['encounter_time'] ?? $now->format('H:i:s'),
            'encounter_type'       => $ctx['encounter_type'] ?? 'office_visit',
            'provider_credentials' => $ctx['provider_credentials'] ?? 'MD',
            'clinic_name'          => $ctx['clinic_name'] ?? ($GLOBALS['openemr_name'] ?? 'Clinic'),
            'location'             => $ctx['location'] ?? 'Main Office',
            'raw_transcript'       => $transcript,
            'diarized_transcript'  => $diarization,
            'duration_seconds'     => $ctx['duration_seconds'] ?? 0,
            'status'               => 'complete',
            'provider_token'       => null,
        ];
    }

    private function buildPatientContext(array $ctx): array
    {
        // All keys are REQUIRED by the FastAPI schema (may be empty).
        // social_history, intake_form, last_visit must be JSON objects ({})
        // not arrays ([]) — FastAPI's Pydantic schema declares them as dicts.
        // (object)[] in PHP serialises to {} via json_encode / Guzzle json option.
        return [
            'demographics'       => $ctx['demographics']      ?? [],
            'problem_list'       => $ctx['problem_list']      ?? [],
            'active_medications' => $ctx['active_medications'] ?? [],
            'allergies'          => $ctx['allergies']          ?? [],
            'vitals_current'     => $ctx['vitals_current']     ?? [],
            'labs_recent'        => $ctx['labs_recent']        ?? [],
            'diagnostic_tests'   => $ctx['diagnostic_tests']   ?? [],
            'social_history'     => $this->asObject($ctx['social_history']  ?? null),
            'family_history'     => $ctx['family_history']     ?? [],
            'intake_form'        => $this->asObject($ctx['intake_form']     ?? null),
            'last_visit'         => $this->asObject($ctx['last_visit']      ?? null),
        ];
    }

    /**
     * Ensure a value that may be an empty PHP array [] is cast to a
     * stdClass so json_encode() produces {} (object) rather than [] (array).
     * If the value already has content (non-empty array or object) it is
     * returned as-is so existing data is never lost.
     *
     * @param  mixed $value
     * @return mixed  \stdClass for empty / null input; original value otherwise
     */
    private function asObject(mixed $value): mixed
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return new \stdClass();
        }
        return $value;
    }

    // ══════════════════════════════════════════════════════════
    //  RESULT NORMALISATION
    // ══════════════════════════════════════════════════════════

    /**
     * Ensure all expected SOAP keys are present (deep-merge with defaults).
     * FastAPI may return a different key structure depending on the model —
     * this guarantees the PHP caller always gets a consistent shape.
     */
    private function validateAndClean(array $note): array
    {
        $defaults = [
            'chief_complaint'    => '',
            'subjective'         => [
                'hpi'            => '',
                'ros'            => '',
                'pmh'            => '',
                'medications'    => [],
                'allergies'      => [],
                'social_history' => '',
                'family_history' => '',
            ],
            'objective'          => [
                'vital_signs'        => '',
                'physical_exam'      => '',
                'diagnostic_results' => '',
            ],
            'assessment'         => [
                'diagnoses'        => [],
                'clinical_summary' => '',
            ],
            'plan'               => [
                'treatments'        => [],
                'medications'       => [],
                'orders'            => [],
                'referrals'         => [],
                'follow_up'         => '',
                'patient_education' => '',
            ],
            'confidence_scores'  => [
                'chief_complaint' => 0.0,
                'hpi'             => 0.0,
                'ros'             => 0.0,
                'physical_exam'   => 0.0,
                'assessment'      => 0.0,
                'plan'            => 0.0,
            ],
            'extracted_codes'    => [
                'icd10'         => [],
                'cpt_suggested' => [],
            ],
            'visit_type'         => 'sick_visit',
            'note_quality_flags' => [],
        ];

        return $this->deepMerge($defaults, $note);
    }

    private function deepMerge(array $defaults, array $actual): array
    {
        $result = $defaults;
        foreach ($actual as $key => $value) {
            if (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                $result[$key] = $this->deepMerge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function errorResult(string $message): array
    {
        return [
            'soap_note'     => null,
            'model_version' => null,
            'prompt_hash'   => null,
            'processing_ms' => 0,
            'error'         => $message,
        ];
    }
}
