<?php
/**
 * Synapta Dashboard — AJAX handler for card detail panels
 */
require_once(dirname(__FILE__, 5) . "/globals.php");
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: application/json');

// Security checks
if (!AclMain::aclCheckCore('encounters', 'auth')) {
    echo json_encode(['error' => 'Access denied']); exit;
}
if (!CsrfUtils::verifyCsrfToken($_POST['csrf'] ?? '')) {
    echo json_encode(['error' => 'Invalid token']); exit;
}

$pid    = (int)($_POST['pid']    ?? 0);
$enc    = (int)($_POST['encounter'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$pid) { echo json_encode(['error' => 'No patient']); exit; }

// ── Route to correct handler ──────────────────────
switch ($action) {

  case 'vitals':

    // Current/latest vitals
    $current = sqlQuery(
        "SELECT bps, bpd, pulse, temperature, respiration,
                oxygen_saturation, BMI, date
         FROM form_vitals
         WHERE pid = ? 
           AND activity = 1
         ORDER BY date DESC
         LIMIT 1",
        [$pid]
    );

    // Last 6 visits for trends/history
    $history = sqlStatement(
        "SELECT bps, bpd, pulse, oxygen_saturation, date
         FROM form_vitals
         WHERE pid = ?
           AND activity = 1
         ORDER BY date DESC",
        [$pid]
    );

    $rows = [];

    while ($r = sqlFetchArray($history)) {
        $rows[] = [
            'bp'   => $r['bps'] . '/' . $r['bpd'],
            'bps'  => (int)$r['bps'],
            'bpd'  => (int)$r['bpd'],
            'hr'   => formatValues($r['pulse']),
            'spo2' => formatValues($r['oxygen_saturation']),
            'date' => date('M j, Y', strtotime($r['date'])),
            'short_date' => date("M 'y", strtotime($r['date']))
        ];
    }

   echo json_encode([

    'current' => [

        'bp' => $current
            ? formatValues($current['bps']) . '/' . formatValues($current['bpd'])
            : '—',

        'bps' => isset($current['bps'])
            ? formatValues($current['bps'])
            : null,

        'bpd' => isset($current['bpd'])
            ? formatValues($current['bpd'])
            : null,

        'hr' => isset($current['pulse'])
            ? formatValues($current['pulse'])
            : '—',

        'temp' => isset($current['temperature'])
            ? formatValues($current['temperature'])
            : '—',

        'spo2' => isset($current['oxygen_saturation'])
            ? formatValues($current['oxygen_saturation'])
            : '—',

        'rr' => isset($current['respiration'])
            ? formatValues($current['respiration'])
            : '—',

        'bmi' => isset($current['BMI'])
            ? formatValues($current['BMI'])
            : '—',

        'pain' => isset($current['pain'])
            ? formatValues($current['pain'])
            : '—',

        'date' => $current
            ? date('M j, Y', strtotime($current['date']))
            : ''
    ],

    'history' => array_reverse($rows)

]);

    break;

  case 'problems':
    $rows = [];
    $res  = sqlStatement(
        "SELECT title, diagnosis, begdate
         FROM   lists
         WHERE  pid = ? AND (type = 'medical_problem' OR type = 'medication') AND activity = 1
         ORDER  BY begdate DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($res)) $rows[] = $r;
    echo json_encode(['rows' => $rows]);
    break;

  case 'medications':
    $rows = [];
    $res  = sqlStatement(
        "SELECT drug, dosage, unit, route, `interval`, refills
         FROM   prescriptions
         WHERE  patient_id = ? AND active = 1
         ORDER  BY date_added DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($res)) $rows[] = $r;
    echo json_encode(['rows' => $rows]);
    break;

  case 'labs':
    $rows = [];
    $res  = sqlStatement(
        "SELECT pr.result_text, pr.units, pr.range, pr.abnormal,
                po.date_ordered,
                poc.procedure_name AS test_name
         FROM   procedure_result pr
         JOIN   procedure_order_code poc ON poc.procedure_order_id = pr.procedure_report_id
         JOIN   procedure_order po ON po.procedure_order_id = poc.procedure_order_id
         WHERE  po.patient_id = ?
         ORDER  BY po.date_ordered DESC
         LIMIT  20",
        [$pid]
    );
    while ($r = sqlFetchArray($res)) $rows[] = $r;
    echo json_encode(['rows' => $rows]);
    break;

  case 'allergies':
    $rows = [];
    $res  = sqlStatement(
        "SELECT title, reaction, severity_al, begdate
         FROM   lists
         WHERE  pid = ? AND type = 'allergy' AND activity = 1
         ORDER  BY severity_al DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($res)) $rows[] = $r;
    echo json_encode(['rows' => $rows]);
    break;

    case 'surgical_history':
    $rows = [];
    $res  = sqlStatement(
        "SELECT title, begdate, enddate, comments
         FROM   lists
         WHERE  pid = ? AND type = 'surgery'
         ORDER  BY begdate DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($res)) $rows[] = $r;
    echo json_encode(['rows' => $rows]);
    break;

    

    
    case 'save_soap':
    $subj = $_POST['subjective'] ?? '';
    $obj  = $_POST['objective']  ?? '';
    $ass  = $_POST['assessment'] ?? '';
    $plan = $_POST['plan']       ?? '';
    $enc  = $enc ?: ($_SESSION['encounter'] ?? 0);

    if (!$enc) {
        echo json_encode(['error' => 'No active encounter']);
        break;
    }

    // Check if a SOAP form already exists for this encounter
    $existing = sqlQuery(
        "SELECT f.form_id, fs.id AS soap_id
         FROM   forms f
         JOIN   form_soap fs ON fs.id = f.form_id
         WHERE  f.pid = ? AND f.encounter = ?
         AND    f.formdir = 'soap' AND f.deleted = 0
         LIMIT  1",
        [$pid, $enc]
    );

    if ($existing) {
        // Update existing SOAP note
        sqlStatement(
            "UPDATE form_soap
             SET    subjective = ?,
                    objective  = ?,
                    assessment = ?,
                    plan       = ?,
                    date       = NOW()
             WHERE  id = ?",
            [$subj, $obj, $ass, $plan, $existing['soap_id']]
        );
        echo json_encode(['success' => true, 'action' => 'updated']);
    } else {
        // Insert new SOAP note
        $soapId = sqlInsert(
            "INSERT INTO form_soap
             (date, pid, groupname, user, authorized,
              activity, subjective, objective, assessment, plan)
             VALUES (NOW(), ?, ?, ?, 1, 1, ?, ?, ?, ?)",
            [
                $pid,
                $_SESSION['authGroup']    ?? 'Default',
                $_SESSION['authUser']     ?? 'admin',
                $subj, $obj, $ass, $plan
            ]
        );
        // Register the form in OpenEMR's forms table
        sqlInsert(
            "INSERT INTO forms
             (date, encounter, form_name, form_id, pid,
              user, groupname, authorized, formdir, deleted)
             VALUES (NOW(), ?, 'SOAP', ?, ?, ?, ?, 1, 'soap', 0)",
            [
                $enc, $soapId, $pid,
                $_SESSION['authUser']  ?? 'admin',
                $_SESSION['authGroup'] ?? 'Default'
            ]
        );
        echo json_encode(['success' => true, 'action' => 'created']);
    }
    break;
  case 'vital_trends':
    set_time_limit(480); // allow up to 8 min — AI model can be slow
    // ── Fetch last 3 vitals ────────────────────────────────────────────────
    $vt3  = [];
    $stmt = sqlStatement(
        "SELECT bps, bpd, pulse, temperature, respiration,
                weight, height, BMI, oxygen_saturation, date
         FROM   form_vitals
         WHERE  pid = ? AND activity = 1
         ORDER  BY date DESC LIMIT 3",
        [$pid]
    );
    while ($r = sqlFetchArray($stmt)) { $vt3[] = $r; }

    if (empty($vt3)) {
        echo json_encode(['error' => 'No vitals found']); break;
    }

    // ── Patient info ───────────────────────────────────────────────────────
    $patInfo = sqlQuery(
        "SELECT sex, TIMESTAMPDIFF(YEAR, DOB, CURDATE()) AS age FROM patient_data WHERE pid = ?",
        [$pid]
    );
    $vtAge = (int)($patInfo['age'] ?? 0);
    $vtSex = substr($patInfo['sex'] ?? 'Unknown', 0, 1) ?: 'Unknown';

    $vtProblems = [];
    $pStmt = sqlStatement(
        "SELECT title, diagnosis FROM lists
         WHERE  pid = ? AND (type='medical_problem' OR type='medication') AND activity=1",
        [$pid]
    );
    while ($r = sqlFetchArray($pStmt)) {
        $t = trim(($r['title'] ?? '') . (!empty($r['diagnosis']) ? ' ('.$r['diagnosis'].')' : ''));
        if ($t) $vtProblems[] = $t;
    }

    $vtMeds = [];
    $mStmt = sqlStatement(
        "SELECT drug, size, dosage FROM prescriptions WHERE patient_id = ? AND active = 1",
        [$pid]
    );
    while ($r = sqlFetchArray($mStmt)) {
        $t = trim(($r['drug'] ?? '') . ' ' . ($r['size'] ?? '') . ($r['dosage'] ?? ''));
        if ($t) $vtMeds[] = $t;
    }

    $vtAllergies = [];
    $aStmt = sqlStatement(
        "SELECT title FROM lists WHERE pid = ? AND type='allergy' AND activity=1",
        [$pid]
    );
    while ($r = sqlFetchArray($aStmt)) {
        if (!empty($r['title'])) $vtAllergies[] = $r['title'];
    }

    // ── Build payload ──────────────────────────────────────────────────────
    $fnD = function($old, $new) {
        if ($old===null||$old===''||$new===null||$new==='') return ['value'=>null,'direction'=>null,'percent_change'=>null];
        $o=(float)$old; $n=(float)$new; $d=round($n-$o,2);
        $pct=$o!=0?round(($d/abs($o))*100,1):null;
        return ['value'=>$d,'direction'=>$d>0?'up':($d<0?'down':'same'),'percent_change'=>$pct];
    };

    $rLabels  = ['latest','previous','oldest'];
    $readings = [];
    foreach ($vt3 as $idx => $v) {
        $nv = function($k) use ($v) { return ($v[$k]!==''&&$v[$k]!==null)?(float)$v[$k]:null; };
        $readings[] = [
            'reading'           => $rLabels[$idx] ?? ('reading_'.$idx),
            'date'              => $v['date'] ? date('Y-m-d', strtotime($v['date'])) : null,
            'blood_pressure'    => ['systolic'=>$nv('bps'),'diastolic'=>$nv('bpd'),'unit'=>'mmHg'],
            'pulse'             => ['value'=>$nv('pulse'),'unit'=>'bpm'],
            'temperature'       => ['value'=>$nv('temperature'),'unit'=>'°F'],
            'respiration'       => ['value'=>$nv('respiration'),'unit'=>'br/min'],
            'oxygen_saturation' => ['value'=>$nv('oxygen_saturation'),'unit'=>'%'],
            'weight'            => ['value'=>$nv('weight'),'unit'=>'lbs'],
            'height'            => ['value'=>$nv('height'),'unit'=>'in'],
            'bmi'               => ['value'=>$nv('BMI')],
        ];
    }

    $d3=$vt3[0]; $p3=$vt3[1]??[];
    $deltas = [
        'systolic_bp'       => $fnD($p3['bps']??null,$d3['bps']??null),
        'diastolic_bp'      => $fnD($p3['bpd']??null,$d3['bpd']??null),
        'pulse'             => $fnD($p3['pulse']??null,$d3['pulse']??null),
        'temperature'       => $fnD($p3['temperature']??null,$d3['temperature']??null),
        'respiration'       => $fnD($p3['respiration']??null,$d3['respiration']??null),
        'oxygen_saturation' => $fnD($p3['oxygen_saturation']??null,$d3['oxygen_saturation']??null),
        'weight'            => $fnD($p3['weight']??null,$d3['weight']??null),
        'bmi'               => $fnD($p3['BMI']??null,$d3['BMI']??null),
    ];

    $vtPayload = [
        'patient_info' => [
            'pid'                => $pid,
            'age'                => $vtAge,
            'sex'                => $vtSex,
            'active_problems'    => $vtProblems,
            'active_medications' => $vtMeds,
            'allergies'          => $vtAllergies,
        ],
        'vitals_readings' => $readings,
        'deltas'          => $deltas,
    ];

    // ── Call FastAPI /vital_trends ─────────────────────────────────────────
    $ch = curl_init('http://localhost:8000/vital_trends');
    if ($ch === false) {
        echo json_encode(['error' => 'cURL unavailable']); break;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($vtPayload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 480,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $vtRaw  = curl_exec($ch);
    $vtCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$vtRaw || $vtCode !== 200) {
        echo json_encode(['error' => 'API unavailable (HTTP '.$vtCode.')']); break;
    }

    $vtDec = json_decode($vtRaw, true);
    if (!$vtDec || !isset($vtDec['clinical_analysis'])) {
        echo json_encode(['error' => 'Invalid API response']); break;
    }

    // Normalise: move recommendations to top level if nested inside clinical_analysis
    if (!isset($vtDec['recommendations']) && isset($vtDec['clinical_analysis']['recommendations'])) {
        $vtDec['recommendations'] = $vtDec['clinical_analysis']['recommendations'];
    }

    echo json_encode(['success' => true, 'data' => $vtDec]);
    break;

  // ════════════════════════════════════════════════════════════════════════
  //  AI Summary / Risk Stratification  →  localhost:8000/summary
  // ════════════════════════════════════════════════════════════════════════
  case 'summary':
    set_time_limit(120);

    // ── 1. Patient demographics ───────────────────────────────────────────
    $pt = sqlQuery(
        "SELECT fname, lname, DOB, sex, race, ethnicity, occupation
         FROM   patient_data WHERE pid = ?",
        [$pid]
    );
    $age = $pt['DOB']
        ? (int) date_diff(date_create($pt['DOB']), date_create('today'))->y
        : 0;

    // ── 2. Current encounter reason + chief complaints ────────────────────
    $enc_row = sqlQuery(
        "SELECT date, reason FROM form_encounter WHERE encounter = ? AND pid = ?",
        [$enc, $pid]
    );
    $ccRows = [];
    $ccStmt = sqlStatement(
        "SELECT complaint_text, severity, duration, associated_symptoms, date_created
         FROM   patient_chief_complaint WHERE pid = ? ORDER BY date_created DESC LIMIT 3",
        [$pid]
    );
    while ($r = sqlFetchArray($ccStmt)) { $ccRows[] = $r; }

    // ── Helper: clean decimal(12,6) MySQL values → clean strings ─────────
    // FastAPI _PSVitalsReading expects Optional[str] for all vital fields.
    // MySQL decimal(12,6) returns "88.000000" — strip trailing zeros.
    $cleanVitals = function($row) {
        if (!$row) return null;
        foreach (['pulse','temperature','respiration','oxygen_saturation',
                  'weight','height','BMI'] as $f) {
            if (isset($row[$f]) && $row[$f] !== '' && $row[$f] !== null) {
                $v = (float)$row[$f];
                // Format to max 2 decimals, strip trailing zeros
                $row[$f] = $v > 0 ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : null;
            } else {
                $row[$f] = null;
            }
        }
        foreach (['bps','bpd'] as $f) {
            $row[$f] = (isset($row[$f]) && $row[$f] !== '') ? (string)$row[$f] : null;
        }
        return $row;
    };

    // ── 3. Vitals — latest + last 3 ──────────────────────────────────────
    $vLatest = sqlQuery(
        "SELECT bps, bpd, pulse, temperature, respiration,
                oxygen_saturation, weight, height, BMI, date
         FROM   form_vitals WHERE pid = ? AND activity = 1
         ORDER  BY date DESC LIMIT 1",
        [$pid]
    );
    $vLatest = $cleanVitals($vLatest);

    $vHistory = [];
    $vhStmt = sqlStatement(
        "SELECT bps, bpd, pulse, temperature, respiration,
                oxygen_saturation, weight, height, BMI, date
         FROM   form_vitals WHERE pid = ? AND activity = 1
         ORDER  BY date DESC LIMIT 3",
        [$pid]
    );
    while ($r = sqlFetchArray($vhStmt)) { $vHistory[] = $cleanVitals($r); }

    // ── 4. Active problems ────────────────────────────────────────────────
    $problems = [];
    $pStmt = sqlStatement(
        "SELECT title, diagnosis, begdate
         FROM   lists WHERE pid = ? AND type = 'medical_problem' AND activity = 1
         ORDER  BY begdate DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($pStmt)) { $problems[] = $r; }

    // ── 5. Active medications ─────────────────────────────────────────────
    $meds = [];
    $mStmt = sqlStatement(
        "SELECT drug, dosage, size, route, drug_dosage_instructions,
                indication, start_date, rxnorm_drugcode
         FROM   prescriptions WHERE patient_id = ? AND active = 1
         ORDER  BY date_added DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($mStmt)) { $meds[] = $r; }

    // ── 6. Allergies ──────────────────────────────────────────────────────
    $allergies = [];
    $aStmt = sqlStatement(
        "SELECT title, severity_al, reaction FROM lists
         WHERE  pid = ? AND type = 'allergy' AND activity = 1
         ORDER  BY severity_al DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($aStmt)) { $allergies[] = $r; }

    // ── 7. Recent abnormal labs ───────────────────────────────────────────
    $abnormalLabs = [];
    $alStmt = sqlStatement(
        "SELECT poc.procedure_name AS test_name,
                pr.result, pr.units, pr.range, pr.abnormal, pr.comments,
                COALESCE(pr.date, prep.date_report, po.date_ordered) AS result_date
         FROM   procedure_order po
         JOIN   procedure_order_code poc ON poc.procedure_order_id = po.procedure_order_id
         JOIN   procedure_report prep    ON prep.procedure_order_id  = po.procedure_order_id
                                        AND prep.procedure_order_seq = poc.procedure_order_seq
         JOIN   procedure_result pr      ON pr.procedure_report_id   = prep.procedure_report_id
         WHERE  po.patient_id = ? AND po.activity = 1
           AND  pr.abnormal IN ('yes','high','low') AND pr.result != ''
         ORDER  BY result_date DESC LIMIT 8",
        [$pid]
    );
    while ($r = sqlFetchArray($alStmt)) { $abnormalLabs[] = $r; }

    // ── 8. Lab trends (HbA1c, LDL, eGFR, Glucose, Creatinine) ───────────
    $labTrends = [];
    $ltStmt = sqlStatement(
        "SELECT poc.procedure_name AS test_name,
                pr.result, pr.units, po.date_ordered AS date
         FROM   procedure_result pr
         JOIN   procedure_order_code poc ON poc.procedure_order_id = pr.procedure_report_id
         JOIN   procedure_order po       ON po.procedure_order_id  = poc.procedure_order_id
         WHERE  po.patient_id = ?
           AND  LOWER(poc.procedure_name) REGEXP
                'hba1c|hemoglobin a1c|ldl|egfr|glucose|cholesterol|creatinine'
         ORDER  BY po.date_ordered DESC LIMIT 24",
        [$pid]
    );
    while ($r = sqlFetchArray($ltStmt)) { $labTrends[] = $r; }
    // Group by normalised name
    $labTrendMap = [];
    foreach ($labTrends as $lt) {
        $k = strtolower($lt['test_name']);
        if (str_contains($k, 'hba1c') || str_contains($k, 'hemoglobin a')) $k = 'HbA1c';
        elseif (str_contains($k, 'ldl'))         $k = 'LDL';
        elseif (str_contains($k, 'egfr'))        $k = 'eGFR';
        elseif (str_contains($k, 'glucose'))     $k = 'Glucose';
        elseif (str_contains($k, 'creatinine'))  $k = 'Creatinine';
        elseif (str_contains($k, 'cholesterol')) $k = 'Cholesterol';
        if (!isset($labTrendMap[$k])) $labTrendMap[$k] = [];
        if (count($labTrendMap[$k]) < 6)
            $labTrendMap[$k][] = ['value' => (float)$lt['result'], 'date' => $lt['date']];
    }

    // ── 9. Pending lab orders ─────────────────────────────────────────────
    $pendingOrders = [];
    $poStmt = sqlStatement(
        "SELECT po.procedure_order_type, po.date_ordered, po.order_status,
                poc.procedure_name AS test_name
         FROM   procedure_order po
         LEFT JOIN procedure_order_code poc ON poc.procedure_order_id = po.procedure_order_id
         WHERE  po.patient_id = ? AND po.order_status IN ('pending','routed')
         ORDER  BY po.date_ordered DESC LIMIT 8",
        [$pid]
    );
    while ($r = sqlFetchArray($poStmt)) { $pendingOrders[] = $r; }

    // ── 10. Family history ────────────────────────────────────────────────
    $fhRow = sqlQuery("SELECT * FROM history_data WHERE pid = ? ORDER BY id DESC LIMIT 1", [$pid]);
    $familyHistory = [];
    if ($fhRow) {
        foreach (['father','mother','siblings','spouse'] as $rel) {
            $h = trim($fhRow["history_$rel"] ?? '');
            $d = trim($fhRow["dc_$rel"] ?? '');
            if ($h || $d) $familyHistory[] = ['relation' => ucfirst($rel), 'history' => $h, 'condition' => $d];
        }
    }
    $relativeFlags = [];
    $relCols = ['cancer','diabetes','high_blood_pressure','heart_problems','stroke','epilepsy','mental_illness'];
    foreach ($relCols as $col) {
        $val = trim($fhRow["relatives_$col"] ?? '');
        if ($val && $val !== '0') $relativeFlags[$col] = $val;
    }

    // ── 11. Social history ────────────────────────────────────────────────
    $socialRow = sqlQuery(
        "SELECT tobacco_status, tobacco_amount, alcohol_status, alcohol_drinks_per_week,
                drug_status, exercise_frequency, diet_type, occupation, social_history_notes
         FROM   patient_social_history WHERE pid = ? ORDER BY id DESC LIMIT 1",
        [$pid]
    );

    // ── 12. Overdue screenings from history_data ──────────────────────────
    $screenings = [];
    if ($fhRow) {
        $screenCols = [
            'last_mammogram', 'last_sigmoidoscopy_colonoscopy', 'last_ecg',
            'last_psa', 'last_retinal', 'last_fluvax', 'last_pneuvax', 'last_ldl'
        ];
        foreach ($screenCols as $col) {
            $val = trim($fhRow[$col] ?? '');
            if ($val) $screenings[$col] = $val;
        }
    }

    // ── 13. Immunizations ─────────────────────────────────────────────────
    $immunizations = [];
    $iStmt = sqlStatement(
        "SELECT cvx_code, administered_date, note,
                CONCAT('CVX ', cvx_code) AS vaccine_name
         FROM   immunizations WHERE patient_id = ?
         ORDER  BY administered_date DESC LIMIT 10",
        [$pid]
    );
    while ($r = sqlFetchArray($iStmt)) { $immunizations[] = $r; }

    // ── 14. Surgical history ──────────────────────────────────────────────
    $surgicalHistory = [];
    $sStmt = sqlStatement(
        "SELECT title, begdate, enddate, comments FROM lists
         WHERE  pid = ? AND type = 'surgery' ORDER BY begdate DESC",
        [$pid]
    );
    while ($r = sqlFetchArray($sStmt)) { $surgicalHistory[] = $r; }

    // ── 15. Last visit SOAP snapshot ──────────────────────────────────────
    $lastVisit = sqlQuery(
        "SELECT fe.date, fe.reason,
                fs.subjective, fs.assessment, fs.plan,
                CONCAT(u.fname,' ',u.lname) AS provider
         FROM   form_encounter fe
         LEFT JOIN forms f      ON f.encounter = fe.encounter
                               AND f.formdir = 'soap' AND f.deleted = 0
         LEFT JOIN form_soap fs ON fs.id = f.form_id
         LEFT JOIN users u      ON u.id  = fe.provider_id
         WHERE  fe.pid = ? AND fe.encounter != ?
         ORDER  BY fe.date DESC LIMIT 1",
        [$pid, $enc]
    );

    // ── 16. SDOH ──────────────────────────────────────────────────────────
    $sdoh = sqlQuery(
        "SELECT food_insecurity, housing_instability, transportation_insecurity,
                financial_strain, social_isolation, employment_status
         FROM   form_history_sdoh WHERE pid = ? ORDER BY created_at DESC LIMIT 1",
        [$pid]
    );

    // ── Build API payload ─────────────────────────────────────────────────
    $payload = [
        'patient_info' => [
            'pid'       => $pid,
            'age'       => $age,
            'sex'       => $pt['sex']        ?? '',
            'race'      => $pt['race']       ?? '',
            'ethnicity' => $pt['ethnicity']  ?? '',
            'occupation'=> $pt['occupation'] ?? '',
        ],
        'current_encounter' => [
            'encounter_id'    => $enc,
            'date'            => $enc_row['date']   ?? '',
            'reason'          => $enc_row['reason'] ?? '',
            'chief_complaints'=> $ccRows,
        ],
        'vitals' => [
            'latest'  => $vLatest  ?: null,
            'history' => $vHistory,
        ],
        'active_problems'     => $problems,
        'active_medications'  => $meds,
        'allergies'           => $allergies,
        'lab_results' => [
            'recent_abnormal' => $abnormalLabs,
            // Empty PHP [] must become {} (object) not [] (array) for Dict[str,Any]
            'trends'          => $labTrendMap ? $labTrendMap : (object)[],
        ],
        'pending_orders'   => $pendingOrders,
        'family_history'   => [
            // Same: empty associative array must be {} not []
            'relatives' => $relativeFlags ? $relativeFlags : (object)[],
            'details'   => $familyHistory,
        ],
        'social_history'   => $socialRow ? [
            'tobacco_status'           => $socialRow['tobacco_status']   ?? null,
            'alcohol_status'           => $socialRow['alcohol_status']   ?? null,
            'alcohol_drinks_per_week'  => $socialRow['alcohol_drinks_per_week'] !== null && $socialRow['alcohol_drinks_per_week'] !== ''
                                          ? (float)$socialRow['alcohol_drinks_per_week'] : null,
            'exercise_frequency'       => $socialRow['exercise_frequency'] ?? null,
        ] : null,
        // $screenings is a flat assoc array — pass as object or null (not [])
        'overdue_screenings' => $screenings ? (object)$screenings : null,
        'immunizations'    => $immunizations,
        'surgical_history' => $surgicalHistory,
        'last_visit'       => $lastVisit   ?: null,
        'sdoh'             => $sdoh        ?: null,
    ];

    // ── Call FastAPI /summary ─────────────────────────────────────────────
    $ch = curl_init('http://localhost:8000/patient_summary');
    if ($ch === false) {
        echo json_encode(['error' => 'cURL unavailable']); break;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $sumRaw  = curl_exec($ch);
    $sumCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    // Connection failed or API not yet running
    if (!$sumRaw || $sumCode === 0 || $sumCode === 404 || $sumCode === 503) {
        echo json_encode([
            'unavailable' => true,
            'message'     => 'AI Summary API is not yet available. Please check back once the service is running.',
        ]);
        break;
    }
    // Pydantic / request validation error — return details for debugging
    if ($sumCode === 422) {
        $detail = json_decode($sumRaw, true);
        error_log('[Synapta summary] 422 from FastAPI: ' . $sumRaw);
        echo json_encode([
            'unavailable' => true,
            'message'     => 'API validation error (422). Check server log for details.',
            'debug_422'   => $detail,
            'payload_sent'=> $payload,
        ]);
        break;
    }
    if ($sumCode !== 200) {
        echo json_encode([
            'unavailable' => true,
            'message'     => 'AI Summary API returned HTTP ' . $sumCode . '. Service may be starting up.',
        ]);
        break;
    }

    $sumDec = json_decode($sumRaw, true);
    if (!$sumDec) {
        echo json_encode([
            'unavailable' => true,
            'message'     => 'AI Summary API returned an invalid response.',
        ]);
        break;
    }

    echo json_encode(['success' => true, 'data' => $sumDec]);
    break;

  default:
    echo json_encode(['error' => 'Unknown action']);
}

function formatValues($value, $decimals = 1)
{
    if ($value === null || $value === '') {
        return '—';
    }

    // Remove unnecessary trailing zeros
    return rtrim(rtrim(number_format((float)$value, $decimals, '.', ''), '0'), '.');
}