<?php
/**
 * PHI De-identification Test
 * Run: http://localhost/openemr/interface/modules/
 *      custom_modules/oe-module-ambient-docs/test_phi.php
 * DELETE this file before deploying to Azure VM
 */

require_once 'C:/xampp/htdocs/synaptaEMR-old/interface/globals.php';
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Clinic\OeModuleAmbientDocs\Service\PhiDeidentifyService;

echo "<h2>PHI De-identification Test</h2><pre>";

// ── Check credentials ─────────────────────────────────────────
echo "AZURE_AI_LANGUAGE_ENDPOINT: ";
$endpoint = $_ENV['AZURE_AI_LANGUAGE_ENDPOINT'] ?? '';
echo ($endpoint ? '✅ ' . $endpoint : '❌ NOT SET') . "\n";

echo "AZURE_AI_LANGUAGE_KEY: ";
$key = $_ENV['AZURE_AI_LANGUAGE_KEY'] ?? '';
echo (strlen($key) > 10 ? '✅ Set (' . strlen($key) . ' chars)' : '❌ NOT SET') . "\n\n";

// ── Test text with PHI ────────────────────────────────────────
$testText = "Patient John Smith, date of birth January 15, 1980, "
          . "MRN 123456, called from 555-867-5309. "
          . "Email: john.smith@email.com. "
          . "Lives at 123 Main Street, Springfield. "
          . "SSN 123-45-6789. "
          . "Patient is a 44-year-old male presenting with chest pain. "
          . "Provider: Dr. Sarah Johnson reviewed the case.";

echo "ORIGINAL TEXT:\n";
echo $testText . "\n\n";

// ── Run de-identification ─────────────────────────────────────
$phi     = new PhiDeidentifyService();
$result  = $phi->deidentify($testText, 'test_session_001');

echo "DE-IDENTIFIED TEXT (method: {$result['method']}):\n";
echo $result['deidentified_text'] . "\n\n";

echo "PHI TOKENS FOUND: " . $result['token_count'] . "\n\n";

if ($result['error']) {
    echo "❌ ERROR: " . $result['error'] . "\n\n";
}

// ── Test re-identification ────────────────────────────────────
echo "RE-IDENTIFIED TEXT (after GPT-4o returns note):\n";
$restored = $phi->reidentify($result['deidentified_text'], 'test_session_001');
echo $restored . "\n\n";

// ── Verify round-trip ─────────────────────────────────────────
$roundTripOk = ($restored === $testText);
echo "ROUND-TRIP TEST (original === restored): ";
echo ($roundTripOk ? "✅ PASSED" : "⚠️ Some differences (check above)") . "\n\n";

// ── Verify PHI is not in de-identified text ───────────────────
echo "PHI REMOVAL VERIFICATION:\n";
$phiToCheck = [
    'John Smith'      => 'Patient name',
    '1980'            => 'Birth year',
    '123456'          => 'MRN',
    '555-867-5309'    => 'Phone',
    'john.smith'      => 'Email',
    '123 Main Street' => 'Address',
    '123-45-6789'     => 'SSN',
    'Sarah Johnson'   => 'Provider name',
];

$allRemoved = true;
foreach ($phiToCheck as $phi_item => $label) {
    $found = str_contains($result['deidentified_text'], $phi_item);
    echo ($found ? "❌ STILL PRESENT" : "✅ Removed") . " — {$label}: '{$phi_item}'\n";
    if ($found) $allRemoved = false;
}

echo "\n";
echo ($allRemoved
    ? "✅ ALL PHI REMOVED — Safe to send to GPT-4o\n"
    : "⚠️ SOME PHI REMAINS — Review de-identification rules\n");

// ── Clean up ──────────────────────────────────────────────────
$phi->clearSession('test_session_001');
echo "\nToken store cleared for test session.\n";
echo "</pre>";