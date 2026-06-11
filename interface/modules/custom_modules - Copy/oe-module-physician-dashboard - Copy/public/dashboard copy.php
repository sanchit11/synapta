<?php
/**
 * Synapta Dashboard — Main PHP entry point
 * Reads real patient data from OpenEMR database
 */

// OpenEMR security — must be included first
require_once(dirname(__FILE__, 5) . "/globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;

// Security check — user must be logged in
if (!AclMain::aclCheckCore('encounters', 'auth')) {
    echo "<p style='color:red;'>Access Denied.</p>";
    exit;
}

// Get current patient ID and encounter ID from OpenEMR session
if(isset($_GET['set_pid']))
    {
         $pid        = $_GET['set_pid'];
    }
    else{
        $pid        = $_SESSION['pid'] ?? ($GLOBALS['pid'] ?? 0);
    }

$encounter  = $_SESSION['encounter'] ?? ($GLOBALS['encounter'] ?? 0);



// ── QUERY 1: Patient basic info ──────────────────────────────────────────────
$patientData = sqlQuery(
    "SELECT pd.fname, pd.mname, pd.lname, pd.DOB, pd.sex,
            pd.pubpid, pd.pid,
            TIMESTAMPDIFF(YEAR, pd.DOB, CURDATE()) AS age
     FROM   patient_data pd
     WHERE  pd.pid = ?",
    [$pid]
);

// ── QUERY 2: Insurance info ───────────────────────────────────────────────────
$insuranceData = sqlQuery(
    "SELECT id.provider, id.policy_number, id.plan_name,
            ic.name AS company_name
     FROM   insurance_data id
     LEFT JOIN insurance_companies ic ON ic.id = id.provider
     WHERE  id.pid = ?
     AND    id.type = 'primary'
     ORDER  BY id.id DESC
     LIMIT  1",
    [$pid]
);

// ── QUERY 3: Allergies ────────────────────────────────────────────────────────
$allergyResult = sqlStatement(
    "SELECT title, severity_al
     FROM   lists
     WHERE  pid = ?
     AND    type = 'allergy'
     AND    activity = 1
     ORDER  BY severity_al DESC
     LIMIT  5",
    [$pid]
);
$allergies = [];
while ($row = sqlFetchArray($allergyResult)) {
    $allergies[] = $row;
}

// ── QUERY 4: Current encounter info ──────────────────────────────────────────
$encounterData = sqlQuery(
    "SELECT fe.date, fe.reason, fe.encounter,
            CONCAT(u.fname, ' ', u.lname) AS provider_name,
            u.title AS provider_title
     FROM   form_encounter fe
     LEFT JOIN users u ON u.id = fe.provider_id
     WHERE  fe.encounter = ?
     AND    fe.pid = ?",
    [$encounter, $pid]
);

// ── QUERY 5: Primary care provider ───────────────────────────────────────────
$providerData = sqlQuery(
    "SELECT CONCAT(u.fname, ' ', u.lname) AS name,
            u.title
     FROM   patient_data pd
     LEFT JOIN users u ON u.id = pd.providerID
     WHERE  pd.pid = ?",
    [$pid]
);

// ── Build display variables ───────────────────────────────────────────────────
$patientName    = '';
$patientInitials = '';
if ($patientData) {
    $fname = text($patientData['fname']);
    $lname = text($patientData['lname']);
    $patientName    = $lname . ', ' . $fname;
    $patientInitials = strtoupper(substr($patientData['fname'], 0, 1) . substr($patientData['lname'], 0, 1));
}

$dob        = $patientData['DOB']    ? date('m/d/Y', strtotime($patientData['DOB'])) : 'N/A';
$age        = $patientData['age']    ?? 'N/A';
$sex        = $patientData['sex']    ? substr($patientData['sex'], 0, 1) : '';
$mrn        = text($patientData['pubpid'] ?? '');
$insurance  = text($insuranceData['company_name'] ?? ($insuranceData['plan_name'] ?? 'No Insurance on File'));
$encDate    = $encounterData['date']   ? date('M d, Y', strtotime($encounterData['date'])) : date('M d, Y');
$encReason  = text($encounterData['reason'] ?? 'Office Visit');
$provName   = text($encounterData['provider_name'] ?? ($providerData['name'] ?? 'Provider'));
$provTitle  = text($encounterData['provider_title'] ?? 'MD');
$provInitials = '';
if (!empty($provName)) {
    $parts = explode(' ', strip_tags($provName));
    foreach ($parts as $p) {
        $provInitials .= strtoupper(substr($p, 0, 1));
    }
    $provInitials = substr($provInitials, 0, 2);
}

// ── QUERY 6: Latest Vitals ────────────────────────────────────────────────────
$vitalsData = sqlQuery(
    "SELECT bps, bpd, pulse, temperature, respiration,
            weight, height, BMI, oxygen_saturation,
            date
     FROM   form_vitals
     WHERE  pid = ?
     AND    activity = 1
     ORDER  BY date DESC
     LIMIT  1",
    [$pid]
);

// ── QUERY 7: Vitals history for trend (last 6 readings) ───────────────────────
$vitalsHistory = [];
$vhResult = sqlStatement(
    "SELECT bps, bpd, pulse, date
     FROM   form_vitals
     WHERE  pid = ?
     AND    activity = 1
     ORDER  BY date DESC
     LIMIT  6",
    [$pid]
);
while ($row = sqlFetchArray($vhResult)) {
    $vitalsHistory[] = $row;
}
$vitalsHistory = array_reverse($vitalsHistory);

// ── QUERY 8: Problem List ─────────────────────────────────────────────────────
$problems = [];
$probResult = sqlStatement(
    "SELECT title, diagnosis, begdate, enddate, activity
     FROM   lists
     WHERE  pid = ?
     AND    (type = 'medical_problem'  OR    type = 'medication')
     AND    activity = 1
     ORDER  BY begdate DESC",
    [$pid]
);
while ($row = sqlFetchArray($probResult)) {
    $problems[] = $row;
}

// ── QUERY 9: Medications ──────────────────────────────────────────────────────
$medications = [];
$medResult = sqlStatement(
    "SELECT *
     FROM   prescriptions
     WHERE  patient_id = ?
     AND    active = 1
     ORDER  BY date_added DESC",
    [$pid]
);

while ($row = sqlFetchArray($medResult)) {
    $medications[] = $row;
}

// ── QUERY 10: Lab Results (latest per test) ────────────────────────────────────
$labs = [];
$labResult = sqlStatement(
    "SELECT pr.result_text, pr.units, pr.range,
            pr.abnormal, pr.result_status,
            po.date_ordered,
            poc.procedure_name AS test_name
     FROM   procedure_result pr
     JOIN   procedure_order_code poc ON poc.procedure_order_id = pr.procedure_report_id
     JOIN   procedure_order po ON po.procedure_order_id = poc.procedure_order_id
     WHERE  po.patient_id = ?
     AND    pr.result_status != 'incomplete'
     ORDER  BY po.date_ordered DESC
     LIMIT  8",
    [$pid]
);
while ($row = sqlFetchArray($labResult)) {
    $labs[] = $row;
}

// ── QUERY 11: Allergies full list ─────────────────────────────────────────────
$allergyFull = [];
$afResult = sqlStatement(
    "SELECT title, severity_al, reaction, begdate
     FROM   lists
     WHERE  pid = ?
     AND    type = 'allergy'
     AND    activity = 1
     ORDER  BY severity_al DESC",
    [$pid]
);
while ($row = sqlFetchArray($afResult)) {
    $allergyFull[] = $row;
}


// ── QUERY 13: Drug interactions (active meds) ─────────────────────────────────
$drugNames = [];
if (!empty($medications)) {
    foreach ($medications as $med) {
        if (!empty($med['drug'])) {
            $drugNames[] = $med['drug'];
        }
    }
}

// ── QUERY 14: Recent billing codes for this patient ───────────────────────────
$billingCodes = [];
$billResult = sqlStatement(
    "SELECT b.code, b.code_type, b.units,
            b.authorized, b.billed,
            b.fee
     FROM   billing b
     WHERE  b.pid = ?
     AND    b.activity = 1
     ORDER  BY b.id DESC
     LIMIT  8",
    [$pid]
);
while ($row = sqlFetchArray($billResult)) {
    $billingCodes[] = $row;
}

// ── QUERY 15: Pending orders ──────────────────────────────────────────────────
$pendingOrders = [];
$poResult = sqlStatement(
    "SELECT po.procedure_order_type, po.date_ordered,
            poc.procedure_name AS test_name,
            po.order_status
     FROM   procedure_order po
     LEFT JOIN procedure_order_code poc
            ON poc.procedure_order_id = po.procedure_order_id
     WHERE  po.patient_id = ?
     AND    po.order_status IN ('pending','complete','results')
     ORDER  BY po.date_ordered DESC
     LIMIT  5",
    [$pid]
);
while ($row = sqlFetchArray($poResult)) {
    $pendingOrders[] = $row;
}

// ── QUERY 16: Last visit summary ──────────────────────────────────────────────
$lastVisit = sqlQuery(
    "SELECT fe.date, fe.reason,
            fs.assessment, fs.plan,
            CONCAT(u.fname,' ',u.lname) AS provider
     FROM   form_encounter fe
     LEFT JOIN forms f
            ON f.encounter = fe.encounter
           AND f.formdir   = 'soap'
           AND f.deleted   = 0
     LEFT JOIN form_soap fs ON fs.id = f.form_id
     LEFT JOIN users u ON u.id = fe.provider_id
     WHERE  fe.pid = ?
     AND    fe.encounter != ?
     ORDER  BY fe.date DESC
     LIMIT  1",
    [$pid, $encounter]
);


// ── QUERY: Family History full list ─────────────────────────────────────────────
$familyHistoryFull = [];

$result = sqlStatement(
    "SELECT *
     FROM history_data
     WHERE pid = ?
     ORDER BY id DESC",
    [$pid]
);

while ($row = sqlFetchArray($result)) {

    $map = [
        'Father'   => 'father',
        'Mother'   => 'mother',
        'Siblings' => 'siblings',
        'Spouse'   => 'spouse',
    ];

    foreach ($map as $label => $key) {

        $history = $row["history_$key"] ?? '';
        $condition = $row["dc_$key"] ?? '';

        if (!empty($history) || !empty($condition)) {
            $familyHistoryFull[] = [
                'relation' => $label,
                'history'  => $history,
                'condition'=> $condition
            ];
        }
    }
}


// ── QUERY: Surgical History ──────────────────────────────────────────────────
$surgicalHistory = [];
$surgicalHistorypoResult = sqlStatement(
    "SELECT title, begdate, enddate, comments
     FROM   lists
     WHERE  pid = ?
     AND    type='surgery'
     ORDER  BY begdate DESC",
    [$pid]
);
while ($row = sqlFetchArray($surgicalHistorypoResult)) {
    $surgicalHistory[] = $row;
}

// ── QUERY 21: Vitals history for trend charts (last 10 readings) ──────────────
$vitalsForCharts = [];
$vcResult = sqlStatement(
    "SELECT bps, bpd, pulse, temperature,
            oxygen_saturation, BMI, weight,
            DATE_FORMAT(date,'%b %d') AS label,
            date
     FROM   form_vitals
     WHERE  pid      = ?
     AND    activity = 1
     ORDER  BY date  DESC
     LIMIT  10",
    [$pid]
);
while ($row = sqlFetchArray($vcResult)) {
    $vitalsForCharts[] = $row;
}
$vitalsForCharts = array_reverse($vitalsForCharts);

// ── QUERY 22: Lab trends (HbA1c, LDL, eGFR, Glucose) ─────────────────────────
$labTrends = [];
$ltResult  = sqlStatement(
    "SELECT pr.result_text, pr.units,
            poc.procedure_name          AS test_name,
            po.date_ordered   AS date
     FROM   procedure_result pr
     JOIN   procedure_order_code poc
            ON poc.procedure_order_id = pr.procedure_report_id
     JOIN   procedure_order po
            ON po.procedure_order_id  = poc.procedure_order_id
     WHERE  po.patient_id = ?
     AND    LOWER(poc.procedure_name) REGEXP
            'hba1c|hemoglobin a1c|ldl|egfr|glucose|cholesterol|creatinine'
     ORDER  BY po.date_ordered DESC
     LIMIT  20",
    [$pid]
);
while ($row = sqlFetchArray($ltResult)) {
    $labTrends[] = $row;
}

// Group lab trends by test name
$labTrendGroups = [];
foreach ($labTrends as $lt) {
    $key = strtolower($lt['test_name']);
    // Normalize key
    if (strpos($key, 'hba1c') !== false
        || strpos($key, 'hemoglobin a') !== false) {
        $key = 'HbA1c';
    } elseif (strpos($key, 'ldl') !== false) {
        $key = 'LDL';
    } elseif (strpos($key, 'egfr') !== false) {
        $key = 'eGFR';
    } elseif (strpos($key, 'glucose') !== false) {
        $key = 'Glucose';
    } elseif (strpos($key, 'creatinine') !== false) {
        $key = 'Creatinine';
    } elseif (strpos($key, 'cholesterol') !== false) {
        $key = 'Cholesterol';
    }
    if (!isset($labTrendGroups[$key])) {
        $labTrendGroups[$key] = [];
    }
    if (count($labTrendGroups[$key]) < 6) {
        $labTrendGroups[$key][] = $lt;
    }
}
// Reverse each group so oldest is first
foreach ($labTrendGroups as $k => $v) {
    $labTrendGroups[$k] = array_reverse($v);
}

// ── QUERY 23: Overdue referrals ───────────────────────────────────────────────
$overdueReferrals = [];
// $orResult = sqlStatement(
//     "SELECT r.id, r.refer_date, r.refer_to, r.reason
//      FROM   refer r
//      WHERE  r.pid        = ?
//      AND    r.reply_date IS NULL
//      AND    r.refer_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
//      ORDER  BY r.refer_date ASC
//      LIMIT  5",
//     [$pid]
// );
// while ($row = sqlFetchArray($orResult)) {
//     $overdueReferrals[] = $row;
// }

// ── QUERY 24: Incomplete / pending lab orders ─────────────────────────────────
$incompleteLabs = [];
$ilResult = sqlStatement(
    "SELECT po.procedure_order_id,
            po.date_ordered,
            poc.procedure_name AS test_name
     FROM   procedure_order po
     LEFT JOIN procedure_order_code poc
            ON poc.procedure_order_id = po.procedure_order_id
     WHERE  po.patient_id   = ?
     AND    po.order_status IN ('pending','submitted')
     ORDER  BY po.date_ordered DESC
     LIMIT  5",
    [$pid]
);
while ($row = sqlFetchArray($ilResult)) {
    $incompleteLabs[] = $row;
}

// ── QUERY 25: Upcoming / overdue preventive care ──────────────────────────────
// Check last flu shot, mammogram etc. from immunizations + procedure orders
$preventiveCare = [];
$pcResult = sqlStatement(
    "SELECT i.administered_date,
            i.cvx_code,
            lc.codes AS vaccine_name
     FROM   immunizations i
     LEFT JOIN list_options lc
            ON lc.list_id  = 'immunizations'
           AND lc.option_id = i.cvx_code
     WHERE  i.patient_id = ?
     AND    i.administered_date IS NOT NULL
     ORDER  BY i.administered_date DESC
     LIMIT  5",
    [$pid]
);
while ($row = sqlFetchArray($pcResult)) {
    $preventiveCare[] = $row;
}

// ── Build risk score (rule-based) ────────────────────────────────────────────
$riskScore  = 0;
$riskFlags  = [];

// BP risk
if ($vitalsData && $vitalsData['bps'] >= 140) {
    $riskScore += 2;
    $riskFlags[] = ['level' => 'high',
                    'text'  => 'Hypertension — BP ' . $bp];
} elseif ($vitalsData && $vitalsData['bps'] >= 130) {
    $riskScore++;
    $riskFlags[] = ['level' => 'medium',
                    'text'  => 'Elevated BP — ' . $bp];
}

// Polypharmacy risk
if ($medCount >= 5) {
    $riskScore++;
    $riskFlags[] = ['level' => 'medium',
                    'text'  => $medCount . ' active medications (polypharmacy)'];
}

// Abnormal labs
if ($abnormalCount > 0) {
    $riskScore++;
    $riskFlags[] = ['level' => 'high',
                    'text'  => $abnormalCount . ' abnormal lab result'
                              . ($abnormalCount > 1 ? 's' : '')];
}

// Overdue referrals
if (!empty($overdueReferrals)) {
    $riskScore++;
    $riskFlags[] = ['level' => 'medium',
                    'text'  => count($overdueReferrals)
                              . ' overdue referral'
                              . (count($overdueReferrals) > 1 ? 's' : '')
                              . ' awaiting response'];
}

// Incomplete labs
if (!empty($incompleteLabs)) {
    $riskScore++;
    $riskFlags[] = ['level' => 'medium',
                    'text'  => count($incompleteLabs)
                              . ' pending lab order'
                              . (count($incompleteLabs) > 1 ? 's' : '')];
}

// Multiple problems
if ($probCount >= 3) {
    $riskScore++;
    $riskFlags[] = ['level' => 'low',
                    'text'  => $probCount . ' active diagnoses on problem list'];
}

// Allergies
if ($allergyCount > 0) {
    $riskFlags[] = ['level' => 'low',
                    'text'  => $allergyCount . ' known allerg'
                              . ($allergyCount > 1 ? 'ies' : 'y')
                              . ' — verify before prescribing'];
}

// Risk level label
if ($riskScore >= 4) {
    $riskLevel = 'high';
    $riskLabel = 'High Risk';
    $riskColor = 'danger';
} elseif ($riskScore >= 2) {
    $riskLevel = 'medium';
    $riskLabel = 'Moderate Risk';
    $riskColor = 'warn';
} else {
    $riskLevel = 'low';
    $riskLabel = 'Low Risk';
    $riskColor = 'ok';
}

// ── SVG trend chart builder (extended) ───────────────────────────────────────
function buildTrendChart(
    $points, $valueKey, $labelKey,
    $width = 280, $height = 80,
    $color = '#1D9E75'
) {
    if (count($points) < 2) {
        return '<svg width="' . $width . '" height="' . $height . '"
                     xmlns="http://www.w3.org/2000/svg">
                  <text x="50%" y="50%" text-anchor="middle"
                        font-size="11" fill="#bbb">Not enough data</text>
                </svg>';
    }

    $vals = array_map(
        fn($p) => (float)preg_replace('/[^0-9.]/', '', $p[$valueKey] ?? '0'),
        $points
    );
    $vals = array_filter($vals, fn($v) => $v > 0);
    if (count($vals) < 2) {
        return '<svg width="' . $width . '" height="' . $height . '"
                     xmlns="http://www.w3.org/2000/svg">
                  <text x="50%" y="50%" text-anchor="middle"
                        font-size="11" fill="#bbb">Not enough data</text>
                </svg>';
    }
    $vals   = array_values($vals);
    $labels = array_column($points, $labelKey);
    $n      = count($vals);
    $min    = min($vals);
    $max    = max($vals);
    $range  = $max - $min ?: 1;
    $padT   = 14;
    $padB   = 22;
    $padL   = 8;
    $padR   = 8;
    $chartW = $width  - $padL - $padR;
    $chartH = $height - $padT - $padB;

    // Build point coordinates
    $coords = [];
    foreach ($vals as $i => $v) {
        $x = round($padL + ($i / ($n - 1)) * $chartW, 1);
        $y = round($padT + (1 - ($v - $min) / $range) * $chartH, 1);
        $coords[] = ['x' => $x, 'y' => $y, 'v' => $v, 'l' => $labels[$i] ?? ''];
    }

    // Build SVG path
    $polyline = implode(' ', array_map(fn($c) => $c['x'] . ',' . $c['y'], $coords));
    $fillPath = $polyline
        . ' ' . end($coords)['x'] . ',' . ($height - $padB)
        . ' ' . $coords[0]['x']  . ',' . ($height - $padB);

    // Reference lines
    $refLines = '';
    for ($i = 0; $i <= 2; $i++) {
        $y = round($padT + ($i / 2) * $chartH, 1);
        $v = round($max - ($i / 2) * $range, 1);
        $refLines .= '<line x1="' . $padL . '" y1="' . $y . '" '
                    . 'x2="' . ($width - $padR) . '" y2="' . $y . '" '
                    . 'stroke="#e5e7ef" stroke-width="1"/>';
        $refLines .= '<text x="' . ($width - $padR + 2) . '" y="' . ($y + 4) . '" '
                    . 'font-size="8" fill="#bbb">' . $v . '</text>';
    }

    // Data points + labels
    $dots   = '';
    $xlabels = '';
    $last   = end($coords);
    foreach ($coords as $i => $c) {
        $isLast = ($i === count($coords) - 1);
        $r      = $isLast ? 4 : 3;
        $fill   = $isLast ? $color : 'white';
        $stroke = $color;
        $dots .= '<circle cx="' . $c['x'] . '" cy="' . $c['y'] . '" '
               . 'r="' . $r . '" fill="' . $fill . '" '
               . 'stroke="' . $stroke . '" stroke-width="1.5"/>';
        // Value label on last point
        if ($isLast) {
            $dots .= '<text x="' . $c['x'] . '" y="' . ($c['y'] - 7) . '" '
                   . 'font-size="10" font-weight="700" fill="' . $color . '" '
                   . 'text-anchor="middle">' . $c['v'] . '</text>';
        }
        // X labels — first and last only
        if ($i === 0 || $isLast) {
            $anchor = ($i === 0) ? 'start' : 'end';
            $lx     = ($i === 0) ? $padL : ($width - $padR);
            $xlabels .= '<text x="' . $lx . '" y="' . ($height - 4) . '" '
                      . 'font-size="8" fill="#aaa" text-anchor="' . $anchor . '">'
                      . htmlspecialchars($c['l'] ?? '')
                      . '</text>';
        }
    }

    $gradId = 'g' . substr(md5($color . $valueKey), 0, 6);

    return '<svg width="' . $width . '" height="' . $height . '" '
          . 'viewBox="0 0 ' . $width . ' ' . $height . '" '
          . 'xmlns="http://www.w3.org/2000/svg">'
          . '<defs><linearGradient id="' . $gradId . '" x1="0" y1="0" x2="0" y2="1">'
          . '<stop offset="0%" stop-color="' . $color . '" stop-opacity="0.2"/>'
          . '<stop offset="100%" stop-color="' . $color . '" stop-opacity="0"/>'
          . '</linearGradient></defs>'
          . $refLines
          . '<polygon points="' . $fillPath . '" fill="url(#' . $gradId . ')"/>'
          . '<polyline points="' . $polyline . '" fill="none" stroke="' . $color . '" '
          . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
          . $dots
          . $xlabels
          . '</svg>';
}

// Pre-build vitals charts
$bpChart    = buildTrendChart($vitalsForCharts, 'bps',  'label', 280, 90, '#A32D2D');
$hrChart    = buildTrendChart($vitalsForCharts, 'pulse', 'label', 280, 90, '#534AB7');
$bmiChart   = buildTrendChart($vitalsForCharts, 'BMI',   'label', 280, 90, '#C77A0A');
$spo2Chart  = buildTrendChart($vitalsForCharts, 'oxygen_saturation', 'label', 280, 90, '#1D9E75');

// Pre-build lab trend charts
$labCharts = [];
foreach ($labTrendGroups as $testName => $readings) {
    $colors = [
        'HbA1c'       => '#C77A0A',
        'LDL'         => '#A32D2D',
        'eGFR'        => '#534AB7',
        'Glucose'     => '#1D9E75',
        'Creatinine'  => '#6B6B6B',
        'Cholesterol' => '#C77A0A',
    ];
    $c = $colors[$testName] ?? '#1D9E75';
    $labCharts[$testName] = buildTrendChart(
        $readings, 'result_text', 'date', 280, 90, $c
    );
}

// ── QUERY 17: Visit History ───────────────────────────────────────────────────
$visitHistory = [];
$vhResult = sqlStatement(
    "SELECT fe.encounter, fe.date, fe.reason,
            fe.pc_catid,
            CONCAT(u.fname,' ',u.lname) AS provider,
            u.title AS provider_title,
            fs.assessment, fs.subjective
     FROM   form_encounter fe
     LEFT JOIN users u ON u.id = fe.provider_id
     LEFT JOIN forms f
            ON f.encounter = fe.encounter
           AND f.formdir   = 'soap'
           AND f.deleted   = 0
     LEFT JOIN form_soap fs ON fs.id = f.form_id
     WHERE  fe.pid = ?
     ORDER  BY fe.date DESC
     LIMIT  20",
    [$pid]
);
while ($row = sqlFetchArray($vhResult)) {
    $visitHistory[] = $row;
}

// ── QUERY 18: Orders (labs + imaging) ─────────────────────────────────────────
$allOrders = [];
$ordResult = sqlStatement(
    "SELECT po.procedure_order_id,
            po.date_ordered,
            po.order_status,
            po.procedure_order_type,
            poc.procedure_name        AS test_name,
            poc.procedure_code AS proc_code,
            pr.result_text,
            pr.abnormal,
            pr.result_status
     FROM   procedure_order po
     LEFT JOIN procedure_order_code poc
            ON poc.procedure_order_id = po.procedure_order_id
     LEFT JOIN procedure_report prp
            ON prp.procedure_order_id = po.procedure_order_id
     LEFT JOIN procedure_result pr
            ON pr.procedure_report_id = prp.procedure_report_id
     WHERE  po.patient_id = ?
     ORDER  BY po.date_ordered DESC
     LIMIT  30",
    [$pid]
);
while ($row = sqlFetchArray($ordResult)) {
    $allOrders[] = $row;
}

// ── QUERY 19: Referrals ───────────────────────────────────────────────────────
$referrals = [];
// $refResult = sqlStatement(
//     "SELECT r.id, r.refer_date,
//             r.refer_to,
//             r.reason,
//             r.reply_date,
//             r.reply_body,
//             CONCAT(u.fname,' ',u.lname) AS referred_by
//      FROM   refer r
//      LEFT JOIN users u ON u.id = r.authorized
//      WHERE  r.pid = ?
//      ORDER  BY r.refer_date DESC
//      LIMIT  10",
//     [$pid]
// );
// while ($row = sqlFetchArray($refResult)) {
//     $referrals[] = $row;
// }

// ── QUERY 20: Billing claims ──────────────────────────────────────────────────
$claims = [];
$claimResult = sqlStatement(
    "SELECT b.id, b.code, b.code_type,
            b.fee, b.billed, b.authorized,
            b.bill_date, b.activity,
            fe.date AS enc_date,
            fe.reason,fe.facility,
            ic.name AS insurance_name
     FROM   billing b
     LEFT JOIN form_encounter fe
            ON fe.encounter = b.encounter
           AND fe.pid       = b.pid
     LEFT JOIN insurance_data id2
            ON id2.pid  = b.pid
           AND id2.type = 'primary'
     LEFT JOIN insurance_companies ic
            ON ic.id = id2.provider
     WHERE  b.pid      = ?
     AND    b.activity = 1
     ORDER  BY b.id DESC
     LIMIT  20",
    [$pid]
);
while ($row = sqlFetchArray($claimResult)) {
    $claims[] = $row;
}

// ── Billing summary totals ────────────────────────────────────────────────────
$totalBilled   = array_sum(array_column(
    array_filter($claims, fn($c) => $c['billed']), 'fee'));
$totalUnbilled = array_sum(array_column(
    array_filter($claims, fn($c) => !$c['billed']), 'fee'));
$cptClaims     = array_filter($claims,
    fn($c) => in_array($c['code_type'], ['CPT','HCPCS']));
$icdClaims     = array_filter($claims,
    fn($c) => in_array($c['code_type'], ['ICD10','ICD9']));

// ── QUERY 12: SOAP Note content ───────────────────────────────────────────────
$soapNote = sqlQuery(
    "SELECT fs.subjective, fs.objective, fs.assessment, fs.plan,
            fe.date, fe.encounter
     FROM   form_soap fs
     JOIN   forms f ON f.form_id = fs.id AND f.formdir = 'soap'
     JOIN   form_encounter fe ON fe.encounter = f.encounter
     WHERE  f.pid = ?
     AND    f.encounter = ?
     AND    f.deleted = 0
     ORDER  BY fs.id DESC
     LIMIT  1",
    [$pid, $encounter]
);

// If no SOAP for current encounter, get most recent one
if (!$soapNote) {
    $soapNote = sqlQuery(
        "SELECT fs.subjective, fs.objective, fs.assessment, fs.plan,
                fe.date, fe.encounter
         FROM   form_soap fs
         JOIN   forms f ON f.form_id = fs.id AND f.formdir = 'soap'
         JOIN   form_encounter fe ON fe.encounter = f.encounter
         WHERE  f.pid = ?
         AND    f.deleted = 0
         ORDER  BY fe.date DESC
         LIMIT  1",
        [$pid]
    );
}

$soapSubjective = $soapNote['subjective'] ?? '';
$soapObjective  = $soapNote['objective']  ?? '';
$soapAssessment = $soapNote['assessment'] ?? '';
$soapPlan       = $soapNote['plan']       ?? '';
$soapEncDate    = $soapNote['date']
    ? date('M d, Y', strtotime($soapNote['date']))
    : date('M d, Y');

// ── Card counts ───────────────────────────────────────────────────────────────
$vitalsCount  = $vitalsData ? 1 : 0;
$probCount    = count($problems);
$medCount     = count($medications);
$labCount     = count($labs);
$allergyCount = count($allergyFull);
$familyHistoryCount = count($familyHistoryFull);

// ── Vitals display helpers ────────────────────────────────────────────────────
$bp       = ($vitalsData && $vitalsData['bps']) ? $vitalsData['bps'] . '/' . $vitalsData['bpd'] : '—';
$hr       = $vitalsData['pulse']            ?? '—';
$spo2     = $vitalsData['oxygen_saturation'] ?? '—';
$temp     = $vitalsData['temperature']      ?? '—';
$rr       = $vitalsData['respiration']      ?? '—';
$wt       = $vitalsData['weight']           ?? '—';
$ht       = $vitalsData['height']           ?? '—';
$bmi      = $vitalsData['BMI']              ?? '—';
$vitDate  = $vitalsData['date'] ? date('M d', strtotime($vitalsData['date'])) : 'No data';

// BP status
function bpStatus($sys) {
    if (!$sys) return ['label' => 'No Data', 'cls' => 'syd-val-neutral'];
    if ($sys >= 180) return ['label' => 'Hypertensive Crisis', 'cls' => 'syd-val-danger'];
    if ($sys >= 140) return ['label' => 'High',    'cls' => 'syd-val-warn'];
    if ($sys >= 130) return ['label' => 'Elevated','cls' => 'syd-val-warn'];
    if ($sys >= 90)  return ['label' => 'Normal',  'cls' => 'syd-val-ok'];
    return ['label' => 'Low', 'cls' => 'syd-val-warn'];
}
$bpStatus = bpStatus($vitalsData['bps'] ?? null);

// SVG sparkline generator
function buildSparkline($points, $key, $width = 120, $height = 36) {
    if (count($points) < 2) return '<svg width="'.$width.'" height="'.$height.'"></svg>';
    $vals = array_map(fn($p) => (float)($p[$key] ?? 0), $points);
    $vals = array_filter($vals, fn($v) => $v > 0);
    if (count($vals) < 2) return '<svg width="'.$width.'" height="'.$height.'"></svg>';
    $vals  = array_values($vals);
    $min   = min($vals);
    $max   = max($vals);
    $range = $max - $min ?: 1;
    $n     = count($vals);
    $pts   = [];
    foreach ($vals as $i => $v) {
        $x = round($i / ($n - 1) * $width, 1);
        $y = round($height - (($v - $min) / $range * ($height - 6)) - 3, 1);
        $pts[] = "$x,$y";
    }
    $poly = implode(' ', $pts);
    $fill = implode(' ', $pts) . " $width,$height 0,$height";
    return '<svg width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" xmlns="http://www.w3.org/2000/svg">
      <defs><linearGradient id="sg" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#1D9E75" stop-opacity="0.3"/>
        <stop offset="100%" stop-color="#1D9E75" stop-opacity="0"/>
      </linearGradient></defs>
      <polygon points="'.$fill.'" fill="url(#sg)"/>
      <polyline points="'.$poly.'" fill="none" stroke="#1D9E75" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="'.end($pts).'" r="3" fill="#1D9E75" transform="translate(0,0)"/>
    </svg>';
}
$bpSparkline = buildSparkline($vitalsHistory, 'bps');
$hrSparkline = buildSparkline($vitalsHistory, 'pulse');

// CSRF token for AJAX calls later
$csrfToken = CsrfUtils::collectCsrfToken();

// Severity → colour map for allergy chips
function allergyChipClass($severity) {
    $s = strtolower($severity ?? '');
    if (strpos($s, 'sev') !== false || strpos($s, 'high') !== false) return 'syd-chip-danger';
    if (strpos($s, 'mod') !== false || strpos($s, 'med') !== false)  return 'syd-chip-warn';
    return 'syd-chip-mild';
}


function formatValues($value, $decimals = 1)
{
    if ($value === null || $value === '') {
        return '—';
    }

    // Remove unnecessary trailing zeros
    return rtrim(rtrim(number_format((float)$value, $decimals, '.', ''), '0'), '.');
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Synapta Dashboard</title>
    <link rel="stylesheet"
        href="<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-physician-dashboard/public/css/dashboard.css">
</head>

<body>
    <div id="synapta-dashboard" class="syd-with-ai-panel">

        <!-- ══════════════════════════════════════════════════════ -->
        <!--  PATIENT HEADER BAR                                    -->
        <!-- ══════════════════════════════════════════════════════ -->
        <header class="syd-topbar">

            <!-- Brand logo mark -->
            <div class="syd-brand">
                <svg width="36" height="36" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" aria-label="Synapta">
                    <line x1="40" y1="29" x2="40" y2="16" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="51" y1="35" x2="63" y2="28" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="51" y1="45" x2="63" y2="52" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="40" y1="51" x2="40" y2="64" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="29" y1="45" x2="17" y2="52" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="29" y1="35" x2="17" y2="28" stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round" />
                    <circle cx="40" cy="40" r="10" fill="#1D9E75" />
                    <circle cx="40" cy="40" r="6" fill="#04342C" />
                    <rect x="37.5" y="33.5" width="5" height="13" rx="1" fill="#9FE1CB" opacity="0.8" />
                    <rect x="34" y="37" width="12" height="5" rx="1" fill="#9FE1CB" opacity="0.8" />
                    <circle cx="40" cy="12" r="5" fill="#5DCAA5" />
                    <circle cx="40" cy="12" r="2.5" fill="#04342C" />
                    <circle cx="67" cy="25" r="5" fill="#AFA9EC" />
                    <circle cx="67" cy="25" r="2.5" fill="#26215C" />
                    <circle cx="67" cy="55" r="5" fill="#5DCAA5" />
                    <circle cx="67" cy="55" r="2.5" fill="#04342C" />
                    <circle cx="40" cy="68" r="5" fill="#AFA9EC" />
                    <circle cx="40" cy="68" r="2.5" fill="#26215C" />
                    <circle cx="13" cy="55" r="5" fill="#5DCAA5" />
                    <circle cx="13" cy="55" r="2.5" fill="#04342C" />
                    <circle cx="13" cy="25" r="5" fill="#AFA9EC" />
                    <circle cx="13" cy="25" r="2.5" fill="#26215C" />
                </svg>
                <span class="syd-brand-name"><span class="syd-brand-syn">Syn</span><span
                        class="syd-brand-apta">apta</span></span>
            </div>

            <!-- Patient info section -->
            <div class="syd-pt-section">

                <!-- Avatar -->
                <div class="syd-pt-avatar"><?php echo htmlspecialchars($patientInitials); ?></div>

                <!-- Name + meta -->
                <div class="syd-pt-info">
                    <div class="syd-pt-name"><?php echo htmlspecialchars($patientName); ?></div>
                    <div class="syd-pt-meta">
                        DOB: <?php echo $dob; ?>
                        &nbsp;·&nbsp; <?php echo $age . $sex; ?>
                        &nbsp;·&nbsp; MRN: <?php echo $mrn; ?>
                        &nbsp;·&nbsp; <?php echo $insurance; ?>
                        &nbsp;·&nbsp; PCP: Dr. <?php echo $provName; ?>
                        &nbsp;·&nbsp; Enc: <?php echo $encDate; ?>
                    </div>
                </div>

                <!-- Allergy + info chips -->

                <div class="syd-chips">

                    <?php if (empty($allergies)): ?>

                    <div style="margin-bottom:6px;">
                        <span class="syd-chip syd-chip-mild">NKDA</span>
                    </div>

                    <?php else: ?>

                    <!-- Show Only First 2 Allergies -->
                    <?php foreach (array_slice($allergies, 0, 2) as $index => $allergy): ?>

                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">

                        <span class="syd-chip <?php echo allergyChipClass($allergy['severity']); ?>">
                            ⚠ <?php echo text($allergy['title']); ?>
                        </span>

                        <!-- Show +more badge beside last visible allergy -->
                        <?php if (
                    $index == 1 &&
                    count($allergies) > 2
                ): ?>

                        <span class="syd-chip syd-chip-more" style="color:red;font-size:10px; padding: 3px 2px;">
                            +<?php echo count($allergies) - 2; ?> more
                        </span>

                        <?php endif; ?>

                    </div>

                    <?php endforeach; ?>

                    <?php endif; ?>

                </div>


            </div>

            <!-- Action buttons -->
            <div class="syd-actions">
                <button class="syd-btn syd-btn-ghost" onclick="sydOpenChart()">📋 Chart</button>
                <button class="syd-btn syd-btn-ghost">💊 Rx</button>
                <!-- <button class="syd-btn syd-btn-ghost">📞 Telehealth</button> -->
                <button class="syd-btn syd-btn-scribe" id="syd-scribe-btn" onclick="sydToggleScribe()">
                    <span class="syd-live-dot"></span> Scribe — Ready
                </button>
            </div>

            <!-- Provider info -->
            <div class="syd-provider">
                <div class="syd-prov-avatar"><?php echo htmlspecialchars($provInitials); ?></div>
                <div class="syd-prov-name">Dr. <?php echo $provName; ?></div>
            </div>

        </header>
        <!-- /syd-topbar -->

        <nav class="syd-sidebar">
            <!-- Today's Schedule — active by default -->
            <button class="syd-nav-item active" title="Today's Schedule" onclick="sydNavClick(this)">
                📅
                <span class="syd-nav-tip">Today's Schedule</span>
            </button>

            <!-- Patient List -->
            <button class="syd-nav-item" title="Patient List" onclick="sydNavClick(this)">
                🏥
                <span class="syd-nav-tip">Patient List</span>
            </button>

            <!-- Messages with badge -->
            <button class="syd-nav-item" title="Messages" onclick="sydNavClick(this)">
                <span class="syd-nav-badge">4</span>
                💬
                <span class="syd-nav-tip">Messages</span>
            </button>

            <div class="syd-nav-sep"></div>

            <!-- Lab Results with badge -->
            <button class="syd-nav-item" title="Lab Results" onclick="sydNavClick(this)">
                <span class="syd-nav-badge">2</span>
                🧪
                <span class="syd-nav-tip">Lab Results</span>
            </button>

            <!-- Orders -->
            <button class="syd-nav-item" title="Orders" onclick="sydNavClick(this)">
                📝
                <span class="syd-nav-tip">Orders</span>
            </button>

            <!-- Billing -->
            <button class="syd-nav-item" title="Billing & RCM" onclick="sydNavClick(this)">
                💰
                <span class="syd-nav-tip">Billing &amp; RCM</span>
            </button>

            <div class="syd-nav-sep"></div>

            <!-- Analytics -->
            <button class="syd-nav-item" title="Analytics" onclick="sydNavClick(this)">
                📊
                <span class="syd-nav-tip">Analytics</span>
            </button>

            <!-- Settings — pinned to bottom -->
            <button class="syd-nav-item syd-nav-bottom" title="Settings" onclick="sydNavClick(this)">
                ⚙️
                <span class="syd-nav-tip">Settings</span>
            </button>
        </nav>



        <!-- Dashboard body — tabs and content go here in next steps -->
        <!-- ══════════════════════════════════════════════════════ -->
        <!--  MAIN DASHBOARD BODY                                   -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div id="syd-body">

            <!-- Tab navigation -->
            <div class="syd-tab-nav">
                <button class="syd-tab active" onclick="sydSwitchTab('encounter', this)">📋 Encounter</button>
                <button class="syd-tab" onclick="sydSwitchTab('summary',   this)">🧠 Summary</button>
                <button class="syd-tab" onclick="sydSwitchTab('history',   this)">📅 Visit History</button>
                <button class="syd-tab" onclick="sydSwitchTab('orders',    this)">🔬 Orders</button>
                <button class="syd-tab" onclick="sydSwitchTab('billing',   this)">💲 Billing</button>
            </div>
            <!-- Encounter sub-header -->
            <div class="syd-enc-bar">
                <div class="syd-enc-left">
                    <span class="syd-enc-type"><?php echo $encReason ?: 'Office Visit'; ?></span>
                    <span class="syd-enc-sep"></span>
                    <span class="syd-enc-meta"><?php echo $encDate; ?> &nbsp;·&nbsp; Dr. <?php echo $provName; ?>,
                        <?php echo $provTitle; ?></span>
                </div>
                <div class="syd-enc-status" id="syd-enc-status">● In Progress</div>
            </div>

            <!-- ── ENCOUNTER TAB ── -->
            <div id="syd-tab-encounter" class="syd-tab-panel active">
                <div class="syd-enc-wrap">

                    <!-- Encounter sub-header -->
                    <!-- <div class="syd-enc-bar">
          <span class="syd-enc-type"><?php echo text($encReason ?: 'Office Visit'); ?></span>
          <span class="syd-enc-sep"></span>
          <span class="syd-enc-meta">
            <?php echo $encDate; ?> &nbsp;·&nbsp; Dr. <?php echo $provName; ?>, <?php echo $provTitle; ?>
          </span>
          <div class="syd-enc-status" id="syd-enc-status">● In Progress</div>
        </div> -->

                    <!-- 5 Clinical Summary Cards -->

                    <div class="syd-slider-wrap">

                        <!-- LEFT ARROW -->
                        <button class="syd-slider-btn" id="syd-prev" onclick="sydSlide(-1)" disabled
                            aria-label="Previous cards">&lt;</button>

                        <!-- VIEWPORT -->
                        <div class="syd-slider-viewport" id="syd-viewport">
                            <div class="syd-cards-row" id="syd-cards-row">

                                <!-- ── CARD 1: Vitals ── -->
                                <div class="syd-card <?php echo ($vitalsData ? '' : 'syd-card-empty'); ?>"
                                    onclick="sydOpenPanel('vitals')" id="syd-card-vitals">
                                    <div class="syd-card-header">
                                        <span class="syd-card-icon">📊</span>
                                        <span class="syd-card-title">Vitals</span>
                                        <?php if ($vitalsData): ?>
                                        <span class="syd-card-date"><?php echo $vitDate; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syd-card-body">
                                        <?php if ($vitalsData): ?>
                                        <div style="font-size:10.5px;line-height:1.65;color:var(--syd-body);">
                                            BP <strong
                                                class="<?php echo $bpStatus['cls']; ?>"><?php echo $bp; ?></strong>
                                            <?php if ($bp !== '—' && ($vitalsData['bps'] ?? 0) >= 130): ?>↑<?php endif; ?>
                                            · HR <strong><?php echo formatValues($hr); ?></strong> bpm<br>
                                            SpO₂ <strong><?php echo $spo2; ?></strong>%
                                            · Temp <strong><?php echo formatValues($temp); ?>°F</strong><br>
                                            RR <strong><?php echo formatValues($rr); ?></strong>/min
                                            <?php if ($bmi !== '—'): ?>
                                            · BMI <strong><?php echo formatValues($bmi); ?></strong>
                                            <?php endif; ?>
                                            <?php if ($bp !== '—' && ($vitalsData['bps'] ?? 0) >= 130): ?>
                                            <br><span
                                                style="font-size:10px;color:var(--syd-amber);display:block;margin-top:2px;">⚠
                                                BP elevated</span>
                                            <?php endif; ?>
                                            <?php if ($rr !== '—' && (float)$rr > 20): ?>
                                            <span style="font-size:10px;color:var(--syd-amber);display:block;">⚠ RR
                                                mildly elevated</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="syd-card-empty-msg">No vitals recorded</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- ── CARD 2: Problems ── -->
                                <div class="syd-card <?php echo (empty($problems) ? 'syd-card-empty' : ''); ?>"
                                    onclick="sydOpenPanel('problems')" id="syd-card-problems">
                                    <div class="syd-card-header">
                                        <span class="syd-card-icon">📋</span>
                                        <span class="syd-card-title">Problem List</span>
                                        <?php if ($probCount > 0): ?>
                                        <span class="syd-card-count"><?php echo $probCount; ?> Active</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syd-card-body">
                                        <?php if (!empty($problems)): ?>
                                        <?php foreach (array_slice($problems, 0, 3) as $i => $prob): ?>
                                        <div style="font-size:10.5px;line-height:1.6;margin-bottom:1px;">
                                            <strong
                                                <?php if ($i < 2): ?>style="color:<?php echo $i===0?'var(--syd-red)':'var(--syd-amber)'; ?>"
                                                <?php endif; ?>>
                                                <?php echo text($prob['title']); ?>
                                            </strong>
                                            <?php if ($prob['diagnosis']): ?>(<?php echo text($prob['diagnosis']); ?>)<?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if ($probCount > 3): ?>
                                        <div style="font-size:10px;color:var(--syd-muted);margin-top:3px;">
                                            +<?php echo $probCount - 3; ?> more</div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="syd-card-empty-msg">No active problems</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- ── CARD 3: Medications ── -->
                                <div class="syd-card <?php echo (empty($medications) ? 'syd-card-empty' : ''); ?>"
                                    onclick="sydOpenPanel('medications')" id="syd-card-medications">
                                    <div class="syd-card-header">
                                        <span class="syd-card-icon">💊</span>
                                        <span class="syd-card-title">Medications</span>
                                        <?php if (!empty($medCount) && $medCount > 0): ?>
                                        <span
                                            class="syd-card-count syd-card-count-amber"><?php echo (int)$medCount; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syd-card-body">
                                        <?php if (!empty($medications)): ?>
                                        <?php foreach (array_slice($medications, 0, 3) as $med): ?>
                                        <div style="font-size:10.5px;line-height:1.6;margin-bottom:1px;">
                                            <?php echo text($med['drug'] ?? 'Unknown Medication'); ?>
                                            <?php if (!empty($med['dosage']) && !empty($med['unit'])): ?>
                                            <span style="color:var(--syd-muted);font-size:10px;">
                                                · <?php echo htmlspecialchars($med['dosage']); ?>
                                                <?php echo htmlspecialchars($med['unit']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if ($medCount > 3): ?>
                                        <div style="font-size:10px;color:var(--syd-muted);margin-top:3px;">
                                            <?php echo ($medCount - 3); ?> more</div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="syd-card-empty-msg">No active medications</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- ── CARD 4: Labs ── -->
                                <div class="syd-card <?php echo (empty($labs) ? 'syd-card-empty' : ''); ?>"
                                    onclick="sydOpenPanel('labs')" id="syd-card-labs">
                                    <div class="syd-card-header">
                                        <span class="syd-card-icon">🧪</span>
                                        <span class="syd-card-title">Labs &amp; Studies</span>
                                        <?php if ($labCount > 0): ?>
                                        <?php if ($abnormalCount > 0): ?>
                                        <span class="syd-card-alert"><?php echo $abnormalCount; ?> abnormal</span>
                                        <?php else: ?>
                                        <span class="syd-card-count"><?php echo $labCount; ?></span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syd-card-body">
                                        <?php if (!empty($labs)): ?>
                                        <?php foreach (array_slice($labs, 0, 3) as $lab): ?>
                                        <div style="font-size:10.5px;line-height:1.6;margin-bottom:1px;">
                                            <?php if (!empty($lab['abnormal'])): ?>
                                            <span style="color:var(--syd-amber);font-weight:600;">
                                                <?php echo text($lab['test_name'] ?? 'Lab'); ?>
                                                <?php if ($lab['result_text']): ?>
                                                <?php echo text($lab['result_text']); ?>
                                                <?php echo text($lab['units'] ?? ''); ?>
                                                <?php endif; ?>
                                            </span>
                                            <?php else: ?>
                                            <?php echo text($lab['test_name'] ?? 'Lab'); ?>
                                            <?php if ($lab['result_text']): ?>
                                            <span style="color:var(--syd-muted);font-size:10px;">·
                                                <?php echo text($lab['result_text']); ?><?php echo text($lab['units'] ?? ''); ?></span>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if ($labCount > 3): ?>
                                        <div style="font-size:10px;color:var(--syd-teal);margin-top:3px;">
                                            +<?php echo $labCount - 3; ?> more results</div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="syd-card-empty-msg">No recent results</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- ── CARD 5: Allergies ── -->
                                <div class="syd-card <?php echo (empty($allergyFull) ? 'syd-card-empty' : 'syd-card-alert-border'); ?>"
                                    onclick="sydOpenPanel('allergies')" id="syd-card-allergies">
                                    <div class="syd-card-header">
                                        <span class="syd-card-icon">⚠️</span>
                                        <span class="syd-card-title">Allergies</span>
                                        <?php if ($allergyCount > 0): ?>
                                        <span class="syd-card-count"><?php echo $allergyCount; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syd-card-body">
                                        <?php if (!empty($allergyFull)): ?>
                                        <?php foreach (array_slice($allergyFull, 0, 3) as $al): ?>
                                        <div style="font-size:10.5px;line-height:1.6;margin-bottom:1px;">
                                            <?php
                                $sev = strtolower($al['severity'] ?? '');
                                $alColor = 'var(--syd-red)';
                                if (strpos($sev,'mod') !== false || strpos($sev,'med') !== false)
                                    $alColor = 'var(--syd-amber)';
                                ?>
                                            <strong
                                                style="color:<?php echo $alColor; ?>"><?php echo text($al['title']); ?></strong>
                                            <?php if ($al['reaction']): ?>
                                            <span style="color:var(--syd-muted);font-size:10px;">
                                                — <?php echo text($al['reaction']); ?>
                                                (<?php echo text($al['severity'] ?? ''); ?>)
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if ($allergyCount > 3): ?>
                                        <div style="font-size:10px;color:var(--syd-muted);margin-top:3px;">
                                            +<?php echo $allergyCount - 3; ?> more</div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="syd-card-empty-msg" style="color:var(--syd-teal);font-weight:600;">
                                            NKDA</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- ── CARD 6: Family History  (NEW) ── -->
                                <div class="syd-card <?php echo (empty($familyHistoryFull) ? 'syd-card-empty' : ''); ?>"
                                    onclick="sydOpenPanel('family_history')" id="syd-card-family">
                                    <div class="syd-card-header">
                                        <span class="syd-card-icon">👨‍👩‍👦</span>
                                        <span class="syd-card-title">Family History</span>
                                        <?php if (!empty($familyHistoryFull)): ?>
                                        <span class="syd-card-count"><?php echo count($familyHistoryFull); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syd-card-body">
                                        <?php if (!empty($familyHistoryFull)): ?>
                                        <?php foreach (array_slice($familyHistoryFull, 0, 3) as $fh): ?>
                                        <div style="font-size:10.5px;line-height:1.6;margin-bottom:2px;">
                                            <strong><?php echo text($fh['relation']); ?></strong>

                                            <?php if (!empty($fh['condition'])): ?>
                                            <span style="color:var(--syd-muted);font-size:10px;">
                                                — <?php echo text($fh['history']); ?>
                                            </span>
                                            <?php endif; ?>


                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (count($familyHistoryFull) > 3): ?>
                                        <div style="font-size:10px;color:var(--syd-muted);margin-top:3px;">
                                            +<?php echo count($familyHistoryFull) - 3; ?> more</div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="syd-card-empty-msg">No family history recorded</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- ── CARD 7: Surgical History  (NEW) ── -->
                                <div class="syd-card <?php echo (empty($surgicalHistory) ? 'syd-card-empty' : ''); ?>"
                                    onclick="sydOpenPanel('surgical_history')" id="syd-card-surgical">
                                    <div class="syd-card-header">
                                        <span class="syd-card-icon">🔪</span>
                                        <span class="syd-card-title">Surgical History</span>
                                        <?php if (!empty($surgicalHistory)): ?>
                                        <span class="syd-card-count"><?php echo count($surgicalHistory); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="syd-card-body">
                                        <?php if (!empty($surgicalHistory)): ?>
                                        <?php foreach (array_slice($surgicalHistory, 0, 3) as $sx): ?>
                                        <div style="font-size:10.5px;line-height:1.6;margin-bottom:2px;">

                                            <strong>
                                                <?php echo text($sx['title'] ?? 'Procedure'); ?>
                                            </strong>
                                      </div>
                                        <?php endforeach; ?>
                                        <?php if (count($surgicalHistory) > 3): ?>
                                        <div style="font-size:10px;color:var(--syd-muted);margin-top:3px;">
                                            +<?php echo count($surgicalHistory) - 3; ?> more</div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="syd-card-empty-msg">No surgical history recorded</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div><!-- /syd-cards-row -->
                        </div><!-- /syd-slider-viewport -->

                        <!-- RIGHT ARROW -->
                        <button class="syd-slider-btn" id="syd-next" onclick="sydSlide(1)"
                            aria-label="Next cards">&gt;</button>

                    </div><!-- /syd-slider-wrap -->

                    <!-- Dot indicators -->
                    <div class="syd-slider-dots" id="syd-dots">
                        <div class="syd-slider-dot active"></div>
                        <div class="syd-slider-dot"></div>
                    </div>
                    <!-- /syd-cards-row -->

                    <!-- TWO-COLUMN WORKSPACE -->
                    <div class="syd-workspace">

                        <!-- LEFT: SOAP Note Editor -->
                        <div class="syd-soap-col">

                            <!-- SOAP Header -->
                            <div class="syd-soap-header">
                                <div class="syd-soap-header-left">
                                    <span class="syd-soap-title">📝 Encounter Note</span>
                                    <span class="syd-soap-date"><?php echo $soapEncDate; ?></span>
                                    <span class="syd-soap-badge" id="syd-save-status">Unsaved</span>
                                </div>
                                <div class="syd-soap-header-right">
                                    <button class="syd-soap-btn syd-soap-btn-ghost" onclick="sydClearNote()">🗑
                                        Clear</button>
                                    <button class="syd-soap-btn syd-soap-btn-ghost" onclick="sydExpandAll()">⬆
                                        Expand</button>
                                    <button class="syd-soap-btn syd-soap-btn-primary" onclick="sydSaveNote()"
                                        id="syd-save-btn">
                                        💾 Save Note
                                    </button>
                                </div>
                            </div>

                            <!-- SOAP Sections Body -->
                            <div class="syd-soap-body">

                                <!-- S — Subjective -->
                                <div class="syd-soap-section" id="syd-section-s">
                                    <div class="syd-soap-section-header" onclick="sydToggleSection('s')">
                                        <div class="syd-soap-section-left">
                                            <span class="syd-soap-letter syd-letter-s"></span>
                                            <span class="syd-soap-section-title">Subjective</span>
                                        </div>
                                        <div class="syd-soap-section-right">
                                            <?php if ($soapSubjective): ?>
                                            <span class="syd-soap-filled">● Filled</span>
                                            <?php else: ?>
                                            <span class="syd-soap-empty-badge">○ Empty</span>
                                            <?php endif; ?>
                                            <span class="syd-soap-chevron" id="syd-chevron-s">▼</span>
                                        </div>
                                    </div>
                                    <div class="syd-soap-section-body" id="syd-body-s">
                                        <div class="syd-ai-suggest" id="syd-ai-s" style="display:none;">
                                            <span class="syd-ai-label">🤖 AI Draft</span>
                                            <span class="syd-ai-text" id="syd-ai-s-text"></span>
                                            <button class="syd-ai-accept" onclick="sydAcceptAI('s')">Accept</button>
                                        </div>
                                        <textarea id="syd-soap-s" class="syd-soap-textarea"
                                            placeholder="Chief complaint, HPI, patient-reported symptoms…"
                                            oninput="sydMarkUnsaved()"
                                            rows="4"><?php echo htmlspecialchars($soapSubjective); ?></textarea>
                                        <div class="syd-soap-footer">
                                            <span class="syd-char-count"
                                                id="syd-count-s"><?php echo strlen($soapSubjective); ?> chars</span>
                                            <button class="syd-soap-micro" onclick="sydInsertTemplate('s')">📋
                                                Template</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- O — Objective -->
                                <div class="syd-soap-section" id="syd-section-o">
                                    <div class="syd-soap-section-header" onclick="sydToggleSection('o')">
                                        <div class="syd-soap-section-left">
                                            <span class="syd-soap-letter syd-letter-o"></span>
                                            <span class="syd-soap-section-title">Objective</span>
                                        </div>
                                        <div class="syd-soap-section-right">
                                            <?php if ($soapObjective): ?>
                                            <span class="syd-soap-filled">● Filled</span>
                                            <?php else: ?>
                                            <span class="syd-soap-empty-badge">○ Empty</span>
                                            <?php endif; ?>
                                            <span class="syd-soap-chevron" id="syd-chevron-o">▼</span>
                                        </div>
                                    </div>
                                    <div class="syd-soap-section-body" id="syd-body-o">
                                        <div class="syd-ai-suggest" id="syd-ai-o" style="display:none;">
                                            <span class="syd-ai-label">🤖 AI Draft</span>
                                            <span class="syd-ai-text" id="syd-ai-o-text"></span>
                                            <button class="syd-ai-accept" onclick="sydAcceptAI('o')">Accept</button>
                                        </div>
                                        <?php if ($vitalsData): ?>
                                        <div class="syd-vitals-bar">
                                            <span class="syd-vb-item">
                                                <span class="syd-vb-label">BP</span>
                                                <span class="syd-vb-val"><?php echo $bp; ?></span>
                                            </span>
                                            <span class="syd-vb-item">
                                                <span class="syd-vb-label">HR</span>
                                                <span class="syd-vb-val"><?php echo $hr; ?></span>
                                            </span>
                                            <span class="syd-vb-item">
                                                <span class="syd-vb-label">Temp</span>
                                                <span class="syd-vb-val"><?php echo $temp; ?>°F</span>
                                            </span>
                                            <span class="syd-vb-item">
                                                <span class="syd-vb-label">SpO2</span>
                                                <span class="syd-vb-val"><?php echo $spo2; ?>%</span>
                                            </span>
                                            <span class="syd-vb-item">
                                                <span class="syd-vb-label">RR</span>
                                                <span class="syd-vb-val"><?php echo $rr; ?></span>
                                            </span>
                                            <button class="syd-vb-insert" onclick="sydInsertVitals()">↓ Insert
                                                Vitals</button>
                                        </div>
                                        <?php endif; ?>
                                        <textarea id="syd-soap-o" class="syd-soap-textarea"
                                            placeholder="Vitals, physical exam findings, observations…"
                                            oninput="sydMarkUnsaved()"
                                            rows="4"><?php echo htmlspecialchars($soapObjective); ?></textarea>
                                        <div class="syd-soap-footer">
                                            <span class="syd-char-count"
                                                id="syd-count-o"><?php echo strlen($soapObjective); ?> chars</span>
                                            <button class="syd-soap-micro" onclick="sydInsertTemplate('o')">📋
                                                Template</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- A — Assessment -->
                                <div class="syd-soap-section" id="syd-section-a">
                                    <div class="syd-soap-section-header" onclick="sydToggleSection('a')">
                                        <div class="syd-soap-section-left">
                                            <span class="syd-soap-letter syd-letter-a"></span>
                                            <span class="syd-soap-section-title">Assessment</span>
                                        </div>
                                        <div class="syd-soap-section-right">
                                            <?php if ($soapAssessment): ?>
                                            <span class="syd-soap-filled">● Filled</span>
                                            <?php else: ?>
                                            <span class="syd-soap-empty-badge">○ Empty</span>
                                            <?php endif; ?>
                                            <span class="syd-soap-chevron" id="syd-chevron-a">▼</span>
                                        </div>
                                    </div>
                                    <div class="syd-soap-section-body" id="syd-body-a">
                                        <div class="syd-ai-suggest" id="syd-ai-a" style="display:none;">
                                            <span class="syd-ai-label">🤖 AI Draft</span>
                                            <span class="syd-ai-text" id="syd-ai-a-text"></span>
                                            <button class="syd-ai-accept" onclick="sydAcceptAI('a')">Accept</button>
                                        </div>
                                        <?php if (!empty($problems)): ?>
                                        <div class="syd-prob-chips">
                                            <?php foreach (array_slice($problems, 0, 5) as $prob): ?>
                                            <button class="syd-prob-chip"
                                                onclick="sydInsertProblem('<?php echo addslashes(text($prob['title'])); ?>','<?php echo addslashes(text($prob['diagnosis'] ?? '')); ?>')">
                                                + <?php echo text($prob['title']); ?>
                                                <?php if ($prob['diagnosis']): ?>
                                                <span
                                                    class="syd-chip-code"><?php echo text($prob['diagnosis']); ?></span>
                                                <?php endif; ?>
                                            </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        <textarea id="syd-soap-a" class="syd-soap-textarea"
                                            placeholder="Diagnosis, clinical impression, differential…"
                                            oninput="sydMarkUnsaved()"
                                            rows="4"><?php echo htmlspecialchars($soapAssessment); ?></textarea>
                                        <div class="syd-soap-footer">
                                            <span class="syd-char-count"
                                                id="syd-count-a"><?php echo strlen($soapAssessment); ?> chars</span>
                                            <button class="syd-soap-micro" onclick="sydInsertTemplate('a')">📋
                                                Template</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- P — Plan -->
                                <div class="syd-soap-section" id="syd-section-p">
                                    <div class="syd-soap-section-header" onclick="sydToggleSection('p')">
                                        <div class="syd-soap-section-left">
                                            <span class="syd-soap-letter syd-letter-p"></span>
                                            <span class="syd-soap-section-title">Plan</span>
                                        </div>
                                        <div class="syd-soap-section-right">
                                            <?php if ($soapPlan): ?>
                                            <span class="syd-soap-filled">● Filled</span>
                                            <?php else: ?>
                                            <span class="syd-soap-empty-badge">○ Empty</span>
                                            <?php endif; ?>
                                            <span class="syd-soap-chevron" id="syd-chevron-p">▼</span>
                                        </div>
                                    </div>
                                    <div class="syd-soap-section-body" id="syd-body-p">
                                        <div class="syd-ai-suggest" id="syd-ai-p" style="display:none;">
                                            <span class="syd-ai-label">🤖 AI Draft</span>
                                            <span class="syd-ai-text" id="syd-ai-p-text"></span>
                                            <button class="syd-ai-accept" onclick="sydAcceptAI('p')">Accept</button>
                                        </div>
                                        <textarea id="syd-soap-p" class="syd-soap-textarea"
                                            placeholder="Medications, labs, referrals, follow-up, patient education…"
                                            oninput="sydMarkUnsaved()"
                                            rows="4"><?php echo htmlspecialchars($soapPlan); ?></textarea>
                                        <div class="syd-soap-footer">
                                            <span class="syd-char-count"
                                                id="syd-count-p"><?php echo strlen($soapPlan); ?> chars</span>
                                            <button class="syd-soap-micro" onclick="sydInsertTemplate('p')">📋
                                                Template</button>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /syd-soap-body -->

                            <!-- Action Bar — bottom of SOAP col -->
                            <div class="syd-soap-action-bar">
                                <button class="syd-action-btn" onclick="sydPrintNote()">
                                    <span class="ab-icon">🖨</span>Print
                                </button>
                                <button class="syd-action-btn" onclick="sydAddendum()">
                                    <span class="ab-icon">✏️</span>Addend
                                </button>
                                <button class="syd-action-btn" onclick="sydSaveNote()">
                                    <span class="ab-icon">💾</span>Save Draft
                                </button>
                                <button class="syd-action-btn" onclick="sydSignNote()">
                                    <span class="ab-icon">✍️</span>Sign
                                </button>
                                <button class="syd-action-btn syd-action-sign-close" onclick="sydSignNote()">
                                    <span class="ab-icon">✅</span>Sign &amp; Close
                                </button>
                            </div>

                        </div><!-- /syd-soap-col -->

                        <!-- RIGHT: Detail Panel -->
                        <div class="syd-right-col" id="syd-right-col">
                            <div id="syd-detail-header" class="syd-detail-header" style="display:none;">
                                <span class="syd-detail-title" id="syd-detail-ttl">Detail</span>
                                <button class="syd-detail-close" onclick="sydClosePanel()">✕</button>
                            </div>
                            <div class="syd-detail-body" id="syd-detail-inner">
                                <div class="syd-right-placeholder">
                                    <div class="syd-rp-icon">☝️</div>
                                    <div class="syd-rp-text">
                                        Click any card above —<br>
                                        <strong>Vitals · Problem List · Medications<br>
                                            Labs &amp; Studies · Allergies</strong><br><br>
                                        — to view full history here.
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /syd-workspace -->

                </div><!-- /syd-enc-wrap -->
            </div><!-- /encounter tab -->

            <!-- Other tabs — placeholders for now -->
            <!-- ══════════════════════════════════════════════════ -->
            <!--  TAB: SUMMARY                                      -->
            <!-- ══════════════════════════════════════════════════ -->
            <div id="syd-tab-summary" class="syd-tab-panel" style="display:none;">
                <div class="syd-tab-inner syd-summary-inner">


                    <div class="risk-banner fade">

                        <div style="font-size:22px;flex-shrink:0;margin-top:2px;">⚡</div>

                        <div style="flex:1;">

                            <!-- Top Row -->
                            <div style="
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
        margin-bottom:6px;
    ">

                                <!-- Title -->
                                <div style="
          font-size:9.5px;
          font-weight:700;
          text-transform:uppercase;
          letter-spacing:1.2px;
          color:#AFA9EC;
      ">
                                    Synapta Scribe · AI Risk Stratification
                                </div>

                                <!-- Disclaimer Badge -->
                                <span style="
          background:rgba(255,255,255,0.12);
          border:1px solid rgba(255,255,255,0.18);
          color:#FFD6D6;
          padding:4px 10px;
          border-radius:999px;
          font-size:9px;
          font-weight:500;
          letter-spacing:.4px;
          white-space:nowrap;
          backdrop-filter:blur(4px);
          cursor:pointer;
      ">
                                    AI-Assisted Risk Indicator · Requires Clinical Review
                                </span>

                            </div>

                            <!-- Main Text -->
                            <div style="
        font-size:11.5px;
        font-weight:600;
        color:#fff;
        line-height:1.6;
    ">

                                This patient has
                                <span style="color:#F4B0B0;">
                                    3 uncontrolled cardiovascular risk factors
                                </span>
                                (hypertension, type 2 diabetes, hyperlipidemia),
                                a
                                <span style="color:#F4B0B0;">
                                    strong family history of MI
                                </span>,
                                and presents today with chest pain — ACS must be ruled out before this visit concludes.
                                Two preventive screenings (mammogram, colonoscopy) are also overdue.

                            </div>

                        </div>

                    </div>

                    <!-- ── ROW 1: Risk Banner ── -->
                    <div class="syd-risk-banner syd-risk-<?php echo $riskColor; ?>" style="display:none">
                        <div class="syd-rb-left">
                            <div class="syd-rb-icon">
                                <?php
                echo $riskColor === 'danger' ? '🔴'
                   : ($riskColor === 'warn'  ? '🟡' : '🟢');
              ?>
                            </div>
                            <div class="syd-rb-content">
                                <div class="syd-rb-title">
                                    <?php echo htmlspecialchars($riskLabel); ?>
                                    <span class="syd-rb-score">
                                        Risk Score: <?php echo $riskScore; ?>/7
                                    </span>
                                </div>
                                <div class="syd-rb-subtitle">
                                    <?php if (!empty($riskFlags)): ?>
                                    <?php echo htmlspecialchars(
                      implode(' · ', array_column($riskFlags, 'text'))
                  ); ?>
                                    <?php else: ?>
                                    No significant risk factors identified at this visit.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="syd-rb-right">
                            <div class="syd-risk-meter">
                                <div class="syd-risk-meter-bar">
                                    <div class="syd-risk-meter-fill
                  syd-risk-fill-<?php echo $riskColor; ?>"
                                        style="width:<?php echo min(100, ($riskScore / 7) * 100); ?>%">
                                    </div>
                                </div>
                                <div class="syd-risk-meter-labels">
                                    <span>Low</span>
                                    <span>Medium</span>
                                    <span>High</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── ROW 2: Summary Grid ── -->
                    <div class="syd-summary-grid">

                        <!-- LEFT COLUMN -->
                        <div class="syd-summary-col-left">

                            <!-- Outstanding Action Items -->
                            <div class="syd-summary-card">
                                <div class="syd-sc-header">
                                    <span class="syd-sc-title">⚡ Outstanding Action Items</span>
                                    <?php
                  $totalActions = count($overdueReferrals)
                                + count($incompleteLabs);
                ?>
                                    <?php if ($totalActions > 0): ?>
                                    <span class="syd-sc-badge syd-sc-badge-red">
                                        <?php echo $totalActions; ?> items
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="syd-sc-body">

                                    <?php if (empty($overdueReferrals) && empty($incompleteLabs)): ?>
                                    <div class="syd-action-ok">
                                        <span>✅</span>
                                        No outstanding action items.
                                    </div>
                                    <?php endif; ?>

                                    <!-- Overdue referrals -->
                                    <?php foreach ($overdueReferrals as $ref):
                  $refAge = (int)((time() - strtotime($ref['refer_date'])) / 86400);
                ?>
                                    <div class="syd-action-item syd-ai-danger">
                                        <div class="syd-ai-left">
                                            <span class="syd-ai-dot syd-dot-danger"></span>
                                            <div>
                                                <div class="syd-ai-title">
                                                    Overdue Referral —
                                                    <?php echo text($ref['refer_to'] ?: 'Specialist'); ?>
                                                </div>
                                                <div class="syd-ai-meta">
                                                    <?php echo text($ref['reason'] ?: '—'); ?>
                                                    · Sent <?php echo $refAge; ?> days ago
                                                </div>
                                            </div>
                                        </div>
                                        <span class="syd-ai-tag syd-tag-bad">
                                            <?php echo $refAge; ?>d overdue
                                        </span>
                                    </div>
                                    <?php endforeach; ?>

                                    <!-- Incomplete labs -->
                                    <?php foreach ($incompleteLabs as $lab):
                  $labAge = $lab['date_ordered']
                    ? (int)((time() - strtotime($lab['date_ordered'])) / 86400)
                    : 0;
                ?>
                                    <div class="syd-action-item syd-ai-warn">
                                        <div class="syd-ai-left">
                                            <span class="syd-ai-dot syd-dot-warn"></span>
                                            <div>
                                                <div class="syd-ai-title">
                                                    Pending Lab —
                                                    <?php echo text($lab['test_name'] ?: 'Lab Order'); ?>
                                                </div>
                                                <div class="syd-ai-meta">
                                                    Ordered <?php echo $labAge; ?> days ago
                                                </div>
                                            </div>
                                        </div>
                                        <span class="syd-ai-tag syd-tag-warn">Pending</span>
                                    </div>
                                    <?php endforeach; ?>

                                    <!-- Risk flags -->
                                    <?php foreach (array_slice($riskFlags, 0, 3) as $flag): ?>
                                    <div class="syd-action-item
                    syd-ai-<?php echo $flag['level'] === 'high'
                        ? 'danger' : ($flag['level'] === 'medium'
                        ? 'warn' : 'info'); ?>">
                                        <div class="syd-ai-left">
                                            <span class="syd-ai-dot
                        syd-dot-<?php echo $flag['level'] === 'high'
                            ? 'danger' : ($flag['level'] === 'medium'
                            ? 'warn' : 'ok'); ?>">
                                            </span>
                                            <div class="syd-ai-title">
                                                <?php echo htmlspecialchars($flag['text']); ?>
                                            </div>
                                        </div>
                                        <span class="syd-ai-tag
                      syd-tag-<?php echo $flag['level'] === 'high'
                          ? 'bad' : ($flag['level'] === 'medium'
                          ? 'warn' : 'ok'); ?>">
                                            <?php echo ucfirst($flag['level']); ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>

                                </div>
                            </div>

                            <!-- Last Visit Snapshot -->
                            <?php if ($lastVisit): ?>
                            <div class="syd-summary-card syd-sc-mt">
                                <div class="syd-sc-header">
                                    <span class="syd-sc-title">🕐 Last Visit Snapshot</span>
                                    <span class="syd-sc-date">
                                        <?php echo $lastVisit['date']
                    ? date('M d, Y', strtotime($lastVisit['date']))
                    : '—'; ?>
                                    </span>
                                </div>
                                <div class="syd-sc-body">
                                    <?php if ($lastVisit['reason']): ?>
                                    <div class="syd-lv2-row">
                                        <span class="syd-lv2-label">Reason</span>
                                        <span class="syd-lv2-val">
                                            <?php echo text($lastVisit['reason']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($lastVisit['provider']): ?>
                                    <div class="syd-lv2-row">
                                        <span class="syd-lv2-label">Provider</span>
                                        <span class="syd-lv2-val">
                                            Dr. <?php echo text($lastVisit['provider']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($lastVisit['assessment']): ?>
                                    <div class="syd-lv2-row syd-lv2-block">
                                        <span class="syd-lv2-label">Assessment</span>
                                        <span class="syd-lv2-val">
                                            <?php echo text(substr($lastVisit['assessment'], 0, 300))
                              . (strlen($lastVisit['assessment']) > 300
                                  ? '…' : ''); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($lastVisit['plan']): ?>
                                    <div class="syd-lv2-row syd-lv2-block">
                                        <span class="syd-lv2-label">Plan</span>
                                        <span class="syd-lv2-val">
                                            <?php echo text(substr($lastVisit['plan'], 0, 300))
                              . (strlen($lastVisit['plan']) > 300
                                  ? '…' : ''); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Preventive Care -->
                            <?php if (!empty($preventiveCare)): ?>
                            <div class="syd-summary-card syd-sc-mt">
                                <div class="syd-sc-header">
                                    <span class="syd-sc-title">💉 Recent Immunizations</span>
                                    <span class="syd-sc-badge syd-sc-badge-teal">
                                        <?php echo count($preventiveCare); ?>
                                    </span>
                                </div>
                                <div class="syd-sc-body">
                                    <?php foreach ($preventiveCare as $imm):
                  $iDate = $imm['administered_date']
                    ? date('M d, Y', strtotime($imm['administered_date']))
                    : '—';
                ?>
                                    <div class="syd-action-item syd-ai-info">
                                        <div class="syd-ai-left">
                                            <span class="syd-ai-dot syd-dot-active"></span>
                                            <div>
                                                <div class="syd-ai-title">
                                                    <?php echo text(
                              $imm['vaccine_name'] ?: 'CVX ' . $imm['cvx_code']
                          ); ?>
                                                </div>
                                                <div class="syd-ai-meta"><?php echo $iDate; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div><!-- /syd-summary-col-left -->

                        <!-- RIGHT COLUMN: Trend Charts -->
                        <div class="syd-summary-col-right">

                            <!-- Vitals Trends -->
                            <div class="syd-summary-card" style="display:none">
                                <div class="syd-sc-header">
                                    <span class="syd-sc-title">📈 Vitals Trends</span>
                                    <span class="syd-sc-date">
                                        Last <?php echo count($vitalsForCharts); ?> readings
                                    </span>
                                </div>
                                <div class="syd-sc-body">
                                    <?php if (count($vitalsForCharts) >= 2): ?>

                                    <div class="syd-chart-block">
                                        <div class="syd-chart-label">
                                            <span>Systolic BP</span>
                                            <span class="syd-chart-current
                        <?php echo $bpStatus['cls']; ?>">
                                                <?php echo $bp; ?> mmHg
                                            </span>
                                        </div>
                                        <div class="syd-chart-svg">
                                            <?php echo $bpChart; ?>
                                        </div>
                                    </div>

                                    <div class="syd-chart-block">
                                        <div class="syd-chart-label">
                                            <span>Heart Rate</span>
                                            <span class="syd-chart-current">
                                                <?php echo $hr; ?> bpm
                                            </span>
                                        </div>
                                        <div class="syd-chart-svg">
                                            <?php echo $hrChart; ?>
                                        </div>
                                    </div>

                                    <div class="syd-chart-block">
                                        <div class="syd-chart-label">
                                            <span>SpO2</span>
                                            <span class="syd-chart-current">
                                                <?php echo $spo2; ?>%
                                            </span>
                                        </div>
                                        <div class="syd-chart-svg">
                                            <?php echo $spo2Chart; ?>
                                        </div>
                                    </div>

                                    <div class="syd-chart-block">
                                        <div class="syd-chart-label">
                                            <span>BMI</span>
                                            <span class="syd-chart-current">
                                                <?php echo $bmi; ?>
                                            </span>
                                        </div>
                                        <div class="syd-chart-svg">
                                            <?php echo $bmiChart; ?>
                                        </div>
                                    </div>

                                    <?php else: ?>
                                    <div class="syd-chart-empty">
                                        <span>📊</span>
                                        Not enough vitals data for trend charts.
                                        At least 2 readings are needed.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lab Trends -->
                            <?php if (!empty($labCharts)): ?>
                            <div class="syd-summary-card syd-sc-mt">
                                <div class="syd-sc-header">
                                    <span class="syd-sc-title">🧪 Lab Trends</span>
                                    <span class="syd-sc-date">
                                        <?php echo count($labCharts); ?> tracked tests
                                    </span>
                                </div>
                                <div class="syd-sc-body">
                                    <?php foreach ($labCharts as $testName => $chartSvg):
                  // Get latest value for display
                  $latestLab = end($labTrendGroups[$testName]);
                  $latestVal = $latestLab['result_text'] ?? '—';
                  $latestUnit = $latestLab['units'] ?? '';
                ?>
                                    <div class="syd-chart-block">
                                        <div class="syd-chart-label">
                                            <span><?php echo htmlspecialchars($testName); ?></span>
                                            <span class="syd-chart-current">
                                                <?php echo htmlspecialchars($latestVal);
                              echo $latestUnit
                                  ? ' ' . htmlspecialchars($latestUnit)
                                  : ''; ?>
                                            </span>
                                        </div>
                                        <div class="syd-chart-svg">
                                            <?php echo $chartSvg; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Problem Summary -->
                            <?php if (!empty($problems)): ?>
                            <div class="syd-summary-card">
                                <div class="syd-sc-header">
                                    <span class="syd-sc-title">🩺 Active Problems</span>
                                    <span class="syd-sc-badge syd-sc-badge-teal">
                                        <?php echo $probCount; ?>
                                    </span>
                                </div>
                                <div class="syd-sc-body syd-problems-list">
                                    <?php foreach ($problems as $prob):
                  $since = $prob['begdate']
                    ? date('Y', strtotime($prob['begdate']))
                    : '—';
                ?>
                                    <div class="syd-prob-summary-row">
                                        <span class="syd-ai-dot syd-dot-active"
                                            style="flex-shrink:0;margin-top:6px;"></span>
                                        <div class="syd-psrow-content">
                                            <span class="syd-psrow-title">
                                                <?php echo text($prob['title']); ?>
                                            </span>
                                            <span class="syd-psrow-meta">
                                                <?php if ($prob['diagnosis']): ?>
                                                <span class="syd-code">
                                                    <?php echo text($prob['diagnosis']); ?>
                                                </span>
                                                <?php endif; ?>
                                                Since <?php echo $since; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div><!-- /syd-summary-col-right -->

                    </div><!-- /syd-summary-grid -->

                </div>
            </div>
            <!-- ══════════════════════════════════════════════════ -->
            <!--  TAB: VISIT HISTORY                                -->
            <!-- ══════════════════════════════════════════════════ -->
            <div id="syd-tab-history" class="syd-tab-panel" style="display:none;">
                <div class="syd-tab-inner">

                    <!-- Header -->

                    <div class="syd-summary-card">
                        <div class="syd-sc-header">
                            <span class="syd-sc-title">📅 Complete Visit History</span>

                            <span class="syd-sc-badge syd-sc-badge-red">
                                <?php echo count($visitHistory); ?> encounters on record
                            </span>

                        </div>
                        <div class="syd-sc-body">

                            <div class="syd-visit-list" id="syd-visit-list">
                                <?php if (empty($visitHistory)): ?>
                                <div class="syd-empty-state">
                                    <div class="syd-es-icon">📋</div>
                                    <div class="syd-es-text">No visit history found for this patient.</div>
                                </div>
                                <?php else: ?>
                                <?php foreach ($visitHistory as $idx => $visit):
              $vDate     = $visit['date']
                         ? date('M d, Y', strtotime($visit['date']))
                         : '—';
              $vReason   = $visit['reason']   ?: 'Office Visit';
              $vProvider = $visit['provider'] ?: 'Unknown Provider';
              $vAssess   = $visit['assessment']
                         ? substr($visit['assessment'], 0, 140)
                           . (strlen($visit['assessment']) > 140 ? '…' : '')
                         : '';
              $vSubj     = $visit['subjective']
                         ? substr($visit['subjective'], 0, 100)
                           . (strlen($visit['subjective']) > 100 ? '…' : '')
                         : '';
              $isFirst   = $idx === 0;
            ?>
                                <div class="syd-visit-row <?php echo $isFirst ? 'syd-visit-current' : ''; ?>"
                                    data-search="<?php echo strtolower(text($vReason . ' ' . $vProvider)); ?>">

                                    <!-- Date column -->
                                    <div class="syd-vr-date">
                                        <div class="syd-vr-day">
                                            <?php echo date('M d', strtotime($visit['date'] ?? 'now')); ?>,
                                            <?php echo date('Y', strtotime($visit['date'] ?? 'now')); ?>
                                        </div>

                                    </div>

                                    <!-- Content -->
                                    <div class="syd-vr-content">
                                        <div class="syd-vr-top">
                                            <span class="syd-vr-reason">
                                                <?php echo text($vReason); ?>
                                            </span>
                                            <?php if ($isFirst): ?>
                                            <span class="syd-vr-badge syd-badge-current">
                                                Current
                                            </span>
                                            <?php endif; ?>
                                            <span class="syd-vr-enc">
                                                Enc #<?php echo text($visit['encounter']); ?>
                                            </span>
                                        </div>
                                        <div class="syd-vr-provider">
                                            Dr. <?php echo text($vProvider); ?>
                                            <?php if ($visit['provider_title']): ?>
                                            , <?php echo text($visit['provider_title']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($vAssess): ?>
                                        <div class="syd-vr-summary">
                                            <span class="syd-vr-label">A:</span>
                                            <?php echo text($vAssess); ?>
                                        </div>
                                        <?php elseif ($vSubj): ?>
                                        <div class="syd-vr-summary">
                                            <span class="syd-vr-label">S:</span>
                                            <?php echo text($vSubj); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <div class="syd-vr-actions">
                                        <button class="syd-vr-btn" onclick="sydViewEncounter(
                          <?php echo (int)$visit['encounter']; ?>
                        )">
                                            View
                                        </button>
                                    </div>

                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>


                        </div>
                    </div>



                    <!-- Visit list -->


                </div>
            </div>

            <!-- ══════════════════════════════════════════════════ -->
            <!--  TAB: ORDERS & REFERRALS                           -->
            <!-- ══════════════════════════════════════════════════ -->

            <div id="syd-tab-orders" class="syd-tab-panel" style="display:none; margin: 10px;">

                <div class="syd-tab-inner">

                    <!-- Header -->
                    <div class="syd-tab-header">
                        <div class="syd-tab-header-left">

                        </div>

                        <div>
                            <button class="syd-btn-order">
                                + New Order
                            </button>
                        </div>
                    </div>

                    <!-- ─────────────────────────────
         TABS
    ───────────────────────────── -->
                    <div class="syd-orders-tabs">

                        <button class="syd-orders-tab active" onclick="sydShowOrderTab('pending', this)">
                            🚨 Today's Orders — Stat / Pending
                        </button>

                        <button class="syd-orders-tab" onclick="sydShowOrderTab('referrals', this)">
                            📤 Referrals
                        </button>

                        <button class="syd-orders-tab" onclick="sydShowOrderTab('completed', this)">
                            ✅ Completed — Recent 90 Days
                        </button>

                    </div>

                    <!-- ─────────────────────────────
         PANEL WRAPPER
    ───────────────────────────── -->
                    <div class="syd-orders-panel-wrap">

                        <!-- =========================================
           PENDING ORDERS
      ========================================== -->
                        <div class="syd-orders-panel" id="syd-orders-panel-pending">

                            <?php
        $pendingOrders = array_filter($allOrders, function($o){
          $st = strtolower($o['order_status'] ?? '');
          return $st !== 'complete'
              && $st !== 'completed';
        });
        ?>

                            <?php if (!empty($pendingOrders)): ?>

                            <?php foreach ($pendingOrders as $order):


            $oName = $order['test_name']
              ?: ('Order #' . $order['procedure_order_id']);

            $oType = strtolower($order['procedure_order_type'] ?? 'lab');

            $isImg = strpos($oType, 'image') !== false
                  || strpos($oType, 'radiology') !== false;

            $oStatus = ucfirst($order['order_status'] ?? 'Pending');

          ?>

                            <div class="syd-orow">

                                <div class="syd-orow-left">

                                    <span class="syd-otype <?php echo $isImg
                ? 'syd-ot-img'
                : 'syd-ot-lab'; ?>">

                                        <?php echo $oType; ?>

                                    </span>

                                    <div>
                                        <div class="syd-oname">
                                            <?php echo text($oName); ?>
                                        </div>
                                    </div>

                                </div>

                                <div class="syd-ostat syd-ostat-pending">
                                    ⏳ <?php echo text($oStatus); ?>
                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php else: ?>

                            <div class="syd-empty-mini">
                                No pending orders.
                            </div>

                            <?php endif; ?>

                        </div>

                        <!-- =========================================
           REFERRALS
      ========================================== -->
                        <div class="syd-orders-panel" id="syd-orders-panel-referrals" style="display:none;">

                            <?php if (!empty($referrals)): ?>

                            <?php foreach ($referrals as $ref):

            $rDate = $ref['refer_date']
              ? date('M d, Y', strtotime($ref['refer_date']))
              : '—';

            $isComplete = !empty($ref['reply_date']);

            $isOld = false;

            if (!empty($ref['refer_date'])) {
              $daysOld = (time() - strtotime($ref['refer_date'])) / 86400;
              $isOld = $daysOld > 60 && !$isComplete;
            }

          ?>

                            <div class="syd-orow">

                                <div class="syd-orow-left">

                                    <span class="syd-otype <?php echo $isOld
                ? 'syd-ot-overdue'
                : 'syd-ot-ref'; ?>">

                                        <?php echo $isOld ? 'Overdue' : 'Referral'; ?>

                                    </span>

                                    <div>
                                        <div class="syd-oname">
                                            <?php echo text(
                    $ref['refer_to'] ?: 'Specialist'
                  ); ?>
                                        </div>

                                        <div class="syd-oref-meta">
                                            <?php echo text($ref['reason'] ?: '—'); ?>
                                            · <?php echo $rDate; ?>
                                        </div>
                                    </div>

                                </div>

                                <div class="syd-ostat <?php echo $isComplete
              ? 'syd-ostat-done'
              : ($isOld
                  ? 'syd-ostat-danger'
                  : 'syd-ostat-pending'); ?>">

                                    <?php if ($isComplete): ?>
                                    ✅ Response received
                                    <?php elseif ($isOld): ?>
                                    ⚠ Overdue
                                    <?php else: ?>
                                    ⏳ Awaiting response
                                    <?php endif; ?>

                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php else: ?>

                            <div class="syd-empty-mini">
                                No referrals found.
                            </div>

                            <?php endif; ?>

                        </div>

                        <!-- =========================================
           COMPLETED
      ========================================== -->
                        <div class="syd-orders-panel" id="syd-orders-panel-completed" style="display:none;">

                            <?php
        $completedOrders = array_filter($allOrders, function($o){
          $st = strtolower($o['order_status'] ?? '');
          return $st === 'complete'
              || $st === 'completed';
        });
        ?>

                            <?php if (!empty($completedOrders)): ?>

                            <?php foreach ($completedOrders as $order):

            $oName = $order['test_name']
              ?: ('Order #' . $order['procedure_order_id']);

          ?>

                            <div class="syd-orow">

                                <div class="syd-orow-left">

                                    <span class="syd-otype syd-ot-done">
                                        Done
                                    </span>

                                    <div class="syd-oname">
                                        <?php echo text($oName); ?>
                                    </div>

                                </div>

                                <div class="syd-ostat">
                                    <?php if (!empty($order['result_text'])): ?>
                                    <?php echo text($order['result_text']); ?>
                                    <?php else: ?>
                                    Completed
                                    <?php endif; ?><br>
                                    <samll style="font-weight:normal; font-size:9px;">
                                        <?php echo $order['date_ordered']; ?> </small>
                                </div>

                            </div>

                            <?php endforeach; ?>

                            <?php else: ?>

                            <div class="syd-empty-mini">
                                No completed orders.
                            </div>

                            <?php endif; ?>

                        </div>

                    </div>
                </div>
            </div>
            <div id="syd-tab-orders" class="syd-tab-panel" style="display:none;">
                <div class="syd-tab-inner">

                    <!-- Header -->
                    <div class="syd-tab-header">
                        <div class="syd-tab-header-left">
                            <span class="syd-tab-title">🔬 Orders &amp; Referrals</span>
                            <span class="syd-tab-subtitle">
                                <?php echo count($allOrders); ?> orders ·
                                <?php echo count($referrals); ?> referrals
                            </span>
                        </div>
                        <div class="syd-tab-header-right">
                            <div class="syd-filter-pills" id="syd-order-filters">
                                <button class="syd-filter-pill active"
                                    onclick="sydFilterOrders('all', this)">All</button>
                                <button class="syd-filter-pill"
                                    onclick="sydFilterOrders('pending', this)">Pending</button>
                                <button class="syd-filter-pill"
                                    onclick="sydFilterOrders('complete', this)">Complete</button>
                                <button class="syd-filter-pill"
                                    onclick="sydFilterOrders('referral', this)">Referrals</button>
                            </div>
                        </div>
                    </div>

                    <div class="syd-orders-body">

                        <!-- Orders section -->
                        <?php if (!empty($allOrders)): ?>
                        <div class="syd-orders-group" id="syd-orders-group-orders">
                            <div class="syd-og-label">Lab &amp; Imaging Orders</div>
                            <div class="syd-orders-table-wrap">
                                <table class="syd-orders-table">
                                    <thead>
                                        <tr>
                                            <th>Test / Study</th>
                                            <th>Type</th>
                                            <th>Ordered</th>
                                            <th>Status</th>
                                            <th>Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allOrders as $order):
                    $oDate   = $order['date_ordered']
                             ? date('M d, Y', strtotime($order['date_ordered']))
                             : '—';
                    $oStatus = strtolower($order['order_status'] ?? 'pending');
                    $oName   = $order['test_name'] ?: ('Order #' . $order['procedure_order_id']);
                    $oAbn    = !empty($order['abnormal']);
                  ?>
                                        <tr class="syd-order-row" data-status="<?php echo $oStatus; ?>">
                                            <td>
                                                <span class="syd-order-name">
                                                    <?php echo text($oName); ?>
                                                </span>
                                                <?php if ($order['proc_code']): ?>
                                                <span class="syd-order-code">
                                                    <?php echo text($order['proc_code']); ?>
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="syd-order-type-badge">
                                                    <?php echo text(ucfirst($order['order_type'] ?? 'Lab')); ?>
                                                </span>
                                            </td>
                                            <td class="syd-td-muted"><?php echo $oDate; ?></td>
                                            <td>
                                                <span class="syd-status-badge
                        syd-status-<?php echo $oStatus; ?>">
                                                    <?php echo ucfirst($oStatus); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($order['result_text']): ?>
                                                <span class="syd-result-val
                          <?php echo $oAbn ? 'syd-result-abn' : ''; ?>">
                                                    <?php echo text($order['result_text']); ?>
                                                    <?php if ($oAbn): ?>
                                                    <span class="syd-abn-flag">!</span>
                                                    <?php endif; ?>
                                                </span>
                                                <?php else: ?>
                                                <span class="syd-td-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Referrals section -->
                        <?php if (!empty($referrals)): ?>
                        <div class="syd-orders-group syd-referrals-group" id="syd-orders-group-referral">
                            <div class="syd-og-label">Referrals</div>
                            <?php foreach ($referrals as $ref):
              $rDate    = $ref['refer_date']
                        ? date('M d, Y', strtotime($ref['refer_date']))
                        : '—';
              $rReply   = !empty($ref['reply_date']);
              $rStatus  = $rReply ? 'complete' : 'pending';
            ?>
                            <div class="syd-referral-row syd-order-row" data-status="referral">
                                <div class="syd-ref-left">
                                    <div class="syd-ref-to">
                                        <?php echo text($ref['refer_to'] ?: 'Specialist'); ?>
                                    </div>
                                    <div class="syd-ref-reason">
                                        <?php echo text($ref['reason'] ?: '—'); ?>
                                    </div>
                                    <div class="syd-ref-meta">
                                        Referred <?php echo $rDate; ?>
                                        <?php if ($ref['referred_by']): ?>
                                        · by Dr. <?php echo text($ref['referred_by']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="syd-ref-right">
                                    <span class="syd-status-badge syd-status-<?php echo $rStatus; ?>">
                                        <?php echo $rReply ? 'Response received' : 'Awaiting response'; ?>
                                    </span>
                                    <?php if ($rReply && $ref['reply_body']): ?>
                                    <div class="syd-ref-reply">
                                        <?php echo text(substr($ref['reply_body'], 0, 100)); ?>…
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Empty state -->
                        <?php if (empty($allOrders) && empty($referrals)): ?>
                        <div class="syd-empty-state">
                            <div class="syd-es-icon">🔬</div>
                            <div class="syd-es-text">No orders or referrals on file.</div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════ -->
            <!--  TAB: BILLING & CODING                             -->
            <!-- ══════════════════════════════════════════════════ -->
            <div id="syd-tab-billing" class="syd-tab-panel" style="display:none;">
                <div class="syd-tab-inner">

                    <div class="tscroll">
                        <div class="two-col fade" style="margin-bottom:0;">
                            <div class="bm">
                                <div class="bm-lbl">Total Billed</div>
                                <div class="bm-val">$<?php echo number_format($totalBilled, 2); ?></div>

                            </div>

                            <div class="bm">
                                <div class="bm-lbl">Unbilled</div>
                                <div class="bm-val">$<?php echo number_format($totalUnbilled, 2); ?></div>

                            </div>

                            <div class="bm">
                                <div class="bm-lbl"> CPT Codes</div>
                                <div class="bm-val g"><?php echo count($cptClaims); ?></div>
                            </div>

                            <div class="bm">
                                <div class="bm-lbl"> Insurance</div>
                                <div class="bm-val a" style="font-size:16px;"><?php echo text($insurance ?: '—'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="card fade">
                            <div class="card-hd">
                                <div class="card-ttl">💰 Synapta AI — Suggested Codes</div>
                                <div class="sacts"><button class="sbtn">Edit</button><button class="sbtn pri">Submit
                                        Claim</button></div>
                            </div>
                            <div class="card-body">
                                <div
                                    style="padding:9px 11px;background:var(--purple-l);border-radius:8px;font-size:12px;color:var(--purple);margin-bottom:12px;border:1px solid rgba(83,74,183,.2);">
                                    ✦ Codes auto-suggested from today's signed SOAP note. Review before submitting. All
                                    ICD-10 codes are grounded in documented diagnoses.</div>
                                <!-- <div class="code-row"><span class="cpill">99214</span><div class="cdesc">Office or outpatient visit — moderate medical decision complexity</div><div class="csrc">CPT · AI from note</div><div class="camt">$147</div></div> -->
                            </div>
                        </div>
                        <div class="card fade">
                            <div class="card-hd">
                                <div class="card-ttl">📊 Claims Status — Recent Encounters</div>
                            </div>
                            <div class="card-body">

                                <?php foreach ($claims as $claim):
                $cType    = strtolower($claim['code_type'] ?? '');
                $isCPT    = in_array($cType, ['cpt','hcpcs']);
                $isICD    = in_array($cType, ['icd10','icd9']);
                $isBilled = !empty($claim['billed']);
                $isAuth   = !empty($claim['authorized']);
                $cDate    = $claim['enc_date']
                          ? date('M d, Y', strtotime($claim['enc_date']))
                          : '—';
              ?>
                                <div class="orow">
                                    <div class="oname"><?php echo $cDate; ?> — <?php echo $claim['reason']; ?></div>
                                    <span class="otype">
                                        <?php if ($isBilled): ?>
                                        <span class="syd-status-badge syd-status-complete">
                                            Billed
                                        </span>
                                        <?php else: ?>
                                        <span class="syd-status-badge syd-status-pending">
                                            Unbilled
                                        </span>
                                        <?php endif; ?>
                                    </span>
                                    <div class="ostat">
                                        <?php echo $claim['fee']
                    ? '$' . number_format((float)$claim['fee'], 2)
                    : '—'; ?> ·
                                        <?php echo text($claim['code']); ?> ·
                                        <?php echo text(strtoupper($claim['code_type'])); ?>
                                    </div>
                                </div>

                                <?php endforeach; ?>

                            </div>
                        </div>
                    </div>


                </div>
            </div>

        </div><!-- /syd-body -->

        <!-- ══════════════════════════════════════════════════════ -->
        <!--  AI PANEL SIDEBAR                                      -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div class="syd-ai-panel" id="syd-ai-panel">

            <!-- Panel Header -->
            <div class="syd-aip-header">


                <div class="syd-aip-header-left">

                    <div>

                        <!-- Top Row -->
                        <div style="
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
    ">

                            <!-- Title -->
                            <div class="syd-aip-title">
                                Synapta AI
                            </div>

                            <!-- AI Disclaimer Badge -->
                            <span style="
          background:rgba(255,255,255,0.08);
          border:1px solid rgba(255,255,255,0.14);
          color:#FFD6D6;
          padding:3px 9px;
          border-radius:999px;
          font-size:8px;
          font-weight:500;
          letter-spacing:.4px;
          white-space:nowrap;
          line-height:1.2;
          cursor:pointer;
      ">
                                Clinical Decision Support - Not a Directive
                            </span>

                        </div>

                    </div>

                </div>


                <!-- <div class="syd-aip-header-right">
        <span class="syd-aip-status" id="syd-aip-status">
          <span class="syd-live-dot"></span> Ready
        </span>
        <button class="syd-aip-collapse" onclick="sydToggleAIPanel()"
                title="Collapse panel">◀</button>
      </div> -->
            </div>

            <!-- Scrollable content -->
            <div class="syd-aip-body" id="syd-aip-body">

                <!-- ── SECTION 1: Pre-visit Intelligence ── -->
                <div class="syd-aip-section ai-pv fade">
                    <div class="syd-aip-section-title ai-lbl">
                        <span>⚡ Pre-Visit Intelligence</span>
                        <button class="syd-aip-refresh" onclick="sydRefreshIntelligence()"
                            title="Refresh AI analysis">↻</button>
                    </div>
                    <div id="syd-intelligence-content ai-item">
                        <?php
          // Build pre-visit bullets from real data (no GPT call yet — rule-based)
          $bullets = [];

          // Check BP
          if ($vitalsData && $vitalsData['bps'] >= 140) {
              $bullets[] = [
                  'type'  => 'warn',
                  'icon'  => '⚠️',
                  'text'  => 'BP ' . $bp . ' — Elevated. Consider medication review.',
              ];
          }

          // Check for abnormal labs
          if ($abnormalCount > 0) {
              $bullets[] = [
                  'type'  => 'warn',
                  'icon'  => '🧪',
                  'text'  => $abnormalCount . ' abnormal lab result'
                            . ($abnormalCount > 1 ? 's' : '')
                            . ' require attention.',
              ];
          }

          // Check for overdue follow-up
          if ($lastVisit && $lastVisit['date']) {
              $daysSince = (int)((time() - strtotime($lastVisit['date'])) / 86400);
              if ($daysSince > 180) {
                  $bullets[] = [
                      'type'  => 'info',
                      'icon'  => '📅',
                      'text'  => 'Last visit was ' . $daysSince
                                . ' days ago (' . date('M d, Y', strtotime($lastVisit['date']))
                                . ').',
                  ];
              }
          }

          // Medication count
          if ($medCount > 5) {
              $bullets[] = [
                  'type'  => 'info',
                  'icon'  => '💊',
                  'text'  => $medCount . ' active medications — polypharmacy review recommended.',
              ];
          }

          // Allergy reminder
          if ($allergyCount > 0) {
              $bullets[] = [
                  'type'  => 'alert',
                  'icon'  => '⚠️',
                  'text'  => $allergyCount . ' known allerg'
                            . ($allergyCount > 1 ? 'ies' : 'y')
                            . ' on file — verify before prescribing.',
              ];
          }

          // Pending orders
          if (!empty($pendingOrders)) {
              $bullets[] = [
                  'type'  => 'info',
                  'icon'  => '🔬',
                  'text'  => count($pendingOrders) . ' pending order'
                            . (count($pendingOrders) > 1 ? 's' : '')
                            . ' — review results before new orders.',
              ];
          }

          // Default if nothing
          if (empty($bullets)) {
              $bullets[] = [
                  'type'  => 'ok',
                  'icon'  => '✅',
                  'text'  => 'No critical alerts for this visit.',
              ];
          }

          foreach ($bullets as $bullet): ?>
                        <div class="syd-intel-item syd-intel-<?php echo $bullet['type']; ?> ">
                            <span class="syd-intel-dot"></span>
                            <span class="syd-intel-text">
                                <?php echo $bullet['icon']; ?>
                                <?php echo htmlspecialchars($bullet['text']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── SECTION 2: Drug Interaction Alerts ── -->

                <!-- =========================
     DRUG ALERTS CARD
========================= -->

                <div class="syd-aip-section cds cr fade">



                    <div class="syd-aip-section-title ">
                        <span>💊 Drug Alerts</span>

                        <span class="syd-aip-count">
                            <?php echo count($drugNames); ?> meds
                        </span>
                    </div>

                    <div id="syd-drug-alerts">

                        <?php if (empty($drugNames)): ?>

                        <div class="syd-aip-empty">
                            No active medications on file.
                        </div>

                        <?php else: ?>

                        <?php
            // Common interaction pairs
            $interactions = [
                ['Warfarin',   'Aspirin',     'Bleeding risk — monitor INR closely'],
                ['Warfarin',   'Ibuprofen',   'Increased anticoagulant effect'],
                ['Metformin',  'Alcohol',     'Lactic acidosis risk'],
                ['Lisinopril', 'Potassium',   'Hyperkalemia risk — monitor levels'],
                ['Simvastatin','Amiodarone',  'Myopathy risk — dose adjustment needed'],
                ['SSRIs',      'Tramadol',    'Serotonin syndrome risk'],
            ];

            $foundInteractions = [];

            foreach ($interactions as $pair) {

                $found0 = false;
                $found1 = false;

                foreach ($drugNames as $dn) {

                    if (stripos($dn, $pair[0]) !== false) {
                        $found0 = true;
                    }

                    if (stripos($dn, $pair[1]) !== false) {
                        $found1 = true;
                    }
                }

                if ($found0 && $found1) {
                    $foundInteractions[] = $pair;
                }
            }
            ?>

                        <?php if (!empty($foundInteractions)): ?>

                        <?php foreach ($foundInteractions as $ix): ?>

                        <div class="syd-drug-alert" id="syd-ix-<?php echo md5($ix[0] . $ix[1]); ?>">

                            <div class="syd-da-header">

                                <span class="syd-da-icon">⚠️</span>

                                <span class="syd-da-drugs">
                                    <?php echo text($ix[0]); ?>
                                    +
                                    <?php echo text($ix[1]); ?>
                                </span>

                                <button class="syd-da-dismiss" onclick="sydDismissAlert(this)" title="Dismiss">
                                    ✕
                                </button>

                            </div>

                            <div class="syd-da-text">
                                <?php echo htmlspecialchars($ix[2]); ?>
                            </div>

                        </div>

                        <?php endforeach; ?>

                        <?php else: ?>

                        <div class="syd-aip-ok">
                            <span>✅</span>
                            No interactions detected between current medications.
                        </div>

                        <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </div>



                <!-- =========================
     ALLERGY SAFETY CARD
========================= -->

                <?php if ($allergyCount > 0): ?>

                <div class="syd-aip-section allergy-card fade cds cr fade">

                    <div class="syd-aip-section-title">

                        <span>🚫 Allergy Check</span>

                        <span class="syd-aip-count">
                            <?php echo (int)$allergyCount; ?>
                            allerg<?php echo $allergyCount > 1 ? 'ies' : 'y'; ?>
                        </span>

                    </div>

                    <div class="syd-allergy-wrap">

                        <div class="syd-allergy-alert">



                            <div class="syd-da-text">

                                Patient has
                                <strong><?php echo (int)$allergyCount; ?></strong>

                                documented allerg<?php echo $allergyCount > 1 ? 'ies' : 'y'; ?>.

                                Verify all medications and prescriptions against allergy history before prescribing.

                            </div>

                        </div>

                    </div>

                </div>

                <?php endif; ?>



                <!-- ── SECTION 3: AI Draft SOAP Note ── -->
                <div class="syd-aip-section ai-note fade">
                    <div class="syd-aip-section-title">
                        <span>📝 AI Draft Note</span>
                        <span class="syd-aip-badge" id="syd-draft-badge">Waiting for recording</span>
                    </div>
                    <div id="syd-draft-note-content">
                        <div class="syd-draft-placeholder" id="syd-draft-placeholder">
                            <div class="syd-dp2-icon">🎙️</div>
                            <div class="syd-dp2-text">
                                Start recording with the <strong>Scribe</strong> button to generate an AI draft note.
                            </div>
                            <button class="syd-dp2-btn" onclick="sydToggleScribe()">
                                Start Recording
                            </button>
                        </div>

                        <!-- Draft sections — hidden until AI generates content -->
                        <div id="syd-draft-sections" style="display:none;">
                            <div class="syd-draft-section" id="syd-ds-s">
                                <div class="syd-ds-label">S — Subjective</div>
                                <div class="syd-ds-text" id="syd-ds-s-text"></div>
                                <button class="syd-ds-accept" onclick="sydAcceptDraftSection('s')">Accept ↓</button>
                            </div>
                            <div class="syd-draft-section" id="syd-ds-o">
                                <div class="syd-ds-label">O — Objective</div>
                                <div class="syd-ds-text" id="syd-ds-o-text"></div>
                                <button class="syd-ds-accept" onclick="sydAcceptDraftSection('o')">Accept ↓</button>
                            </div>
                            <div class="syd-draft-section" id="syd-ds-a">
                                <div class="syd-ds-label">A — Assessment</div>
                                <div class="syd-ds-text" id="syd-ds-a-text"></div>
                                <button class="syd-ds-accept" onclick="sydAcceptDraftSection('a')">Accept ↓</button>
                            </div>
                            <div class="syd-draft-section" id="syd-ds-p">
                                <div class="syd-ds-label">P — Plan</div>
                                <div class="syd-ds-text" id="syd-ds-p-text"></div>
                                <button class="syd-ds-accept" onclick="sydAcceptDraftSection('p')">Accept ↓</button>
                            </div>
                            <div class="syd-draft-actions">
                                <button class="syd-draft-accept-all" onclick="sydAcceptAllDraft()">
                                    ✅ Accept All Sections
                                </button>
                                <button class="syd-draft-discard" onclick="sydDiscardDraft()">
                                    🗑 Discard
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 4: Billing Code Suggestions ── -->
                <div class="syd-aip-section ai-codes fade">
                    <div class="syd-aip-section-title">
                        <span>💲 Billing Codes</span>
                        <span class="syd-aip-count"><?php echo count($billingCodes); ?> on file</span>
                    </div>
                    <div id="syd-billing-content">

                        <?php if (!empty($billingCodes)):
            // Separate CPT from ICD
            $cptCodes = array_filter($billingCodes,
                fn($b) => in_array($b['code_type'], ['CPT','HCPCS']));
            $icdCodes = array_filter($billingCodes,
                fn($b) => in_array($b['code_type'], ['ICD10','ICD9']));
          ?>

                        <?php if (!empty($cptCodes)): ?>
                        <div class="syd-bill-group-label">CPT Procedure Codes</div>
                        <?php foreach ($cptCodes as $code): ?>
                        <div class="syd-bill-chip syd-bill-cpt">
                            <span class="syd-bill-code">
                                <?php echo text($code['code']); ?>
                            </span>
                            <span class="syd-bill-type">CPT</span>
                            <?php if ($code['fee']): ?>
                            <span class="syd-bill-fee">
                                $<?php echo number_format((float)$code['fee'], 2); ?>
                            </span>
                            <?php endif; ?>
                            <span class="syd-bill-status
                    <?php echo $code['billed'] ? 'syd-bill-billed' : 'syd-bill-pending'; ?>">
                                <?php echo $code['billed'] ? 'Billed' : 'Pending'; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($icdCodes)): ?>
                        <div class="syd-bill-group-label" style="margin-top:10px;">
                            ICD-10 Diagnosis Codes
                        </div>
                        <?php foreach ($icdCodes as $code): ?>
                        <div class="syd-bill-chip syd-bill-icd">
                            <span class="syd-bill-code">
                                <?php echo text($code['code']); ?>
                            </span>
                            <span class="syd-bill-type">ICD-10</span>
                            <?php if ($code['authorized']): ?>
                            <span class="syd-bill-status syd-bill-auth">Authorized</span>
                            <?php else: ?>
                            <span class="syd-bill-status syd-bill-pending">Pending</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="syd-aip-empty">
                            No billing codes on file for this patient.
                        </div>
                        <div class="syd-bill-suggest-hint">
                            Complete the SOAP note and use the
                            <strong>AI Suggest Codes</strong> button to generate
                            CPT and ICD-10 suggestions.
                        </div>
                        <?php endif; ?>

                        <!-- AI Suggest button -->
                        <button class="syd-bill-suggest-btn" onclick="sydSuggestCodes()" id="syd-suggest-codes-btn">
                            🤖 AI Suggest Codes
                        </button>
                        <div id="syd-suggested-codes" style="margin-top:10px;"></div>

                    </div>
                </div>

                <!-- ── SECTION 5: Last Visit Snapshot ── -->
                <?php if ($lastVisit): ?>
                <div class="syd-aip-section ai-codes fade">
                    <div class="syd-aip-section-title">
                        <span>🕐 Last Visit</span>
                        <span class="syd-aip-count">
                            <?php echo $lastVisit['date']
                ? date('M d, Y', strtotime($lastVisit['date']))
                : '—'; ?>
                        </span>
                    </div>
                    <div class="syd-last-visit">
                        <?php if ($lastVisit['reason']): ?>
                        <div class="syd-lv-row">
                            <span class="syd-lv-label">Reason</span>
                            <span class="syd-lv-val">
                                <?php echo text($lastVisit['reason']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($lastVisit['provider']): ?>
                        <div class="syd-lv-row">
                            <span class="syd-lv-label">Provider</span>
                            <span class="syd-lv-val">
                                Dr. <?php echo text($lastVisit['provider']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($lastVisit['assessment']): ?>
                        <div class="syd-lv-row">
                            <span class="syd-lv-label">Assessment</span>
                            <span class="syd-lv-val syd-lv-truncate">
                                <?php echo text(substr($lastVisit['assessment'], 0, 120))
                    . (strlen($lastVisit['assessment']) > 120 ? '…' : ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($lastVisit['plan']): ?>
                        <div class="syd-lv-row">
                            <span class="syd-lv-label">Plan</span>
                            <span class="syd-lv-val syd-lv-truncate">
                                <?php echo text(substr($lastVisit['plan'], 0, 120))
                    . (strlen($lastVisit['plan']) > 120 ? '…' : ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /syd-aip-body -->

            <!-- Collapsed tab (shown when panel is hidden) -->
            <div class="syd-aip-collapsed-tab" id="syd-aip-tab" onclick="sydToggleAIPanel()" style="display:none;">
                <span>🤖</span>
                <span class="syd-aip-tab-label">AI</span>
            </div>

        </div><!-- /syd-ai-panel -->

    </div><!-- /synapta-dashboard -->



    <script
        src="<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-physician-dashboard/public/js/dashboard.js">
    </script>
    <script>
    // Pass PHP data to JavaScript
    const SYD = {
        pid: <?php echo (int)$pid; ?>,
        encounter: <?php echo (int)$encounter; ?>,
        csrf: "<?php echo $csrfToken; ?>",
        webroot: "<?php echo $GLOBALS['webroot']; ?>"
    };
    </script>

    <!-- ══════════════════════════════════════════════════════ -->
    <!--  CHART MODAL — 14-TAB FULL PATIENT CHART DRAWER        -->
    <!-- ══════════════════════════════════════════════════════ -->


    <div class="syd-modal-overlay" id="syd-modal-overlay" onclick="sydCloseChart()"></div>

    <div class="syd-chart-modal" id="syd-chart-modal">

        <!-- Modal Header -->
        <div class="syd-cm-header">
            <div class="syd-cm-header-left">
                <div class="">
                    <svg width="36" height="36" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg"
                        style="flex-shrink:0;display:block;" aria-label="Synapta">
                        <line x1="40" y1="29" x2="40" y2="16" stroke="#5DCAA5" stroke-width="1.8"
                            stroke-linecap="round" />
                        <line x1="51" y1="35" x2="63" y2="28" stroke="#5DCAA5" stroke-width="1.8"
                            stroke-linecap="round" />
                        <line x1="51" y1="45" x2="63" y2="52" stroke="#5DCAA5" stroke-width="1.8"
                            stroke-linecap="round" />
                        <line x1="40" y1="51" x2="40" y2="64" stroke="#5DCAA5" stroke-width="1.8"
                            stroke-linecap="round" />
                        <line x1="29" y1="45" x2="17" y2="52" stroke="#5DCAA5" stroke-width="1.8"
                            stroke-linecap="round" />
                        <line x1="29" y1="35" x2="17" y2="28" stroke="#5DCAA5" stroke-width="1.8"
                            stroke-linecap="round" />
                        <circle cx="40" cy="40" r="10" fill="#1D9E75" />
                        <circle cx="40" cy="40" r="6" fill="#04342C" />
                        <rect x="37.5" y="33.5" width="5" height="13" rx="1" fill="#9FE1CB" opacity="0.85" />
                        <rect x="34" y="37" width="12" height="5" rx="1" fill="#9FE1CB" opacity="0.85" />
                        <circle cx="40" cy="12" r="5" fill="#5DCAA5" />
                        <circle cx="40" cy="12" r="2.5" fill="#04342C" />
                        <circle cx="67" cy="25" r="5" fill="#AFA9EC" />
                        <circle cx="67" cy="25" r="2.5" fill="#26215C" />
                        <circle cx="67" cy="55" r="5" fill="#5DCAA5" />
                        <circle cx="67" cy="55" r="2.5" fill="#04342C" />
                        <circle cx="40" cy="68" r="5" fill="#AFA9EC" />
                        <circle cx="40" cy="68" r="2.5" fill="#26215C" />
                        <circle cx="13" cy="55" r="5" fill="#5DCAA5" />
                        <circle cx="13" cy="55" r="2.5" fill="#04342C" />
                        <circle cx="13" cy="25" r="5" fill="#AFA9EC" />
                        <circle cx="13" cy="25" r="2.5" fill="#26215C" />
                    </svg>
                </div>
                <div>
                    <div class="syd-cm-patient-name">
                        <?php echo htmlspecialchars($patientName); ?>
                    </div>
                    <div class="syd-cm-patient-meta">
                        DOB: <?php echo $dob; ?>
                        &nbsp;·&nbsp; MRN: <?php echo $mrn; ?>
                        &nbsp;·&nbsp; <?php echo $age . $sex; ?>
                    </div>
                </div>
            </div>
            <div class="syd-cm-header-right">

                <button class="syd-cm-close" onclick="sydCloseChart()">✕</button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="syd-cm-body">

            <!-- Tab Rail (left) -->
            <div class="syd-cm-tabs-bar">
                <?php
      $chartTabs = [
        ['key'=>'demographics',   'icon'=>'👤', 'label'=>'Demographics'],
        ['key'=>'chief_complaint','icon'=>'📋', 'label'=>'Chief Complaint'],
        ['key'=>'emergency',      'icon'=>'🚨', 'label'=>'Emergency Contacts'],
        ['key'=>'pharmacy',       'icon'=>'🏥', 'label'=>'Pharmacy'],
        ['key'=>'providers',      'icon'=>'👨‍⚕️','label'=>'Other Providers'],
        ['key'=>'medical_history','icon'=>'🩺', 'label'=>'Medical History'],
        ['key'=>'medications',    'icon'=>'💊', 'label'=>'Medications'],
        ['key'=>'surgical',       'icon'=>'🔪', 'label'=>'Surgical History'],
        ['key'=>'allergies',      'icon'=>'⚠️', 'label'=>'Allergies'],
        ['key'=>'social',         'icon'=>'🏠', 'label'=>'Social History'],
        ['key'=>'family',         'icon'=>'👨‍👩‍👧','label'=>'Family History'],
        // ['key'=>'screening',      'icon'=>'📊', 'label'=>'Screening Forms'],
        // ['key'=>'custom_forms',   'icon'=>'📝', 'label'=>'Custom Forms'],
        // ['key'=>'consent',        'icon'=>'✍️', 'label'=>'Consent Forms'],
      ];
      foreach ($chartTabs as $idx => $tab): ?>
                <button class="syd-cm-tab <?php echo $idx===0 ? 'active' : ''; ?>"
                    onclick="sydChartTab('<?php echo $tab['key']; ?>', this)" id="syd-ctab-<?php echo $tab['key']; ?>">
                    <span class="syd-cm-tab-icon"><?php echo $tab['icon']; ?></span>
                    <span class="syd-cm-tab-label"><?php echo $tab['label']; ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Content Area (right) -->
            <div class="syd-cm-content chart-content" id="syd-cm-content">
                <div class="syd-cm-loading" id="syd-cm-loading">
                    <div class="syd-cm-spinner"></div>
                    <span>Loading chart data…</span>
                </div>
                <div id="syd-cm-panel" style="display:none;"></div>
            </div>

        </div><!-- /syd-cm-body -->

    </div><!-- /syd-chart-modal -->
</body>

</html>