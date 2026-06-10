<?php

// Load OpenEMR bootstrap
require_once(dirname(__FILE__, 3) . "/globals.php");

use OpenEMR\Common\Acl\AclMain;

// Security check
if (!AclMain::aclCheckCore('patients', 'demo')) {
    require_once(dirname(__FILE__) . '/demographics_original.php');
    exit;
}

// ── Get pid from URL or session ───────────────────────────
$pid = (int)($_GET['set_pid']
    ?? $_GET['pid']
    ?? $_SESSION['pid']
    ?? $GLOBALS['pid']
    ?? 0);

// Set session pid if provided via URL
if (!empty($_GET['set_pid'])) {
    $_SESSION['pid'] = (int)$_GET['set_pid'];
    $GLOBALS['pid']  = (int)$_GET['set_pid'];
    $pid             = (int)$_GET['set_pid'];
}

// If no patient selected — show original demographics
if (!$pid) {
    require_once(dirname(__FILE__) . '/demographics_original.php');
    exit;
}

// ── Build Synapta Dashboard URL ───────────────────────────
$webroot      = $GLOBALS['webroot'] ?? '';
$portalSetup = !empty($_GET['portal_setup']);

$dashboardUrl = $webroot
    . '/interface/modules/custom_modules'
    . '/oe-module-physician-dashboard/public/dashboard.php'
    . '?pid=' . $pid
    . '&set_pid=' . $pid;

    
if ($portalSetup) {
    $dashboardUrl .= '&portal_setup=1';
}

// Pass encounter if available
$encounter = (int)($_GET['set_encounter']
    ?? $_SESSION['encounter']
    ?? $GLOBALS['encounter']
    ?? 0);
if ($encounter) {
    $dashboardUrl .= '&encounter=' . $encounter;
}

// ── Verify the dashboard file exists ─────────────────────
$dashboardFile = dirname(__FILE__, 3)
    . '/modules/custom_modules'
    . '/oe-module-physician-dashboard/public/dashboard.php';

if (!file_exists($dashboardFile)) {
    // Dashboard not found — fall back to original
    require_once(dirname(__FILE__) . '/demographics_original.php');
    exit;
}

// ── REDIRECT to Synapta Dashboard ────────────────────────
// Use HTML meta-refresh so it works inside OpenEMR's tab frame
// (header() redirect can cause issues inside iframes/tabs)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($dashboardUrl); ?>">
  <title>Loading dashboard…</title>
  <style>
    html, body {
      margin: 0; padding: 0;
      background: #0F1117;
      display: flex; align-items: center; justify-content: center;
      height: 100vh;
      font-family: system-ui, sans-serif;
    }
    .wrap { text-align: center; }
    .logo { font-size: 24px; font-weight: 600; margin-bottom: 14px; }
    .syn  { color: #5DCAA5; }
    .apta { color: #AFA9EC; }
    .bar-wrap { width: 180px; height: 3px; background: rgba(255,255,255,.1);
                border-radius: 99px; overflow: hidden; margin: 0 auto; }
    .bar { height: 100%; background: linear-gradient(90deg,#1D9E75,#5DCAA5);
           animation: load 1.2s ease-in-out infinite; }
    @keyframes load {
      0%  { width:0%;   margin-left:0; }
      50% { width:70%;  margin-left:15%; }
      100%{ width:0%;   margin-left:100%; }
    }
    p { color: rgba(255,255,255,.3); font-size: 12px; margin-top: 12px; }
    a { color: #5DCAA5; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="logo"><span class="syn">Syn</span><span class="apta">apta</span></div>
    <div class="bar-wrap"><div class="bar"></div></div>
    <p>Loading patient dashboard…<br>
       <a href="<?php echo htmlspecialchars($dashboardUrl); ?>">
         Click here if not redirected
       </a>
    </p>
  </div>
   <script>
//     window.location.replace(
//     "<?php echo htmlspecialchars($dashboardUrl); ?>"
// );
window.location.replace(<?= json_encode($dashboardUrl) ?>);
  </script>
</body>
</html>