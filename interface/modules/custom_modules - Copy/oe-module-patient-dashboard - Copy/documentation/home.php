<?php
/**
 * home.php — Patient Portal Home (Synapta Dashboard)
 *
 * DROP-IN REPLACEMENT for /openemr/portal/home.php
 *
 * Renders the custom Patient Dashboard module UI after verifying
 * the patient portal session exactly the way OpenEMR 8.0 expects.
 *
 * HOW TO DEPLOY:
 *   1. Back up original:
 *        cp /openemr/portal/home.php /openemr/portal/home.php.bak
 *   2. Copy this file:
 *        cp home.php /openemr/portal/home.php
 *
 * WHAT CAUSED THE REDIRECT LOOP (and how it is fixed here):
 *   1. Wrong globals path  — OE8 portal uses
 *        require_once(__DIR__ . "/../src/Common/Session/SessionUtil.php")
 *        then require_once(__DIR__ . "/../interface/globals.php")
 *      NOT a bare '../interface/globals.php' without the session file first.
 *
 *   2. Wrong session key   — OE8 stores the portal flag in
 *        $_SESSION['patient_portal_onsite_two']
 *      NOT 'portal_login_username' (that was OE6/7 syntax).
 *
 *   3. Wrong portal_auth   — /portal/lib/portal_auth.php does NOT exist
 *      in a stock OE8 install; including it causes a fatal error which
 *      OpenEMR catches and redirects to login.
 *
 *   4. Wrong CSRF check    — CsrfUtils::setupCsrfKey() is not a plain
 *      function; calling function_exists() on it always returns false
 *      so it was silently skipped (harmless but wrong).
 *
 *   5. Wrong namespace     — the old override still referenced
 *      OpenEMR\Modules\SynaptaPortal (the old name).
 */

// ── Step 1: Start / resume the session the OE8 way ──────────────────────────
// OpenEMR 8 requires SessionUtil to be loaded before globals.php so that
// the session is started with the correct cookie parameters.
$sessionUtil = __DIR__ . '/../src/Common/Session/SessionUtil.php';
if (file_exists($sessionUtil)) {
    require_once $sessionUtil;
    \OpenEMR\Common\Session\SessionUtil::portalSessionStart();
} else {
    // Fallback for installations where SessionUtil path differs
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// ── Step 2: Bootstrap OpenEMR globals ───────────────────────────────────────
$ignoreAuth_onlyNoSitesetup = false;
require_once __DIR__ . '/../interface/globals.php';

// ── Step 3: Verify portal session — OE8 session keys ───────────────────────
//
// OpenEMR 8.0 sets ALL of these when a patient successfully logs in.
// If any is missing the session is invalid and we redirect to login.
//
//   $_SESSION['pid']                         integer   patient PID
//   $_SESSION['patient_portal_onsite_two']   bool/1    "portal is active" flag
//   $_SESSION['portal_username']             string    patient's portal username
//
// NOTE: 'portal_login_username' was the OE6/7 key — it does NOT exist in OE8.

$portalSessionValid =
    !empty($_SESSION['pid']) &&
    !empty($_SESSION['patient_portal_onsite_two']) &&
    !empty($_SESSION['portal_username']);

if (!$portalSessionValid) {
    // Preserve any "destination after login" so the portal can redirect back
    if (!empty($_SERVER['REQUEST_URI'])) {
        $_SESSION['portal_redirect_url'] = $_SERVER['REQUEST_URI'];
    }
    header('Location: ' . $GLOBALS['web_root'] . '/portal/index.php?w=1');
    exit;
}

// ── Step 4: CSRF token refresh (OE8 standard) ───────────────────────────────
// CsrfUtils is a class, not a standalone function — use class_exists().
if (class_exists('\OpenEMR\Common\Csrf\CsrfUtils')) {
    \OpenEMR\Common\Csrf\CsrfUtils::setupCsrfKey();
}

// ── Step 5: Autoload the module ──────────────────────────────────────────────
// OpenEMR's Composer autoloader is already active after globals.php, so the
// PatientDashboard namespace is available if you ran `composer dump-autoload`
// from the OpenEMR root.
//
// We also provide a manual require_once fallback so the page works even
// before composer dump-autoload is run.

$moduleRoot = __DIR__ . '/../interface/modules/custom_modules/oe-module-patient-dashboard';

if (!class_exists('\OpenEMR\Modules\PatientDashboard\Controller\DashboardController')) {
    $controllerFile = $moduleRoot . '/src/Controller/DashboardController.php';
    if (!file_exists($controllerFile)) {
        // Module is not installed — show the default portal home fallback
        showDefaultPortalHome();
        exit;
    }
    require_once $controllerFile;
}

// ── Step 6: Render the dashboard ────────────────────────────────────────────
$controller = new \OpenEMR\Modules\PatientDashboard\Controller\DashboardController();
$controller->render();
exit;

// ── Fallback: minimal portal home if module is missing ──────────────────────
function showDefaultPortalHome(): void
{
    global $GLOBALS;
    $webRoot = $GLOBALS['web_root'] ?? '';
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>Patient Portal</title></head><body>
          <h2>Patient Portal</h2>
          <p>The Patient Dashboard module is not installed.</p>
          <p>Please contact your administrator or
          <a href="' . htmlspecialchars($webRoot, ENT_QUOTES) . '/portal/index.php">
          return to login</a>.</p>
          </body></html>';
}
