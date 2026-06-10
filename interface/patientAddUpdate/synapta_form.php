<?php
/**
 * Synapta Patient Intake Form - Add & Update Patient Data
 * Supports both new patient creation and editing existing patients
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 */

// Allow public access without authentication
$ignoreAuth = true;

require_once("../globals.php");
require_once("$srcdir/options.inc.php");

// Check if PID is provided in URL for editing
$edit_pid = isset($_GET['pid']) ? intval($_GET['pid']) : null;
$form_mode = $edit_pid ? 'edit' : 'add';

 $insurancei = getInsuranceProviders();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title><?php echo $form_mode === 'edit' ? 'Synapta — Update Patient Profile' : 'Synapta — Patient Intake Form'; ?>
    </title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Outfit:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <style>
    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

 

    :root {
        --teal: #1D9E75;
        --teal-d: #0F6E56;
        --teal-deep: #085041;
        --teal-l: #E1F5EE;
        --teal-mid: #5DCAA5;
        --slate: #1A2030;
        --slate-l: #2A3142;
        --navy: #0F1117;
        --purple: #534AB7;
        --purple-mid: #7F77DD;
        --purple-l: #EEEDFE;
        --gold: #C8852A;
        --gold-l: #FDF3E6;
        --red: #A32D2D;
        --red-l: #FBEAEA;
        --amber: #C77A0A;
        --ink: #1a1a1a;
        --body: #444441;
        --muted: #888780;
        --border: #D3D1C7;
        --border-l: #F1EFE8;
        --surface: #FAFAF7;
        --card: #FFFFFF;
        --sans: 'Outfit', system-ui, sans-serif;
        --serif: 'DM Serif Display', Georgia, serif;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: var(--sans);
        background: var(--surface);
        color: var(--ink);
        min-height: 100vh;
    }

    /* ── TOP NAV ── */
    .topbar {
        background: var(--navy);
        border-bottom: 2px solid rgba(29, 158, 117, .3);
        position: sticky;
        top: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        padding: 0 28px;
        height: 64px;
        gap: 16px;
    }

    .logo-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logo-hex {
        width: 36px;
        height: 36px;
    }

    .logo-text {
        font-family: var(--serif);
        font-size: 22px;
        letter-spacing: -.3px;
    }

    .logo-syn {
        color: var(--teal-mid);
    }

    .logo-apta {
        color: var(--purple-mid);
    }

    .topbar-title {
        font-size: 13px;
        color: rgba(255, 255, 255, .45);
        margin-left: 8px;
        padding-left: 16px;
        border-left: 1px solid rgba(255, 255, 255, .1);
    }

    .topbar-right {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .patient-badge {
        background: rgba(29, 158, 117, .15);
        border: 1px solid rgba(29, 158, 117, .3);
        color: var(--teal-mid);
        font-size: 12px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
    }

    .save-btn {
        background: var(--teal);
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-family: var(--sans);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }

    .save-btn:hover {
        background: var(--teal-d);
    }

    /* ── PROGRESS BAR ── */
    .progress-wrap {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        padding: 16px 28px;
        position: sticky;
        top: 64px;
        z-index: 90;
    }

    .progress-steps {
        display: flex;
        gap: 0;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .progress-steps::-webkit-scrollbar {
        display: none;
    }

    .step-item {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 0;
    }

    .step-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 8px;
        border: none;
        background: transparent;
        font-family: var(--sans);
        font-size: 12px;
        font-weight: 500;
        color: var(--muted);
        cursor: pointer;
        transition: all .18s;
        white-space: nowrap;
        width: 100%;
    }

    .step-btn:hover {
        background: var(--teal-l);
        color: var(--teal);
    }

    .step-btn.active {
        background: var(--teal);
        color: #fff;
        font-weight: 600;
    }

    .step-btn.done {
        color: var(--teal-d);
    }

    .step-btn.done .step-num {
        background: var(--teal);
        color: #fff;
    }

    .step-num {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--border-l);
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all .18s;
    }

    .step-btn.active .step-num {
        background: rgba(255, 255, 255, .25);
        color: #fff;
    }

    .step-connector {
        width: 20px;
        height: 1px;
        background: var(--border);
        flex-shrink: 0;
        margin: 0 2px;
    }

    /* ── LAYOUT ── */
    .layout {
        display: grid;
        grid-template-columns: 220px 1fr;
        gap: 0;
        min-height: calc(100vh - 130px);
    }

    /* ── SIDEBAR NAV ── */
    .side-nav {
        background: var(--card);
        border-right: 1px solid var(--border);
        padding: 20px 0;
        position: sticky;
        top: 130px;
        height: calc(100vh - 130px);
        overflow-y: auto;
    }

    .nav-section-label {
        font-size: 10px;
        font-weight: 700;
        color: var(--muted);
        letter-spacing: .08em;
        text-transform: uppercase;
        padding: 12px 16px 4px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 16px;
        cursor: pointer;
        transition: all .15s;
        border-left: 3px solid transparent;
        font-size: 13px;
        color: var(--body);
    }

    .nav-item:hover {
        background: var(--teal-l);
        color: var(--teal);
    }

    .nav-item.active {
        background: var(--teal-l);
        color: var(--teal);
        border-left-color: var(--teal);
        font-weight: 600;
    }

    .nav-item.complete {
        color: var(--teal-d);
    }

    .nav-icon {
        font-size: 15px;
        width: 18px;
        text-align: center;
        flex-shrink: 0;
    }

    .nav-check {
        margin-left: auto;
        color: var(--teal);
        font-size: 12px;
    }

    /* ── MAIN CONTENT ── */
    .main-content {
        padding: 28px 32px;
        overflow-y: auto;
        max-height: calc(100vh - 130px);
    }

    .section-page {
        display: none;
    }

    .section-page.active {
        display: block;
    }

    /* ── SECTION HEADER ── */
    .section-header {
        margin-bottom: 24px;
    }

    .section-tag {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--teal);
        margin-bottom: 6px;
    }

    .section-title {
        font-family: var(--serif);
        font-size: 28px;
        color: var(--ink);
        line-height: 1.2;
    }

    .section-desc {
        font-size: 13px;
        color: var(--muted);
        margin-top: 6px;
        line-height: 1.5;
    }

    .section-divider {
        height: 2px;
        background: linear-gradient(to right, var(--teal), transparent);
        margin-top: 14px;
        border-radius: 2px;
    }

    /* ── FORM CARD ── */
    .form-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        margin-bottom: 18px;
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, var(--slate) 0%, var(--slate-l) 100%);
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header-icon {
        font-size: 16px;
        color: var(--teal-mid);
    }

    .card-header-title {
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        letter-spacing: .02em;
    }

    .card-header-sub {
        font-size: 11px;
        color: rgba(255, 255, 255, .45);
        margin-left: auto;
    }

    .card-body {
        padding: 20px;
    }

    /* AI badge */
    .ai-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: var(--purple-l);
        color: var(--purple);
        border: 1px solid rgba(83, 74, 183, .2);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .04em;
        padding: 3px 8px;
        border-radius: 20px;
        margin-left: 8px;
    }

    /* ── FORM GRID ── */
    .form-grid {
        display: grid;
        gap: 16px;
    }

    .g2 {
        grid-template-columns: 1fr 1fr;
    }

    .g3 {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .g4 {
        grid-template-columns: 1fr 1fr 1fr 1fr;
    }

    .g-auto {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .span2 {
        grid-column: span 2;
    }

    .span3 {
        grid-column: span 3;
    }

    .span4 {
        grid-column: span 4;
    }

    /* ── FIELD ── */
    .field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .field label {
        font-size: 12px;
        font-weight: 600;
        color: var(--body);
        letter-spacing: .01em;
    }

    .field label .req {
        color: var(--red);
        margin-left: 2px;
    }

    .field label .hint {
        font-weight: 400;
        color: var(--muted);
        font-size: 11px;
        margin-left: 4px;
    }

    .field input,
    .field select,
    .field textarea {
        padding: 9px 12px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-family: var(--sans);
        font-size: 13px;
        color: var(--ink);
        background: var(--surface);
        transition: border-color .15s, box-shadow .15s;
        width: 100%;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        outline: none;
        border-color: var(--teal);
        box-shadow: 0 0 0 3px rgba(29, 158, 117, .12);
        background: var(--card);
    }

    .field input::placeholder,
    .field textarea::placeholder {
        color: #BBBBB0;
        font-size: 12px;
    }

    .field textarea {
        resize: vertical;
        min-height: 80px;
    }

    .field select {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23888780'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
    }

    /* ── RADIO / CHECKBOX GROUP ── */
    .radio-group,
    .check-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .radio-pill,
    .check-pill {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border: 1.5px solid var(--border);
        border-radius: 20px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        color: var(--body);
        transition: all .15s;
        user-select: none;
    }

    .radio-pill:hover,
    .check-pill:hover {
        border-color: var(--teal);
        color: var(--teal);
        background: var(--teal-l);
    }

    .radio-pill input,
    .check-pill input {
        display: none;
    }

    .radio-pill.selected,
    .check-pill.selected {
        border-color: var(--teal);
        background: var(--teal-l);
        color: var(--teal);
        font-weight: 600;
    }

    /* ── DYNAMIC LISTS ── */
    .dynamic-list {
        border: 1.5px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
    }

    .dynamic-list-header {
        background: var(--surface);
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border);
    }

    .dynamic-list-title {
        font-size: 12px;
        font-weight: 600;
        color: var(--body);
    }

    .add-row-btn {
        display: flex;
        align-items: center;
        gap: 5px;
        background: var(--teal);
        color: #fff;
        border: none;
        padding: 5px 12px;
        border-radius: 6px;
        font-family: var(--sans);
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }

    .add-row-btn:hover {
        background: var(--teal-d);
    }

    .dynamic-list-empty {
        padding: 16px;
        text-align: center;
        color: var(--muted);
        font-size: 12px;
        font-style: italic;
    }

    .dynamic-row {
        display: grid;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-l);
        align-items: center;
    }

    .dynamic-row:last-child {
        border-bottom: none;
    }

    .remove-btn {
        background: var(--red-l);
        color: var(--red);
        border: none;
        width: 26px;
        height: 26px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* ── SEVERITY / INTENSITY ── */
    .severity-row {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .sev-btn {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 1.5px solid var(--border);
        background: var(--surface);
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        color: var(--muted);
        transition: all .15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sev-btn:hover {
        border-color: var(--teal);
    }

    .sev-btn.s1,
    .sev-btn.s2,
    .sev-btn.s3 {
        border-color: #4ade80;
        background: #f0fdf4;
        color: #16a34a;
    }

    .sev-btn.s4,
    .sev-btn.s5,
    .sev-btn.s6 {
        border-color: #fbbf24;
        background: #fffbeb;
        color: #d97706;
    }

    .sev-btn.s7,
    .sev-btn.s8 {
        border-color: #f97316;
        background: #fff7ed;
        color: #c2410c;
    }

    .sev-btn.s9,
    .sev-btn.s10 {
        border-color: #ef4444;
        background: #fef2f2;
        color: #dc2626;
    }

    .sev-btn.selected {
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
    }

    /* ── NOTICE BANNER ── */
    .notice {
        background: var(--gold-l);
        border: 1px solid rgba(200, 133, 42, .25);
        border-radius: 10px;
        padding: 12px 16px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 18px;
    }

    .notice-icon {
        font-size: 16px;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .notice-text {
        font-size: 12px;
        color: var(--amber);
        line-height: 1.5;
    }

    .notice-text strong {
        font-weight: 700;
        display: block;
        margin-bottom: 2px;
    }

    .hipaa-notice {
        background: var(--purple-l);
        border: 1px solid rgba(83, 74, 183, .2);
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 20px;
    }

    .hipaa-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--purple);
        margin-bottom: 6px;
    }

    .hipaa-text {
        font-size: 12px;
        color: var(--body);
        line-height: 1.6;
    }

    /* ── SECTION DIVIDER ── */
    .sub-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 20px 0 14px;
    }

    .sub-divider-line {
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    .sub-divider-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .07em;
        white-space: nowrap;
    }

    /* ── FOOTER ACTIONS ── */
    .form-footer {
        background: var(--card);
        border-top: 1px solid var(--border);
        padding: 16px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        bottom: 0;
        z-index: 80;
    }

    .footer-progress {
        font-size: 12px;
        color: var(--muted);
    }

    .footer-progress strong {
        color: var(--teal);
        font-weight: 700;
    }

    .footer-btns {
        display: flex;
        gap: 10px;
    }

    .btn-back {
        background: var(--surface);
        color: var(--body);
        border: 1.5px solid var(--border);
        padding: 10px 22px;
        border-radius: 9px;
        font-family: var(--sans);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all .15s;
    }

    .btn-back:hover {
        border-color: var(--teal);
        color: var(--teal);
    }

    .btn-next {
        background: var(--teal);
        color: #fff;
        border: none;
        padding: 10px 24px;
        border-radius: 9px;
        font-family: var(--sans);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-next:hover {
        background: var(--teal-d);
    }

    .btn-submit {
        background: var(--teal-deep);
        color: #fff;
        border: none;
        padding: 10px 28px;
        border-radius: 9px;
        font-family: var(--sans);
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s;
    }

    .btn-submit:hover {
        background: #052d25;
    }

    /* ── SIGNATURE ── */
    .sig-box {
        border: 2px dashed var(--border);
        border-radius: 10px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--muted);
        font-size: 13px;
        cursor: pointer;
        background: var(--surface);
        transition: all .15s;
    }

    .sig-box:hover {
        border-color: var(--teal);
        background: var(--teal-l);
        color: var(--teal);
    }

    /* ── TOOLTIP ── */
    .tooltip {
        position: relative;
        display: inline-block;
        cursor: help;
    }

    .tooltip .tip {
        visibility: hidden;
        background: var(--slate);
        color: #fff;
        font-size: 11px;
        padding: 6px 10px;
        border-radius: 6px;
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        white-space: nowrap;
        z-index: 200;
        opacity: 0;
        transition: opacity .15s;
    }

    .tooltip:hover .tip {
        visibility: visible;
        opacity: 1;
    }

    /* ── RESPONSIVE ── */
    @media(max-width:900px) {
        .layout {
            grid-template-columns: 1fr;
        }

        .side-nav {
            display: none;
        }

        .g2,
        .g3,
        .g4 {
            grid-template-columns: 1fr;
        }

        .span2,
        .span3,
        .span4 {
            grid-column: span 1;
        }
    }

    /* ── PRINT ── */
    @media print {

        .topbar,
        .side-nav,
        .progress-wrap,
        .form-footer {
            display: none !important;
        }

        .layout {
            grid-template-columns: 1fr;
        }

        .main-content {
            max-height: none;
            overflow: visible;
        }

        .section-page {
            display: block !important;
            page-break-before: always;
        }

        .section-page:first-child {
            page-break-before: auto;
        }
    }

    /* AI suggestion style */
    .ai-suggestion {
        background: var(--purple-l);
        border: 1px solid rgba(83, 74, 183, .15);
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 12px;
        color: var(--purple);
        margin-top: 6px;
        display: flex;
        gap: 8px;
        align-items: flex-start;
    }

    .ai-suggestion-icon {
        flex-shrink: 0;
        font-size: 14px;
    }

    /* Required legend */
    .required-legend {
        font-size: 11px;
        color: var(--muted);
        margin-bottom: 16px;
    }

    .required-legend span {
        color: var(--red);
    }
    </style>
</head>

<body>

    <!-- ── TOP BAR ── -->
    <!-- <div class="topbar">
  <div class="logo-wrap">
    <svg class="logo-hex" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg">
      <polygon points="40,8 68,24 68,56 40,72 12,56 12,24" fill="none" stroke="#1D9E75" stroke-width="2.5"/>
      <polygon points="40,18 58,28 58,52 40,62 22,52 22,28" fill="none" stroke="#534AB7" stroke-width="1.5" opacity=".6"/>
      <circle cx="40" cy="40" r="8" fill="#1D9E75"/>
      <circle cx="40" cy="40" r="4" fill="#085041"/>
      <line x1="40" y1="18" x2="40" y2="32" stroke="#5DCAA5" stroke-width="1.5"/>
      <line x1="40" y1="48" x2="40" y2="62" stroke="#5DCAA5" stroke-width="1.5"/>
      <line x1="22" y1="28" x2="34" y2="35" stroke="#7F77DD" stroke-width="1.5"/>
      <line x1="46" y1="45" x2="58" y2="52" stroke="#7F77DD" stroke-width="1.5"/>
      <line x1="58" y1="28" x2="46" y2="35" stroke="#5DCAA5" stroke-width="1.5"/>
      <line x1="34" y1="45" x2="22" y2="52" stroke="#5DCAA5" stroke-width="1.5"/>
    </svg>
    <div class="logo-text"><span class="logo-syn">Syn</span><span class="logo-apta">apta</span></div>
    <div class="topbar-title">Patient Intake Form</div>
  </div>
  <div class="topbar-right">
    <span class="patient-badge">🔒 HIPAA Secure</span>
    <button class="save-btn" onclick="saveProgress()">Save Progress</button>
  </div>
</div> -->

    <!-- ── PROGRESS STEPS ── -->
    <!-- <div class="progress-wrap">
  <div class="progress-steps" id="progressSteps">
    <div class="step-item"><button class="step-btn active" onclick="goToSection(0)"><span class="step-num">1</span>Who</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(1)"><span class="step-num">2</span>Contact</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(2)"><span class="step-num">3</span>Choices</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(3)"><span class="step-num">4</span>Employer</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(4)"><span class="step-num">5</span>Stats</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(5)"><span class="step-num">6</span>Insurance</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(6)"><span class="step-num">7</span>Symptoms</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(7)"><span class="step-num">8</span>Conditions</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(8)"><span class="step-num">9</span>Medications</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(9)"><span class="step-num">10</span>Allergies</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(10)"><span class="step-num">11</span>Surgical Hx</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(11)"><span class="step-num">12</span>Family Hx</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(12)"><span class="step-num">13</span>Social Hx</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(13)"><span class="step-num">14</span>Related</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(14)"><span class="step-num">15</span>Consent</button></div>
  </div>
</div> -->

    <!-- ── MAIN LAYOUT ── -->
    <div class="layout">

        <!-- Side Nav -->
        <nav class="side-nav">
            <div class="nav-section-label">Demographics</div>
            <div class="nav-item active" onclick="goToSection(0)"><span class="nav-icon">👤</span>Who (Identity)</div>
            <div class="nav-item" onclick="goToSection(1)"><span class="nav-icon">📍</span>Contact</div>
            <div class="nav-item" onclick="goToSection(2)"><span class="nav-icon">⚙️</span>Choices & Preferences</div>
            <div class="nav-item" onclick="goToSection(3)"><span class="nav-icon">💼</span>Employer</div>
            <div class="nav-item" onclick="goToSection(4)"><span class="nav-icon">📊</span>Stats & Social</div>
            <div class="nav-item" onclick="goToSection(5)"><span class="nav-icon">🏥</span>Insurance</div>
            <div class="nav-section-label">Clinical History</div>
            <div class="nav-item" onclick="goToSection(6)"><span class="nav-icon">🤒</span>Current Symptoms</div>
            <div class="nav-item" onclick="goToSection(7)"><span class="nav-icon">🩺</span>Medical Conditions</div>
            <div class="nav-item" onclick="goToSection(8)"><span class="nav-icon">💊</span>Current Medications</div>
            <div class="nav-item" onclick="goToSection(9)"><span class="nav-icon">⚠️</span>Allergies</div>
            <div class="nav-item" onclick="goToSection(10)"><span class="nav-icon">🔪</span>Surgical History</div>
            <div class="nav-item" onclick="goToSection(11)"><span class="nav-icon">👨‍👩‍👧</span>Family Health History
            </div>
            <div class="nav-item" onclick="goToSection(12)"><span class="nav-icon">🌿</span>Social History</div>
            <div class="nav-section-label">Final Steps</div>
            <div class="nav-item" onclick="goToSection(13)"><span class="nav-icon">👥</span>Related Persons</div>
            <div class="nav-item" onclick="goToSection(14)"><span class="nav-icon">✍️</span>Consent & Signature</div>
        </nav>

        <!-- ── MAIN CONTENT ── -->
        <main class="main-content" id="mainContent">
          <?php
            function renderListOptions($listId, $selected = '')
            {
                  $res = sqlStatement(
                      "SELECT option_id, title
                      FROM list_options
                      WHERE list_id = ?
                      AND activity = 1
                      ORDER BY seq",
                      [$listId]
                  );

                  while ($row = sqlFetchArray($res)) {
                      $sel = ($selected == $row['option_id']) ? 'selected' : '';
                      echo "<option value='" . attr($row['option_id']) . "' $sel>" .
                          text($row['title']) .
                          "</option>";
                  }
              }

          ?>

            <!-- DEBUG PANEL FOR TROUBLESHOOTING -->
            <div id="debugPanel"
                style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 10px; border-radius: 5px; display: none;">
                <h3 style="margin-top: 0; color: #856404;">🔧 DEBUG INFO (Press F12 for more details)</h3>
                <p><strong>Edit Mode:</strong> <span id="debugEditMode">Loading...</span></p>
                <p><strong>Patient PID:</strong> <span id="debugPID">Loading...</span></p>
                <p><strong>Page URL:</strong> <span id="debugURL">Loading...</span></p>
                <p style="margin-bottom: 0;"><strong>Status:</strong> <span id="debugStatus">Initializing...</span></p>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 0 — WHO (IDENTITY)
    ══════════════════════════════════════ -->
            <div class="section-page active" id="section-0">
                <div class="section-header">
                    <div class="section-tag">Section 1 of 15</div>
                    <div class="section-title">Who are you?</div>
                    <div class="section-desc">Legal identity and demographic information. Fields marked in <span
                            style="color:var(--red)">red</span> are required.</div>
                    <div class="section-divider"></div>
                </div>
                <div class="required-legend"><span>*</span> Required fields</div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">👤</span><span
                            class="card-header-title">Legal Name & Identity</span></div>
                    <div class="card-body">
                        <div class="form-grid g4" style="margin-bottom:16px;">
                            <div class="field"><label>Title</label><select name="title">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('titles'); ?>
                                    
                                </select></div>
                            <div class="field"><label>First Name <span class="req">*</span></label><input type="text"
                                    name="fname" placeholder="Legal first name" /></div>
                            <div class="field"><label>Middle Name</label><input type="text" name="mname"
                                    placeholder="Middle name" /></div>
                            <div class="field"><label>Last Name <span class="req">*</span></label><input type="text"
                                    name="lname" placeholder="Legal last name" /></div>
                        </div>
                        <div class="form-grid g3">
                            <div class="field"><label>Name Suffix</label>
                            <input type="text"
                                    name="suffix" placeholder="Name Suffix" />
                            
                            <!-- <select name="suffix">
                                    <option>-- None --</option>
                                    <option>Jr.</option>
                                    <option>Sr.</option>
                                    <option>II</option>
                                    <option>III</option>
                                    <option>IV</option>
                                </select> -->
                              </div>
                            <div class="field span2"><label>Preferred Name</label><input type="text"
                                    name="preferred_name" placeholder="e.g. nickname or chosen name" /></div>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Birth Name (if different)</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="form-grid g3">
                            <div class="field"><label>Birth First Name</label><input type="text" name="birth_fname"
                                    placeholder="Birth first name" /></div>
                            <div class="field"><label>Birth Middle Name</label><input type="text" name="birth_mname"
                                    placeholder="Birth middle name" /></div>
                            <div class="field"><label>Birth Last Name</label><input type="text" name="birth_lname"
                                    placeholder="Birth last name" /></div>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Key Demographics</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="form-grid g4">
                            <div class="field"><label>Date of Birth <span class="req">*</span></label><input type="date"
                                    name="DOB" /></div>
                            <div class="field"><label>Birth Sex <span class="req">*</span></label><select name="sex">
                                    <option>-- Select --</option>
                                     <?php renderListOptions('sex'); ?>
                                </select></div>
                            <div class="field"><label>Gender Identity</label><select name="gender_identity">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('gender_identity'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Pronouns</label><select name="pronoun">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('pronoun'); ?>
                                    
                                </select></div>

                              
                        </div>
                        <div class="form-grid g4" style="margin-top:16px;">
                            <div class="field"><label>Sexual Orientation</label><select name="sexual_orientation">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('sexual_orientation'); ?>
                                    
                                </select></div>
                                
                            <div class="field"><label>Sex (Administrative)</label><select name="sex_administrative">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('sex'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Marital Status</label>
                                <select name="marital_status">
                                    <option value="">-- Select --</option>
                                    <?php renderListOptions('marital'); ?>
                                </select>
                            </div>
                            <div class="field"><label>Social Security # <span class="hint">(last 4)</span></label><input
                                    type="text" name="ss" placeholder="XXX-XX-____" maxlength="11" /></div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🪪</span><span
                            class="card-header-title">Identification</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>License / State ID #</label><input type="text"
                                    name="drivers_license" placeholder="ID number" /></div>
                            <div class="field"><label>External Patient ID</label><input type="text" name="pubpid"
                                    placeholder="External system ID" /></div>
                            <div class="field"><label>Billing Note</label><input type="text" name="billing_note"
                                    placeholder="Note for billing staff" /></div>
                        </div>
                        <div class="form-grid g2" style="margin-top:16px;">
                            <div class="field"><label>Previous Names <span class="hint">(maiden, former legal
                                        names)</span></label><textarea name="name_history"
                                    placeholder="List any previous legal names..."></textarea></div>
                            <div class="field"><label>User Defined Fields</label>
                                <div class="form-grid g2">
                                  <input type="text" placeholder="Custom field 1" name="user_defined_field_1" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--sans);font-size:13px;" />
                                  <input
                                        type="text" placeholder="Custom field 2" name="user_defined_field_2"  style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--sans);font-size:13px;" />
                                        <input
                                        type="text" placeholder="Custom field 3" name="user_defined_field_3" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--sans);font-size:13px;" />
                                        <input
                                        type="text" placeholder="Custom field 4" name="user_defined_field_4"  style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--sans);font-size:13px;" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 1 — CONTACT
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-1">
                <div class="section-header">
                    <div class="section-tag">Section 2 of 15</div>
                    <div class="section-title">Contact Information</div>
                    <div class="section-desc">Home address, phone numbers, and emergency contacts.</div>
                    <div class="section-divider"></div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">📍</span><span
                            class="card-header-title">Home Address</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field"><label>Address Line 1 <span class="req">*</span></label><input
                                    type="text" name="street" placeholder="Street address" /></div>
                            <div class="field"><label>Address Line 2</label><input type="text" name="street_line_2"
                                    placeholder="Apt, Suite, Unit" /></div>
                            <div class="field"><label>City <span class="req">*</span></label><input type="text"
                                    name="city" placeholder="City" /></div>
                            <div class="field"><label>State <span class="req">*</span></label><select name="state">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('state'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Postal Code <span class="req">*</span></label><input type="text"
                                    name="postal_code" placeholder="ZIP code" maxlength="10" /></div>
                            <div class="field"><label>County</label><select name="county">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('county'); ?>
                                   
                                </select></div>
                            <div class="field"><label>Country</label><select name="country_code">
                                <option>-- Select --</option>
                                <?php renderListOptions('country'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Mother's Name</label><input type="text" name="mothersname"
                                    placeholder="Mother's full name" /></div>
                        </div>
                    </div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">📞</span><span
                            class="card-header-title">Phone & Email</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Home Phone</label><input type="tel" name="phone_home"
                                    placeholder="(___) ___-____" /></div>
                            <div class="field"><label>Mobile Phone <span class="req">*</span></label><input type="tel"
                                    name="phone_cell" placeholder="(___) ___-____" /></div>
                            <div class="field"><label>Work Phone</label><input type="tel" name="phone_biz"
                                    placeholder="(___) ___-____" /></div>
                            <div class="field"><label>Trusted Email <span class="req">*</span></label><input
                                    type="email" name="email" placeholder="your@email.com" /></div>
                            <div class="field"><label>Contact Email <span class="hint">(if
                                        different)</span></label><input type="email" name="email_alternate"
                                    placeholder="alternate@email.com" /></div>
                            <div class="field"><label>Preferred Contact Method</label><select
                                    name="phone_preferred_method">
                                    <option>-- Select --</option>
                                    <option>Mobile Phone (Call)</option>
                                    <option>Mobile Phone (Text)</option>
                                    <option>Email</option>
                                    <option>Home Phone</option>
                                    <option>Patient Portal</option>
                                </select></div>
                        </div>
                    </div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🚨</span><span
                            class="card-header-title">Emergency Contact</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Emergency Contact Name <span class="req">*</span></label><input
                                    type="text" name="emergency_contact_name" placeholder="Full name" /></div>
                            <div class="field"><label>Relationship</label><select name="emergency_contact_relationship">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('next_of_kin_relationship'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Emergency Phone <span class="req">*</span></label><input
                                    type="tel" name="emergency_contact_phone" placeholder="(___) ___-____" /></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 2 — CHOICES
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-2">
                <div class="section-header">
                    <div class="section-tag">Section 3 of 15</div>
                    <div class="section-title">Choices & Preferences</div>
                    <div class="section-desc">Communication preferences, provider assignment, and privacy choices.</div>
                    <div class="section-divider"></div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">👨‍⚕️</span><span
                            class="card-header-title">Provider & Pharmacy</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Primary Care Provider</label>
                            <select name="providerID">
                                    <option>-- Select --</option>
                                     <?php
                                          $res = sqlStatement("
                                              SELECT id, fname, lname
                                              FROM users
                                              WHERE active = 1
                                              ORDER BY lname, fname
                                          ");

                                          while ($row = sqlFetchArray($res)) {
                                              echo "<option value='" . attr($row['id']) . "'>" .
                                                  text($row['lname'] . ', ' . $row['fname']) .
                                                  "</option>";
                                          }
                                          ?>
                                </select></div>
                            <div class="field"><label>Provider Since Date</label><input type="date"
                                    name="provider_since_date" /></div>
                            <div class="field"><label>Referring Provider</label><select name="referring_provider">
                                    <option>-- Select --</option>
                                    <?php
                                          $res = sqlStatement("
                                              SELECT id, fname, lname
                                              FROM users
                                              WHERE active = 1
                                              ORDER BY lname, fname
                                          ");

                                          while ($row = sqlFetchArray($res)) {
                                              echo "<option value='" . attr($row['id']) . "'>" .
                                                  text($row['lname'] . ', ' . $row['fname']) .
                                                  "</option>";
                                          }
                                          ?>
                                    <option>External referral</option>
                                    <option>Self-referred</option>
                                </select></div>
                            <div class="field span3"><label>Preferred Pharmacy</label>
                        <select name="preferred_pharmacy">
                                    <option>-- Select --</option>
                            <?php
                                $res = sqlStatement("
                                    SELECT id, name
                                    FROM pharmacies
                                    ORDER BY name
                                ");

                                while ($row = sqlFetchArray($res)) {
                                    echo "<option value='" . attr($row['id']) . "'>" .
                                        text($row['name']) .
                                        "</option>";
                                }
                                ?>
                                </select>
                            <!-- <input type="text"
                                    name="preferred_pharmacy" placeholder="Pharmacy name and address" />
                                     -->
                                  </div>
                        </div>
                    </div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">💬</span><span
                            class="card-header-title">Communication Permissions</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>HIPAA Notice Received</label><select name="hipaa_notice_received">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Allow Voice Message</label><select name="allow_voice_message">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                    
                                    
                                </select></div>
                            <div class="field"><label>Leave Message With</label><input type="text"
                                    name="voice_message_with" placeholder="Name of person to leave message with" />
                            </div>
                            <div class="field"><label>Allow Mail Message</label><select name="allow_mail_message">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Allow SMS / Text</label><select name="allow_sms_text">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Allow Email</label><select name="allow_email_message">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Allow Patient Portal</label><select name="allow_patient_portal">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Allow Immunization Registry</label><select
                                    name="allow_imm_reg_use">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                </select></div>
                            <div class="field"><label>Allow Immunization Info Sharing</label><select
                                    name="allow_imm_info_share">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                </select></div>
                            <div class="field"><label>Allow Health Info Exchange</label><select
                                    name="allow_health_info_ex">
                                    <option>-- Select --</option>
                                   <?php renderListOptions('yesno'); ?>
                                </select></div>
                            <div class="field"><label>CMS Portal Login</label><input type="text" name="cmsportal_login"
                                    placeholder="CMS Blue Button login (optional)" /></div>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Registry & Compliance</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="form-grid g3">
                            <div class="field"><label>Immunization Registry Status</label><select name="imm_reg_status">
                                    <option>-- Select --</option>
                                     <?php renderListOptions('immunization_registry_status'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Registry Effective Date</label><input type="date"
                                    name="imm_reg_stat_effdate" /></div>
                            <div class="field"><label>Publicity Code</label><select name="publicity_code">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('publicity_code'); ?>
                                   
                                </select></div>
                            <div class="field"><label>Publicity Code Effective Date</label><input type="date"
                                    name="publ_code_eff_date" /></div>
                            <div class="field"><label>Protection Indicator</label><select name="protect_indicator">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                   
                                </select></div>
                            <div class="field"><label>Protection Effective Date</label><input type="date"
                                    name="prot_indi_effdate" /></div>
                            <div class="field"><label>Care Team (Provider)</label><input type="text"
                                    name="care_team_provider" placeholder="Care team provider name" /></div>
                            <div class="field"><label>Care Team Status</label><select name="care_team_status">
                              <option>-- Select --</option>
                                    <?php renderListOptions('Care_Team_Status'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Patient Category</label><select name="patient_category">
                              <option>-- Select --</option>
                                    <?php renderListOptions('Patient_Groupings'); ?>
                                </select></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 3 — EMPLOYER
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-3">
                <div class="section-header">
                    <div class="section-tag">Section 4 of 15</div>
                    <div class="section-title">Employer Information</div>
                    <div class="section-desc">Current employment details used for insurance and billing purposes.</div>
                    <div class="section-divider"></div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">💼</span><span
                            class="card-header-title">Occupation & Employer</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field"><label>Occupation</label><select name="occupation">
                              
                                    <option>-- Select --</option>
                                    <?php renderListOptions('OccupationODH'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Industry</label><select name="industry">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('IndustryODH'); ?>
                                   
                                </select></div>
                            <div class="field"><label>Employer Name</label><input type="text" name="employer_name"
                                    placeholder="Employer or company name" /></div>
                            <div class="field"><label>Employer Address</label><input type="text" name="em_street"
                                    placeholder="Street address" /></div>
                            <div class="field"><label>Employer Address Line 2</label><input type="text"
                                    name="employer_address_line_2" placeholder="Suite, floor" /></div>
                            <div class="field"><label>City <span class="req">*</span></label><input type="text"
                                    name="em_city" placeholder="City" /></div>
                            <div class="field"><label>State <span class="req">*</span></label><select name="em_state">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('state'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Postal Code</label><input type="text" name="em_postal_code"
                                    placeholder="ZIP" /></div>
                            <div class="field"><label>Country</label><select name="em_country_code">
                                <option>-- Select --</option>
                                   <?php renderListOptions('country'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Employment Start Date</label><input type="date"
                                    name="employment_start_date" /></div>
                            <div class="field"><label>Employment End Date <span class="hint">(if no longer
                                        employed)</span></label><input type="date" name="employment_end_date" /></div>
                            <div class="field"><label>Employment Status</label>
                                <select name="employment_status">
                                    <option value="">-- Select --</option>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Self-employed">Self-employed</option>
                                    <option value="Unemployed">Unemployed</option>
                                    <option value="Student">Student</option>
                                    <option value="Retired">Retired</option>
                                    <option value="Disability">Disability</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 4 — STATS
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-4">
                <div class="section-header">
                    <div class="section-tag">Section 5 of 15</div>
                    <div class="section-title">Demographics & Social Stats</div>
                    <div class="section-desc">Used for quality reporting, FQHC metrics, and care coordination.</div>
                    <div class="section-divider"></div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🌍</span><span
                            class="card-header-title">Race, Ethnicity & Language</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Primary Language</label><select name="language">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('language'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Ethnicity</label><select name="ethnicity">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('ethnicity'); ?>
                                </select></div>
                            <div class="field"><label>Race</label><select name="race">
                                    <option>-- Select --</option>
                                      <?php renderListOptions('race'); ?>
                                </select></div>
                            <div class="field"><label>Nationality</label><select name="nationality_country">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('nationality_with_country'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Interpreter Needed</label><select name="interpreter_needed">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                </select></div>
                            <div class="field"><label>Interpreter Comments</label><input type="text"
                                    name="interpreter_dialect_notes" placeholder="Dialect or special notes" /></div>
                        </div>
                    </div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">📊</span><span
                            class="card-header-title">Financial & Social Factors</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Financial Review Date</label><input type="date"
                                    name="financial_review_date" /></div>
                            <div class="field"><label>Monthly Household Income</label><input type="text"
                                    name="monthly_income" placeholder="$ amount" /></div>
                            <div class="field"><label>Household / Family Size</label><input type="number"
                                    name="household_size" placeholder="# of people" min="1" max="20" /></div>
                            <div class="field"><label>Homeless / Unstably Housed</label><input type="text"
                                    name="homeless" placeholder="Yes / No / Specify" /></div>
                            <div class="field"><label>Migrant / Seasonal Worker</label><input type="text"
                                    name="migrantseasonal" placeholder="Yes / No / Seasonal dates" /></div>
                            <div class="field"><label>Referral Source</label><select name="referral_source">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('refsource'); ?>
                                    
                                </select></div>
                            <div class="field"><label>VFC Eligibility (Vaccines for Children)</label><select
                                    name="vfc_eligibility_status">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('eligibility'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Religion</label><select name="religion">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('religious_affiliation'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Tribal Affiliations</label><select name="tribal_affiliations">
                                    <option>-- Select --</option>
                                   <?php renderListOptions('tribal_affiliations'); ?> 
                                </select></div>
                        </div>
                    </div>
                </div>
                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">⚰️</span><span
                            class="card-header-title">Misc</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field"><label>Date Deceased</label><input type="date" name="deceased_date" />
                            </div>
                            <div class="field"><label>Reason Deceased</label><input type="text" name="deceased_reason"
                                    placeholder="If applicable" /></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 5 — INSURANCE
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-5">
                <div class="section-header">
                    <div class="section-tag">Section 6 of 15</div>
                    <div class="section-title">Insurance Information</div>
                    <div class="section-desc">Primary, secondary, and tertiary insurance coverage. Red fields are
                        required for billing.</div>
                    <div class="section-divider"></div>
                </div>
                <div class="notice">
                    <div class="notice-icon">📸</div>
                    <div class="notice-text"><strong>Insurance Card Upload</strong>Please have your insurance card
                        ready. You can take a photo with your phone camera to auto-fill fields below.</div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🏥</span><span
                            class="card-header-title">Primary Insurance</span><span class="card-header-sub">Main
                            coverage</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field span2"><label>Primary Insurance Provider <span
                                        class="req">*</span></label>
                                        <select name="insurance_provider" class="form-control">
                                  <option value=""><?php echo xlt('Unassigned'); ?></option>
                                  <?php
                                    foreach ($insurancei as $iid => $iname) {
                                        echo "<option value='" . attr($iid) . "'";
                                        if (!empty($result3["provider"]) && (strtolower((string) $iid) == strtolower((string) $result3["provider"]))) {
                                            echo " selected";
                                        }
                                        echo ">" . text($iname) . "</option>\n";
                                    }
                                    ?>
                              </select>
                                        <!-- <select name="insurance_provider">
                                    <option>-- Select or Search --</option>
                                    <option>Medi-Cal</option>
                                    <option>Medicare</option>
                                    <option>Blue Shield of California</option>
                                    <option>Anthem Blue Cross</option>
                                    <option>Covered California</option>
                                    <option>Kaiser Permanente</option>
                                    <option>Uninsured / Self-Pay</option>
                                    <option>Other</option>
                                </select> -->
                              </div>
                            <div class="field"><label>Plan Name <span class="req">*</span></label><input type="text"
                                    name="plan_name" placeholder="Plan name" /></div>
                            <div class="field"><label>Policy Number <span class="req">*</span></label><input type="text"
                                    name="policy_number" placeholder="Member ID / Policy #" /></div>
                            <div class="field"><label>Group Number</label><input type="text" name="group_number"
                                    placeholder="Group #" /></div>
                            <div class="field"><label>Effective Date <span class="req">*</span></label><input
                                    type="date" name="date_start" /></div>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Subscriber Information</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="form-grid g3">
                            <div class="field"><label>Subscriber First Name <span class="req">*</span></label><input
                                    type="text" name="subscriber_fname" placeholder="First" /></div>
                            <div class="field"><label>Subscriber Middle</label><input type="text"
                                    name="subscriber_mname" placeholder="Middle" /></div>
                            <div class="field"><label>Subscriber Last Name <span class="req">*</span></label><input
                                    type="text" name="subscriber_lname" placeholder="Last" /></div>
                            <div class="field"><label>Relationship to Patient <span class="req">*</span></label><select
                                    name="subscriber_relationship">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('sub_relation'); ?>
                                </select></div>
                            <div class="field"><label>Subscriber DOB</label><input type="date" name="subscriber_dob" />
                            </div>
                            <div class="field"><label>Subscriber Sex</label><select name="subscriber_sex">
                                    <option>-- Select --</option>
                                   <?php renderListOptions('sex'); ?>
                                </select></div>
                            <div class="field"><label>Subscriber SSN <span class="hint">(if
                                        different)</span></label><input type="text" name="subscriber_ssn"
                                    placeholder="XXX-XX-____" /></div>
                            <div class="field"><label>Subscriber Phone</label><input type="tel" name="subscriber_phone"
                                    placeholder="(___) ___-____" /></div>
                            <div class="field"><label>Subscriber Employer (SE)</label><input type="text"
                                    name="subscriber_employer"
                                    placeholder="If unemployed: Student, PT Student, or leave blank" /></div>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Subscriber Address (if different from patient)</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="form-grid g3">
                            <div class="field"><label>SE Address</label><input type="text" name="subscriber_street"
                                    placeholder="Street" /></div>
                            <div class="field"><label>Subscriber Address Line 1</label><input type="text"
                                    name="subscriber_address_line_1" placeholder="Address line 1" /></div>
                            <div class="field"><label>Subscriber Address Line 2</label><input type="text"
                                    name="subscriber_address_line_2" placeholder="Address line 2" /></div>
                            <div class="field"><label>SE City</label><input type="text" name="se_city"
                                    placeholder="SE City" /></div>
                            <div class="field"><label>City <span class="req">*</span></label><input type="text"
                                    name="subscriber_city" placeholder="City" /></div>
                            <div class="field"><label>State <span class="req">*</span></label>
                             <!-- <?php
                            generate_form_field(['data_type' => $GLOBALS['state_data_type'],'field_id' => ('subscriber_state'),'list_id' => $GLOBALS['state_list'],'fld_length' => '15','max_length' => '63','edit_options' => 'C', 'smallform' => 'true'], ($result3['subscriber_employer_state'] ?? ''));
                            ?> -->
                                <select name="subscriber_state">
                                    <option value="">-- Select --</option>
                                    <?php renderListOptions('state'); ?>
                                </select>
                            </div>
                            <div class="field"><label>SE State</label><select name="se_state">
                                    <option>-- Select --</option>
                                     <?php renderListOptions('state'); ?>
                                </select></div>
                            <div class="field"><label>Zip Code</label><input type="text" name="subscriber_postal_code"
                                    placeholder="ZIP" /></div>
                            <div class="field"><label>SE Zip Code</label><input type="text" name="se_postal_code"
                                    placeholder="SE ZIP" /></div>
                            <div class="field"><label>Country</label><select name="subscriber_country_code">
                              <option>-- Select --</option>
                               <?php renderListOptions('country'); ?>
                                   
                                </select></div>
                            <div class="field"><label>SE Country</label><select name="se_country_code">
                                    <option>-- Select --</option>
                                 <?php renderListOptions('country'); ?>
                                </select></div>
                            <div class="field"><label>Co-Pay Amount</label><input type="text" name="copay_amount"
                                    placeholder="$ amount" /></div>
                            <div class="field"><label>Accept Assignment</label><select name="accept_assignment">
                                    <option>-- Select --</option>
                               <?php renderListOptions('yesno'); ?>
                                </select></div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🏥</span><span
                            class="card-header-title">Secondary Insurance</span><span class="card-header-sub">If
                            applicable</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field span2"><label>Secondary Insurance Provider</label><select
                                    name="insurance_provider_secondary">
                                   <option value=""><?php echo xlt('Unassigned'); ?></option>
                                  <?php
                                    foreach ($insurancei as $iid => $iname) {
                                        echo "<option value='" . attr($iid) . "'";
                                        if (!empty($result3["provider"]) && (strtolower((string) $iid) == strtolower((string) $result3["provider"]))) {
                                            echo " selected";
                                        }
                                        echo ">" . text($iname) . "</option>\n";
                                    }
                                    ?>
                                </select></div>
                            <div class="field"><label>Plan Name</label><input type="text" name="plan_name_secondary"
                                    placeholder="Plan name" /></div>
                            <div class="field"><label>Policy Number</label><input type="text"
                                    name="policy_number_secondary" placeholder="Policy #" /></div>
                            <div class="field"><label>Group Number</label><input type="text"
                                    name="group_number_secondary" placeholder="Group #" /></div>
                            <div class="field"><label>Effective Date</label><input type="date"
                                    name="date_start_secondary" /></div>
                            <div class="field"><label>Subscriber</label><input type="text"
                                    name="subscriber_name_secondary" placeholder="Subscriber name" /></div>
                            <div class="field"><label>Relationship</label><select
                                    name="subscriber_relationship_secondary">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('sub_relation'); ?>
                                    
                                </select></div>
                            <div class="field"><label>Co-Pay</label><input type="text" name="copay_amount_secondary"
                                    placeholder="$ amount" /></div>
                            <div class="field"><label>Accept Assignment</label><select
                                    name="accept_assignment_secondary">
                                    <option>-- Select --</option>
                                     <?php renderListOptions('yesno'); ?>
                                </select></div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🏥</span><span
                            class="card-header-title">Tertiary Insurance</span><span class="card-header-sub">If
                            applicable</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field span2"><label>Tertiary Insurance Provider</label><select
                                    name="insurance_provider_tertiary">
                                    <option>-- None --</option>
                                    <?php
                                    foreach ($insurancei as $iid => $iname) {
                                        echo "<option value='" . attr($iid) . "'";
                                        if (!empty($result3["provider"]) && (strtolower((string) $iid) == strtolower((string) $result3["provider"]))) {
                                            echo " selected";
                                        }
                                        echo ">" . text($iname) . "</option>\n";
                                    }
                                    ?>
                                </select></div>
                            <div class="field"><label>Plan Name</label><input type="text" name="plan_name_tertiary"
                                    placeholder="Plan name" /></div>
                            <div class="field"><label>Policy Number</label><input type="text"
                                    name="policy_number_tertiary" placeholder="Policy #" /></div>
                            <div class="field"><label>Group Number</label><input type="text"
                                    name="group_number_tertiary" placeholder="Group #" /></div>
                            <div class="field"><label>Effective Date</label><input type="date"
                                    name="date_start_tertiary" /></div>
                            <div class="field"><label>Co-Pay</label><input type="text" name="copay_amount_tertiary"
                                    placeholder="$ amount" /></div>
                            <div class="field"><label>Accept Assignment</label><select
                                    name="accept_assignment_tertiary">
                                    <option>-- Select --</option>
                                    <?php renderListOptions('yesno'); ?>
                                </select></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 12 — CURRENT SYMPTOMS
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-6">
                <div class="section-header">
                    <div class="section-tag">Section 7 of 15</div>
                    <div class="section-title">Current Symptoms <span class="ai-badge">✦ AI Triage</span></div>
                    <div class="section-desc">Tell us what's bringing you in today. Synapta AI will assess urgency and
                        pre-populate your provider's note. Please be as specific as possible.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="notice">
                    <div class="notice-icon">🚨</div>
                    <div class="notice-text"><strong>If you are experiencing a medical emergency</strong> — chest pain,
                        difficulty breathing, stroke symptoms, or severe injury — please call 911 or go to the nearest
                        Emergency Room immediately. Do not complete this form.</div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🤒</span><span
                            class="card-header-title">Chief Complaint</span></div>
                    <div class="card-body">
                        <div class="field"><label>What is the main reason for your visit today? <span
                                    class="req">*</span></label><textarea name="chief_complaint"
                                placeholder="Describe your main concern in your own words (e.g. 'I've had chest tightness and shortness of breath for 3 days')..."
                                style="min-height:100px;"></textarea></div>
                        <div class="form-grid g3" style="margin-top:16px;">
                            <div class="field"><label>When did symptoms start? <span class="req">*</span></label><select
                                    name="symptom_start">
                                    <option>-- Select --</option>
                                    <option>Today</option>
                                    <option>1–3 days ago</option>
                                    <option>4–7 days ago</option>
                                    <option>1–2 weeks ago</option>
                                    <option>2–4 weeks ago</option>
                                    <option>1–3 months ago</option>
                                    <option>3–6 months ago</option>
                                    <option>More than 6 months ago</option>
                                    <option>Chronic / ongoing</option>
                                </select></div>
                            <div class="field"><label>How did symptoms start?</label>
                                <select name="symptom_onset_type">
                                    <option value="">-- Select --</option>
                                    <option value="Sudden / acute onset">Sudden / acute onset</option>
                                    <option value="Gradual over time">Gradual over time</option>
                                    <option value="After an injury / event">After an injury / event</option>
                                    <option value="After a medication change">After a medication change</option>
                                    <option value="After illness (e.g. COVID, flu)">After illness (e.g. COVID, flu)
                                    </option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Are symptoms getting?</label>
                                <div class="radio-group">
                                    <!-- ADDED value="Better" -->
                                    <label class="radio-pill">
                                        <input type="radio" name="sympprog" value="Better"
                                            onchange="selectPill(this)" />Better
                                    </label>

                                    <!-- ADDED value="Same" -->
                                    <label class="radio-pill">
                                        <input type="radio" name="sympprog" value="Same"
                                            onchange="selectPill(this)" />Same
                                    </label>

                                    <!-- ADDED value="Worse" -->
                                    <label class="radio-pill">
                                        <input type="radio" name="sympprog" value="Worse"
                                            onchange="selectPill(this)" />Worse
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">📋</span><span
                            class="card-header-title">Review of Systems</span><span class="card-header-sub">Check all
                            current symptoms</span></div>
                    <div class="card-body">
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">General</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Fever / Chills" onchange="togglePill(this)" />Fever /
                                Chills</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Fatigue / Weakness" onchange="togglePill(this)" />Fatigue /
                                Weakness</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Unintentional Weight Loss" onchange="togglePill(this)" />Unintentional
                                Weight Loss</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Unintentional Weight Gain" onchange="togglePill(this)" />Unintentional
                                Weight Gain</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Night Sweats" onchange="togglePill(this)" />Night
                                Sweats</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Loss of Appetite" onchange="togglePill(this)" />Loss of
                                Appetite</label>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Head, Eyes, Ears, Nose, Throat</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Headache</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Vision Changes" onchange="togglePill(this)" />Vision
                                Changes</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Eye Pain / Redness" onchange="togglePill(this)" />Eye Pain /
                                Redness</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Hearing Loss / Ringing" onchange="togglePill(this)" />Hearing Loss
                                / Ringing</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Ear Pain" onchange="togglePill(this)" />Ear
                                Pain</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Nasal Congestion" onchange="togglePill(this)" />Nasal
                                Congestion</label>
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Nosebleed</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Sore Throat" onchange="togglePill(this)" />Sore
                                Throat</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Difficulty Swallowing" onchange="togglePill(this)" />Difficulty
                                Swallowing</label>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Cardiovascular & Respiratory</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Chest Pain / Pressure" onchange="togglePill(this)" />Chest Pain /
                                Pressure</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Palpitations / Fast Heartbeat" onchange="togglePill(this)" />Palpitations
                                / Fast Heartbeat</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Shortness of Breath" onchange="togglePill(this)" />Shortness of
                                Breath</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Shortness of Breath at Rest" onchange="togglePill(this)" />Shortness of
                                Breath at Rest</label>
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Wheezing</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Chronic Cough" onchange="togglePill(this)" />Chronic
                                Cough</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Coughing Blood" onchange="togglePill(this)" />Coughing
                                Blood</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Leg Swelling" onchange="togglePill(this)" />Leg
                                Swelling</label>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Gastrointestinal</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Nausea</label>
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Vomiting</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Abdominal Pain" onchange="togglePill(this)" />Abdominal
                                Pain</label>
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Diarrhea</label>
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Constipation</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Blood in Stool" onchange="togglePill(this)" />Blood in
                                Stool</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Heartburn / Reflux" onchange="togglePill(this)" />Heartburn /
                                Reflux</label>
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Bloating</label>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Musculoskeletal & Neurological</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Joint Pain" onchange="togglePill(this)" />Joint
                                Pain</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Muscle Pain / Cramps" onchange="togglePill(this)" />Muscle Pain /
                                Cramps</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Back Pain" onchange="togglePill(this)" />Back
                                Pain</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Numbness / Tingling" onchange="togglePill(this)" />Numbness /
                                Tingling</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Weakness in Limbs" onchange="togglePill(this)" />Weakness in
                                Limbs</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Dizziness / Vertigo" onchange="togglePill(this)" />Dizziness /
                                Vertigo</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Fainting / Near-fainting" onchange="togglePill(this)" />Fainting /
                                Near-fainting</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Tremor / Shaking" onchange="togglePill(this)" />Tremor /
                                Shaking</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Memory Problems" onchange="togglePill(this)" />Memory
                                Problems</label>
                        </div>
                        <div class="sub-divider">
                            <div class="sub-divider-line"></div>
                            <div class="sub-divider-label">Urinary, Skin & Other</div>
                            <div class="sub-divider-line"></div>
                        </div>
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Frequent Urination" onchange="togglePill(this)" />Frequent
                                Urination</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Painful Urination" onchange="togglePill(this)" />Painful
                                Urination</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Blood in Urine" onchange="togglePill(this)" />Blood in
                                Urine</label>
                            <label class="check-pill"><input type="checkbox"
                                    onchange="togglePill(this)" />Incontinence</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Skin Rash" onchange="togglePill(this)" />Skin
                                Rash</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Skin Lesion / Mole Change" onchange="togglePill(this)" />Skin Lesion /
                                Mole Change</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Excessive Thirst" onchange="togglePill(this)" />Excessive
                                Thirst</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Excessive Hunger" onchange="togglePill(this)" />Excessive
                                Hunger</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Depression / Low Mood" onchange="togglePill(this)" />Depression /
                                Low Mood</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Anxiety / Panic" onchange="togglePill(this)" />Anxiety /
                                Panic</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Sleep Problems" onchange="togglePill(this)" />Sleep
                                Problems</label>
                            <label class="check-pill"><input type="checkbox" name="ros_symptoms[]" value="Mood Changes" onchange="togglePill(this)" />Mood
                                Changes</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">😣</span><span
                            class="card-header-title">Pain Assessment</span></div>
                    <div class="card-body">
                        <div class="field"><label>Current Pain Level <span class="hint">(0 = no pain, 10 = worst
                                    imaginable)</span></label>
                            <div class="severity-row" style="margin-top:6px;">
                                <span style="font-size:12px;color:var(--muted);width:28px;">0</span>
                                <button class="sev-btn" onclick="selectSev(this,0)">0</button>
                                <button class="sev-btn s1" onclick="selectSev(this,1)">1</button>
                                <button class="sev-btn s2" onclick="selectSev(this,2)">2</button>
                                <button class="sev-btn s3" onclick="selectSev(this,3)">3</button>
                                <button class="sev-btn s4" onclick="selectSev(this,4)">4</button>
                                <button class="sev-btn s5" onclick="selectSev(this,5)">5</button>
                                <button class="sev-btn s6" onclick="selectSev(this,6)">6</button>
                                <button class="sev-btn s7" onclick="selectSev(this,7)">7</button>
                                <button class="sev-btn s8" onclick="selectSev(this,8)">8</button>
                                <button class="sev-btn s9" onclick="selectSev(this,9)">9</button>
                                <button class="sev-btn s10" onclick="selectSev(this,10)">10</button>
                                <span style="font-size:12px;color:var(--muted);">10</span>
                            </div>
                            <input type="hidden" name="pain_score" id="pain_score" value="">
                        </div>
                        <div class="form-grid g3" style="margin-top:16px;">
                            <div class="field"><label>Pain Location</label><input type="text" name="pain_location"
                                    placeholder="Where does it hurt?" /></div>
                            <div class="field"><label>Pain Character</label><select name="pain_character">
                                    <option>-- Select --</option>
                                    <option>Sharp / Stabbing</option>
                                    <option>Dull / Aching</option>
                                    <option>Burning</option>
                                    <option>Throbbing / Pulsating</option>
                                    <option>Pressure / Squeezing</option>
                                    <option>Cramping</option>
                                    <option>Shooting</option>
                                    <option>Tingling / Numbness</option>
                                </select></div>
                            <div class="field"><label>What makes pain worse?</label><input type="text" name="pain_worse"
                                    placeholder="e.g. Movement, eating, stress" /></div>
                            <div class="field"><label>What makes pain better?</label><input type="text"
                                    name="pain_better" placeholder="e.g. Rest, ice, medication" /></div>
                            <div class="field"><label>Is pain constant or intermittent?</label>
                                <div class="radio-group"><label class="radio-pill"><input type="radio" name="paincont"
                                            onchange="selectPill(this)" />Constant</label><label
                                        class="radio-pill"><input type="radio" name="paincont"
                                            onchange="selectPill(this)" />Comes and goes</label></div>
                            </div>
                            <div class="field"><label>Does pain radiate / spread?</label><input type="text"
                                  name="pain_radiate"  placeholder="e.g. Down left arm, into jaw" /></div>
                        </div>
                        <div class="field" style="margin-top:16px;"><label>Additional Symptom Notes</label><textarea name="symptom_notes"
                                placeholder="Any other symptoms or concerns you want your provider to know about..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 6 — MEDICAL CONDITIONS
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-7">
                <div class="section-header">
                    <div class="section-tag">Section 8 of 15</div>
                    <div class="section-title">Medical Conditions <span class="ai-badge">✦ AI Pre-filled</span></div>
                    <div class="section-desc">Check all conditions that apply. This information is used to guide your
                        care and AI clinical decision support.</div>
                    <div class="section-divider"></div>
                </div>
                <div class="ai-suggestion"><span class="ai-suggestion-icon">✦</span>Based on your demographics, Synapta
                    AI may pre-populate likely condition categories during your visit. Your provider will review and
                    confirm all entries.</div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">❤️</span><span
                            class="card-header-title">Cardiovascular</span></div>
                    <div class="card-body">
                        <div class="check-group" id="cardio-group">
                            <label class="check-pill"><input type="checkbox" value="Hypertension"
                                    onchange="togglePill(this)" />Hypertension</label>
                            <label class="check-pill"><input type="checkbox" value="Heart Disease (CAD)"
                                    onchange="togglePill(this)" />Heart Disease (CAD)</label>
                            <label class="check-pill"><input type="checkbox" value="Heart Failure (CHF)"
                                    onchange="togglePill(this)" />Heart Failure (CHF)</label>
                            <label class="check-pill"><input type="checkbox" value="Atrial Fibrillation"
                                    onchange="togglePill(this)" />Atrial Fibrillation</label>
                            <label class="check-pill"><input type="checkbox" value="History of Heart Attack"
                                    onchange="togglePill(this)" />History of Heart Attack</label>
                            <label class="check-pill"><input type="checkbox" value="History of Stroke"
                                    onchange="togglePill(this)" />History of Stroke</label>
                            <label class="check-pill"><input type="checkbox" value="High Cholesterol (Dyslipidemia)"
                                    onchange="togglePill(this)" />High Cholesterol (Dyslipidemia)</label>
                            <label class="check-pill"><input type="checkbox" value="Peripheral Artery Disease"
                                    onchange="togglePill(this)" />Peripheral Artery Disease</label>
                            <label class="check-pill"><input type="checkbox" value="Deep Vein Thrombosis / PE"
                                    onchange="togglePill(this)" />Deep Vein Thrombosis / PE</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🩸</span><span
                            class="card-header-title">Metabolic & Endocrine</span></div>
                    <div class="card-body">
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" value="Type 1 Diabetes"
                                    onchange="togglePill(this)" />Type 1 Diabetes</label>
                            <label class="check-pill"><input type="checkbox" value="Type 2 Diabetes"
                                    onchange="togglePill(this)" />Type 2 Diabetes</label>
                            <label class="check-pill"><input type="checkbox" value="Pre-diabetes"
                                    onchange="togglePill(this)" />Pre-diabetes</label>
                            <label class="check-pill"><input type="checkbox" value="Obesity"
                                    onchange="togglePill(this)" />Obesity</label>
                            <label class="check-pill"><input type="checkbox" value="Thyroid Disease (Hypo/Hyper)"
                                    onchange="togglePill(this)" />Thyroid Disease (Hypo/Hyper)</label>
                            <label class="check-pill"><input type="checkbox" value="Gout"
                                    onchange="togglePill(this)" />Gout</label>
                            <label class="check-pill"><input type="checkbox" value="Polycystic Ovary Syndrome (PCOS)"
                                    onchange="togglePill(this)" />Polycystic Ovary Syndrome (PCOS)</label>
                            <label class="check-pill"><input type="checkbox" value="Adrenal Disorders"
                                    onchange="togglePill(this)" />Adrenal Disorders</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🫁</span><span
                            class="card-header-title">Pulmonary & Respiratory</span></div>
                    <div class="card-body">
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" value="Asthma"
                                    onchange="togglePill(this)" />Asthma</label>
                            <label class="check-pill"><input type="checkbox" value="COPD"
                                    onchange="togglePill(this)" />COPD</label>
                            <label class="check-pill"><input type="checkbox" value="Sleep Apnea"
                                    onchange="togglePill(this)" />Sleep Apnea</label>
                            <label class="check-pill"><input type="checkbox" value="Chronic Bronchitis"
                                    onchange="togglePill(this)" />Chronic Bronchitis</label>
                            <label class="check-pill"><input type="checkbox" value="Pulmonary Hypertension"
                                    onchange="togglePill(this)" />Pulmonary Hypertension</label>
                            <label class="check-pill"><input type="checkbox" value="Emphysema"
                                    onchange="togglePill(this)" />Emphysema</label>
                            <label class="check-pill"><input type="checkbox" value="Pulmonary Fibrosis"
                                    onchange="togglePill(this)" />Pulmonary Fibrosis</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🧠</span><span
                            class="card-header-title">Neurological & Mental Health</span></div>
                    <div class="card-body">
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" value="Depression"
                                    onchange="togglePill(this)" />Depression</label>
                            <label class="check-pill"><input type="checkbox" value="Anxiety"
                                    onchange="togglePill(this)" />Anxiety</label>
                            <label class="check-pill"><input type="checkbox" value="Bipolar Disorder"
                                    onchange="togglePill(this)" />Bipolar Disorder</label>
                            <label class="check-pill"><input type="checkbox" value="PTSD"
                                    onchange="togglePill(this)" />PTSD</label>
                            <label class="check-pill"><input type="checkbox" value="ADHD"
                                    onchange="togglePill(this)" />ADHD</label>
                            <label class="check-pill"><input type="checkbox" value="Schizophrenia"
                                    onchange="togglePill(this)" />Schizophrenia</label>
                            <label class="check-pill"><input type="checkbox" value="Epilepsy / Seizures"
                                    onchange="togglePill(this)" />Epilepsy / Seizures</label>
                            <label class="check-pill"><input type="checkbox" value="Migraines"
                                    onchange="togglePill(this)" />Migraines</label>
                            <label class="check-pill"><input type="checkbox" value="Parkinson's Disease"
                                    onchange="togglePill(this)" />Parkinson's Disease</label>
                            <label class="check-pill"><input type="checkbox" value="Dementia / Alzheimer's"
                                    onchange="togglePill(this)" />Dementia / Alzheimer's</label>
                            <label class="check-pill"><input type="checkbox" value="Multiple Sclerosis"
                                    onchange="togglePill(this)" />Multiple Sclerosis</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🦴</span><span
                            class="card-header-title">Musculoskeletal & Other</span></div>
                    <div class="card-body">
                        <div class="check-group">
                            <label class="check-pill"><input type="checkbox" value="Arthritis (Osteo)"
                                    onchange="togglePill(this)" />Arthritis (Osteo)</label>
                            <label class="check-pill"><input type="checkbox" value="Rheumatoid Arthritis"
                                    onchange="togglePill(this)" />Rheumatoid Arthritis</label>
                            <label class="check-pill"><input type="checkbox" value="Osteoporosis"
                                    onchange="togglePill(this)" />Osteoporosis</label>
                            <label class="check-pill"><input type="checkbox" value="Chronic Back Pain"
                                    onchange="togglePill(this)" />Chronic Back Pain</label>
                            <label class="check-pill"><input type="checkbox" value="Kidney Disease (CKD)"
                                    onchange="togglePill(this)" />Kidney Disease (CKD)</label>
                            <label class="check-pill"><input type="checkbox" value="Kidney Stones"
                                    onchange="togglePill(this)" />Kidney Stones</label>
                            <label class="check-pill"><input type="checkbox" value="Liver Disease / Hepatitis"
                                    onchange="togglePill(this)" />Liver Disease / Hepatitis</label>
                            <label class="check-pill"><input type="checkbox" value="Crohn's / Colitis"
                                    onchange="togglePill(this)" />Crohn's / Colitis</label>
                            <label class="check-pill"><input type="checkbox" value="GERD / Acid Reflux"
                                    onchange="togglePill(this)" />GERD / Acid Reflux</label>
                            <label class="check-pill"><input type="checkbox" value="HIV/AIDS"
                                    onchange="togglePill(this)" />HIV/AIDS</label>
                            <label class="check-pill"><input type="checkbox" value="Cancer (specify below)"
                                    onchange="togglePill(this)" />Cancer (specify below)</label>
                            <label class="check-pill"><input type="checkbox" value="Autoimmune Disorder"
                                    onchange="togglePill(this)" />Autoimmune Disorder</label>
                            <label class="check-pill"><input type="checkbox" value="Anemia"
                                    onchange="togglePill(this)" />Anemia</label>
                        </div>
                        <div class="field" style="margin-top:16px;"><label>Additional Conditions / Cancer Type / Other
                                Details</label><textarea
                                name="additional_medical_conditions" placeholder="Please specify any conditions not listed above, cancer type, or additional details..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 7 — MEDICATIONS
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-8">
                <div class="section-header">
                    <div class="section-tag">Section 9 of 15</div>
                    <div class="section-title">Current Medications <span class="ai-badge">✦ AI Assisted</span></div>
                    <div class="section-desc">List all prescription medications, over-the-counter drugs, vitamins, and
                        supplements you currently take.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">💊</span><span
                            class="card-header-title">Medication List</span><span class="card-header-sub">Add all
                            current medications</span></div>
                    <div class="card-body">
                        <div class="dynamic-list" id="med-list">
                            <div class="dynamic-list-header">
                                <span class="dynamic-list-title">Medications</span>
                                <button class="add-row-btn" onclick="addMedRow()">+ Add Medication</button>
                            </div>
                            <div class="dynamic-row"
                                style="grid-template-columns:2fr 1fr 1fr 1fr 1.5fr 32px;font-size:11px;font-weight:700;color:var(--muted);background:var(--surface);">
                                <span>Medication
                                    Name</span><span>Dose</span><span>Frequency</span><span>Route</span><span>Prescribing
                                    MD</span><span></span>
                            </div>
                            <div class="dynamic-row" id="med-rows"
                                style="grid-template-columns:2fr 1fr 1fr 1fr 1.5fr 32px;">
                                <input type="text" placeholder="e.g. Metformin"
                                    style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                <input type="text" placeholder="500mg"
                                    style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                <!-- Frequency Select Dropdown -->
                                <select
                                    style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;">
                                    <option value="Daily">Daily</option>
                                    <option value="Twice daily">Twice daily</option>
                                    <option value="Three times daily">Three times daily</option>
                                    <option value="Weekly">Weekly</option>
                                    <option value="As needed">As needed</option>
                                    <option value="Other">Other</option>
                                </select>

                                <!-- Route of Administration Select Dropdown -->
                                <select
                                    style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;">
                                    <option value="Oral">Oral</option>
                                    <option value="Topical">Topical</option>
                                    <option value="Injection">Injection</option>
                                    <option value="Inhaled">Inhaled</option>
                                    <option value="IV">IV</option>
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" placeholder="Dr. Name"
                                    style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                <button class="remove-btn" onclick="removeRow(this)">×</button>
                            </div>
                        </div>
                        <div class="form-grid g2" style="margin-top:20px;">
                            <div class="field"><label>OTC Medications / Vitamins / Supplements</label><textarea
                                    name="otc_medications" placeholder="e.g. Aspirin 81mg daily, Vitamin D 2000IU, Fish oil..."
                                    style="width:100%;min-height:100px;padding:10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:13px;"></textarea>
                            </div>
                            <div class="field"><label>Herbal Remedies / Alternative Treatments</label><textarea
                                    name="herbal_remedies" placeholder="e.g. Turmeric, Ashwagandha, Traditional medicine..."
                                    style="width:100%;min-height:100px;padding:10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:13px;"></textarea>
                            </div>
                        </div>
                        <div class="field" style="margin-top:16px;"><label>Any medications recently stopped? <span
                                    class="hint">(within 3 months)</span></label><textarea
                                name="recently_stopped_medications" placeholder="List discontinued medications and reason if known..."
                                style="width:100%;min-height:100px;padding:10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:13px;"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 8 — ALLERGIES
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-9">
                <div class="section-header">
                    <div class="section-tag">Section 10 of 15</div>
                    <div class="section-title">Allergies</div>
                    <div class="section-desc">List all known allergies to medications, foods, environmental triggers,
                        and materials. This information is used for clinical safety checks.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">⚠️</span><span
                            class="card-header-title">Drug Allergies</span><span class="card-header-sub">Critical for
                            prescribing safety</span></div>
                    <div class="card-body">
                        <div class="dynamic-list">
                            <div class="dynamic-list-header">
                                <span class="dynamic-list-title">Drug Allergies</span>
                                <button class="add-row-btn" onclick="addAllergyRow('drug-allergy-rows','drug')">+ Add
                                    Drug Allergy</button>
                            </div>
                            <div class="dynamic-row"
                                style="grid-template-columns:2fr 2fr 1fr 32px;font-size:11px;font-weight:700;color:var(--muted);background:var(--surface);">
                                <span>Medication / Drug</span><span>Reaction /
                                    Symptom</span><span>Severity</span><span></span>
                            </div>
                            <div id="drug-allergy-rows">
                                <div class="dynamic-row" style="grid-template-columns:2fr 2fr 1fr 32px;">
                                    <input type="text" placeholder="e.g. Penicillin, Sulfa, NSAIDs"
                                        style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                    <input type="text" placeholder="e.g. Hives, Anaphylaxis, Rash"
                                        style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                    <select
                                        style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;">
                                        <option>Mild</option>
                                        <option>Moderate</option>
                                        <option>Severe</option>
                                        <option>Life-threatening</option>
                                    </select>
                                    <button class="remove-btn" onclick="removeRow(this)">×</button>
                                </div>
                            </div>
                        </div>

                        <div class="dynamic-list" style="margin-top:16px;">
                            <div class="dynamic-list-header">
                                <span class="dynamic-list-title">Food Allergies</span>
                                <button class="add-row-btn" onclick="addAllergyRow('food-allergy-rows','food')">+ Add
                                    Food Allergy</button>
                            </div>
                            <div class="dynamic-row"
                                style="grid-template-columns:2fr 2fr 1fr 32px;font-size:11px;font-weight:700;color:var(--muted);background:var(--surface);">
                                <span>Food Item</span><span>Reaction</span><span>Severity</span><span></span>
                            </div>
                            <div id="food-allergy-rows">
                                <div class="dynamic-row" style="grid-template-columns:2fr 2fr 1fr 32px;">
                                    <input type="text" placeholder="e.g. Peanuts, Shellfish, Dairy"
                                        style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                    <input type="text" placeholder="e.g. Swelling, GI distress"
                                        style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                    <select
                                        style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;">
                                        <option>Mild</option>
                                        <option>Moderate</option>
                                        <option>Severe</option>
                                        <option>Life-threatening</option>
                                    </select>
                                    <button class="remove-btn" onclick="removeRow(this)">×</button>
                                </div>
                            </div>
                        </div>

                        <div class="field" style="margin-top:16px;"><label>Environmental / Other Allergies <span
                                    class="hint">(latex, pollen, pet dander, contrast dye, etc.)</span></label><textarea
                               name="environmental_allergies" placeholder="List environmental allergies and reactions..."
                                style="width:100%;min-height:100px;padding:10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:13px;"></textarea>
                        </div>
                        <div class="field" style="margin-top:12px;"><label>No Known Drug Allergies (NKDA)</label>
                            <div class="radio-group"><label class="radio-pill"><input type="radio" name="nkda"
                                        onchange="selectPill(this)"  value="Yes"/>Yes — No known drug allergies</label><label
                                    class="radio-pill"><input type="radio"  value="No" name="nkda" onchange="selectPill(this)" />No
                                    — I have drug allergies (listed above)</label></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 9 — SURGICAL HISTORY
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-10">
                <div class="section-header">
                    <div class="section-tag">Section 11 of 15</div>
                    <div class="section-title">Surgical History</div>
                    <div class="section-desc">List all past surgeries, procedures, and hospitalizations in order from
                        most recent.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🔪</span><span
                            class="card-header-title">Past Surgeries & Procedures</span></div>
                    <div class="card-body">
                        <div class="dynamic-list">
                            <div class="dynamic-list-header">
                                <span class="dynamic-list-title">Surgical Procedures</span>
                                <button class="add-row-btn" onclick="addSurgRow()">+ Add Surgery</button>
                            </div>
                            <div class="dynamic-row"
                                style="grid-template-columns:2fr 1fr 2fr 1fr 32px;font-size:11px;font-weight:700;color:var(--muted);background:var(--surface);">
                                <span>Procedure / Surgery</span><span>Year</span><span>Hospital /
                                    Facility</span><span>Complications</span><span></span>
                            </div>
                            <div id="surg-rows">
                                <div class="dynamic-row" style="grid-template-columns:2fr 1fr 2fr 1fr 32px;">
                                    <input type="text" placeholder="e.g. Appendectomy, C-section, Knee replacement"
                                        style="padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;" />
                                    <input type="text" placeholder="Year"
                                        style="padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;" />
                                    <input type="text" placeholder="Hospital or clinic name"
                                        style="padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;" />
                                    <select
                                        style="padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;">
                                        <option>None</option>
                                        <option>Minor</option>
                                        <option>Major</option>
                                    </select>
                                    <button class="remove-btn" onclick="removeRow(this)">×</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-grid g2" style="margin-top:20px;">
                            <div class="field"><label>Anesthesia Complications <span class="hint">(past reactions to
                                        anesthesia)</span></label><textarea
                                name="anesthesia_complications" placeholder="Describe any reactions to anesthesia or none..."></textarea></div>
                            <div class="field"><label>Hospitalizations <span class="hint">(not surgical — illness,
                                        injury, psychiatric)</span></label><textarea
                                  name="hospitalizations" placeholder="Year, reason, facility for significant hospitalizations..."></textarea>
                            </div>
                        </div>
                        <div class="field" style="margin-top:12px;"><label>Blood Transfusions</label>
                            <div class="radio-group"><label class="radio-pill"><input type="radio" name="transfusion"
                                        onchange="selectPill(this)" value="Yes" />Yes</label><label class="radio-pill"><input
                                        type="radio" name="transfusion" onchange="selectPill(this)" value="No" />No</label></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 10 — FAMILY HEALTH HISTORY
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-11">
                <div class="section-header">
                    <div class="section-tag">Section 12 of 15</div>
                    <div class="section-title">Family Health History</div>
                    <div class="section-desc">Check conditions that run in your biological family. This helps identify
                        genetic risk factors for your care.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">👨‍👩‍👧</span><span
                            class="card-header-title">Family Conditions</span><span class="card-header-sub">Check all
                            that apply and note which relative</span></div>
                    <div class="card-body">
                        <div class="dynamic-list">
                            <div class="dynamic-list-header">
                                <span class="dynamic-list-title">Family History of Conditions</span>
                                <button class="add-row-btn" onclick="addFamilyRow()">+ Add Condition</button>
                            </div>
                            <div class="dynamic-row"
                                style="grid-template-columns:2fr 2fr 1fr 32px;font-size:11px;font-weight:700;color:var(--muted);background:var(--surface);">
                                <span>Condition</span><span>Relative(s)</span><span>Age of Onset</span><span></span>
                            </div>
                            <div id="family-rows">
                                <div class="dynamic-row" style="grid-template-columns:2fr 2fr 1fr 32px;">
                                    
                                <select
    id="family_history_condition"
    style="padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;">
</select>
                                
                                    <input type="text" placeholder="e.g. Father, Mother, Both parents, Sibling"
                                        style="padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;" />
                                    <input type="text" placeholder="Age or decade"
                                        style="padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;" />
                                    <button class="remove-btn" onclick="removeRow(this)">×</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-grid g2" style="margin-top:20px;">
                            <div class="field"><label>Father — Status & Cause of Death <span class="hint">(if
                                        deceased)</span></label><input type="text" name="dc_father"
                                    placeholder="Age, cause of death or 'Living, age X'" /></div>
                            <div class="field"><label>Mother — Status & Cause of Death <span class="hint">(if
                                        deceased)</span></label><input type="text" name="dc_mother"
                                    placeholder="Age, cause of death or 'Living, age X'" /></div>
                        </div>
                        <div class="field" style="margin-top:16px;"><label>Additional Family History
                                Notes</label><textarea name="additional_history"
                                placeholder="Any other relevant family health information, genetic testing results, hereditary conditions..."></textarea>
                        </div>
                        <div class="field" style="margin-top:12px;"><label>Family History Unknown</label>
                            <div class="radio-group"><label class="radio-pill"><input type="radio" name="famhxunknown"
                                        value="Yes — adopted or unknown" onchange="selectPill(this)" />Yes — adopted or
                                    unknown</label><label class="radio-pill"><input type="radio" name="famhxunknown"
                                        value="No — history is known" onchange="selectPill(this)" />No — history is
                                    known</label></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 11 — SOCIAL HISTORY
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-12">
                <div class="section-header">
                    <div class="section-tag">Section 13 of 15</div>
                    <div class="section-title">Social History</div>
                    <div class="section-desc">Lifestyle and behavioral factors that affect your health. All responses
                        are confidential and used only for your care.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🚬</span><span
                            class="card-header-title">Tobacco Use</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Tobacco Status</label>
                                <div class="radio-group">
                                    <label class="radio-pill"><input type="radio" name="tobacco" value="Never"
                                            onchange="selectPill(this)" />Never</label>
                                    <label class="radio-pill"><input type="radio" name="tobacco" value="Former"
                                            onchange="selectPill(this)" />Former</label>
                                    <label class="radio-pill"><input type="radio" name="tobacco" value="Current"
                                            onchange="selectPill(this)" />Current</label>
                                </div>
                            </div>
                            <div class="field"><label>Product Type</label>
                                <select name="tobacco_product_type">
                                    <option value="">-- Select --</option>
                                    <option value="Cigarettes">Cigarettes</option>
                                    <option value="Cigars">Cigars</option>
                                    <option value="Pipe">Pipe</option>
                                    <option value="Chewing tobacco">Chewing tobacco</option>
                                    <option value="E-cigarette / Vape">E-cigarette / Vape</option>
                                    <option value="Hookah">Hookah</option>
                                    <option value="Multiple">Multiple</option>
                                </select>
                            </div>
                            <div class="field"><label>Packs per Day / Amount</label>
                                <input type="text" name="tobacco_amount" placeholder="e.g. 1 ppd, 5 cigarettes/day" />
                            </div>
                            <div class="field"><label>Years Used</label><input type="text" name="tobacco_years"
                                    placeholder="Number of years" /></div>
                            <div class="field"><label>Quit Date <span class="hint">(if former)</span></label><input
                                    type="date" name="tobacco_quit_date" /></div>
                            <div class="field"><label>Interested in Cessation?</label>
                                <select name="tobacco_cessation_interest">
                                    <option value="">-- Select --</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                    <option value="Already quit">Already quit</option>
                                    <option value="Not applicable">Not applicable</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🍺</span><span
                            class="card-header-title">Alcohol Use</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Alcohol Use</label>
                                <div class="radio-group"><label class="radio-pill"><input type="radio" name="alcohol"
                                            value="Never" onchange="selectPill(this)" />Never</label><label
                                        class="radio-pill"><input type="radio" name="alcohol" value="Former"
                                            onchange="selectPill(this)" />Former</label><label class="radio-pill"><input
                                            type="radio" name="alcohol" value="Current"
                                            onchange="selectPill(this)" />Current</label></div>
                            </div>
                            <div class="field"><label>Drinks per Week</label><input type="number"
                                    name="alcohol_drinks_per_week" placeholder="# drinks/week" min="0" /></div>
                            <div class="field"><label>Type of Alcohol</label>
                                <select name="alcohol_type">
                                    <option value="">-- Select --</option>
                                    <option value="Beer">Beer</option>
                                    <option value="Wine">Wine</option>
                                    <option value="Spirits / Liquor">Spirits / Liquor</option>
                                    <option value="Mixed">Mixed</option>
                                </select>
                            </div>
                            <div class="field span3"><label>AUDIT-C Screening <span class="hint">(How often do you have
                                        6+ drinks on one occasion?)</span></label>
                                <div class="radio-group"><label class="radio-pill"><input type="radio" name="audit"
                                            value="Never" onchange="selectPill(this)" />Never</label><label
                                        class="radio-pill"><input type="radio" name="audit" value="Less than monthly"
                                            onchange="selectPill(this)" />Less than monthly</label><label
                                        class="radio-pill"><input type="radio" name="audit" value="Monthly"
                                            onchange="selectPill(this)" />Monthly</label><label
                                        class="radio-pill"><input type="radio" name="audit" value="Weekly"
                                            onchange="selectPill(this)" />Weekly</label><label class="radio-pill"><input
                                            type="radio" name="audit" value="Daily / almost daily"
                                            onchange="selectPill(this)" />Daily / almost daily</label></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">💉</span><span
                            class="card-header-title">Substance Use</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field"><label>Recreational Drug Use</label>
                                <div class="radio-group"><label class="radio-pill"><input type="radio" name="drugs"
                                            value="Never" onchange="selectPill(this)" />Never</label><label
                                        class="radio-pill"><input type="radio" name="drugs" value="Former"
                                            onchange="selectPill(this)" />Former</label><label class="radio-pill"><input
                                            type="radio" name="drugs" value="Current"
                                            onchange="selectPill(this)" />Current</label></div>
                            </div>
                            <div class="field"><label>Substances Used <span
                                        class="hint">(confidential)</span></label><input type="text"
                                    name="substances_used"
                                    placeholder="e.g. Cannabis, opioids — or 'Decline to specify'" /></div>
                            <div class="field"><label>Frequency</label>
                                <select name="drug_frequency">
                                    <option value="">-- Select --</option>
                                    <option value="Daily">Daily</option>
                                    <option value="Weekly">Weekly</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Occasionally">Occasionally</option>
                                    <option value="Former use only">Former use only</option>
                                </select>
                            </div>
                            <div class="field"><label>Interested in Treatment / Support?</label><select
                                    name="drug_treatment_interest">
                                    <option>-- Select --</option>
                                    <option>Yes</option>
                                    <option>No</option>
                                    <option>Already in treatment</option>
                                </select></div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🏃</span><span
                            class="card-header-title">Physical Activity & Diet</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Exercise Frequency</label><select name="exercise_frequency">
                                    <option>-- Select --</option>
                                    <option>Daily</option>
                                    <option>3–5x per week</option>
                                    <option>1–2x per week</option>
                                    <option>Rarely</option>
                                    <option>Never</option>
                                </select></div>
                            <div class="field"><label>Exercise Type</label><input type="text" name="exercise_type"
                                    placeholder="e.g. Walking, gym, swimming" /></div>
                            <div class="field"><label>Minutes per Session</label><input type="number"
                                    name="exercise_minutes" placeholder="e.g. 30" min="0" /></div>
                            <div class="field"><label>Diet Type</label><select name="diet_type">
                                    <option>-- Select --</option>
                                    <option>Standard / No restrictions</option>
                                    <option>Vegetarian</option>
                                    <option>Vegan</option>
                                    <option>Gluten-free</option>
                                    <option>Diabetic diet</option>
                                    <option>Low-sodium</option>
                                    <option>Low-carb / Keto</option>
                                    <option>Halal</option>
                                    <option>Kosher</option>
                                    <option>Other</option>
                                </select></div>
                            <div class="field"><label>Daily Water Intake</label><select name="water_intake">
                                    <option>-- Select --</option>
                                    <option>Less than 4 cups</option>
                                    <option>4–8 cups</option>
                                    <option>8+ cups</option>
                                </select></div>
                            <div class="field"><label>Sleep Hours per Night</label><input type="number"
                                    name="sleep_hours" placeholder="e.g. 7" min="0" max="24" /></div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🏠</span><span
                            class="card-header-title">Living Situation & Safety</span></div>
                    <div class="card-body">
                        <div class="form-grid g3">
                            <div class="field"><label>Living Situation</label><select name="living_situation">
                                    <option>-- Select --</option>
                                    <option>Lives alone</option>
                                    <option>Lives with spouse / partner</option>
                                    <option>Lives with family</option>
                                    <option>Lives with roommates</option>
                                    <option>Group home / assisted living</option>
                                    <option>Homeless / unstably housed</option>
                                </select></div>
                            <div class="field"><label>Highest Education Level</label><select name="education_level">
                                    <option>-- Select --</option>
                                    <option>Less than high school</option>
                                    <option>High school / GED</option>
                                    <option>Some college</option>
                                    <option>Associate's degree</option>
                                    <option>Bachelor's degree</option>
                                    <option>Graduate degree</option>
                                </select></div>
                            <div class="field"><label>Occupation Category</label><select name="occupation_category">
                                    <option>-- Select --</option>
                                    <option>Healthcare worker</option>
                                    <option>Teacher / Education</option>
                                    <option>Manual / Physical labor</option>
                                    <option>Office / Administrative</option>
                                    <option>Food service</option>
                                    <option>Agriculture</option>
                                    <option>Caregiver</option>
                                    <option>Unemployed</option>
                                    <option>Student</option>
                                    <option>Retired</option>
                                </select></div>
                            <div class="field"><label>Domestic Violence — Do you feel safe at home?</label>
                                <div class="radio-group"><label class="radio-pill"><input type="radio" name="dvsafety"
                                            value="Yes" onchange="selectPill(this)" />Yes</label><label
                                        class="radio-pill"><input type="radio" name="dvsafety" value="No"
                                            onchange="selectPill(this)" />No</label><label class="radio-pill"><input
                                            type="radio" name="dvsafety" value="Prefer not to say"
                                            onchange="selectPill(this)" />Prefer not to say</label></div>
                            </div>
                            <div class="field"><label>Seat Belt Use</label>
                                <div class="radio-group"><label class="radio-pill"><input type="radio" name="seatbelt"
                                            value="Always" onchange="selectPill(this)" />Always</label><label
                                        class="radio-pill"><input type="radio" name="seatbelt" value="Sometimes"
                                            onchange="selectPill(this)" />Sometimes</label><label
                                        class="radio-pill"><input type="radio" name="seatbelt" value="Never"
                                            onchange="selectPill(this)" />Never</label></div>
                            </div>
                            <div class="field"><label>Firearms in Household</label>
                                <div class="radio-group"><label class="radio-pill"><input type="radio" name="firearms"
                                            value="Yes" onchange="selectPill(this)" />Yes</label><label
                                        class="radio-pill"><input type="radio" name="firearms" value="No"
                                            onchange="selectPill(this)" />No</label><label class="radio-pill"><input
                                            type="radio" name="firearms" value="Decline"
                                            onchange="selectPill(this)" />Decline</label></div>
                            </div>
                        </div>
                        <div class="field" style="margin-top:16px;"><label>Additional Social History
                                Notes</label><textarea name="social_history_notes"
                                placeholder="Military service, recent life stressors, caregiver responsibilities, other relevant context..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 13 — RELATED PERSONS
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-13">
                <div class="section-header">
                    <div class="section-tag">Section 14 of 15</div>
                    <div class="section-title">Related Persons</div>
                    <div class="section-desc">Guardians, guarantors, authorized contacts, and representatives for this
                        patient.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">👥</span><span
                            class="card-header-title">Related Persons List</span><span class="card-header-sub">Add
                            guardians, guarantors, contacts</span></div>
                    <div class="card-body">
                        <div class="dynamic-list" id="related-list">
                            <div class="dynamic-list-header">
                                <span class="dynamic-list-title">Related Persons</span>
                                <button class="add-row-btn" onclick="addRelatedPersonRow()">+ Add Related
                                    Person</button>
                            </div>
                            <div class="dynamic-row" id="related-rows"
                                style="grid-template-columns: 2fr 1.5fr 1fr 1.5fr 1.5fr 1fr 32px;">
                                <input type="text" placeholder="Full Name"
                                    style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                <select
                                    style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;">
                                    <option>Spouse</option>
                                    <option>Parent</option>
                                    <option>Child</option>
                                    <option>Sibling</option>
                                    <option>Guardian</option>
                                    <option>Guarantor</option>
                                    <option>POA</option>
                                    <option>Caregiver</option>
                                    <option>Other</option>
                                </select>
                                <select
                                    style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;">
                                    <option>M</option>
                                    <option>F</option>
                                    <option>Other</option>
                                </select>
                                <input type="text" placeholder="Phone"
                                    style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                <input type="email" placeholder="Email"
                                    style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                <input type="text" placeholder="City"
                                    style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;" />
                                <button class="remove-btn" onclick="removeRow(this)">×</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════
         SECTION 14 — CONSENT
    ══════════════════════════════════════ -->
            <div class="section-page" id="section-14">
                <div class="section-header">
                    <div class="section-tag">Section 15 of 15</div>
                    <div class="section-title">Consent & Authorization</div>
                    <div class="section-desc">Please review and sign the following consents to complete your
                        registration.</div>
                    <div class="section-divider"></div>
                </div>

                <div class="hipaa-notice">
                    <div class="hipaa-title">🔒 HIPAA Notice of Privacy Practices</div>
                    <div class="hipaa-text">This practice is required by law to protect the privacy of your health
                        information and to provide you with notice of our legal duties and privacy practices with
                        respect to protected health information. We will use and disclose your protected health
                        information only as allowed by law. For a full copy of our Notice of Privacy Practices, please
                        ask a front desk staff member or visit our patient portal.</div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">📋</span><span
                            class="card-header-title">Consent to Treatment</span></div>
                    <div class="card-body">
                        <div class="field">
                            <p style="font-size:13px;color:var(--body);line-height:1.6;margin-bottom:16px;">I hereby
                                consent to medical treatment by the providers of this practice, including examinations,
                                diagnostic procedures, and treatments deemed necessary. I understand that I have the
                                right to ask questions, refuse treatment, and to be informed about my condition and care
                                plan. I authorize this practice to use my health information for treatment, payment, and
                                healthcare operations as described in the HIPAA Notice of Privacy Practices.</p>
                            <label class="check-pill" style="display:inline-flex;"><input type="checkbox"
                                    name="consent_to_treatment" value="1" onchange="togglePill(this)" /> I consent to
                                treatment and acknowledge receipt of the Notice of Privacy Practices</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">💳</span><span
                            class="card-header-title">Financial Responsibility & Insurance Authorization</span></div>
                    <div class="card-body">
                        <div class="field">
                            <p style="font-size:13px;color:var(--body);line-height:1.6;margin-bottom:16px;">I authorize
                                this practice to bill my insurance company on my behalf and agree to be responsible for
                                any balance not covered by insurance, including co-pays, deductibles, and non-covered
                                services. I authorize release of any medical records needed for billing purposes.</p>
                            <label class="check-pill" style="display:inline-flex;"><input type="checkbox"
                                    name="financial_responsibility_consent" value="1" onchange="togglePill(this)" /> I
                                authorize insurance billing and accept financial responsibility for non-covered
                                charges</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">📱</span><span
                            class="card-header-title">Digital Communications Consent</span></div>
                    <div class="card-body">
                        <div class="field">
                            <p style="font-size:13px;color:var(--body);line-height:1.6;margin-bottom:16px;">I consent to
                                receive appointment reminders, test results, and health information via the Synapta
                                patient portal, email, and/or SMS text messages to the contact information provided. I
                                understand I may opt out at any time.</p>
                            <label class="check-pill" style="display:inline-flex;"><input type="checkbox"
                                    name="digital_communications_consent" value="1" onchange="togglePill(this)" /> I
                                consent to digital communications including portal messages, email, and SMS</label>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">✍️</span><span
                            class="card-header-title">Patient Signature</span></div>
                    <div class="card-body">
                        <div class="form-grid g3" style="margin-bottom:16px;">
                            <div class="field"><label>Printed Name <span class="req">*</span></label><input type="text"
                                    name="signature_printed_name" placeholder="Full legal name" /></div>
                            <div class="field"><label>Date <span class="req">*</span></label><input type="date"
                                    name="signature_date" /></div>
                            <div class="field"><label>Relationship to Patient <span class="hint">(if signing for
                                        another)</span></label><select name="signature_relationship">
                                    <option>Self (Patient)</option>
                                    <option>Parent / Guardian</option>
                                    <option>Legal Representative</option>
                                    <option>Power of Attorney</option>
                                </select></div>
                        </div>
                        <div class="field"><label>Signature <span class="req">*</span></label><input type="text"
                                name="signature_digital" placeholder="Click to sign digitally or enter signature"
                                value="✍️ Signed digitally" style="cursor: pointer;"
                                onclick="this.value='✍️ Signed digitally — ' + new Date().toLocaleDateString()" /></div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="card-header"><span class="card-header-icon">🩺</span><span class="card-header-title">How
                            did you hear about us?</span></div>
                    <div class="card-body">
                        <div class="form-grid g2">
                            <div class="field"><label>Referral Source</label><select name="consentAuthorization_referral_source">
                                    <option>-- Select --</option>
                                    <option>Physician referral</option>
                                    <option>Friend / Family</option>
                                    <option>Online search</option>
                                    <option>Insurance directory</option>
                                    <option>Social media</option>
                                    <option>Community Health Worker</option>
                                    <option>Walk-in</option>
                                    <option>Other</option>
                                </select></div>
                            <div class="field"><label>Referring Provider Name <span class="hint">(if
                                        applicable)</span></label><input type="text" name="referring_provider_name"
                                    placeholder="Referring provider name" /></div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- ── FOOTER ACTIONS ── -->
    <div class="form-footer">
        <div class="footer-progress">Section <strong id="currentSectionLabel">1</strong> of <strong>15</strong></div>
        <div class="footer-btns">
            <button class="btn-back" id="btnBack" onclick="prevSection()" style="display:none;">← Back</button>
            <button class="btn-next" id="btnNext" onclick="nextSection()">Continue →</button>
            <button class="btn-submit" id="btnSubmit" onclick="submitForm()" style="display:none;">✓ Submit Intake
                Form</button>
        </div>
    </div>

    <script>
    let currentSection = 0;
    const totalSections = 15;

    const familyHistoryConditions = [
    'Heart Disease',
    'High Blood Pressure',
    'Stroke',
    'Diabetes (Type 1)',
    'Diabetes (Type 2)',
    'Cancer (specify)',
    'Breast Cancer',
    'Colon Cancer',
    'Prostate Cancer',
    'Lung Cancer',
    'Ovarian Cancer',
    'Mental Illness',
    'Depression / Anxiety',
    'Bipolar Disorder',
    'Schizophrenia',
    "Alzheimer's / Dementia",
    "Parkinson's Disease",
    'Epilepsy',
    'Asthma',
    'COPD',
    'Kidney Disease',
    'Liver Disease',
    'Osteoporosis',
    'Thyroid Disease',
    'Autoimmune Disorder',
    'HIV/AIDS',
    'Substance Use Disorder',
    'Suicide',
    'Other'
];

document.addEventListener('DOMContentLoaded', function () {

    const select = document.getElementById('family_history_condition');

    if (select) {
        select.innerHTML = buildConditionOptions();
    }

});

function buildConditionOptions(selectedValue = '') {
    let html = '<option value="">-- Select condition --</option>';

    familyHistoryConditions.forEach(condition => {
        html += `
            <option value="${condition}"
                ${selectedValue === condition ? 'selected' : ''}>
                ${condition}
            </option>
        `;
    });

    return html;
}

    function goToSection(n) {
        document.querySelectorAll('.section-page').forEach((el, i) => el.classList.toggle('active', i === n));
        document.querySelectorAll('.step-btn').forEach((el, i) => {
            el.classList.remove('active', 'done');
            if (i === n) el.classList.add('active');
            else if (i < n) el.classList.add('done');
        });
        document.querySelectorAll('.nav-item').forEach((el, i) => el.classList.toggle('active', i === n));
        currentSection = n;
        document.getElementById('currentSectionLabel').textContent = n + 1;
        document.getElementById('btnBack').style.display = n === 0 ? 'none' : '';
        document.getElementById('btnNext').style.display = n === totalSections - 1 ? 'none' : '';
        document.getElementById('btnSubmit').style.display = n === totalSections - 1 ? '' : 'none';
        document.getElementById('mainContent').scrollTop = 0;
    }

    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Validate email fields in current section
    function validateEmailFields() {
        const currentSectionEl = document.getElementById(`section-${currentSection}`);
        const emailInputs = currentSectionEl.querySelectorAll('input[type="email"]');

        for (let email of emailInputs) {
            if (email.value.trim() !== '') {
                if (!isValidEmail(email.value.trim())) {
                    const label = email.closest('.field').querySelector('label').textContent.replace('*', '').trim();
                    alert(
                        `❌ Invalid email format in "${label}"\n\nPlease enter a valid email address (e.g., user@example.com)`);
                    email.focus();
                    return false;
                }
            }
        }
        return true;
    }

    // Validate required fields in current section
    function validateRequiredFields() {
        const currentSectionEl = document.getElementById(`section-${currentSection}`);
        if (!currentSectionEl) return true;

        const missingFields = [];
        const allLabels = currentSectionEl.querySelectorAll('label');

        for (let label of allLabels) {
            // Check if this label has a required marker (*)
            if (label.querySelector('.req')) {
                const fieldName = label.textContent.replace('*', '').replace('(if applicable)', '').trim();
                const field = label.closest('.field');

                if (!field) continue;

                // Get the input, select, or textarea field
                let input = field.querySelector('input[type="text"]');
                if (!input) input = field.querySelector('input[type="tel"]');
                if (!input) input = field.querySelector('input[type="email"]');
                if (!input) input = field.querySelector('input[type="date"]');
                if (!input) input = field.querySelector('select');
                if (!input) input = field.querySelector('textarea');

                // Special handling for signature box
                let sigBox = field.querySelector('.sig-box');
                if (sigBox && !sigBox.textContent.includes('Signed')) {
                    missingFields.push(fieldName);
                    continue;
                }

                if (input) {
                    const value = input.value ? input.value.trim() : '';

                    // Check if empty or has default "-- Select --"
                    if (!value || value === '-- Select --' || value === '-- Select or Search --') {
                        missingFields.push(fieldName);
                    }
                }
            }
        }

        if (missingFields.length > 0) {
            alert('❌ Please fill in all required fields before continuing:\n\n• ' + missingFields.join('\n• '));
            return false;
        }

        return true;
    }

    function nextSection() {
        if (currentSection < totalSections - 1) {
            // Validate required fields first
            if (!validateRequiredFields()) {
                return;
            }
            // Validate email fields
            if (!validateEmailFields()) {
                return;
            }
            goToSection(currentSection + 1);
        }
    }

    function prevSection() {
        if (currentSection > 0) goToSection(currentSection - 1);
    }

    function togglePill(input) {
        input.closest('.check-pill').classList.toggle('selected', input.checked);
    }

    function selectPill(input) {
        const name = input.name;
       
        document.querySelectorAll(`input[name="${name}"]`).forEach(r => r.closest('.radio-pill').classList.remove(
            'selected'));
        input.closest('.radio-pill').classList.add('selected');
    }

    function selectSev(btn, val) {
        btn.closest('.severity-row').querySelectorAll('.sev-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('pain_score').value = val;
    }

    function loadPainScore(score) {

    document.getElementById('pain_score').value = score;

    document.querySelectorAll('.severity-row .sev-btn')
        .forEach(btn => {

            if (parseInt(btn.textContent) === parseInt(score)) {
                btn.classList.add('selected');
            } else {
                btn.classList.remove('selected');
            }
        });
}

    function addMedRow() {
        const container = document.getElementById('med-list') || document.getElementById('med-rows').parentElement;
        const row = document.createElement('div');
        row.className = 'dynamic-row';
        row.style.gridTemplateColumns = '2fr 1fr 1fr 1fr 1.5fr 32px';
        row.innerHTML =
            `<input type="text" placeholder="Medication name" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><input type="text" placeholder="Dose" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option>Daily</option><option>Twice daily</option><option>Three times daily</option><option>As needed</option></select><select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option>Oral</option><option>Topical</option><option>Injection</option><option>Inhaled</option></select><input type="text" placeholder="Dr. Name" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><button class="remove-btn" onclick="removeRow(this)">×</button>`;
        container.appendChild(row);
    }

    function addAllergyRow(id, type) {
        const container = document.getElementById(id);
        const row = document.createElement('div');
        row.className = 'dynamic-row';
        row.style.gridTemplateColumns = '2fr 2fr 1fr 32px';
        row.innerHTML =
            `<input type="text" placeholder="${type==='drug'?'Drug / Medication':'Food item'}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><input type="text" placeholder="Reaction / Symptom" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option>Mild</option><option>Moderate</option><option>Severe</option><option>Life-threatening</option></select><button class="remove-btn" onclick="removeRow(this)">×</button>`;
        container.appendChild(row);
    }

    function addSurgRow() {
        const container = document.getElementById('surg-rows');
        const row = document.createElement('div');
        row.className = 'dynamic-row';
        row.style.gridTemplateColumns = '2fr 1fr 2fr 1fr 32px';
        row.innerHTML =
            `<input type="text" placeholder="Procedure name" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><input type="text" placeholder="Year" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><input type="text" placeholder="Hospital / facility" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option>None</option><option>Minor</option><option>Major</option></select><button class="remove-btn" onclick="removeRow(this)">×</button>`;
        container.appendChild(row);
    }

    function addFamilyRow() {
        const container = document.getElementById('family-rows');
        const row = document.createElement('div');
        row.className = 'dynamic-row';
        row.style.gridTemplateColumns = '2fr 2fr 1fr 32px';
        // row.innerHTML =
        //     `<select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option>-- Select condition --</option><option>Heart Disease</option><option>Diabetes (Type 2)</option><option>Cancer</option><option>Stroke</option><option>Mental Illness</option><option>Kidney Disease</option><option>Other</option></select><input type="text" placeholder="e.g. Father, Mother" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><input type="text" placeholder="Age / decade" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><button class="remove-btn" onclick="removeRow(this)">×</button>`;
       row.innerHTML = `
    <select
        style="width:100%;
               padding:8px 6px;
               border:1.5px solid var(--border);
               border-radius:6px;
               font-family:var(--sans);
               font-size:12px;
               box-sizing:border-box;">
        ${buildConditionOptions()}
    </select>

    <input type="text"
           placeholder="e.g. Father, Mother"
           style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>

    <input type="text"
           placeholder="Age / decade"
           style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>

    <button class="remove-btn" onclick="removeRow(this)">×</button>
`;
        container.appendChild(row);
    }

    function removeRow(btn) {
        const row = btn.closest('.dynamic-row');
        row.remove();
    }

    function addRelatedPersonRow() {
        const container = document.getElementById('related-rows').parentElement;
        const row = document.createElement('div');
        row.className = 'dynamic-row';
        row.style.gridTemplateColumns = '2fr 1.5fr 1fr 1.5fr 1.5fr 1fr 32px';
        row.innerHTML =
            `<input type="text" placeholder="Full Name" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option>Spouse</option><option>Parent</option><option>Child</option><option>Sibling</option><option>Guardian</option><option>Guarantor</option><option>POA</option><option>Caregiver</option><option>Other</option></select><select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option>M</option><option>F</option><option>Other</option></select><input type="tel" placeholder="Phone" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><input type="email" placeholder="Email" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><input type="text" placeholder="City" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/><button class="remove-btn" onclick="removeRow(this)">×</button>`;
        container.appendChild(row);
    }

    function saveProgress() {
        alert('✓ Progress saved. You can return to complete the form at any time using your patient portal link.');
    }
    // ============================================
    // LOAD PATIENT DATA FOR EDITING
    // ============================================

    let currentPatientPID = <?php echo $edit_pid ? $edit_pid : 'null'; ?>;
    let isEditMode = <?php echo $form_mode === 'edit' ? 'true' : 'false'; ?>;

    // Load patient data from database when form loads in edit mode
    async function loadPatientData() {
        if (!isEditMode || !currentPatientPID) {
            console.log('DEBUG: Not in edit mode or no PID. isEditMode=' + isEditMode + ', pid=' +
                currentPatientPID);
            return; // Skip if not in edit mode
        }

        console.log('DEBUG: Loading patient data for PID=' + currentPatientPID);

        try {
            // Try relative path first (more reliable)
            const url = `patient_get_simple.php?pid=${currentPatientPID}`;
            console.log('DEBUG: Fetching from URL: ' + url);

            const response = await fetch(url);
            console.log('DEBUG: Response status: ' + response.status);

            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const data = await response.json();
            console.log('DEBUG: Response data:', data);

            if (!data.success) {
                alert('❌ Error loading patient data: ' + data.message);
                return;
            }

            // Populate form with patient data
            console.log('DEBUG: About to call populatePatientForm');
            try {
                populatePatientForm(data);
                console.log('DEBUG: populatePatientForm completed successfully');
            } catch (error) {
                console.error('ERROR in populatePatientForm:', error);
            }

            // Show success message
            console.log('✅ Patient data loaded successfully');

        } catch (error) {
            console.error('Error loading patient data:', error);
            alert('❌ Failed to load patient data: ' + error.message +
            '\n\nCheck browser console (F12) for details');
        }
    }

    // Helper function to safely set field values
    function setFieldValue(fieldName, value) {
        if (value !== null && value !== undefined && value !== '') {
            const field = document.querySelector(`[name="${fieldName}"]`);

            // Handle RADIO BUTTONS
            if (field && field.type === 'radio') {
                const radioButtons = document.querySelectorAll(`input[type="radio"][name="${fieldName}"]`);
                let found = false;

                for (let radio of radioButtons) {
                    if (radio.value === String(value) || radio.value.toLowerCase() === String(value).toLowerCase()) {
                        radio.checked = true;

                        // CRITICAL: Trigger the change event so onchange handlers (like selectPill) execute
                        radio.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));

                        found = true;
                        console.log(`✅ Set radio ${fieldName} = ${value} (checked & event triggered)`);
                        break;
                    }
                }

                if (!found) {
                    console.log(`⚠️  Radio value "${value}" not found for ${fieldName}`);
                }
                return;
            }

            if (field) {
                // For SELECT elements (dropdowns)
                if (field.tagName === 'SELECT') {
                    // Try exact match first
                    field.value = value;

                    // If no match, try to find by option text or numeric ID
                    if (!field.value || field.value === '') {
                        // Convert to string for comparison
                        const stringValue = String(value).toLowerCase().trim();
                        const options = field.querySelectorAll('option');

                        for (let option of options) {
                            if (option.textContent.toLowerCase().trim() === stringValue ||
                                option.value === stringValue ||
                                option.value === value) {
                                field.value = option.value;
                                break;
                            }
                        }
                    }

                    if (field.value) {
                        console.log(`✅ Set dropdown ${fieldName} = ${value}`);
                    } else {
                        console.log(`⚠️  Value "${value}" not found in ${fieldName} dropdown options`);
                    }
                } else {
                    // For text inputs, dates, etc.
                    field.value = value;
                    console.log(`✅ Set ${fieldName} = ${value}`);
                }
            } else {
                console.log(`❌ Field not found: ${fieldName}`);
            }
        }
    }

    // Populate form fields with patient data
    function populatePatientForm(data) {
        console.log('DEBUG: populatePatientForm called with data:', data);

        const patientData = data.patient_data || {};
        const employerData = data.employer_data || {};
        const historyData = data.history_data || {};
        const chiefComplaintData = data.chief_complaint_data || {};
        const listData = data.lists_data || [];
        const insuranceData = data.insurance_data || [];
        const choicesPreferencesData = data.choices_preferences_data || {};
        const demographicsSocialData = data.demographics_social_data || {};
        const relatedPersonsData = data.related_persons_data || [];
        const socialHistoryData = data.social_history_data || {};
        const consentAuthorizationData = data.consent_authorization_data || {};
        const rosData = data.review_of_systems_data || {};


        console.log('DEBUG: employerData:', employerData);
        console.log('DEBUG: historyData:', historyData);
        console.log('DEBUG: listData:', listData);
        console.log('DEBUG: insuranceData:', insuranceData);
        console.log('DEBUG: chiefComplaintData:', chiefComplaintData);
        console.log('DEBUG: choicesPreferencesData:', choicesPreferencesData);
        console.log('DEBUG: demographicsSocialData:', demographicsSocialData);
        console.log('DEBUG: relatedPersonsData:', relatedPersonsData);
        console.log('DEBUG: socialHistoryData:', socialHistoryData);
        console.log('DEBUG: consentAuthorizationData:', consentAuthorizationData);

        // ============================================
        // Section 1: Basic Information
        // ============================================

        setFieldValue('title', patientData.title);
        setFieldValue('fname', patientData.fname);
        setFieldValue('mname', patientData.mname);
        setFieldValue('lname', patientData.lname);
        setFieldValue('suffix', patientData.suffix);
        setFieldValue('preferred_name', patientData.preferred_name);
        setFieldValue('birth_fname', patientData.birth_fname);
        setFieldValue('birth_mname', patientData.birth_mname);
        setFieldValue('birth_lname', patientData.birth_lname);

        // ============================================
        // Section 2: Demographics
        // ============================================

        if (patientData.DOB) {
            const dobField = document.querySelector('[name="DOB"]');
            if (dobField) {
                dobField.value = formatDateForInput(patientData.DOB);
            }
        }

        setFieldValue('sex', patientData.sex);
        setFieldValue('gender_identity', patientData.gender_identity);
        setFieldValue('marital_status', patientData.marital_status);
        setFieldValue('pronoun', patientData.pronoun);
        setFieldValue('sexual_orientation', patientData.sexual_orientation);
        setFieldValue('race', patientData.race);
        setFieldValue('ethnicity', patientData.ethnicity);
        setFieldValue('language', patientData.language);
        setFieldValue('religion', patientData.religion);
        setFieldValue('interpreter_needed', patientData.interpreter_needed);
        setFieldValue('user_defined_field_1', patientData.user_defined_field_1);
        setFieldValue('user_defined_field_2', patientData.user_defined_field_2);
        setFieldValue('user_defined_field_3', patientData.user_defined_field_3);
        setFieldValue('user_defined_field_4', patientData.user_defined_field_4);
        setFieldValue('providerID', patientData.providerID);
        

        setFieldValue('additional_medical_conditions', patientData.additional_medical_conditions);
        setFieldValue('otc_medications', patientData.otc_medications);
        setFieldValue('herbal_remedies', patientData.herbal_remedies);
        setFieldValue('recently_stopped_medications', patientData.recently_stopped_medications);
        setFieldValue('environmental_allergies', patientData.environmental_allergies);
        setFieldValue('anesthesia_complications', patientData.anesthesia_complications);
        setFieldValue('hospitalizations', patientData.hospitalizations);

        
        
        
        

        // ============================================
        // Section 3: Choices & Preferences
        // ============================================

        // Provider & Pharmacy
        // 1. Log exactly what we have to work with
        console.log('DEBUG: Target Value to set:', choicesPreferencesData.providerID);

        // const pcpField = document.querySelector('[name="providerID"]');

        // setTimeout(() => {
        //     const pcp = document.querySelector('select[name="providerID"]');
        //     if (pcp) pcp.value = choicesPreferencesData
        //     .providerID; // or whatever the correct value is
        // }, 100);
        //setFieldValue('providerID', choicesPreferencesData.providerID);



        if (choicesPreferencesData.provider_since_date) {
            const providerDateField = document.querySelector('[name="provider_since_date"]');
            if (providerDateField) {
                providerDateField.value = formatDateForInput(choicesPreferencesData.provider_since_date);
            }
        }
        setFieldValue('referring_provider', choicesPreferencesData.referring_provider);
        setFieldValue('preferred_pharmacy', choicesPreferencesData.preferred_pharmacy);

        // Communication Permissions
        setFieldValue('hipaa_notice_received', choicesPreferencesData.hipaa_notice_received);
        setFieldValue('allow_voice_message', choicesPreferencesData.allow_voice_message);
        setFieldValue('voice_message_with', choicesPreferencesData.voice_message_with);
        setFieldValue('allow_mail_message', choicesPreferencesData.allow_mail_message);
        setFieldValue('allow_sms_text', choicesPreferencesData.allow_sms_text);
        setFieldValue('allow_email_message', choicesPreferencesData.allow_email_message);
        setFieldValue('allow_patient_portal', choicesPreferencesData.allow_patient_portal);
        setFieldValue('allow_imm_reg_use', choicesPreferencesData.allow_imm_reg_use);
        setFieldValue('allow_imm_info_share', choicesPreferencesData.allow_imm_info_share);
        setFieldValue('allow_health_info_ex', choicesPreferencesData.allow_health_info_ex);
        setFieldValue('cmsportal_login', choicesPreferencesData.cmsportal_login);

        // Registry & Compliance
        setFieldValue('imm_reg_status', choicesPreferencesData.imm_reg_status);
        if (choicesPreferencesData.imm_reg_stat_effdate) {
            const immRegField = document.querySelector('[name="imm_reg_stat_effdate"]');
            if (immRegField) {
                immRegField.value = formatDateForInput(choicesPreferencesData.imm_reg_stat_effdate);
            }
        }
        setFieldValue('publicity_code', choicesPreferencesData.publicity_code);
        if (choicesPreferencesData.publ_code_eff_date) {
            const publCodeField = document.querySelector('[name="publ_code_eff_date"]');
            if (publCodeField) {
                publCodeField.value = formatDateForInput(choicesPreferencesData.publ_code_eff_date);
            }
        }
        setFieldValue('protect_indicator', choicesPreferencesData.protect_indicator);
        if (choicesPreferencesData.prot_indi_effdate) {
            const protIndField = document.querySelector('[name="prot_indi_effdate"]');
            if (protIndField) {
                protIndField.value = formatDateForInput(choicesPreferencesData.prot_indi_effdate);
            }
        }
        setFieldValue('care_team_provider', choicesPreferencesData.care_team_provider);
        setFieldValue('care_team_status', choicesPreferencesData.care_team_status);
        setFieldValue('patient_category', choicesPreferencesData.patient_category);

        // ============================================
        // Section 4: Contact Information
        // ============================================

        setFieldValue('street', patientData.street);
        setFieldValue('street_line_2', patientData.street_line_2);
        setFieldValue('city', patientData.city);
        setFieldValue('state', patientData.state);
        setFieldValue('postal_code', patientData.postal_code);
        setFieldValue('county', patientData.county);
        setFieldValue('country_code', patientData.country_code);

        setFieldValue('phone_home', patientData.phone_home);
        setFieldValue('phone_cell', patientData.phone_cell);
        setFieldValue('phone_biz', patientData.phone_biz);
        setFieldValue('email', patientData.email);
        setFieldValue('email_alternate', patientData.email_alternate);
        setFieldValue('phone_preferred_method', patientData.phone_preferred_method);

        // ============================================
        // Section 3B: HIPAA & Permissions
        // ============================================

        setFieldValue('hipaa_notice_received', patientData.hipaa_notice_received);
        setFieldValue('allow_patient_portal', patientData.allow_patient_portal);
        setFieldValue('allow_imm_reg_use', patientData.allow_imm_reg_use);
        setFieldValue('allow_imm_info_share', patientData.allow_imm_info_share);
        setFieldValue('allow_health_info_ex', patientData.allow_health_info_ex);
        setFieldValue('cmsportal_login', patientData.cmsportal_login);

        if (patientData.provider_since_date) {
            const providerField = document.querySelector('[name="provider_since_date"]');
            if (providerField) {
                providerField.value = formatDateForInput(patientData.provider_since_date);
            }
        }

        // ============================================
        // Section 4: Emergency Contact
        // ============================================

        setFieldValue('mothersname', patientData.mothersname);
        setFieldValue('emergency_contact_name', patientData.emergency_contact_name);
        setFieldValue('emergency_contact_relationship', patientData.emergency_contact_relationship);
        setFieldValue('emergency_contact_phone', patientData.emergency_contact_phone);

        // ============================================
        // Section 5: Employment (from employer_data table)
        // ============================================

        setFieldValue('occupation', employerData.occupation);
        setFieldValue('industry', employerData.industry);
        setFieldValue('employer_name', employerData.name);
        setFieldValue('em_street', employerData.street);
        setFieldValue('employer_address_line_2', employerData.street_line_2);
        setFieldValue('em_city', employerData.city);
        setFieldValue('em_state', employerData.state);
        setFieldValue('em_postal_code', employerData.postal_code);
        setFieldValue('em_country_code', employerData.country);

        if (employerData.start_date) {
            const startField = document.querySelector('[name="employment_start_date"]');
            if (startField) {
                startField.value = formatDateForInput(employerData.start_date);
            }
        }

        if (employerData.end_date) {
            const endField = document.querySelector('[name="employment_end_date"]');
            if (endField) {
                endField.value = formatDateForInput(employerData.end_date);
            }
        }

        //setFieldValue('employment_status', employerData.employment_status);
        if (employerData.employment_status) {
            const employment_status = document.querySelector('[name="employment_status"]');
            if (employment_status) {
                employment_status.value = employerData.employment_status;
            }
        }

        // ============================================
        // Section 5B: Additional Demographics
        // ============================================

        setFieldValue('status', patientData.contact_relationship);
        setFieldValue('sex_administrative', patientData.sex_administrative);
        setFieldValue('ss', patientData.ss);
        setFieldValue('drivers_license', patientData.drivers_license);
        setFieldValue('pubpid', patientData.pubpid);
        setFieldValue('billing_note', patientData.billing_note);
        setFieldValue('name_history', patientData.name_history);
        setFieldValue('nationality_country', patientData.nationality_country);
        setFieldValue('interpreter_dialect_notes', patientData.interpreter_dialect_notes);
        setFieldValue('household_size', patientData.household_size);
        //setFieldValue('providerID', patientData.providerID);
        

        // ============================================
        // Section 5C: Care Team & Communication
        // ============================================

       // setFieldValue('providerID', patientData.providerID);
        setFieldValue('referring_provider', patientData.referring_provider);
        setFieldValue('care_team_provider', patientData.care_team_provider);
        setFieldValue('care_team_status', patientData.care_team_status);
        setFieldValue('care_team_facility', patientData.care_team_facility);
        setFieldValue('allow_voice_message', patientData.allow_voice_message);
        setFieldValue('voice_message_with', patientData.voice_message_with);
        setFieldValue('allow_mail_message', patientData.allow_mail_message);
        setFieldValue('allow_sms_text', patientData.allow_sms_text);
        setFieldValue('allow_email_message', patientData.allow_email_message);

        // ============================================
        // Section 5D: Financial & Social
        // ============================================

        if (patientData.financial_review_date) {
            const finReviewField = document.querySelector('[name="financial_review_date"]');
            if (finReviewField) {
                finReviewField.value = formatDateForInput(patientData.financial_review_date);
            }
        }

        setFieldValue('monthly_income', patientData.monthly_income);
        setFieldValue('homeless', patientData.homeless);
        setFieldValue('migrantseasonal', patientData.migrantseasonal);
        setFieldValue('referral_source', patientData.referral_source);
        setFieldValue('vfc_eligibility_status', patientData.vfc_eligibility_status);
        setFieldValue('tribal_affiliations', patientData.tribal_affiliations);

        // ============================================
        // Section 5G: Demographics & Social Stats (from demographics_social table)
        // ============================================

        // Race, Ethnicity & Language
        setFieldValue('primary_language', demographicsSocialData.primary_language);
        setFieldValue('race', demographicsSocialData.race);
        setFieldValue('ethnicity', demographicsSocialData.ethnicity);
        setFieldValue('nationality_country', demographicsSocialData.nationality_country);
        setFieldValue('interpreter_needed', demographicsSocialData.interpreter_needed);
        setFieldValue('interpreter_dialect_notes', demographicsSocialData.interpreter_dialect_notes);

        // Financial & Social Factors
        if (demographicsSocialData.financial_review_date) {
            const finReviewField = document.querySelector('[name="financial_review_date"]');
            if (finReviewField) {
                finReviewField.value = formatDateForInput(demographicsSocialData.financial_review_date);
            }
        }

        setFieldValue('monthly_income', demographicsSocialData.monthly_income);
        setFieldValue('household_size', demographicsSocialData.household_size);
        setFieldValue('homeless', demographicsSocialData.homeless);
        setFieldValue('migrantseasonal', demographicsSocialData.migrantseasonal);
        setFieldValue('referral_source', demographicsSocialData.referral_source);
        setFieldValue('vfc_eligibility_status', demographicsSocialData.vfc_eligibility_status);
        setFieldValue('religion', demographicsSocialData.religion);
        setFieldValue('tribal_affiliations', demographicsSocialData.tribal_affiliations);

        // ============================================
        // Section 5E: Insurance
        // ============================================

        // Note: Insurance data comes from insurance_data table, not patient_data
        // This would need to be enhanced to fetch and populate insurance_data
        // For now, we'll handle the fields that might be in patient_data
        //setFieldValue('deceased_date', patientData.deceased_date);
        if (patientData.deceased_date) {
    const date = new Date(patientData.deceased_date);
    const formatted = date.toISOString().split('T')[0];
    setFieldValue('deceased_date', formatted);
}
        setFieldValue('deceased_reason', patientData.deceased_reason);
       

        // ============================================
        // Section 5F: Insurance Information
        // ============================================

        // Populate primary insurance (first record, insurance_sequence = 1)
        if (insuranceData && insuranceData.length > 0) {
            const primaryInsurance = insuranceData[0];
            setFieldValue('insurance_provider', primaryInsurance.provider);
            setFieldValue('plan_name', primaryInsurance.plan_name);
            setFieldValue('policy_number', primaryInsurance.policy_number);
            setFieldValue('group_number', primaryInsurance.group_number);
            setFieldValue('copay_amount', primaryInsurance.copay_amount);
            setFieldValue('accept_assignment', primaryInsurance.accept_assignment);
            setFieldValue('subscriber_employer', primaryInsurance.subscriber_employer);
            setFieldValue('se_city', primaryInsurance.subscriber_employer_city);
            setFieldValue('se_state', primaryInsurance.subscriber_employer_state);
            setFieldValue('se_postal_code', primaryInsurance.subscriber_postal_code);
            setFieldValue('subscriber_postal_code', primaryInsurance.subscriber_employer_postal_code);
            setFieldValue('subscriber_country_code', primaryInsurance.subscriber_employer_country);
            setFieldValue('se_country_code', primaryInsurance.subscriber_country);
            setFieldValue('subscriber_street', primaryInsurance.subscriber_street);
            
             
            

            if (primaryInsurance.date) {
                const dateStartField = document.querySelector('[name="date_start"]');
                console.log('dateStartField', dateStartField);
                if (dateStartField) {
                    dateStartField.value = formatDateForInput(primaryInsurance.date);
                }
            }

            // Populate subscriber information from insurance data
            if (primaryInsurance.subscriber_fname) setFieldValue('subscriber_fname', primaryInsurance.subscriber_fname);
            if (primaryInsurance.subscriber_mname) setFieldValue('subscriber_mname', primaryInsurance.subscriber_mname);
            if (primaryInsurance.subscriber_lname) setFieldValue('subscriber_lname', primaryInsurance.subscriber_lname);
            if (primaryInsurance.subscriber_relationship) setFieldValue('subscriber_relationship', primaryInsurance
                .subscriber_relationship);
            if (primaryInsurance.subscriber_DOB) {
                const dobField = document.querySelector('[name="subscriber_dob"]');
                if (dobField) dobField.value = formatDateForInput(primaryInsurance.subscriber_DOB);
            }
            if (primaryInsurance.subscriber_sex) setFieldValue('subscriber_sex', primaryInsurance.subscriber_sex);
            if (primaryInsurance.subscriber_ss) setFieldValue('subscriber_ssn', primaryInsurance.subscriber_ss);
            if (primaryInsurance.subscriber_phone) setFieldValue('subscriber_phone', primaryInsurance.subscriber_phone);
            if (primaryInsurance.subscriber_city) setFieldValue('subscriber_city', primaryInsurance.subscriber_city);
            if (primaryInsurance.subscriber_state) {
                const stateField = document.querySelector('[name="subscriber_state"]');
                if (stateField) stateField.value = primaryInsurance.subscriber_state;
            }
            if (primaryInsurance.subscriber_address_line_1) setFieldValue('subscriber_address_line_1', primaryInsurance
                .subscriber_address_line_1);
            if (primaryInsurance.subscriber_address_line_2) setFieldValue('subscriber_address_line_2', primaryInsurance
                .subscriber_address_line_2);
            if (primaryInsurance.subscriber_postal_code) setFieldValue('subscriber_postal_code', primaryInsurance
                .subscriber_postal_code);

            // Populate secondary insurance (if available)
            if (insuranceData.length > 1) {
                const secondaryInsurance = insuranceData[1];
                setFieldValue('insurance_provider_secondary', secondaryInsurance.provider);
                setFieldValue('plan_name_secondary', secondaryInsurance.plan_name);
                setFieldValue('policy_number_secondary', secondaryInsurance.policy_number);
                setFieldValue('group_number_secondary', secondaryInsurance.group_number);
                setFieldValue('subscriber_name_secondary', secondaryInsurance.subscriber_lname);
                setFieldValue('subscriber_relationship_secondary', secondaryInsurance.subscriber_relationship);
                
                
                setFieldValue('copay_amount_secondary', secondaryInsurance.copay_amount);
                setFieldValue('accept_assignment_secondary', secondaryInsurance.accept_assignment);

                if (secondaryInsurance.date) {
                    const dateStartField = document.querySelector('[name="date_start_secondary"]');
                    if (dateStartField) dateStartField.value = formatDateForInput(secondaryInsurance.date);
                }
            }

            // Populate tertiary insurance (if available)
            if (insuranceData.length > 2) {
                const tertiaryInsurance = insuranceData[2];
                setFieldValue('insurance_provider_tertiary', tertiaryInsurance.provider);
                setFieldValue('plan_name_tertiary', tertiaryInsurance.plan_name);
                setFieldValue('policy_number_tertiary', tertiaryInsurance.policy_number);
                setFieldValue('group_number_tertiary', tertiaryInsurance.group_number);
                setFieldValue('copay_amount_tertiary', tertiaryInsurance.copay_amount);
                setFieldValue('accept_assignment_tertiary', tertiaryInsurance.accept_assignment);

                if (tertiaryInsurance.date) {
                    const dateStartField = document.querySelector('[name="date_start_tertiary"]');
                    if (dateStartField) dateStartField.value = formatDateForInput(tertiaryInsurance.date);
                }
            }
        }

        // ============================================
        // Section 6: Chief Complaint
        // ============================================

        setFieldValue('chief_complaint', chiefComplaintData.complaint_text);
        setFieldValue('symptom_start', chiefComplaintData.onset_date);
        setFieldValue('nkda', chiefComplaintData.nkda);
        setFieldValue('transfusion', chiefComplaintData.transfusion);
        setFieldValue('famhxunknown', chiefComplaintData.famhxunknown);
         
        // 1. Populate the 'symptom_onset_type' select dropdown
        if (chiefComplaintData.symptom_onset_type) {
            const selectField = document.querySelector('[name="symptom_onset_type"]');
            if (selectField) {
                selectField.value = chiefComplaintData.symptom_onset_type;
            }
        }

        // 2. Populate the 'sympprog' radio buttons
        if (chiefComplaintData.symptom_condition) {
            // Finds the specific radio input matching the value from your data
            const radioValue = chiefComplaintData.symptom_condition;
            const radioField = document.querySelector(`[name="sympprog"][value="${radioValue}"]`);

            if (radioField) {
                radioField.checked = true;

                // Since your HTML uses an onchange="selectPill(this)" attribute, 
                // we manually call it so your visual "pill" UI styles update correctly!
                if (typeof selectPill === 'function') {
                    selectPill(radioField);
                }
            }
        }

        setFieldValue('pain_location', chiefComplaintData.pain_location);
        setFieldValue('pain_character', chiefComplaintData.pain_character);
        setFieldValue('pain_worse', chiefComplaintData.pain_worse);
        setFieldValue('pain_better', chiefComplaintData.pain_better);
        setFieldValue('paincont', chiefComplaintData.paincont);
        setFieldValue('pain_radiate', chiefComplaintData.pain_radiate);
        
        setFieldValue('symptom_notes', chiefComplaintData.symptom_notes);
        if (chiefComplaintData.pain_score) {
            loadPainScore(chiefComplaintData.pain_score);
        }

        // ============================================
        // Section 7: Medical History & Social History
        // ============================================

        // Base social history fields
        // Handle old 'on' values - convert to empty string
        const tobacco = historyData.tobacco === 'on' ? '' : historyData.tobacco;
        const alcohol = historyData.alcohol === 'on' ? '' : historyData.alcohol;
        const drugs = historyData.recreational_drugs === 'on' ? '' : historyData.recreational_drugs;
        const seatbelt = historyData.seatbelt_use === 'on' ? '' : historyData.seatbelt_use;
        const dvsafety = historyData.hazardous_activities === 'on' ? '' : historyData.hazardous_activities;

        setFieldValue('tobacco', tobacco);
        setFieldValue('alcohol', alcohol);
        setFieldValue('drugs', drugs);
        setFieldValue('seatbelt', seatbelt);
        setFieldValue('dvsafety', dvsafety);


        // ============================================
        // Section 8: Family History
        // ============================================


        // Family history fields (added previously)
        if (historyData.dc_father || historyData.dc_mother || historyData.additional_history) {
            setFieldValue('dc_father', historyData.dc_father);
            setFieldValue('dc_mother', historyData.dc_mother);
           // setFieldValue('additional_history', historyData.additional_history);
        }


        // TOBACCO USE
        setFieldValue('tobacco', socialHistoryData.tobacco_status);
        setFieldValue('tobacco_product_type', socialHistoryData.tobacco_product_type);
        setFieldValue('tobacco_amount', socialHistoryData.tobacco_amount);
        setFieldValue('tobacco_years', socialHistoryData.tobacco_years);
        if (socialHistoryData.tobacco_quit_date) {
            const quitDateField = document.querySelector('[name="tobacco_quit_date"]');
            if (quitDateField) quitDateField.value = formatDateForInput(socialHistoryData.tobacco_quit_date);
        }
        setFieldValue('tobacco_cessation_interest', socialHistoryData.tobacco_cessation_interest);

        // ALCOHOL USE
        setFieldValue('alcohol', socialHistoryData.alcohol_status);
        setFieldValue('alcohol_drinks_per_week', socialHistoryData.alcohol_drinks_per_week);
        setFieldValue('alcohol_type', socialHistoryData.alcohol_type);
        setFieldValue('audit', socialHistoryData.audit_score);

        // SUBSTANCE USE
        setFieldValue('drugs', socialHistoryData.drug_status);
        setFieldValue('substances_used', socialHistoryData.substances_used);
        setFieldValue('drug_frequency', socialHistoryData.drug_frequency);
        setFieldValue('drug_treatment_interest', socialHistoryData.drug_treatment_interest);


        // EXERCISE & DIET - Now from patient_social_history table
        setFieldValue('exercise_frequency', socialHistoryData.exercise_frequency);
        setFieldValue('exercise_type', socialHistoryData.exercise_type);
        setFieldValue('exercise_minutes', socialHistoryData.exercise_minutes);
        setFieldValue('diet_type', socialHistoryData.diet_type);
        setFieldValue('water_intake', socialHistoryData.water_intake);
        setFieldValue('sleep_hours', socialHistoryData.sleep_hours);

        // LIVING SITUATION & SAFETY - Now from patient_social_history table
        setFieldValue('living_situation', socialHistoryData.living_situation);
        setFieldValue('education_level', socialHistoryData.education_level);
        setFieldValue('occupation_category', socialHistoryData.occupation);
        setFieldValue('dvsafety', socialHistoryData.domestic_violence_safe);
        setFieldValue('seatbelt', socialHistoryData.seatbelt_use);
        setFieldValue('firearms', socialHistoryData.firearms_in_home);
        setFieldValue('social_history_notes', socialHistoryData.social_history_notes);

        // Fall back to legacy history_data parsing if socialHistoryData is empty (for backward compatibility)
        if (!socialHistoryData || Object.keys(socialHistoryData).length === 0) {
            if (historyData.exercise_patterns) {
                const exerciseData = historyData.exercise_patterns.split('|').reduce((obj, item) => {
                    const [key, value] = item.split(':').map(s => s.trim());
                    if (key && value) obj[key] = value;
                    return obj;
                }, {});

                setFieldValue('exercise_frequency', exerciseData['Frequency']);
                setFieldValue('exercise_type', exerciseData['Type']);
                if (exerciseData['Duration']) {
                    const minutes = exerciseData['Duration'].replace(' min', '').trim();
                    setFieldValue('exercise_minutes', minutes);
                }
                setFieldValue('diet_type', exerciseData['Diet']);
                setFieldValue('water_intake', exerciseData['Water']);
            }

            if (historyData.sleep_patterns) {
                const lifestyleData = historyData.sleep_patterns.split('|').reduce((obj, item) => {
                    const [key, value] = item.split(':').map(s => s.trim());
                    if (key && value) obj[key] = value;
                    return obj;
                }, {});

                if (lifestyleData['Sleep']) {
                    const hours = lifestyleData['Sleep'].replace(' hrs', '').trim();
                    setFieldValue('sleep_hours', hours);
                }
                setFieldValue('living_situation', lifestyleData['Living']);
                setFieldValue('education_level', lifestyleData['Education']);
                setFieldValue('occupation', lifestyleData['Occupation']);
                setFieldValue('firearms', lifestyleData['Firearms']);
                setFieldValue('social_history_notes', lifestyleData['Notes']);
            }
        }

        // ============================================
        // Section 8: Medical Conditions
        // ============================================

        if (listData.conditions && listData.conditions.length > 0) {
            // Populate medical conditions - these are typically checkboxes
            listData.conditions.forEach(condition => {
                const field = document.querySelector(`input[type="checkbox"][value="${condition.title}"]`);
                if (field) {
                    field.checked = true;
                    // CRITICAL: Trigger the change event so onchange handlers (like togglePill) execute
                    field.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                    console.log(`✅ Checked condition: ${condition.title}`);
                } else {
                    console.log(`⚠️  Condition checkbox not found: ${condition.title}`);
                }
            });
        }

        // ============================================
        // Section 9: Current Medications
        // ============================================

        if (listData.medications && listData.medications.length > 0) {
            populateDynamicRows('med-rows', listData.medications, 'medication');
        }

        // ============================================
        // Section 10: Allergies (Drug & Food)
        // ============================================

        if (listData.allergies_drug && listData.allergies_drug.length > 0) {
            populateDynamicRows('drug-allergy-rows', listData.allergies_drug, 'drug_allergy');
        }

        if (listData.allergies_food && listData.allergies_food.length > 0) {
            populateDynamicRows('food-allergy-rows', listData.allergies_food, 'food_allergy');
        }

        // ============================================
        // Section 11: Surgical History
        // ============================================

        if (listData.surgeries && listData.surgeries.length > 0) {
            populateDynamicRows('surg-rows', listData.surgeries, 'surgery');
        }

        // ============================================
        // Section 12: Family Health History
        // ============================================

        if (listData.family_history && listData.family_history.length > 0) {
            populateDynamicRows('family-rows', listData.family_history, 'family_history');
        }

        // Populate Father & Mother death information and additional notes
        setFieldValue('dc_father', historyData.dc_father);
        setFieldValue('dc_mother', historyData.dc_mother);
        setFieldValue('additional_history', historyData.additional_history);

        // ============================================
        // Section 14: Related Persons
        // ============================================

        if (relatedPersonsData && relatedPersonsData.length > 0) {
            populateRelatedPersons(relatedPersonsData);
        }

        // ============================================
        // Section 15: Consent & Authorization
        // ============================================

        if (consentAuthorizationData && Object.keys(consentAuthorizationData).length > 0) {
            // Set checkboxes
            if (consentAuthorizationData.consent_to_treatment) {
                const checkbox = document.querySelector('input[name="consent_to_treatment"]');
                if (checkbox) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            }
            if (consentAuthorizationData.financial_responsibility_consent) {
                const checkbox = document.querySelector('input[name="financial_responsibility_consent"]');
                if (checkbox) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            }
            if (consentAuthorizationData.digital_communications_consent) {
                const checkbox = document.querySelector('input[name="digital_communications_consent"]');
                if (checkbox) {
                    checkbox.checked = true;
                    checkbox.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            }

            // Set text and select fields
            setFieldValue('signature_printed_name', consentAuthorizationData.signature_printed_name);
            if (consentAuthorizationData.signature_date) {
                const dateField = document.querySelector('input[name="signature_date"]');
                if (dateField) dateField.value = formatDateForInput(consentAuthorizationData.signature_date);
            }
            setFieldValue('signature_relationship', consentAuthorizationData.signature_relationship);
            setFieldValue('signature_digital', consentAuthorizationData.signature_digital);
            setFieldValue('consentAuthorization_referral_source', consentAuthorizationData.referral_source);
            setFieldValue('referring_provider_name', consentAuthorizationData.referring_provider_name);
        }

        // ============================================
        // Section 16: Review of Systems
        // ============================================

        if (rosData && rosData.symptoms) {
            const checkedSymptoms = rosData.symptoms.split('|').map(s => s.trim()).filter(Boolean);
            checkedSymptoms.forEach(symptom => {
                const cb = document.querySelector('input[name="ros_symptoms[]"][value="' + symptom + '"]');
                if (cb) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }

        // Update button text
        const submitBtn = document.getElementById('btnSubmit');
        if (submitBtn && isEditMode) {
            submitBtn.textContent = '✏️ Update Patient';
            submitBtn.title = 'Update this patient\'s information';
        }
    }

    // Helper function to populate dynamic rows (medications, allergies, surgeries, family history)
    function populateDynamicRows(containerId, dataArray, type) {
        let container = document.getElementById(containerId);
        if (!container) return;

        // If container is itself a dynamic-row (template row), hide it and use parent as append target.
        // We HIDE instead of remove so getElementById still works in submitForm.
        let appendTarget = container;
        if (container.classList.contains('dynamic-row')) {
            appendTarget = container.parentElement;
            container.style.display = 'none'; // Hide template row — do NOT remove
            container.dataset.template = 'true';
        } else {
            // Remove existing dynamic rows from container (but keep template rows)
            const existingRows = container.querySelectorAll('.dynamic-row:not([data-template])');
            existingRows.forEach(row => row.remove());
        }

        // Add rows for each item
        dataArray.forEach(item => {
            const newRow = document.createElement('div');
            newRow.className = 'dynamic-row';

            // Set grid-template-columns based on type
            if (type === 'medication') {
                newRow.style.gridTemplateColumns = '2fr 1fr 1fr 1fr 1.5fr 32px';
            } else if (type === 'drug_allergy' || type === 'food_allergy') {
                newRow.style.gridTemplateColumns = '2fr 2fr 1fr 32px';
            } else if (type === 'surgery') {
                newRow.style.gridTemplateColumns = '2fr 1fr 2fr 1fr 32px';
            } else if (type === 'family_history') {
                newRow.style.gridTemplateColumns = '2fr 2fr 1fr 32px';
            }

            let html = '';

            if (type === 'medication') {
                html = `
        <input type="text" placeholder="Medication name" value="${item.title || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <input type="text" placeholder="Dose" value="${extractFieldFromComments(item.comments, 'Dose') || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option value="Once daily" ${extractFieldFromComments(item.comments, 'Frequency') === 'Once daily' ? 'selected' : ''}>Once daily</option><option value="Twice daily" ${extractFieldFromComments(item.comments, 'Frequency') === 'Twice daily' ? 'selected' : ''}>Twice daily</option><option value="As needed" ${extractFieldFromComments(item.comments, 'Frequency') === 'As needed' ? 'selected' : ''}>As needed</option></select>
        <select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option value="Oral" ${extractFieldFromComments(item.comments, 'Route') === 'Oral' ? 'selected' : ''}>Oral</option><option value="Injection" ${extractFieldFromComments(item.comments, 'Route') === 'Injection' ? 'selected' : ''}>Injection</option><option value="Topical" ${extractFieldFromComments(item.comments, 'Route') === 'Topical' ? 'selected' : ''}>Topical</option></select>
        <input type="text" placeholder="Prescriber" value="${extractFieldFromComments(item.comments, 'Prescriber') || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <button class="remove-btn" type="button" onclick="this.closest('.dynamic-row').remove()">×</button>
      `;
            } else if (type === 'drug_allergy' || type === 'food_allergy') {
                html = `
        <input type="text" placeholder="Allergen name" value="${item.title || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <input type="text" placeholder="Reaction" value="${item.reaction || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option value="Mild" ${item.severity_al === 'Mild' ? 'selected' : ''}>Mild</option><option value="Moderate" ${item.severity_al === 'Moderate' ? 'selected' : ''}>Moderate</option><option value="Severe" ${item.severity_al === 'Severe' ? 'selected' : ''}>Severe</option></select>
        <button class="remove-btn" type="button" onclick="this.closest('.dynamic-row').remove()">×</button>
      `;
            } else if (type === 'surgery') {
                html = `
        <input type="text" placeholder="Procedure" value="${item.title || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <input type="text" placeholder="Year" value="${extractFieldFromComments(item.comments, 'Year') || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <input type="text" placeholder="Facility" value="${extractFieldFromComments(item.comments, 'Facility') || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option value="None" ${extractFieldFromComments(item.comments, 'Complications') === 'None' ? 'selected' : ''}>None</option><option value="Minor" ${extractFieldFromComments(item.comments, 'Complications') === 'Minor' ? 'selected' : ''}>Minor</option><option value="Major" ${extractFieldFromComments(item.comments, 'Complications') === 'Major' ? 'selected' : ''}>Major</option></select>
        <button class="remove-btn" type="button" onclick="this.closest('.dynamic-row').remove()">×</button>
      `;
            } else if (type === 'family_history') {
                html = `
       <select
    style="width:100%;
           padding:8px 6px;
           border:1.5px solid var(--border);
           border-radius:6px;
           font-family:var(--sans);
           font-size:12px;
           box-sizing:border-box;">
    ${buildConditionOptions(item.title)}
</select>
<input type="text" placeholder="Relationship" value="${extractFieldFromComments(item.comments, 'Relationship') || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <input type="text" placeholder="Age at onset (optional)" value="${extractFieldFromComments(item.comments, 'Age') || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
        <button class="remove-btn" type="button" onclick="this.closest('.dynamic-row').remove()">×</button>
      `;
            }

            newRow.innerHTML = html;
            appendTarget.appendChild(newRow);
        });
    }

    // Helper function to populate related persons
    function populateRelatedPersons(dataArray) {
        const container = document.getElementById('related-rows');
        if (!container) return;

        // Hide template row (not remove) so getElementById still works in submitForm
        const appendTarget = container.parentElement;
        container.style.display = 'none';
        container.dataset.template = 'true';

        dataArray.forEach(item => {
            const newRow = document.createElement('div');
            newRow.className = 'dynamic-row';
            newRow.style.gridTemplateColumns = '2fr 1.5fr 1fr 1.5fr 1.5fr 1fr 32px';

            const html = `
      <input type="text" placeholder="Full Name" value="${item.full_name || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
      <select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option value="Spouse" ${item.relationship === 'Spouse' || item.relationship === 'Spouse / Partner' ? 'selected' : ''}>Spouse</option><option value="Parent" ${item.relationship === 'Parent' ? 'selected' : ''}>Parent</option><option value="Child" ${item.relationship === 'Child' ? 'selected' : ''}>Child</option><option value="Sibling" ${item.relationship === 'Sibling' ? 'selected' : ''}>Sibling</option><option value="Guardian" ${item.relationship === 'Legal Guardian' || item.relationship === 'Guardian' ? 'selected' : ''}>Guardian</option><option value="Guarantor" ${item.relationship === 'Guarantor' ? 'selected' : ''}>Guarantor</option><option value="POA" ${item.relationship === 'Power of Attorney' || item.relationship === 'POA' ? 'selected' : ''}>POA</option><option value="Caregiver" ${item.relationship === 'Caregiver' ? 'selected' : ''}>Caregiver</option><option value="Other" ${item.relationship === 'Other' ? 'selected' : ''}>Other</option></select>
      <select style="width:100%;padding:8px 6px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"><option value="M" ${item.sex === 'Male' || item.sex === 'M' ? 'selected' : ''}>M</option><option value="F" ${item.sex === 'Female' || item.sex === 'F' ? 'selected' : ''}>F</option><option value="Other" ${item.sex === 'Other' ? 'selected' : ''}>Other</option></select>
      <input type="tel" placeholder="Phone" value="${item.phone || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
      <input type="email" placeholder="Email" value="${item.email || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
      <input type="text" placeholder="City" value="${item.city || ''}" style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:var(--sans);font-size:12px;box-sizing:border-box;"/>
      <button class="remove-btn" type="button" onclick="this.closest('.dynamic-row').remove()">×</button>
    `;

            newRow.innerHTML = html;
            appendTarget.appendChild(newRow);
        });
    }

    // Helper function to extract field values from comments
    function extractFieldFromComments(comments, fieldName) {
        if (!comments) return '';
        const regex = new RegExp(`${fieldName}: ([^,]+)`);
        const match = comments.match(regex);
        return match ? match[1].trim() : '';
    }

    // Helper function to format date for input field
    function formatDateForInput(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // Load patient data when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadPatientData();
    });

    function submitForm() {
        // Collect all form data
        const formData = {};

        // Get all input, select, and textarea elements with name attributes
        // IMPORTANT: Only query actual form elements, not all elements with name attribute (to exclude meta tags, etc.)
        document.querySelectorAll('input[name], select[name], textarea[name]').forEach(field => {
            const name = field.name;

            // Skip fields with placeholder values or "-- Select --"
            if (field.value === '-- Select --' || field.value === '-- Select condition --' || field.value ===
                '-- Select option --') {
                return;
            }

            if (field.type === 'checkbox') {
                // ros_symptoms[] checkboxes are collected separately; skip here
                if (name === 'ros_symptoms[]') return;
                if (field.checked) {
                    formData[name] = field.value || 'on';
                }
            } else if (field.type === 'radio') {
                if (field.checked) {
                    formData[name] = field.value;
                }
            } else {
                const value = field.value || '';
                // Only add non-empty values
                if (value.trim()) {
                    formData[name] = value;
                }
            }
        });

        // ============================================
        // EXTRACT DYNAMIC FORM DATA
        // ============================================

        // Extract Medical Conditions (checkboxes)
        // Exclude ros_symptoms[] checkboxes — they are collected separately below
        const conditions = [];
        document.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
            if (cb.name === 'ros_symptoms[]') return; // skip ROS symptoms
            if (cb.value && cb.value.trim()) {
                conditions.push(cb.value);
            }
        });
        if (conditions.length > 0) {
            formData.conditions = conditions;
        }

        // Extract Review of Systems (ROS) symptoms
        const rosSymptoms = [];
        document.querySelectorAll('input[name="ros_symptoms[]"]:checked').forEach(cb => {
            if (cb.value && cb.value.trim()) {
                rosSymptoms.push(cb.value);
            }
        });
        if (rosSymptoms.length > 0) {
            formData.ros_symptoms = rosSymptoms;
        }

        // Extract Current Medications
        const medications = [];
        // Scope to #med-list (stable wrapper). Skip hidden template rows and header-only rows.
        const medList = document.getElementById('med-list');
        if (medList) {
            medList.querySelectorAll('.dynamic-row').forEach(row => {
                if (row.dataset.template === 'true' || row.style.display === 'none') return;
                const inputs = row.querySelectorAll('input');
                const selects = row.querySelectorAll('select');
                if (inputs.length >= 3 && inputs[0].value && inputs[0].value.trim()) {
                    medications.push({
                        name: inputs[0].value.trim(),
                        dose: inputs[1].value.trim(),
                        frequency: selects[0] ? selects[0].value : '',
                        route: selects[1] ? selects[1].value : '',
                        prescriber: inputs[2].value.trim()
                    });
                }
            });
        }
        if (medications.length > 0) {
            formData.medications = medications;
        }

        // Extract Drug Allergies
        const drugAllergies = [];
        const drugAllergyContainer = document.getElementById('drug-allergy-rows');
        if (drugAllergyContainer) {
            drugAllergyContainer.querySelectorAll('.dynamic-row').forEach(row => {
                if (row.dataset.template === 'true' || row.style.display === 'none') return;
                const inputs = row.querySelectorAll('input');
                const select = row.querySelector('select');
                if (inputs.length >= 2 && inputs[0].value && inputs[0].value.trim()) {
                    drugAllergies.push({
                        name: inputs[0].value.trim(),
                        reaction: inputs[1].value.trim(),
                        severity: select ? select.value : ''
                    });
                }
            });
        }
        if (drugAllergies.length > 0) {
            formData.allergies_drug = drugAllergies;
        }

        // Extract Food Allergies
        const foodAllergies = [];
        const foodAllergyContainer = document.getElementById('food-allergy-rows');
        if (foodAllergyContainer) {
            foodAllergyContainer.querySelectorAll('.dynamic-row').forEach(row => {
                if (row.dataset.template === 'true' || row.style.display === 'none') return;
                const inputs = row.querySelectorAll('input');
                const select = row.querySelector('select');
                if (inputs.length >= 2 && inputs[0].value && inputs[0].value.trim()) {
                    foodAllergies.push({
                        name: inputs[0].value.trim(),
                        reaction: inputs[1].value.trim(),
                        severity: select ? select.value : ''
                    });
                }
            });
        }
        if (foodAllergies.length > 0) {
            formData.allergies_food = foodAllergies;
        }

        // Extract Surgical History
        const surgeries = [];
        const surgContainer = document.getElementById('surg-rows');
        if (surgContainer) {
            surgContainer.querySelectorAll('.dynamic-row').forEach(row => {
                if (row.dataset.template === 'true' || row.style.display === 'none') return;
                const inputs = row.querySelectorAll('input');
                const select = row.querySelector('select');
                if (inputs.length >= 3 && inputs[0].value && inputs[0].value.trim()) {
                    surgeries.push({
                        procedure: inputs[0].value.trim(),
                        year: inputs[1].value.trim(),
                        facility: inputs[2].value.trim(),
                        complications: select ? select.value : ''
                    });
                }
            });
        }
        if (surgeries.length > 0) {
            formData.surgeries = surgeries;
        }

        // Extract Family Health History
        const familyHistory = [];
        const familyContainer = document.getElementById('family-rows');
        if (familyContainer) {
            familyContainer.querySelectorAll('.dynamic-row').forEach(row => {
                if (row.dataset.template === 'true' || row.style.display === 'none') return;
                const select = row.querySelector('select');
                const inputs = row.querySelectorAll('input');
                if (select && select.value && select.value !== '-- Select condition --' && inputs.length >= 2) {
                    familyHistory.push({
                        condition: select.value,
                        relationship: inputs[0].value.trim(),
                        age: inputs[1].value.trim()
                    });
                }
            });
        }
        if (familyHistory.length > 0) {
            formData.family_history = familyHistory;
        }

        // Extract Related Persons
        // const relatedPersons = [];
        // const relatedContainer = document.getElementById('related-rows');
        // if (relatedContainer && relatedContainer.parentElement) {
        //     relatedContainer.parentElement.querySelectorAll('.dynamic-row').forEach(row => {
        //         if (row.dataset.template === 'true' || row.style.display === 'none') return;
        //         const inputs = row.querySelectorAll('input');
        //         const selects = row.querySelectorAll('select');
        //         if (inputs.length >= 3 && inputs[0].value && inputs[0].value.trim()) {
        //             relatedPersons.push({
        //                 full_name: inputs[0].value.trim(),
        //                 relationship: selects[0] ? selects[0].value : '',
        //                 sex: selects[1] ? selects[1].value : '',
        //                 phone: inputs[1].value.trim(),
        //                 email: inputs[2].value.trim(),
        //                 city: inputs[3].value.trim()
        //             });
        //         }
        //     });
        // }

        const relatedPersons = [];
const relatedContainer = document.getElementById('related-rows');

if (relatedContainer && relatedContainer.parentElement) {
    relatedContainer.parentElement.querySelectorAll('.dynamic-row').forEach(row => {

        if (row.dataset.template === 'true' || row.style.display === 'none') return;

        const inputs = row.querySelectorAll('input');
        const selects = row.querySelectorAll('select');

        const full_name = inputs[0]?.value?.trim();
        if (!full_name) return;

        relatedPersons.push({
            full_name,
            relationship: selects[0]?.value || '',
            sex: selects[1]?.value || '',
            phone: inputs[1]?.value?.trim() || '',
            email: inputs[2]?.value?.trim() || '',
            city: inputs[3]?.value?.trim() || ''
        });
    });
}
        if (relatedPersons.length > 0) {
            formData.related_persons = relatedPersons;
        }

        // Extract Consent & Authorization
        formData.consent_to_treatment = document.querySelector('input[name="consent_to_treatment"]')?.checked ? 1 : 0;
        formData.financial_responsibility_consent = document.querySelector(
            'input[name="financial_responsibility_consent"]')?.checked ? 1 : 0;
        formData.digital_communications_consent = document.querySelector('input[name="digital_communications_consent"]')
            ?.checked ? 1 : 0;
        formData.signature_printed_name = document.querySelector('input[name="signature_printed_name"]')?.value || '';
        formData.signature_date = document.querySelector('input[name="signature_date"]')?.value || '';
        formData.signature_relationship = document.querySelector('select[name="signature_relationship"]')?.value || '';
        formData.signature_digital = document.querySelector('input[name="signature_digital"]')?.value || '';
        formData.referral_source = document.querySelector('select[name="referral_source"]')?.value || '';
        formData.referring_provider_name = document.querySelector('input[name="referring_provider_name"]')?.value || '';

        // Add PID if in edit mode
        if (isEditMode && currentPatientPID) {
            formData.pid = currentPatientPID;
        }

        // Validate required fields
        const required = ['fname', 'lname', 'DOB', 'sex', 'phone_cell', 'email'];
        const missing = required.filter(f => !formData[f] || formData[f].trim() === '');

        if (missing.length > 0) {
            alert('❌ Please fill in all required fields:\n• ' + missing.join('\n• '));
            return;
        }

        // Validate email format
        if (formData['email'] && !isValidEmail(formData['email'])) {
            alert(
                '❌ Invalid email format in "Trusted Email"\n\nPlease enter a valid email address (e.g., user@example.com)');
            return;
        }

        // Validate alternate email if provided
        if (formData['email_alternate'] && formData['email_alternate'].trim() !== '' && !isValidEmail(formData[
                'email_alternate'])) {
            alert(
                '❌ Invalid email format in "Contact Email"\n\nPlease enter a valid email address (e.g., user@example.com)');
            return;
        }

        // Show loading state
        const btnSubmit = document.getElementById('btnSubmit');
        const originalText = btnSubmit.textContent;
        btnSubmit.disabled = true;
        btnSubmit.textContent = '⏳ Submitting...';

        // Send data to backend
        fetch('../patientAddUpdate/patient_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    let tablesMsg = (result.tables_updated && result.tables_updated.length > 0) ?
                        '\n\nData updated in: ' + result.tables_updated.join(', ') :
                        '';

                    if (isEditMode) {
                        // alert('✓ Patient information has been updated successfully.\n\nPatient ID: ' + result.pid +
                        //     tablesMsg);
                          alert('✓ Patient information has been updated successfully.');
                    } else {
                        //alert('✓ Your intake form has been submitted successfully.\n\nPatient ID: ' + result.pid +
                          //  tablesMsg + '\n\nPage will now refresh...');

                          alert('✓ Your intake form has been submitted successfully.\n\nPatient ID: \n\nPage will now refresh...');
                    }

                    // === REFRESH THE PAGE AFTER SUCCESS ===
                    setTimeout(() => {
                        location.reload();
                    }, 1200); // Gives user time to read the success message

                } else {
                    alert('❌ Error: ' + result.message);
                }
            })
            .catch(error => {
                alert(error)
                alert('❌ Submission failed: ' + error.message);
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.textContent = originalText;
            });
    }
    </script>
</body>

</html>