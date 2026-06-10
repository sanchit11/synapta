<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  HOW TO ADD THE "Request Early Access" BUTTON TO YOUR Home.php
 * ═══════════════════════════════════════════════════════════════════
 *
 * This file is a REFERENCE SNIPPET — not a standalone page.
 * Copy the relevant section into your existing Home.php file.
 *
 * FILE:  openemr/interface/synapta/Home.php
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── In your Home.php <head>, make sure globals is loaded: ───────────────────
//   require_once __DIR__ . '/../../../globals.php';
// ────────────────────────────────────────────────────────────────────────────


// ════════════════════════════════════════════════════════════════════
//  OPTION A — Open EarlyAccess.php in the SAME TAB (full page nav)
//  Simplest approach. User clicks → new page loads → back button returns.
// ════════════════════════════════════════════════════════════════════
?>

<!-- Paste this button wherever you want it on Home.php -->
<a
  href="<?php echo $GLOBALS['webroot']; ?>/interface/synapta/EarlyAccess.php"
  class="synapta-cta-btn"
>
  <span class="synapta-cta-dot"></span>
  Request Early Access
</a>

<!-- CSS for the button — add to Home.php <head> or your stylesheet -->
<style>
.synapta-cta-btn {
  display:         inline-flex;
  align-items:     center;
  gap:             10px;
  padding:         14px 32px;
  border-radius:   10px;
  background:      linear-gradient(135deg, #0C7A87, #1A9DAD);
  color:           #fff;
  font-family:     'Outfit', sans-serif;
  font-size:       15px;
  font-weight:     600;
  text-decoration: none;
  letter-spacing:  0.3px;
  cursor:          pointer;
  box-shadow:      0 4px 20px rgba(12,122,135,0.35);
  transition:      box-shadow 0.2s, transform 0.15s;
  white-space:     nowrap;
}
.synapta-cta-btn:hover {
  box-shadow:  0 6px 28px rgba(12,122,135,0.48);
  transform:   translateY(-2px);
  color:       #fff;
  text-decoration: none;
}
.synapta-cta-btn:active { transform: translateY(0); }

/* Pulsing live dot */
.synapta-cta-dot {
  display:       inline-block;
  width:         8px;
  height:        8px;
  border-radius: 50%;
  background:    #fff;
  flex-shrink:   0;
  animation:     synapta-pulse 2.2s infinite;
}
@keyframes synapta-pulse {
  0%,100% { box-shadow: 0 0 0 0   rgba(255,255,255,0.6); }
  50%     { box-shadow: 0 0 0 6px rgba(255,255,255,0);   }
}
</style>

<?php
// ════════════════════════════════════════════════════════════════════
//  OPTION B — Open EarlyAccess.php in a NEW TAB
//  Good when you want users to keep Home.php open.
// ════════════════════════════════════════════════════════════════════
?>

<!-- Change target="_blank" to open in new tab -->
<a
  href="<?php echo $GLOBALS['webroot']; ?>/interface/synapta/EarlyAccess.php"
  target="_blank"
  rel="noopener noreferrer"
  class="synapta-cta-btn"
>
  <span class="synapta-cta-dot"></span>
  Request Early Access
</a>

<?php
// ════════════════════════════════════════════════════════════════════
//  OPTION C — Open EarlyAccess.php in a full-screen MODAL overlay
//  Best visual experience. Keeps Home.php behind the form.
//  Uses the native <dialog> element (modern browsers; IE not needed).
// ════════════════════════════════════════════════════════════════════
?>

<!-- 1. Button — paste in your Home.php nav/hero area -->
<button type="button" class="synapta-cta-btn" onclick="synaptaOpen()">
  <span class="synapta-cta-dot"></span>
  Request Early Access
</button>

<!-- 2. Dialog — paste just before </body> in Home.php -->
<dialog id="synapta-dialog" style="
  border:none;padding:0;background:transparent;
  width:100vw;max-width:100vw;height:100vh;max-height:100vh;
  position:fixed;inset:0;z-index:9999;overflow:hidden;
">
  <!-- Close X -->
  <button onclick="synaptaClose()" style="
    position:absolute;top:16px;right:16px;z-index:10001;
    width:36px;height:36px;border-radius:50%;border:none;
    background:rgba(255,255,255,0.15);backdrop-filter:blur(6px);
    color:#fff;font-size:18px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
  ">✕</button>

  <!-- iframe loads the full EarlyAccess.php page inside the dialog -->
  <iframe
    id="synapta-iframe"
    src="about:blank"
    data-src="<?php echo $GLOBALS['webroot']; ?>/interface/synapta/EarlyAccess.php"
    style="display:block;width:100%;height:100%;border:none;"
    sandbox="allow-scripts allow-forms allow-same-origin"
    title="Early Access Sign-up"
  ></iframe>
</dialog>

<style>
/* Dim background behind the dialog */
#synapta-dialog::backdrop {
  background: rgba(10, 31, 46, 0.75);
  backdrop-filter: blur(4px);
}
</style>

<script>
function synaptaOpen() {
  var dlg    = document.getElementById('synapta-dialog');
  var iframe = document.getElementById('synapta-iframe');
  // Lazy-load: only fetch the page when first opened
  if (!iframe.src || iframe.src === 'about:blank') {
    iframe.src = iframe.dataset.src;
  }
  dlg.showModal();
  document.body.style.overflow = 'hidden';
}
function synaptaClose() {
  document.getElementById('synapta-dialog').close();
  document.body.style.overflow = '';
}
// Close on backdrop click
document.getElementById('synapta-dialog').addEventListener('click', function(e) {
  if (e.target === this) synaptaClose();
});
</script>
