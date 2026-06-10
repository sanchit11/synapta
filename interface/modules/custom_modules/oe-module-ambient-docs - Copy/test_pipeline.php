<?php
/**
 * Full Pipeline Test — Day 5
 * Tests: PHI De-ID → NoteStructuring → Re-identification
 *
 * Run: http://localhost/openemr/interface/modules/
 *      custom_modules/oe-module-ambient-docs/test_pipeline.php
 *
 * DELETE this file before deploying to Azure VM
 */

require_once 'C:/xampp/htdocs/synaptaEMR-old/interface/globals.php';
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Clinic\OeModuleAmbientDocs\Service\PhiDeidentifyService;
use Clinic\OeModuleAmbientDocs\Service\NoteStructuringService;

echo "<h2>Full AI Pipeline Test</h2><pre>";

// ── Sample medical transcript with PHI ───────────────────────
$transcript = "
[PROVIDER]: Good morning. Can you tell me what brings you in today?

[PATIENT]: Hi Dr. Sarah Johnson. I'm John Smith, I've been having
chest pain for the past 3 days. It started on Monday.

[PROVIDER]: I see. On a scale of 1 to 10, how bad is the pain?

[PATIENT]: About a 6 out of 10. It gets worse when I walk upstairs.

[PROVIDER]: Any shortness of breath or dizziness?

[PATIENT]: Yes, some shortness of breath when it happens.

[PROVIDER]: Let me check your vitals. Blood pressure is 145 over 90.
Heart rate 88. Oxygen saturation 97 percent. Temperature 98.6.

[PROVIDER]: On examination, heart sounds are regular. Lungs are clear
to auscultation. No peripheral edema.

[PROVIDER]: Given your symptoms and blood pressure, I think we are
looking at hypertension with possible angina. I am going to order
an EKG and some blood work including troponin and BMP.

[PATIENT]: Should I be worried?

[PROVIDER]: We will know more after the tests. I am also going to
start you on Lisinopril 10mg once daily for blood pressure.
Please follow up in one week or sooner if chest pain worsens.
Go to the emergency room immediately if pain becomes severe.
";

echo "STEP 1: Original transcript with PHI\n";
echo str_repeat('-', 50) . "\n";
echo trim($transcript) . "\n\n";

// ── Step 1: De-identify PHI ───────────────────────────────────
echo "STEP 2: PHI De-identification\n";
echo str_repeat('-', 50) . "\n";

$phi       = new PhiDeidentifyService();
$sessionId = 'test_pipeline_' . time();
$deIdResult = $phi->deidentify($transcript, $sessionId);

echo "Method: " . $deIdResult['method'] . "\n";
echo "PHI tokens replaced: " . $deIdResult['token_count'] . "\n\n";
echo "De-identified text:\n";
echo $deIdResult['deidentified_text'] . "\n\n";

// ── Step 2: Structure note via GPT-4o ────────────────────────
echo "STEP 3: GPT-4o SOAP Note Generation\n";
echo str_repeat('-', 50) . "\n";
echo "Sending de-identified transcript to Azure GPT-4o...\n\n";

$noteService = new NoteStructuringService();
$aiResult    = $noteService->structure(
    $deIdResult['deidentified_text'],
    [] // No diarization array for this test
);

if ($aiResult['error']) {
    echo "❌ ERROR: " . $aiResult['error'] . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check AZURE_OPENAI_ENDPOINT in .env\n";
    echo "2. Check AZURE_OPENAI_KEY in .env\n";
    echo "3. Confirm gpt-4o deployment exists in Azure OpenAI Studio\n";
    exit;
}

echo "✅ Note generated successfully!\n";
echo "Model: " . $aiResult['model_version'] . "\n";
echo "Processing time: " . $aiResult['processing_ms'] . "ms\n\n";

// ── Step 3: Re-identify PHI ───────────────────────────────────
echo "STEP 4: Re-inserting PHI into note\n";
echo str_repeat('-', 50) . "\n";

$soapJson         = json_encode($aiResult['soap_note'], JSON_PRETTY_PRINT);
$reidentifiedJson = $phi->reidentify($soapJson, $sessionId);
$finalNote        = json_decode($reidentifiedJson, true);

$phi->clearSession($sessionId);

// ── Display final SOAP note ───────────────────────────────────
echo "STEP 5: Final SOAP Note (with real patient data)\n";
echo str_repeat('-', 50) . "\n\n";

echo "CHIEF COMPLAINT:\n";
echo ($finalNote['chief_complaint'] ?? 'N/A') . "\n\n";

echo "SUBJECTIVE:\n";
echo "HPI: " . ($finalNote['subjective']['hpi'] ?? 'N/A') . "\n";
echo "Medications: " . implode(', ', $finalNote['subjective']['medications'] ?? []) . "\n";
echo "Allergies: "   . implode(', ', $finalNote['subjective']['allergies']   ?? []) . "\n\n";

echo "OBJECTIVE:\n";
echo "Vitals: "      . ($finalNote['objective']['vital_signs']    ?? 'N/A') . "\n";
echo "Exam: "        . ($finalNote['objective']['physical_exam']  ?? 'N/A') . "\n\n";

echo "ASSESSMENT:\n";
foreach ($finalNote['assessment']['diagnoses'] ?? [] as $dx) {
    echo "- " . ($dx['description'] ?? '') . " [" . ($dx['icd10'] ?? '') . "]\n";
}
echo "\n";

echo "PLAN:\n";
foreach ($finalNote['plan']['treatments'] ?? [] as $tx) {
    echo "- " . $tx . "\n";
}
foreach ($finalNote['plan']['medications'] ?? [] as $med) {
    echo "- " . ($med['drug'] ?? '') . " "
              . ($med['dose'] ?? '') . " "
              . ($med['frequency'] ?? '') . "\n";
}
echo "Follow-up: " . ($finalNote['plan']['follow_up'] ?? 'N/A') . "\n\n";

echo "CONFIDENCE SCORES:\n";
foreach ($finalNote['confidence_scores'] ?? [] as $section => $score) {
    $bar      = str_repeat('█', (int)($score * 10));
    $percent  = (int)($score * 100);
    echo "  {$section}: {$bar} {$percent}%\n";
}

echo "\nICD-10 CODES EXTRACTED:\n";
foreach ($finalNote['extracted_codes']['icd10'] ?? [] as $code) {
    echo "  - {$code}\n";
}

echo "\nVISIT TYPE: " . ($finalNote['visit_type'] ?? 'unknown') . "\n";

if (!empty($finalNote['note_quality_flags'])) {
    echo "\n⚠️ QUALITY FLAGS:\n";
    foreach ($finalNote['note_quality_flags'] as $flag) {
        echo "  - {$flag}\n";
    }
}

echo "\n✅ FULL PIPELINE TEST COMPLETE\n";
echo "All 3 services working: PHI De-ID → GPT-4o → Re-identification\n";
echo "</pre>";