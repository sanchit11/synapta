<?php

require_once dirname(__DIR__, 5) . '/vendor/autoload.php';
 
use OpenEMR\Common\Session\SessionUtil;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;
 
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
    http_response_code(403);
    echo '<!DOCTYPE html><html><body>'
       . '<p style="font-family:sans-serif;color:#c00;padding:20px;">'
       . 'Session expired. Please <a href="'
       . htmlspecialchars($GLOBALS['web_root'] ?? '/openemr')
       . '/portal/index.php">log in again</a>.</p>'
       . '</body></html>';
    exit;
}
 
// Sync to $_SESSION and $_GET so add_edit_event_user.php gets what it needs
$_SESSION['pid']                       = $pid;
$_SESSION['portal_username']           = $portalUsername;
$_SESSION['patient_portal_onsite_two'] = $portalActive;
$_GET['patid'] = $_GET['patid'] ?? $pid;
 
// ── Capture output of add_edit_event_user.php ─────────────────────────────────
// chdir to /portal/ so all relative require_once paths inside the file resolve correctly
//$portalDir  = __DIR__ . '/../../../../portal';
$portalDir  = dirname(__DIR__, 5) . '/portal';
$targetFile = $portalDir . '/add_edit_event_user.php';
 
if (!file_exists($targetFile)) {
    echo '<!DOCTYPE html><html><body>'
       . '<p style="font-family:sans-serif;color:#c00;padding:20px;">'
       . 'Appointment form not found. Please contact your administrator.</p>'
       . '</body></html>';
    exit;
}
 
chdir($portalDir);   // Makes relative paths inside the file work
ob_start();
include $targetFile;
$html = ob_get_clean();
 
// ── Inject jQuery right after <head> ─────────────────────────────────────────
// OpenEMR 8 jQuery path
$jqueryUrl = ($GLOBALS['web_root'] ?? '/openemr')
           . '/public/assets/jquery/dist/jquery.min.js';

           
 
// Only inject if jQuery isn't already in <head> (avoid duplicates)
$headPos = stripos($html, '<head');
$firstScriptPos = stripos($html, '<script');
 
// Inject if jQuery loads AFTER the first inline script (i.e. load order problem)
$jqueryPos = stripos($html, 'jquery');
if ($jqueryPos === false || ($firstScriptPos !== false && $jqueryPos > $firstScriptPos)) {
    $jqueryTag  = "\n    "
                . '<script src="' . htmlspecialchars($jqueryUrl) . '"></script>'
                . "\n";
 
    // Insert immediately after the opening <head ...> tag
    $html = preg_replace(
        '/(<head[^>]*>)/i',
        '$1' . $jqueryTag,
        $html,
        1
    );
}
 
echo $html;
exit;