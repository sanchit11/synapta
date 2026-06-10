<?php
/**
 * Synapta Dashboard — Chart Modal AJAX handler
 * Handles all 14 chart tabs
 */
require_once(dirname(__FILE__, 5) . "/globals.php");
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('encounters', 'auth')) {
    echo json_encode(['error' => 'Access denied']); exit;
}
if (!CsrfUtils::verifyCsrfToken($_POST['csrf'] ?? '')) {
    echo json_encode(['error' => 'Invalid token']); exit;
}

$pid    = (int)($_POST['pid']    ?? 0);
$tab    = $_POST['tab']          ?? '';
if (!$pid) { echo json_encode(['error' => 'No patient']); exit; }

// ─────────────────────────────────────────────────────────────────────────────
// Helper: render a simple key-value info grid as HTML
// ─────────────────────────────────────────────────────────────────────────────
function infoGrid(array $rows): string {
    if (empty($rows)) return '<p class="syd-cm-empty">No data on file.</p>';
    $html = '<div class="syd-info-grid">';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') $value = '—';
        $html .= '<div class="syd-ig-item">'
               . '<div class="syd-ig-label">' . htmlspecialchars($label) . '</div>'
               . '<div class="syd-ig-value">' . htmlspecialchars((string)$value) . '</div>'
               . '</div>';
    }
    $html .= '</div>';
    return $html;
}

// Helper: render a table
function dataTable(array $headers, array $rows, string $emptyMsg = 'No data on file.'): string {
    if (empty($rows)) return '<p class="syd-cm-empty">' . htmlspecialchars($emptyMsg) . '</p>';
    $html  = '<div class="syd-cm-table-wrap"><table class="syd-cm-table"><thead><tr>';
    foreach ($headers as $h) {
        $html .= '<th>' . htmlspecialchars($h) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . (is_null($cell) ? '—' : htmlspecialchars((string)$cell)) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}

// Helper: section heading inside a panel
function sectionHead(string $title, string $icon = ''): string {
    return '<div class="syd-cm-section-head">'
         . ($icon ? '<span>' . $icon . '</span>' : '')
         . '<span>' . htmlspecialchars($title) . '</span>'
         . '</div>';
}

// ─────────────────────────────────────────────────────────────────────────────
switch ($tab) {

// ── 1. DEMOGRAPHICS ──────────────────────────────────────────────────────────
case 'demographics':
    $pd = sqlQuery(
        "SELECT fname, mname, lname, DOB, sex, race, ethnicity,
                ss, drivers_license, status,
                phone_home, phone_cell, phone_biz,
                email, street, city, state, postal_code, country_code,
                TIMESTAMPDIFF(YEAR,DOB,CURDATE()) AS age
         FROM   patient_data WHERE pid=?", [$pid]
    );
    $ins = sqlQuery(
        "SELECT id.plan_name, id.policy_number, id.group_number,
                id.subscriber_fname, id.subscriber_lname,
                id.subscriber_DOB, id.copay,
                ic.name AS company_name
         FROM   insurance_data id
         LEFT JOIN insurance_companies ic ON ic.id = id.provider
         WHERE  id.pid=? AND id.type='primary'
         ORDER  BY id.id DESC LIMIT 1", [$pid]
    );

    $html  = sectionHead('Patient Identity', '👤');
    $html .= infoGrid([
        'Full Legal Name'       => trim(($pd['fname']??'') . ' ' . ($pd['mname']??'') . ' ' . ($pd['lname']??'')),
        'Date of Birth / Age' => $pd['DOB']
    ? date('m/d/Y', strtotime($pd['DOB'])) . ' (' . ($pd['age'] ?? '-') . ' years)'
    : '-',
        'Sex'             => $pd['sex']  ?? '-',
       'Race / Ethnicity' => trim(($pd['race'] ?? '-') . ' . ' . ($pd['ethnicity'] ?? '-')),
        'Marital Status'  => $pd['status']    ?? '-',
      //  'SSN'             => $pd['ss']         ? '***-**-' . substr($pd['ss'], -4) : '-',
        //'Driver\'s License'=> $pd['drivers_license'] ?? '-',
    ]);

    $html .= sectionHead('Contact', '📞');
    $html .= infoGrid([
        'Address'     => trim(($pd['street'] ?? '') . ', '
                        . ($pd['city'] ?? '') . ', '
                        . ($pd['state'] ?? '') . ' '
                        . ($pd['postal_code'] ?? ''). ' '
                        . ($pd['country_code'] ?? '')),
        'Home Phone'  => $pd['phone_home'] ?? '—',
        'Cell Phone'  => $pd['phone_cell'] ?? '—',
         'Email'       => $pd['email']      ?? '—',
        
    ]);

    if ($ins) {
        $html .= sectionHead('Primary Insurance', '🛡️');
        $html .= infoGrid([
            'Insurance Company' => $ins['company_name'] ?? '—',
            'Plan Name'         => $ins['plan_name']    ?? '—',
            'Policy Number'     => $ins['policy_number'] ?? '—',
            'Group Number'      => $ins['group_number']  ?? '—',
            'Subscriber Name'   => trim(($ins['subscriber_fname'] ?? '')
                                . ' ' . ($ins['subscriber_lname'] ?? '')),
            'Subscriber DOB'    => $ins['subscriber_DOB']
                                   ? date('m/d/Y', strtotime($ins['subscriber_DOB']))
                                   : '—',
            'Co-pay'            => $ins['copay'] ? '$' . $ins['copay'] : '—',
        ]);
    }

    echo json_encode(['html' => $html]);
    break;

// ── 2. CHIEF COMPLAINT ───────────────────────────────────────────────────────
case 'chief_complaint':
    $encs = [];
    $res  = sqlStatement(
        "SELECT fe.date, fe.reason, fe.encounter,
                CONCAT(u.fname,' ',u.lname) AS provider
         FROM   form_encounter fe
         LEFT JOIN users u ON u.id = fe.provider_id
         WHERE  fe.pid=?
         ORDER  BY fe.date DESC LIMIT 10", [$pid]
    );
    while ($r = sqlFetchArray($res)) $encs[] = $r;

    $rows = array_map(fn($e) => [
        $e['date'] ? date('m/d/Y', strtotime($e['date'])) : '—',
        $e['reason'] ?: 'Office Visit',
        'Enc #' . $e['encounter'],
        'Dr. ' . ($e['provider'] ?: '—'),
    ], $encs);

    $html  = sectionHead('Recent Chief Complaints', '📋');
    $html .= dataTable(
        ['Date', 'Chief Complaint / Reason', 'Encounter', 'Provider'],
        $rows,
        'No encounter history found.'
    );
    echo json_encode(['html' => $html]);
    break;

// ── 3. EMERGENCY CONTACTS ────────────────────────────────────────────────────
case 'emergency':
    $pd = sqlQuery(
        "SELECT guardianrelationship, guardiansname,
                guardianphone, guardianemail,
                hipaa_allowsms,
                hipaa_voice, hipaa_notice
         FROM   patient_data WHERE pid=?", [$pid]
    );

    $html  = sectionHead('Emergency Contact', '🚨');
    $html .= infoGrid([
        'Contact Name'         => $pd['guardiansname']         ?? '—',
        'Relationship'         => $pd['guardianrelationship']  ?? '—',
        'Contact Phone'        => $pd['guardianphone']         ?? '—',
        'Email'    => $pd['guardianemail']     ?? '—',
        
    ]);

    $html .= sectionHead('HIPAA Communication Preferences', '🔒');
    $html .= infoGrid([
        'Allow SMS'    => ($pd['hipaa_allowsms'] === 'YES') ? '✅ Yes' : '❌ No',
        'Allow Voice'  => ($pd['hipaa_voice']    === 'YES') ? '✅ Yes' : '❌ No',
        'Allow Notice' => ($pd['hipaa_notice']   === 'YES') ? '✅ Yes' : '❌ No',
    ]);

    echo json_encode(['html' => $html]);
    break;

// ── 4. PHARMACY ──────────────────────────────────────────────────────────────
case 'pharmacy':
    $pharm = sqlQuery(
        "SELECT p.name, p.email, p.ncpdp, p.npi                
         FROM   pharmacies p
         JOIN   patient_data pd ON pd.pharmacy_id = p.id
         WHERE  pd.pid=?", [$pid]
    );

    $html  = sectionHead('Preferred Pharmacy', '🏥');
    if ($pharm) {
        $html .= infoGrid([
            'Pharmacy Name' => $pharm['name']    ?? '—',
            'Email'       => $pharm['email']  ?? '—',
            'NCPDP'         => $pharm['ncpdp']  ?? '—',
            'NPI'           => $pharm['npi']     ?? '—',
        ]);
    } else {
        $html .= '<p class="syd-cm-empty">No preferred pharmacy on file.</p>';
    }

    // Recent prescriptions
    $rxRows = [];
    $rxRes  = sqlStatement(
        "SELECT drug, dosage, unit, route, `interval`,
                refills, date_added, active
         FROM   prescriptions
         WHERE  patient_id=?
         ORDER  BY date_added DESC LIMIT 15", [$pid]
    );
    while ($r = sqlFetchArray($rxRes)) {
        $rxRows[] = [
            $r['drug'],
            ($r['dosage'] ?? '') . ' ' . ($r['unit'] ?? ''),
            $r['route']    ?? '—',
            $r['interval'] ?? '—',
            $r['refills']  ?? '0',
            $r['active'] ? 'Active' : 'Inactive',
            $r['date_added'] ? date('m/d/Y', strtotime($r['date_added'])) : '—',
        ];
    }
    // $html .= sectionHead('Prescription History', '💊');
    // $html .= dataTable(
    //     ['Medication','Dose','Route','Frequency','Refills','Status','Date'],
    //     $rxRows, 'No prescription history on file.'
    // );
    echo json_encode(['html' => $html]);
    break;

// ── 5. OTHER PROVIDERS ───────────────────────────────────────────────────────
case 'providers':
    $rows = [];
    $res  = sqlStatement(
        "SELECT u.fname, u.lname, u.title,
                u.specialty, u.phone, u.email,
                u.npi
         FROM   users u
         WHERE  u.active=1
         AND    u.authorized=1
         AND    (u.id = (SELECT providerID FROM patient_data WHERE pid=?)
                OR EXISTS (
                    SELECT 1 FROM form_encounter fe
                    WHERE  fe.pid=? AND fe.provider_id=u.id
                ))
         GROUP  BY u.id
         LIMIT  10", [$pid, $pid]
    );
    while ($r = sqlFetchArray($res)) {
        $rows[] = [
            'Dr. ' . $r['fname'] . ' ' . $r['lname'],
          //  $r['title']     ?? '—',
            $r['specialty'] ?? '—',
            $r['phone']     ?? '—',
            $r['npi']       ?? '—',
        ];
    }
    $html  = sectionHead('Specialists', '👨‍⚕️');
    $html .= dataTable(
        ['Name','Specialty','Phone','NPI'],
        $rows, 'No providers on file.'
    );
    echo json_encode(['html' => $html]);
    break;

// ── 6. MEDICAL HISTORY ───────────────────────────────────────────────────────
case 'medical_history':
    // Active problems
    $probs = [];
    $pRes  = sqlStatement(
        "SELECT title, diagnosis, begdate, enddate, activity, comments
         FROM   lists
         WHERE  pid=? AND type='medical_problem'
         ORDER  BY activity DESC, begdate DESC", [$pid]
    );
    while ($r = sqlFetchArray($pRes)) {
        $probs[] = [
            $r['title'],
            $r['diagnosis'] ?? '—',
            $r['begdate']   ? date('m/d/Y', strtotime($r['begdate'])) : '—',
            $r['activity']  ? 'Active' : 'Resolved',
            $r['comments']  ?? '—',
        ];
    }
    $html  = sectionHead('Problem List', '🩺');
    $html .= dataTable(
        ['Diagnosis','ICD-10','Since','Status','Notes'],
        $probs, 'No medical history on file.'
    );

    // History data
    $hd = sqlQuery(
        "SELECT tobacco, alcohol,
                 exercise_patterns,
                coffee
         FROM   history_data
         WHERE  pid=?
         ORDER  BY id DESC LIMIT 1", [$pid]
    );
    if ($hd) {
        $html .= sectionHead('Background History', '📋');
        $html .= infoGrid([
            'Tobacco Use'   => $hd['tobacco']          ?? '—',
            'Alcohol Use'   => $hd['alcohol']           ?? '—',
            'Exercise'      => $hd['exercise_patterns'] ?? '—',
            'Coffee/Caffeine'=> $hd['coffee']           ?? '—',
            
        ]);
    }
    echo json_encode(['html' => $html]);
    break;

// ── 7. MEDICATIONS ───────────────────────────────────────────────────────────
case 'medications':
    $active = [];
    $inact  = [];
    $mRes   = sqlStatement(
        "SELECT drug, dosage, unit, route, `interval`,
                refills, date_added, active, note
         FROM   prescriptions
         WHERE  patient_id=?
         ORDER  BY active DESC, date_added DESC", [$pid]
    );
    while ($r = sqlFetchArray($mRes)) {
        $row = [
            $r['drug'],
            ($r['dosage'] ?? '') . ' ' . ($r['unit'] ?? ''),
            $r['route']    ?? '—',
            $r['interval'] ?? '—',
            $r['refills']  ?? '0',
            $r['date_added'] ? date('m/d/Y', strtotime($r['date_added'])) : '—',
            $r['note']     ?? '—',
        ];
        if ($r['active']) $active[] = $row;
        else              $inact[]  = $row;
    }
    $cols  = ['Medication','Dose','Route','Frequency','Refills','Date','Notes'];
    $html  = sectionHead('Active Medications (' . count($active) . ')', '💊');
    $html .= dataTable($cols, $active, 'No active medications.');
    //$html .= sectionHead('Inactive / Discontinued (' . count($inact) . ')', '📦');
   // $html .= dataTable($cols, $inact, 'No inactive medications.');
    echo json_encode(['html' => $html]);
    break;

// ── 8. SURGICAL HISTORY ──────────────────────────────────────────────────────
case 'surgical':
    $rows = [];
    $res  = sqlStatement(
        "SELECT title, begdate, enddate, comments
         FROM   lists
         WHERE  pid=? AND type='surgery'
         ORDER  BY begdate DESC", [$pid]
    );
    while ($r = sqlFetchArray($res)) {
        $rows[] = [
            $r['title'],
            $r['begdate'] ? date('m/d/Y', strtotime($r['begdate'])) : '—',
            $r['enddate'] ? date('m/d/Y', strtotime($r['enddate'])) : '—',
            $r['comments'] ?? '—',
        ];
    }
    $html  = sectionHead('Surgical History', '🔪');
    $html .= dataTable(
        ['Procedure','Date','Discharge','Notes'],
        $rows, 'No surgical history on file.'
    );
    echo json_encode(['html' => $html]);
    break;

// ── 9. ALLERGIES ─────────────────────────────────────────────────────────────
case 'allergies':
    $rows = [];
    $res  = sqlStatement(
        "SELECT title, reaction, begdate,
                enddate, activity, comments
         FROM   lists
         WHERE  pid=? AND type='allergy'
         ORDER  BY activity DESC", [$pid]
    );
    while ($r = sqlFetchArray($res)) {
        $rows[] = [
            $r['title'],
            $r['reaction']  ?? '—',
            $r['begdate']   ? date('m/d/Y', strtotime($r['begdate'])) : '—',
            $r['activity']  ? 'Active' : 'Inactive',
            $r['comments']  ?? '—',
        ];
    }
    $html  = sectionHead('Allergies & Adverse Reactions', '⚠️');
    if (empty($rows)) {
        $html .= '<div class="syd-cm-nkda">✅ No Known Drug Allergies (NKDA)</div>';
    } else {
        $html .= dataTable(
            ['Allergen','Reaction','Date Noted','Status','Notes'],
            $rows
        );
    }
    echo json_encode(['html' => $html]);
    break;

// ── 10. SOCIAL HISTORY ───────────────────────────────────────────────────────
case 'social':
    $hd = sqlQuery(
        "SELECT tobacco, alcohol,
                exercise_patterns, coffee,
                recreational_drugs,
                sleep_patterns
            
         FROM   history_data
         WHERE  pid=?
         ORDER  BY id DESC LIMIT 1", [$pid]
    );
    $pd = sqlQuery(
        "SELECT sex, status, nationality_country, language
         FROM   patient_data WHERE pid=?", [$pid]
    );

    $html  = sectionHead('Lifestyle & Social History', '🏠');
    $html .= infoGrid([
        'Tobacco Use'        => $hd['tobacco']                   ?? '—',
        'Alcohol Use'        => $hd['alcohol']                    ?? '—',
        'Recreational Drugs' => $hd['recreational_drugs']         ?? '—',
        
        'Exercise'           => $hd['exercise_patterns']          ?? '—',
        'Sleep Patterns'     => $hd['sleep_patterns']             ?? '—',
        'Caffeine Use'       => $hd['coffee']                     ?? '—',
        
       
        'Language'           => $pd['language']                   ?? '—',
        'Nationality'        => $pd['nationality_country']                ?? '—',
    ]);
    echo json_encode(['html' => $html]);
    break;

// ── 11. FAMILY HISTORY ───────────────────────────────────────────────────────
case 'family':
    $hd = sqlQuery(
        "SELECT relatives_cancer, relatives_diabetes,
                relatives_high_blood_pressure,
                relatives_heart_problems,
                relatives_stroke, relatives_epilepsy,
                relatives_mental_illness, relatives_suicide,
                history_father, dc_father,history_mother,dc_mother,
                history_siblings,dc_siblings,history_spouse,dc_spouse
         FROM   history_data
         WHERE  pid=?
         ORDER  BY id DESC LIMIT 1", [$pid]
    );

    $html  = sectionHead('Family Medical History', '👨‍👩‍👧');
    $html .= infoGrid([
        'Father\'s History' => ($hd['history_father'] ?? '—') . ' . ' . ($hd['dc_father'] ?? ''),
        'Mother\'s History' => ($hd['history_mother'] ?? '—') . ' . ' . ($hd['dc_mother'] ?? ''),
        'Siblings\' History'=> ($hd['history_siblings'] ?? '—') . ' . ' . ($hd['dc_siblings'] ?? ''),
        'Spouse\' History'=> ($hd['history_spouse'] ?? '—') . ' . ' . ($hd['dc_spouse'] ?? ''),
        'Cancer'            => $hd['relatives_cancer']             ?? '—',
        'Diabetes'          => $hd['relatives_diabetes']           ?? '—',
        'Hypertension'      => $hd['relatives_high_blood_pressure'] ?? '—',
        'Heart Disease'     => $hd['relatives_heart_problems']     ?? '—',
        'Stroke'            => $hd['relatives_stroke']             ?? '—',
        'Epilepsy'          => $hd['relatives_epilepsy']           ?? '—',
        'Mental Illness'    => $hd['relatives_mental_illness']     ?? '—',
        'Suicide'           => $hd['relatives_suicide']            ?? '—',
       
        
    ]);
    echo json_encode(['html' => $html]);
    break;

// ── 12. SCREENING FORMS ──────────────────────────────────────────────────────
case 'screening':
    // Look for common screening form tables
    $screenForms = [];

    // PHQ-9
    $phq = sqlQuery(
        "SELECT f.date, SUM(
            f.field_value
         ) AS total_score
         FROM   forms f
         JOIN   form_encounter fe ON fe.encounter = f.encounter
         WHERE  f.pid=?
         AND    f.formdir LIKE '%phq%'
         AND    f.deleted=0
         GROUP  BY f.id
         ORDER  BY f.date DESC LIMIT 5", [$pid]
    );
    if ($phq) $screenForms[] = ['PHQ-9 Depression Screen', $phq['date'], 'Score: ' . ($phq['total_score'] ?? '—')];

    // GAD-7
    $gad = sqlQuery(
        "SELECT f.date
         FROM   forms f
         WHERE  f.pid=?
         AND    f.formdir LIKE '%gad%'
         AND    f.deleted=0
         ORDER  BY f.date DESC LIMIT 1", [$pid]
    );
    if ($gad) $screenForms[] = ['GAD-7 Anxiety Screen', $gad['date'], 'Completed'];

    // General form list
    $fRows = [];
    $fRes  = sqlStatement(
        "SELECT f.date, f.form_name, f.formdir,
                fe.reason
         FROM   forms f
         LEFT JOIN form_encounter fe ON fe.encounter = f.encounter
         WHERE  f.pid=?
         AND    f.deleted=0
         AND    f.formdir NOT IN ('soap','encounter','vitals')
         ORDER  BY f.date DESC
         LIMIT  20", [$pid]
    );
    while ($r = sqlFetchArray($fRes)) {
        $fRows[] = [
            $r['form_name'] ?? $r['formdir'],
            $r['date'] ? date('m/d/Y', strtotime($r['date'])) : '-',
            $r['reason'] ?? '-',
        ];
    }

    $html  = sectionHead('Completed Screening Forms', '📊');
    $html .= dataTable(
        ['Form Name','Date','Encounter Reason'],
        $fRows, 'No screening forms on file.'
    );
    echo json_encode(['html' => $html]);
    break;

// ── 13. CUSTOM FORMS ─────────────────────────────────────────────────────────
case 'custom_forms':
    $rows = [];
    $res  = sqlStatement(
        "SELECT f.date, f.form_name, f.formdir,
                f.encounter, f.user
         FROM   forms f
         WHERE  f.pid=?
         AND    f.deleted=0
         ORDER  BY f.date DESC
         LIMIT  30", [$pid]
    );
    while ($r = sqlFetchArray($res)) {
        $rows[] = [
            $r['form_name']  ?? $r['formdir'],
            $r['date']       ? date('m/d/Y', strtotime($r['date'])) : '—',
            'Enc #' . $r['encounter'],
            $r['user']       ?? '—',
        ];
    }
    $html  = sectionHead('All Patient Forms', '📝');
    $html .= dataTable(
        ['Form Name','Date','Encounter','Entered By'],
        $rows, 'No custom forms on file.'
    );
    echo json_encode(['html' => $html]);
    break;

// ── 14. CONSENT FORMS ────────────────────────────────────────────────────────
case 'consent':
    $rows = [];
    $res  = sqlStatement(
        "SELECT f.date, f.form_name, f.user,
                f.encounter
         FROM   forms f
         WHERE  f.pid=?
         AND    f.deleted=0
         AND    (LOWER(f.form_name) LIKE '%consent%'
                OR LOWER(f.formdir) LIKE '%consent%'
                OR LOWER(f.form_name) LIKE '%hipaa%'
                OR LOWER(f.form_name) LIKE '%authorization%')
         ORDER  BY f.date DESC", [$pid]
    );
    while ($r = sqlFetchArray($res)) {
        $rows[] = [
            $r['form_name'],
            $r['date'] ? date('m/d/Y', strtotime($r['date'])) : '—',
            $r['user']        ?? '—',
            'Enc #' . ($r['encounter'] ?? '—'),
        ];
    }

    $html  = sectionHead('Consent & Authorization Forms', '✍️');
    $html .= dataTable(
        ['Form Name','Signed Date','Entered By','Encounter'],
        $rows, 'No consent forms on file.'
    );

    // HIPAA acknowledgment from patient_data
    $hipaa = sqlQuery(
        "SELECT hipaa_ack, hipaa_date, hipaa_employee,
                hipaa_notice, hipaa_allowsms, hipaa_voice
         FROM   patient_data WHERE pid=?", [$pid]
    );
    if ($hipaa) {
        $html .= sectionHead('HIPAA Acknowledgment', '🔒');
        $html .= infoGrid([
            'HIPAA Acknowledged'  => $hipaa['hipaa_ack']      ?? '—',
            'Date Acknowledged'   => $hipaa['hipaa_date']
                                     ? date('m/d/Y', strtotime($hipaa['hipaa_date']))
                                     : '—',
            'Entered By'          => $hipaa['hipaa_employee']  ?? '—',
            'Allow SMS Contact'   => ($hipaa['hipaa_allowsms'] === 'YES') ? '✅ Yes' : '❌ No',
            'Allow Voice Contact' => ($hipaa['hipaa_voice']    === 'YES') ? '✅ Yes' : '❌ No',
        ]);
    }
    echo json_encode(['html' => $html]);
    break;

default:
    echo json_encode(['error' => 'Unknown tab: ' . htmlspecialchars($tab)]);
}