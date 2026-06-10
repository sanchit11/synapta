<?php
/**
 * Widget Test Page — Day 6
 * Standalone page to test the recording widget
 * WITHOUT needing to open a full OpenEMR encounter.
 *
 * Run: http://localhost/openemr/interface/modules/
 *      custom_modules/oe-module-ambient-docs/test_widget.php
 *
 * DELETE this file before deploying to Azure VM
 */

require_once 'C:/xampp/htdocs/synaptaEMR-old/interface/globals.php';

// Simulate OpenEMR session for testing
if (!isset($_SESSION['authUser'])) {
    $_SESSION['authUser']   = 'test_provider';
    $_SESSION['authUserID'] = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ambient Docs — Widget Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #F3F4F6;
            padding: 40px;
            color: #1F2937;
        }
        .test-page {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 { color: #1E40AF; margin-bottom: 6px; }
        .subtitle { color: #6B7280; margin-bottom: 30px; }

        /* Simulated OpenEMR SOAP form */
        .fake-soap-form {
            background: white;
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .fake-soap-form h2 {
            font-size: 16px;
            color: #374151;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #E5E7EB;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #6B7280;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .form-group textarea {
            width: 100%;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
            box-sizing: border-box;
            min-height: 70px;
            transition: border-color 0.2s;
        }
        .form-group textarea:focus {
            outline: none;
            border-color: #2563EB;
        }

        .instructions {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .instructions h3 {
            margin: 0 0 8px;
            color: #1E40AF;
        }
        .instructions ol {
            margin: 0;
            padding-left: 18px;
        }
        .instructions li { margin-bottom: 4px; }

        .context-info {
            background: white;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #6B7280;
            border: 1px solid #E5E7EB;
        }
        .context-info span { font-weight: 600; color: #374151; }
    </style>
</head>
<body>
<div class="test-page">

    <h1>🎙️ Ambient Documentation Widget Test</h1>
    <p class="subtitle">Day 6 test page — widget functionality without full OpenEMR encounter</p>

    <!-- Context info -->
    <div class="context-info">
        Simulated context:
        Encounter ID: <span>1</span> |
        Patient ID: <span>1</span> |
        Provider: <span><?= htmlspecialchars($_SESSION['authUser']) ?></span>
    </div>

    <!-- Instructions -->
    <div class="instructions">
        <h3>How to test:</h3>
        <ol>
            <li>Click <strong>🎙️ Start Recording</strong> in the widget (bottom right)</li>
            <li>Allow microphone access when browser asks</li>
            <li>Speak a sample clinical scenario (see below)</li>
            <li>Click <strong>⏹ Stop</strong> after 30-60 seconds</li>
            <li>Wait for AI to generate SOAP note (~10-20 seconds)</li>
            <li>Review the note sections (blue = AI generated)</li>
            <li>Accept/reject sections, then click <strong>📋 Insert into Note</strong></li>
            <li>Watch the SOAP form fields below get populated!</li>
        </ol>
    </div>

    <!-- Sample script for testing -->
    <div class="instructions" style="background:#F0FDF4;border-color:#A7F3D0;">
        <h3>📢 Sample script to read aloud:</h3>
        <p style="margin:0;line-height:1.7;font-style:italic;">
            "Patient is a 55 year old male presenting with chest pain for 2 days.
            Pain is 7 out of 10, sharp, radiating to the left arm.
            Associated shortness of breath and sweating.
            Blood pressure is 160 over 95. Heart rate 92. Temperature 98.6.
            Heart sounds regular, lungs clear to auscultation.
            Assessment: hypertension and possible acute coronary syndrome.
            Plan: EKG, troponin levels, aspirin 325 milligrams, cardiology referral.
            Follow up in 24 hours or go to emergency room if pain worsens."
        </p>
    </div>

    <!-- Simulated OpenEMR SOAP form -->
    <!-- These IDs match what the widget looks for -->
    <div class="fake-soap-form">
        <h2>📋 Encounter Note (SOAP Form)</h2>
        <div class="form-group">
            <label>Chief Complaint</label>
            <textarea id="form_cc"
                      placeholder="Chief complaint will appear here after inserting note..."></textarea>
        </div>
        <div class="form-group">
            <label>Subjective1</label>
            <textarea id="form_subjective" name="subjective" rows="4" 
                      placeholder="Subjective section will appear here..."></textarea>
        </div>
        <div class="form-group">
            <label>Objective</label>
            <textarea id="form_objective" name="objective" rows="4"
                      placeholder="Objective section will appear here..."></textarea>
        </div>
        <div class="form-group">
            <label>Assessment</label>
            <textarea id="form_assessment" name="assessment" rows="4"
                      placeholder="Assessment section will appear here..."></textarea>
        </div>
        <div class="form-group">
            <label>Plan</label>
            <textarea id="form_plan" name="plan" rows="4"
                      placeholder="Plan section will appear here..."></textarea>
        </div>
    </div>

</div>

<!-- Set context variables for the widget -->
<script>
    // These simulate OpenEMR's globals on encounter pages
    window.encounter = 1;
    window.pid       = 1;
</script>

<!-- Load widget CSS and JS -->
<link rel="stylesheet"
      href="/openemr/interface/modules/custom_modules/oe-module-ambient-docs/public/css/ambient-recorder.css">
<script
    src="/openemr/interface/modules/custom_modules/oe-module-ambient-docs/public/js/ambient-recorder.js">
</script>

</body>
</html>