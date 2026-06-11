<?php

/**
 * message_handler.php
 *
 * AJAX endpoint for the Patient Dashboard messaging system.
 * Handles:
 *   action=send      — patient sends a new message to a staff member
 *   action=mark_read — mark a message as read
 *   action=get_thread — fetch a single message thread
 *
 * Place at:
 *   /openemr/interface/modules/custom_modules/oe-module-patient-dashboard/public/message_handler.php
 *
 * Called from:
 *   synapta-portal.js  via fetch()
 */
 
header('Content-Type: application/json');
 
// ── Bootstrap (same as home.php) ─────────────────────────────────────────────
require_once dirname(__DIR__, 5) . '/vendor/autoload.php';
 
use OpenEMR\Common\Session\SessionUtil;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Common\Csrf\CsrfUtils;
 
$globalsBag = OEGlobalsBag::getInstance();
SessionUtil::setAppCookie(SessionUtil::PORTAL_SESSION_ID);
$_COOKIE[SessionUtil::APP_COOKIE_NAME] = SessionUtil::PORTAL_SESSION_ID;
$session = SessionWrapperFactory::getInstance()->getWrapper();
 
$ignoreAuth_onsite_portal = true;
require_once dirname(__DIR__, 5) . '/interface/globals.php';
 
// ── Auth check ────────────────────────────────────────────────────────────────
$pid            = (int)$session->get('pid', 0);
$portalUsername = $session->get('portal_username', '');
$portalActive   = $session->get('patient_portal_onsite_two', '');
 
if (!$pid || !$portalUsername || !$portalActive) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
 
// Sync to $_SESSION for any OpenEMR functions that read it
$_SESSION['pid']                       = $pid;
$_SESSION['portal_username']           = $portalUsername;
$_SESSION['patient_portal_onsite_two'] = $portalActive;
 
// ── CSRF check (POST requests only) ──────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (class_exists('OpenEMR\Common\Csrf\CsrfUtils')
        && !CsrfUtils::verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// ── Route ─────────────────────────────────────────────────────────────────────
switch ($action) {
 
    // ── Send a new message ───────────────────────────────────────────────────
    case 'send':

    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $toUserId = trim($_POST['to_user'] ?? '');
   
  

    if (empty($subject) || empty($body)) {
        echo json_encode([
            'success' => false,
            'error' => 'Subject and message body are required.'
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE SENDER (PATIENT)
    |--------------------------------------------------------------------------
    */

    $patientRow = sqlQuery(
        "SELECT CONCAT(fname, ' ', lname) AS fullname
         FROM patient_data
         WHERE pid = ?",
        [$pid]
    );

    $patientName = $patientRow['fullname'] ?? ('Patient #' . $pid);

    /*
    |--------------------------------------------------------------------------
    | RESOLVE RECIPIENT (STAFF USERNAME)
    |--------------------------------------------------------------------------
    */

    $toUsername = 'admin';
    $toName     = 'Care Team';

    if ($toUserId > 0) {

        $staffRow = sqlQuery(
            "SELECT username, fname, lname
             FROM users
             WHERE username = ? AND active = 1",
            [$toUserId]
        );

        if (!empty($staffRow['username'])) {
            $toUsername = $staffRow['username'];
            $toName = trim(($staffRow['fname'] ?? '') . ' ' . ($staffRow['lname'] ?? ''));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT INTO onsite_mail
    |--------------------------------------------------------------------------
    */

    sqlInsert(
        "INSERT INTO onsite_mail
        (
            date,
            owner,
            user,
            activity,
            header,
            title,
            body,
            recipient_id,
            recipient_name,
            sender_id,
            sender_name,
            assigned_to,
            message_status,
            deleted
        )
        VALUES
        (
            NOW(),
            ?,
            ?,
            1,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'New',
            0
        )",
        [
            $portalUsername,   // owner
            $patientName,      // user (sender display)
            $subject,          // header
            $subject,          // title
            $subject . "\n\n" . $body,  // body
            $toUserId,         // recipient_id
            $toName,           // recipient_name
            $portalUsername,   // sender_id
            $patientName,      // sender_name
            $toUsername        // assigned_to
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully'
    ]);

    break;
 
    // ── Mark a message as read ───────────────────────────────────────────────
    case 'mark_portal_read':
        $msgId = (int)($_POST['id'] ?? 0);
        if (!$msgId) {
            echo json_encode(['success' => false, 'error' => 'Invalid message ID.']);
            exit;
        }
 
        // Verify this message belongs to this patient before updating
        sqlStatement(
            "UPDATE onsite_mail
             SET    message_status = 'Read',
                    activity       = 0
             WHERE  id  = ?",
            [$msgId]
        );
 
        echo json_encode(['success' => true]);
        break;
 
    // ── Get single message detail ────────────────────────────────────────────
    case 'get_message':
        $msgId = (int)($_GET['id'] ?? 0);
        if (!$msgId) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
            exit;
        }
 
        $row = sqlQuery(
            "SELECT pn.id,
                    COALESCE(CONCAT(u.fname,' ',u.lname), pn.user, 'Care Team') AS sender,
                    pn.title   AS subject,
                    pn.body,
                    pn.date,
                    pn.message_status,
                   
             FROM   pnotes pn
             LEFT JOIN users u ON u.username = pn.user
             WHERE  pn.id  = ?
               AND  pn.pid = ?
               AND  pn.deleted != 1",
            [$msgId, $pid]
        );
 
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Message not found.']);
            exit;
        }
 
        // Auto mark as read when opened
        sqlStatement(
            "UPDATE pnotes SET message_status='Read', activity=0 WHERE id=? AND pid=?",
            [$msgId, $pid]
        );
 
        echo json_encode(['success' => true, 'data' => $row]);
        break;
 
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        break;
}
exit;