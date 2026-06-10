<?php

namespace Clinic\OeModuleAmbientDocs;

/**
 * Module Bootstrap
 *
 * Handles:
 *  - Detecting which OpenEMR page we are on
 *  - Injecting CSS + JS widget into encounter pages
 *  - Passing encounter/patient context to the widget
 */
class Bootstrap
{
    // Pages where we inject the widget
    // These are URL patterns from OpenEMR's interface directory
    private const ENCOUNTER_PAGE_PATTERNS = [
        '/interface/forms/soap/',
        '/interface/forms/SOAP/',
        '/interface/patient_file/encounter/',
        '/interface/forms/note/',
        '/interface/forms/progress_note/',
    ];

    public function subscribeToEvents(): void
    {
        // Step 1: Always register globals first
        $this->registerGlobals();

        // Step 2: Only inject widget on encounter/note pages
        if ($this->isEncounterPage()) {
            $this->injectWidget();
        }
    }

    // ── Private: Detect if current page needs the widget ─────────────────────

    private function isEncounterPage(): bool
    {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';

        foreach (self::ENCOUNTER_PAGE_PATTERNS as $pattern) {
            if (str_contains($currentUri, $pattern)) {
                return true;
            }
        }

        return false;
    }

    // ── Private: Inject widget CSS + JS into the page ────────────────────────

    private function injectWidget(): void
    {
        $moduleWebRoot = $GLOBALS['webroot']
            . '/interface/modules/custom_modules/oe-module-ambient-docs/public';

        // ── Get encounter and patient context ─────────────────
        // OpenEMR sets these as globals on encounter pages
        $encounterId  = (int)($GLOBALS['encounter']          ?? 0);
        $patientId    = (int)($GLOBALS['pid']                ?? 0);
        $providerName = $GLOBALS['authUserFirstName']        ?? '';

        // ── Output CSS ────────────────────────────────────────
        echo '<link rel="stylesheet" href="'
            . attr($moduleWebRoot . '/css/ambient-recorder.css')
            . '">' . "\n";

        // ── Output JS config BEFORE widget JS loads ───────────
        // This passes PHP variables from OpenEMR into JavaScript
        // so the widget knows which encounter/patient it is on
        echo '<script>' . "\n";
        echo 'window.ambientDocConfig = {' . "\n";
        echo '    encounterId:  ' . $encounterId  . ',' . "\n";
        echo '    patientId:    ' . $patientId    . ',' . "\n";
        echo '    providerName: "' . addslashes($providerName) . '",' . "\n";
        echo '    apiUrl: "'
            . addslashes($moduleWebRoot . '/api.php')
            . '",' . "\n";

        // ── SOAP field selectors ──────────────────────────────
        // We added id= attributes to the Twig template (soap_form.twig)
        // so we can reliably target each textarea by ID
        echo '    soapFields: {' . "\n";
        echo '        chief_complaint: null,' . "\n";
        // No chief_complaint textarea in OpenEMR SOAP form.
        // We prepend it into subjective automatically.
        echo '        subjective:  "#soap_subjective",' . "\n";
        echo '        objective:   "#soap_objective",'  . "\n";
        echo '        assessment:  "#soap_assessment",' . "\n";
        echo '        plan:        "#soap_plan",'       . "\n";
        echo '    },' . "\n";
        echo '};' . "\n";
        echo '</script>' . "\n";

        // ── Output widget JavaScript ──────────────────────────
        echo '<script src="'
            . attr($moduleWebRoot . '/js/ambient-recorder.js')
            . '"></script>' . "\n";
    }

    // ── Private: Register module globals ─────────────────────────────────────

    private function registerGlobals(): void
    {
        // Register module web root path for use in templates
        // This makes it easy to reference JS/CSS files from anywhere
        $GLOBALS['ambient_docs_web_root'] = $GLOBALS['webroot']
            . '/interface/modules/custom_modules/oe-module-ambient-docs/public';

        $GLOBALS['ambient_docs_module_root'] = __DIR__ . '/..';
    }
}