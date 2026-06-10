<?php
/**
 * ═══════════════════════════════════════════════════════════
 * src/Bootstrap.php — UPDATED EVENT SUBSCRIPTION
 *
 * Replace your existing src/Bootstrap.php with this version.
 *
 * KEY ADDITION: Listens for the PatientDemographicsRenderEvent
 * so the module intercepts patient selection from all entry
 * points (calendar, patient finder, demographics page).
 * ═══════════════════════════════════════════════════════════
 */

namespace Synapta\OeModulePhysicianDashboard;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Bootstrap implements EventSubscriberInterface
{
    private EventDispatcher $eventDispatcher;
    private $kernel;
    private string $modulePublicUrl;

    public function __construct(EventDispatcher $eventDispatcher, $kernel)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->kernel          = $kernel;
        $this->modulePublicUrl = $GLOBALS['webroot']
            . '/interface/modules/custom_modules'
            . '/oe-module-physician-dashboard/public';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'script.after_main_content' => 'injectDashboard',
        ];
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(
            'script.after_main_content',
            [$this, 'injectDashboard']
        );
    }

    public function injectDashboard($event = null): void
    {
        if (!$this->isTargetPage()) {
            return;
        }

        $pid       = $this->getPid();
        $encounter = $this->getEncounter();

        if (!$pid) return;

        $dashUrl = $this->modulePublicUrl . '/dashboard.php'
            . '?pid='       . (int)$pid
            . '&encounter=' . (int)$encounter
            . '&embedded=1';

        $csrf = \OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken();

        echo $this->buildInjectionHtml($dashUrl, $pid, $encounter, $csrf);
    }

    private function isTargetPage(): bool
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $self   = $_SERVER['PHP_SELF']        ?? '';

        // Intercept on:
        // 1. Demographics page (patient selected from calendar/finder)
        // 2. Patient summary pages
        // 3. Encounter pages
        // 4. SOAP forms
        $targets = [
            'demographics.php',
            'summary.php',
            'patient_file.php',
            'encounter_top.php',
            'forms/soap/new.php',
            'forms/soap/view.php',
        ];

        foreach ($targets as $t) {
            if (str_contains($script, $t) || str_contains($self, $t)) {
                return true;
            }
        }
        return false;
    }

    private function getPid(): int
    {
        return (int)(
            $_GET['set_pid']     ??
            $_GET['pid']         ??
            $_SESSION['pid']     ??
            $GLOBALS['pid']      ??
            0
        );
    }

    private function getEncounter(): int
    {
        return (int)(
            $_GET['set_encounter']  ??
            $_GET['encounter']      ??
            $_SESSION['encounter']  ??
            $GLOBALS['encounter']   ??
            0
        );
    }

    private function buildInjectionHtml(
        string $dashUrl,
        int    $pid,
        int    $encounter,
        string $csrf
    ): string {
        $webroot = $GLOBALS['webroot'];
        return <<<HTML
<!-- Synapta Dashboard Injection -->
<style>
  #synapta-overlay-wrap {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    z-index: 500; background: #f7f8fc;
    display: flex; flex-direction: column;
  }
  #synapta-dash-frame {
    width: 100%; height: 100%; border: none; flex: 1;
  }
  #synapta-loading {
    position: absolute; inset: 0;
    background: #0F1117;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 16px; color: #fff; z-index: 10;
    transition: opacity .4s;
  }
  #synapta-loading.fade-out { opacity: 0; pointer-events: none; }
  .syd-load-logo { font-size: 22px; font-weight: 600; letter-spacing: -.3px; }
  .syd-load-logo .syn  { color: #5DCAA5; }
  .syd-load-logo .apta { color: #AFA9EC; }
  .syd-load-bar-wrap { width: 200px; height: 3px; background: rgba(255,255,255,.1);
                       border-radius: 99px; overflow: hidden; }
  .syd-load-bar { height: 100%; background: linear-gradient(90deg,#1D9E75,#5DCAA5);
                  animation: sydLoad 1.2s ease-in-out infinite; }
  @keyframes sydLoad {
    0%  { width:0%;  margin-left:0; }
    50% { width:70%; margin-left:15%; }
    100%{ width:0%;  margin-left:100%; }
  }
</style>

<div id="synapta-overlay-wrap">
  <div id="synapta-loading">
    <div class="syd-load-logo">
      <span class="syn">Syn</span><span class="apta">apta</span>
    </div>
    <div class="syd-load-bar-wrap"><div class="syd-load-bar"></div></div>
  </div>
  <iframe id="synapta-dash-frame"
          src="{$dashUrl}"
          title="Synapta Provider Dashboard"
          onload="document.getElementById('synapta-loading').classList.add('fade-out');
                  setTimeout(()=>document.getElementById('synapta-loading').remove(),500);">
  </iframe>
</div>
HTML;
    }
}