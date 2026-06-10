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