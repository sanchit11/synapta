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