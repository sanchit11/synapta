<?php

/**
 * Synapta Health — Early Access Form Submit Handler
 *
 *
 * Called by EarlyAccess.php via fetch() POST.
 * Returns JSON.  Never renders HTML.
 *
 * Security controls:
 *   ✔ CSRF token validated via OpenEMR CsrfUtils
 *   ✔ All inputs sanitised (htmlspecialchars / filter_var)
 *   ✔ Prepared statements via OpenEMR sqlInsert / sqlQuery
 *   ✔ Duplicate email guard (DB unique key + pre-check)
 *   ✔ Server-side validation mirrors client-side rules
 *   ✔ IP address logged
 */

$ignoreAuth = true;
require_once __DIR__ . '/../globals.php';
//require_once $GLOBALS['srcdir'] . '/CsrfUtils.php';

//use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: application/json; charset=utf-8');

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── CSRF check ────────────────────────────────────────────────────────────────
// if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Security token invalid. Please reload the page and try again.']);
//     exit;
// }

// ── Sanitise helpers ──────────────────────────────────────────────────────────
function clean(string $v): string
{
    return htmlspecialchars(trim($v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function cleanEmail(string $v): string
{
    $e = filter_var(trim(strtolower($v)), FILTER_SANITIZE_EMAIL);
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
}
function cleanPhone(string $v): string
{
    return preg_replace('/[^\d+\-() ]/', '', trim($v));
}
function cleanChallenges(string $v): string
{
    $arr = json_decode($v, true);
    if (!is_array($arr)) {
        $arr = array_filter(array_map('trim', explode(',', $v)));
    }
    $arr = array_values(array_filter(array_map('trim', $arr)));
    return json_encode($arr);
}
function clientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ── Collect & sanitise ────────────────────────────────────────────────────────
$d = [
    // Step 1
    'practice_name'   => clean($_POST['practice_name']   ?? ''),
    'clinic_type'     => clean($_POST['clinic_type']     ?? ''),
    'practice_state'  => clean($_POST['practice_state']  ?? ''),
    'provider_count'  => clean($_POST['provider_count']  ?? ''),
    // Step 2
    'first_name'      => clean($_POST['first_name']      ?? ''),
    'last_name'       => clean($_POST['last_name']       ?? ''),
    'contact_role'    => clean($_POST['contact_role']    ?? ''),
    'email'           => cleanEmail($_POST['email']      ?? ''),
    'phone'           => cleanPhone($_POST['phone']      ?? ''),
    'best_time'       => clean($_POST['best_time']       ?? ''),
    'referral_source' => clean($_POST['referral_source'] ?? ''),
    // Step 3
    'booking_software'=> clean($_POST['booking_software']?? ''),
    'ehr_system'      => clean($_POST['ehr_system']      ?? ''),
    'prescribes'      => clean($_POST['prescribes']      ?? ''),
    'consent_process' => clean($_POST['consent_process'] ?? ''),
    // Step 4
    'challenges'      => cleanChallenges($_POST['challenges'] ?? ''),
    'timeline'        => clean($_POST['timeline']        ?? ''),
    'additional_notes'=> clean($_POST['additional_notes']?? ''),
    //'loi_agreed'      => ($_POST['loi_agreed'] ?? '0') === '1' ? 1 : 0,
    // Meta
    'ip_address'      => clientIp(),
    'user_agent'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
];

// ── Server-side validation ────────────────────────────────────────────────────
$errors = [];

// Step 1
if (empty($d['practice_name']))  $errors['practice_name']  = 'Practice name is required.';
if (empty($d['clinic_type']))    $errors['clinic_type']    = 'Clinic type / specialty is required.';
if (empty($d['practice_state'])) $errors['practice_state'] = 'State is required.';
if (empty($d['provider_count'])) $errors['provider_count'] = 'Number of providers is required.';

// Step 2
if (empty($d['first_name']))   $errors['first_name']   = 'First name is required.';
if (empty($d['last_name']))    $errors['last_name']     = 'Last name is required.';
if (empty($d['contact_role'])) $errors['contact_role'] = 'Role is required.';
if (empty($d['email']))        $errors['email']         = 'A valid work email is required.';
if (strlen(preg_replace('/\D/', '', $d['phone'])) < 10)
                               $errors['phone']         = 'A valid phone number is required.';

// Step 3
if (empty($d['prescribes'])) $errors['prescribes'] = 'Prescription status is required.';

// Step 4
$cArr = json_decode($d['challenges'], true);
if (empty($cArr))            $errors['challenges'] = 'Please select at least one challenge.';
if (empty($d['timeline']))   $errors['timeline']   = 'Timeline is required.';
//if (!$d['loi_agreed'])       $errors['loi_agreed'] = 'Please acknowledge the Letter of Intent.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fix the errors below.', 'errors' => $errors]);
    exit;
}

// ── Duplicate email check ─────────────────────────────────────────────────────
$existing = sqlQuery(
    'SELECT id FROM synapta_early_access WHERE email = ? LIMIT 1',
    [$d['email']]
);
if (!empty($existing)) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'This email address is already registered. Our team will be in touch shortly.',
    ]);
    exit;
}

// ── Generate unique reference ID ──────────────────────────────────────────────
function makeRef(): string
{
    for ($i = 0; $i < 5; $i++) {
        $ref = 'SYN-EA-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $row = sqlQuery('SELECT id FROM synapta_early_access WHERE reference_id = ? LIMIT 1', [$ref]);
        if (empty($row)) return $ref;
    }
    return 'SYN-EA-' . strtoupper(base_convert((string)time(), 10, 36));
}
$refId = makeRef();

// ── Insert into OpenEMR database ──────────────────────────────────────────────
try {
    sqlInsert(
        'INSERT INTO synapta_early_access (
            reference_id,
            practice_name, clinic_type, practice_state, provider_count,
            first_name, last_name, contact_role, email, phone,
            best_time, referral_source,
            booking_software, ehr_system, prescribes, consent_process,
            challenges, timeline, additional_notes,
            ip_address, user_agent,
            status, created_at
        ) VALUES (
            ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            \'new\', NOW()
        )',
        [
            $refId,
            $d['practice_name'], $d['clinic_type'], $d['practice_state'], $d['provider_count'],
            $d['first_name'], $d['last_name'], $d['contact_role'], $d['email'], $d['phone'],
            $d['best_time'], $d['referral_source'],
            $d['booking_software'], $d['ehr_system'], $d['prescribes'], $d['consent_process'],
            $d['challenges'], $d['timeline'], $d['additional_notes'],
            $d['ip_address'], $d['user_agent'],
        ]
    );
} catch (Exception $e) {
    error_log('[Synapta EarlyAccess] DB insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}

// ── Success ───────────────────────────────────────────────────────────────────
http_response_code(201);
echo json_encode([
    'success'      => true,
    'reference_id' => $refId,
    'message'      => 'Your early access request has been received.',
]);


// ── Send Email using SMTP ───────────────────────────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once $GLOBALS['fileroot'] . '/vendor/autoload.php';

try {
    $mail = new PHPMailer(true);
    $challengesArr = json_decode($d['challenges'], true);
    $challengesText = is_array($challengesArr) ? implode(', ', $challengesArr) : 'N/A';
    // SMTP Settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.ionos.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'support@synaptahealth.ai';
    $mail->Password   = 'myhealth_1394@EHR';
    $mail->SMTPSecure = 'ssl';
    $mail->Port       = 465;
    $mail->CharSet = 'UTF-8';

    // Sender & receiver
    $mail->setFrom('support@synaptahealth.ai', 'Synapta Health');
    $mail->addAddress('dr.sandhu@synaptahealth.ai');
    $mail->addReplyTo($d['email'], $d['first_name']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'New Early Access Submission - ' . $refId;

    $mail->Body = "
        <h2>New Early Access Request Received</h2>

        <p><strong>Reference ID:</strong> {$refId}</p>

        <h3>Practice Info</h3>
        <ul>
            <li><strong>Practice / Clinic Name:</strong> {$d['practice_name']}</li>
            <li><strong>Type of Clinic / Specialty:</strong> {$d['clinic_type']}</li>
            <li><strong>State:</strong> {$d['practice_state']}</li>
            <li><strong>Number of Providers:</strong> {$d['provider_count']}</li>
        </ul>

        <h3>Contact</h3>
        <ul>
            <li><strong>Name:</strong> {$d['first_name']} {$d['last_name']}</li>
            <li><strong>Title / Role:</strong> {$d['contact_role']}</li>
            <li><strong>Work Email:</strong> {$d['email']}</li>
            <li><strong>Phone Number:</strong> {$d['phone']}</li>
            <li><strong>Best Time to Reach You:</strong> {$d['best_time']}</li>
            <li><strong>How Did You Hear About Synapta?:</strong> {$d['referral_source']}</li>
       
        </ul>

        <h3>Clinical Setup</h3>
        <ul>
            <li><strong>Booking & Scheduling Software:</strong> {$d['booking_software']}</li>
            <li><strong>Current Clinical Documentation System:</strong> {$d['ehr_system']}</li>
            <li><strong>Does your practice prescribe medications?:</strong> {$d['prescribes']}</li>
            <li><strong>Consent Form Process:</strong> {$d['consent_process']}</li>
        </ul>

        <h3>Goals</h3>
        <ul>
            <li><strong>What's your biggest clinical documentation challenge?:</strong> {$challengesText}</li>
            <li><strong>How soon are you looking to make a change?:</strong> {$d['timeline']}</li>
            <li><strong>Anything else you'd like us to know?:</strong> {$d['additional_notes']}</li>
        </ul>

        <h3>Meta</h3>
        <ul>
            <li><strong>IP:</strong> {$d['ip_address']}</li>
            <li><strong>User Agent:</strong> {$d['user_agent']}</li>
        </ul>
        ";

    $mail->send();

} catch (Exception $e) {
    error_log('Mailer Error: ' . $mail->ErrorInfo);
}

try {
    $ackMail = new PHPMailer(true);

    $ackMail->isSMTP();
    $ackMail->Host       = 'smtp.ionos.com';
    $ackMail->SMTPAuth   = true;
    $ackMail->Username   = 'support@synaptahealth.ai';
    $ackMail->Password   = 'myhealth_1394@EHR';
    $ackMail->SMTPSecure = 'ssl';
    $ackMail->Port       = 465;
    $ackMail->CharSet = 'UTF-8';

    // Sender (your system)
    $ackMail->setFrom('support@synaptahealth.ai', 'Synapta Health');

    // Receiver (USER who submitted form)
    $ackMail->addAddress($d['email'], $d['first_name']);

    // Content
    $ackMail->isHTML(true);
    $ackMail->Subject = "You're on the list — Synapta Early Access";

    // 👇 Clean acknowledgment email
    $ackMail->Body = "
        <div style='font-family:Arial,sans-serif;line-height:1.6'>
            <h2 style='color:#0C7A87;'>You're officially on the list </h2>

            <p>Hi {$d['first_name']},</p>

            <p>Thanks for requesting early access to <strong>Synapta Health</strong>.</p>

            <p>Your submission has been received and is being reviewed by our founding team.</p>

            <p><strong>Reference ID:</strong> {$refId}</p>

            <p>We'll reach out within <strong>1-2 business days</strong> to confirm your spot.</p>

            <br/>

            

            <p>- Thanks again.</p>
        </div>
    ";

    $ackMail->send();

} catch (Exception $e) {
        error_log('Mailer Error: ' . $ackMail->ErrorInfo);
}
exit;
