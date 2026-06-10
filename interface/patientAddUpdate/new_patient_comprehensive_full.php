<?php
/**
 * Comprehensive Patient Intake Form - All 15 Sections with Full Fields
 * Combines Synapta design with complete patient data collection
 * Accessible without login
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 */

// Allow public access
$GLOBALS['no_auth_required'] = true;
require_once("../globals.php");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>SyaptaEMR — Patient Intake Form</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --teal:#1D9E75;
  --teal-d:#0F6E56;
  --teal-deep:#085041;
  --teal-l:#E1F5EE;
  --teal-mid:#5DCAA5;
  --slate:#1A2030;
  --slate-l:#2A3142;
  --navy:#0F1117;
  --red:#A32D2D;
  --red-l:#FBEAEA;
  --amber:#C77A0A;
  --muted:#888780;
  --border:#D3D1C7;
  --border-l:#F1EFE8;
  --surface:#FAFAF7;
  --card:#FFFFFF;
  --sans:'Outfit',system-ui,sans-serif;
  --serif:'DM Serif Display',Georgia,serif;
}

html{scroll-behavior:smooth;}
body{font-family:var(--sans);background:var(--surface);color:#1a1a1a;min-height:100vh;}

.topbar{background:var(--navy);border-bottom:2px solid rgba(29,158,117,.3);position:sticky;top:0;z-index:100;display:flex;align-items:center;padding:0 28px;height:64px;gap:16px;}
.logo-wrap{display:flex;align-items:center;gap:10px;}
.logo-hex{width:36px;height:36px;}
.logo-text{font-family:var(--serif);font-size:22px;letter-spacing:-.3px;}
.logo-syn{color:var(--teal-mid);}
.logo-apta{color:#7F77DD;}
.topbar-title{font-size:13px;color:rgba(255,255,255,.45);margin-left:8px;padding-left:16px;border-left:1px solid rgba(255,255,255,.1);}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px;}
.patient-badge{background:rgba(29,158,117,.15);border:1px solid rgba(29,158,117,.3);color:var(--teal-mid);font-size:12px;font-weight:600;padding:5px 12px;border-radius:20px;}
.save-btn{background:var(--teal);color:#fff;border:none;padding:8px 20px;border-radius:8px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;}
.save-btn:hover{background:var(--teal-d);}

.progress-wrap{background:var(--card);border-bottom:1px solid var(--border);padding:16px 28px;position:sticky;top:64px;z-index:90;}
.progress-steps{display:flex;gap:0;overflow-x:auto;scrollbar-width:none;}
.step-item{display:flex;align-items:center;flex:1;min-width:0;}
.step-btn{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;border:none;background:transparent;font-family:var(--sans);font-size:12px;font-weight:500;color:var(--muted);cursor:pointer;transition:all .18s;white-space:nowrap;width:100%;}
.step-btn:hover{background:var(--teal-l);color:var(--teal);}
.step-btn.active{background:var(--teal);color:#fff;font-weight:600;}
.step-num{width:22px;height:22px;border-radius:50%;background:var(--border-l);color:var(--muted);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.step-btn.active .step-num{background:rgba(255,255,255,.25);color:#fff;}

.layout{display:grid;grid-template-columns:220px 1fr;gap:0;min-height:calc(100vh - 130px);}
.side-nav{background:var(--card);border-right:1px solid var(--border);padding:20px 0;position:sticky;top:130px;height:calc(100vh - 130px);overflow-y:auto;}
.nav-section-label{font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;padding:12px 16px 4px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;transition:all .15s;border-left:3px solid transparent;font-size:13px;color:var(--body);}
.nav-item:hover{background:var(--teal-l);color:var(--teal);}
.nav-item.active{background:var(--teal-l);color:var(--teal);border-left-color:var(--teal);font-weight:600;}
.nav-icon{font-size:15px;width:18px;text-align:center;flex-shrink:0;}

.main-content{padding:28px 32px;overflow-y:auto;max-height:calc(100vh - 130px);}
.section-page{display:none;}
.section-page.active{display:block;}

.section-header{margin-bottom:24px;}
.section-tag{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--teal);margin-bottom:6px;}
.section-title{font-family:var(--serif);font-size:28px;color:#1a1a1a;line-height:1.2;}
.section-desc{font-size:13px;color:var(--muted);margin-top:6px;line-height:1.5;}
.section-divider{height:2px;background:linear-gradient(to right,var(--teal),transparent);margin-top:14px;border-radius:2px;}

.form-card{background:var(--card);border:1px solid var(--border);border-radius:12px;margin-bottom:18px;overflow:hidden;}
.card-header{background:linear-gradient(135deg,var(--slate) 0%,var(--slate-l) 100%);padding:12px 20px;display:flex;align-items:center;gap:10px;}
.card-header-icon{font-size:16px;color:var(--teal-mid);}
.card-header-title{font-size:13px;font-weight:700;color:#fff;letter-spacing:.02em;}
.card-body{padding:20px;}

.form-grid{display:grid;gap:16px;}
.g2{grid-template-columns:1fr 1fr;}
.g3{grid-template-columns:1fr 1fr 1fr;}
.g4{grid-template-columns:1fr 1fr 1fr 1fr;}
.span2{grid-column:span 2;}
.span3{grid-column:span 3;}

.field{display:flex;flex-direction:column;gap:5px;}
.field label{font-size:12px;font-weight:600;color:#1a1a1a;letter-spacing:.01em;}
.field label .req{color:var(--red);margin-left:2px;}
.field label .hint{font-weight:400;color:var(--muted);font-size:11px;margin-left:4px;}
.field input,.field select,.field textarea{padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--sans);font-size:13px;color:#1a1a1a;background:var(--surface);transition:border-color .15s;width:100%;}
.field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(29,158,117,.12);background:var(--card);}
.field textarea{resize:vertical;min-height:80px;}
.field select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23888780'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px;}

.radio-group,.check-group{display:flex;flex-wrap:wrap;gap:8px;}
.radio-pill,.check-pill{display:flex;align-items:center;gap:6px;padding:7px 14px;border:1.5px solid var(--border);border-radius:20px;cursor:pointer;font-size:12px;font-weight:500;color:var(--body);transition:all .15s;user-select:none;}
.radio-pill:hover,.check-pill:hover{border-color:var(--teal);color:var(--teal);background:var(--teal-l);}
.radio-pill input,.check-pill input{display:none;}
.radio-pill.selected,.check-pill.selected{border-color:var(--teal);background:var(--teal-l);color:var(--teal);font-weight:600;}

.sub-divider{display:flex;align-items:center;gap:10px;margin:20px 0 14px;}
.sub-divider-line{flex:1;height:1px;background:var(--border);}
.sub-divider-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;}

.dynamic-list{border:1.5px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px;}
.dynamic-list-header{background:var(--surface);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);}
.dynamic-list-title{font-size:12px;font-weight:600;color:var(--body);}
.add-row-btn{display:flex;align-items:center;gap:5px;background:var(--teal);color:#fff;border:none;padding:5px 12px;border-radius:6px;font-family:var(--sans);font-size:11px;font-weight:600;cursor:pointer;transition:background .15s;}
.add-row-btn:hover{background:var(--teal-d);}
.dynamic-row{display:grid;gap:10px;padding:12px 14px;border-bottom:1px solid var(--border-l);align-items:center;}
.dynamic-row:last-child{border-bottom:none;}
.remove-btn{background:var(--red-l);color:var(--red);border:none;width:26px;height:26px;border-radius:6px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

.notice{background:#FDF3E6;border:1px solid rgba(200,133,42,.25);border-radius:10px;padding:12px 16px;display:flex;gap:10px;align-items:flex-start;margin-bottom:18px;}
.notice-icon{font-size:16px;flex-shrink:0;margin-top:1px;}
.notice-text{font-size:12px;color:var(--amber);line-height:1.5;}

.hipaa-notice{background:#EEEDFE;border:1px solid rgba(83,74,183,.2);border-radius:10px;padding:14px 16px;margin-bottom:20px;}
.hipaa-title{font-size:13px;font-weight:700;color:#534AB7;margin-bottom:6px;}
.hipaa-text{font-size:12px;color:var(--body);line-height:1.6;}

.form-footer{background:var(--card);border-top:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;bottom:0;z-index:80;}
.footer-progress{font-size:12px;color:var(--muted);}
.footer-progress strong{color:var(--teal);font-weight:700;}
.footer-btns{display:flex;gap:10px;}
.btn-back{background:var(--surface);color:var(--body);border:1.5px solid var(--border);padding:10px 22px;border-radius:9px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;}
.btn-back:hover{border-color:var(--teal);color:var(--teal);}
.btn-next{background:var(--teal);color:#fff;border:none;padding:10px 24px;border-radius:9px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;display:flex;align-items:center;gap:8px;}
.btn-next:hover{background:var(--teal-d);}
.btn-submit{background:var(--teal-deep);color:#fff;border:none;padding:10px 28px;border-radius:9px;font-family:var(--sans);font-size:13px;font-weight:700;cursor:pointer;transition:background .15s;}
.btn-submit:hover{background:#052d25;}

.required-legend{font-size:11px;color:var(--muted);margin-bottom:16px;}
.required-legend span{color:var(--red);}

@media(max-width:900px){
  .layout{grid-template-columns:1fr;}
  .side-nav{display:none;}
  .g2,.g3,.g4{grid-template-columns:1fr;}
  .span2,.span3{grid-column:span 1;}
}

.toast{position:fixed;bottom:20px;right:20px;background:var(--teal);color:#fff;padding:16px 20px;border-radius:8px;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.15);}
</style>
</head>
<body>

<div class="topbar">
  <div class="logo-wrap">
    <svg class="logo-hex" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg">
      <polygon points="40,8 68,24 68,56 40,72 12,56 12,24" fill="none" stroke="#1D9E75" stroke-width="2.5"/>
      <polygon points="40,18 58,28 58,52 40,62 22,52 22,28" fill="none" stroke="#534AB7" stroke-width="1.5" opacity=".6"/>
      <circle cx="40" cy="40" r="8" fill="#1D9E75"/>
      <circle cx="40" cy="40" r="4" fill="#085041"/>
    </svg>
    <div class="logo-text"><span class="logo-syn">Syn</span><span class="logo-apta">apta</span></div>
    <div class="topbar-title">Patient Intake Form</div>
  </div>
  <div class="topbar-right">
    <span class="patient-badge">🔒 HIPAA Secure</span>
    <button class="save-btn" onclick="saveProgress()">Save Progress</button>
  </div>
</div>

<div class="progress-wrap">
  <div class="progress-steps" id="progressSteps">
    <div class="step-item"><button class="step-btn active" onclick="goToSection(0)"><span class="step-num">1</span>Who</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(1)"><span class="step-num">2</span>Contact</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(2)"><span class="step-num">3</span>Choices</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(3)"><span class="step-num">4</span>Employer</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(4)"><span class="step-num">5</span>Stats</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(5)"><span class="step-num">6</span>Insurance</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(6)"><span class="step-num">7</span>Symptoms</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(7)"><span class="step-num">8</span>Conditions</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(8)"><span class="step-num">9</span>Medications</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(9)"><span class="step-num">10</span>Allergies</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(10)"><span class="step-num">11</span>Surgical</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(11)"><span class="step-num">12</span>Family</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(12)"><span class="step-num">13</span>Social</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(13)"><span class="step-num">14</span>Related</button></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(14)"><span class="step-num">15</span>Consent</button></div>
  </div>
</div>

<div class="layout">
  <nav class="side-nav">
    <div class="nav-section-label">Demographics</div>
    <div class="nav-item active" onclick="goToSection(0)"><span class="nav-icon">👤</span>Who (Identity)</div>
    <div class="nav-item" onclick="goToSection(1)"><span class="nav-icon">📍</span>Contact</div>
    <div class="nav-item" onclick="goToSection(2)"><span class="nav-icon">⚙️</span>Choices</div>
    <div class="nav-item" onclick="goToSection(3)"><span class="nav-icon">💼</span>Employer</div>
    <div class="nav-item" onclick="goToSection(4)"><span class="nav-icon">📊</span>Stats</div>
    <div class="nav-item" onclick="goToSection(5)"><span class="nav-icon">🏥</span>Insurance</div>
    <div class="nav-section-label">Clinical History</div>
    <div class="nav-item" onclick="goToSection(6)"><span class="nav-icon">🤒</span>Symptoms</div>
    <div class="nav-item" onclick="goToSection(7)"><span class="nav-icon">🩺</span>Conditions</div>
    <div class="nav-item" onclick="goToSection(8)"><span class="nav-icon">💊</span>Medications</div>
    <div class="nav-item" onclick="goToSection(9)"><span class="nav-icon">⚠️</span>Allergies</div>
    <div class="nav-item" onclick="goToSection(10)"><span class="nav-icon">🔪</span>Surgical</div>
    <div class="nav-item" onclick="goToSection(11)"><span class="nav-icon">👨‍👩‍👧</span>Family</div>
    <div class="nav-item" onclick="goToSection(12)"><span class="nav-icon">🌿</span>Social</div>
    <div class="nav-section-label">Final Steps</div>
    <div class="nav-item" onclick="goToSection(13)"><span class="nav-icon">👥</span>Related</div>
    <div class="nav-item" onclick="goToSection(14)"><span class="nav-icon">✍️</span>Consent</div>
  </nav>

  <main class="main-content" id="mainContent">

    <!-- SECTION 0: WHO (IDENTITY) -->
    <div class="section-page active" id="section-0">
      <div class="section-header">
        <div class="section-tag">Section 1 of 15</div>
        <div class="section-title">Who are you?</div>
        <div class="section-desc">Legal identity and demographic information. Fields marked in <span style="color:var(--red)">red</span> are required.</div>
        <div class="section-divider"></div>
      </div>
      <div class="required-legend"><span>*</span> Required fields</div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">👤</span><span class="card-header-title">Legal Name & Identity</span></div>
        <div class="card-body">
          <div class="form-grid g4">
            <div class="field"><label>Title</label><select name="title"><option>-- Select --</option><option>Mr.</option><option>Ms.</option><option>Mrs.</option><option>Dr.</option><option>Mx.</option><option>Prof.</option></select></div>
            <div class="field"><label>First Name <span class="req">*</span></label><input type="text" name="fname" placeholder="Legal first name"/></div>
            <div class="field"><label>Middle Name</label><input type="text" name="mname" placeholder="Middle name"/></div>
            <div class="field"><label>Last Name <span class="req">*</span></label><input type="text" name="lname" placeholder="Legal last name"/></div>
          </div>
          <div class="form-grid g3" style="margin-top:16px;">
            <div class="field"><label>Name Suffix</label><select name="suffix"><option>-- None --</option><option>Jr.</option><option>Sr.</option><option>II</option><option>III</option><option>IV</option></select></div>
            <div class="field span2"><label>Preferred Name <span class="hint">(name patient goes by)</span></label><input type="text" name="preferred_name" placeholder="e.g. nickname or chosen name"/></div>
          </div>
          <div class="sub-divider"><div class="sub-divider-line"></div><div class="sub-divider-label">Birth Name (if different)</div><div class="sub-divider-line"></div></div>
          <div class="form-grid g3">
            <div class="field"><label>Birth First Name</label><input type="text" name="birth_fname" placeholder="Birth first name"/></div>
            <div class="field"><label>Birth Middle Name</label><input type="text" name="birth_mname" placeholder="Birth middle name"/></div>
            <div class="field"><label>Birth Last Name</label><input type="text" name="birth_lname" placeholder="Birth last name"/></div>
          </div>
          <div class="sub-divider"><div class="sub-divider-line"></div><div class="sub-divider-label">Key Demographics</div><div class="sub-divider-line"></div></div>
          <div class="form-grid g4">
            <div class="field"><label>Date of Birth <span class="req">*</span></label><input type="date" name="DOB"/></div>
            <div class="field"><label>Birth Sex <span class="req">*</span></label><select name="sex"><option>-- Select --</option><option>Male</option><option>Female</option><option>Intersex</option><option>Unknown</option></select></div>
            <div class="field"><label>Gender Identity</label><select name="gender_identity"><option>-- Select --</option><option>Man</option><option>Woman</option><option>Transgender Man</option><option>Transgender Woman</option><option>Non-binary</option><option>Genderqueer</option><option>Prefer not to say</option><option>Other</option></select></div>
            <div class="field"><label>Pronouns</label><select name="pronoun"><option>-- Select --</option><option>He/Him/His</option><option>She/Her/Hers</option><option>They/Them/Theirs</option><option>Ze/Zir/Zirs</option><option>Prefer not to say</option></select></div>
          </div>
          <div class="form-grid g4" style="margin-top:16px;">
            <div class="field"><label>Sexual Orientation</label><select name="sexual_orientation"><option>-- Select --</option><option>Straight/Heterosexual</option><option>Gay or Lesbian</option><option>Bisexual</option><option>Queer</option><option>Asexual</option><option>Prefer not to say</option><option>Other</option></select></div>
            <div class="field"><label>Sex (Administrative)</label><select name="sex_admin"><option>-- Select --</option><option>Male</option><option>Female</option><option>Other</option><option>Unknown</option></select></div>
            <div class="field"><label>Marital Status</label><select name="marital_status"><option>-- Select --</option><option>Single</option><option>Married</option><option>Divorced</option><option>Widowed</option><option>Separated</option><option>Domestic Partner</option></select></div>
            <div class="field"><label>Social Security # <span class="hint">(last 4)</span></label><input type="text" name="ss" placeholder="XXX-XX-____" maxlength="11"/></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🪪</span><span class="card-header-title">Identification</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>License / State ID #</label><input type="text" name="license_id" placeholder="ID number"/></div>
            <div class="field"><label>External Patient ID</label><input type="text" name="external_patient_id" placeholder="External system ID"/></div>
            <div class="field"><label>Billing Note</label><input type="text" name="billing_note" placeholder="Note for billing staff"/></div>
          </div>
          <div class="form-grid g2" style="margin-top:16px;">
            <div class="field"><label>Previous Names <span class="hint">(maiden, former legal names)</span></label><textarea name="previous_names" placeholder="List any previous legal names..."></textarea></div>
            <div class="field"><label>User Defined Fields</label><textarea name="user_defined_fields" placeholder="Any custom information..."></textarea></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 1: CONTACT -->
    <div class="section-page" id="section-1">
      <div class="section-header">
        <div class="section-tag">Section 2 of 15</div>
        <div class="section-title">Contact Information</div>
        <div class="section-desc">Home address, phone numbers, and emergency contacts.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">📍</span><span class="card-header-title">Home Address</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field"><label>Address Line 1 <span class="req">*</span></label><input type="text" name="street" placeholder="Street address"/></div>
            <div class="field"><label>Address Line 2</label><input type="text" name="street_line_2" placeholder="Apt, Suite, Unit"/></div>
            <div class="field"><label>City <span class="req">*</span></label><input type="text" name="city" placeholder="City"/></div>
            <div class="field"><label>State <span class="req">*</span></label><select name="state"><option>-- Select --</option><option>CA</option><option>TX</option><option>NY</option><option>FL</option><option>Other</option></select></div>
            <div class="field"><label>Postal Code <span class="req">*</span></label><input type="text" name="postal_code" placeholder="ZIP code" maxlength="10"/></div>
            <div class="field"><label>County</label><select name="county"><option>-- Select --</option><option>Fresno</option><option>Madera</option><option>Kings</option><option>Tulare</option><option>Other</option></select></div>
            <div class="field"><label>Country</label><select name="country_code"><option>United States</option><option>Mexico</option><option>Other</option></select></div>
            <div class="field"><label>Mother's Name</label><input type="text" name="mother_name" placeholder="Mother's full name"/></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">📞</span><span class="card-header-title">Phone & Email</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Home Phone</label><input type="tel" name="phone_home" placeholder="(___) ___-____"/></div>
            <div class="field"><label>Mobile Phone <span class="req">*</span></label><input type="tel" name="phone_cell" placeholder="(___) ___-____"/></div>
            <div class="field"><label>Work Phone</label><input type="tel" name="phone_biz" placeholder="(___) ___-____"/></div>
            <div class="field"><label>Trusted Email <span class="req">*</span></label><input type="email" name="email" placeholder="your@email.com"/></div>
            <div class="field"><label>Contact Email <span class="hint">(if different)</span></label><input type="email" name="email_alternate" placeholder="alternate@email.com"/></div>
            <div class="field"><label>Preferred Contact Method</label><select name="phone_preferred_method"><option>-- Select --</option><option>Mobile Phone (Call)</option><option>Mobile Phone (Text)</option><option>Email</option><option>Home Phone</option><option>Patient Portal</option></select></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🚨</span><span class="card-header-title">Emergency Contact</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Emergency Contact Name <span class="req">*</span></label><input type="text" name="emergency_contact_name" placeholder="Full name"/></div>
            <div class="field"><label>Relationship</label><select name="emergency_contact_relationship"><option>-- Select --</option><option>Spouse/Partner</option><option>Parent</option><option>Child</option><option>Sibling</option><option>Friend</option><option>Other</option></select></div>
            <div class="field"><label>Emergency Phone <span class="req">*</span></label><input type="tel" name="emergency_contact_phone" placeholder="(___) ___-____"/></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 2: CHOICES & PREFERENCES -->
    <div class="section-page" id="section-2">
      <div class="section-header">
        <div class="section-tag">Section 3 of 15</div>
        <div class="section-title">Choices & Preferences</div>
        <div class="section-desc">Communication preferences, provider assignment, and privacy choices.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">👨‍⚕️</span><span class="card-header-title">Provider & Pharmacy</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Primary Care Provider</label><select name="primary_care_provider"><option>-- Select --</option><option>Unassigned</option></select></div>
            <div class="field"><label>Provider Since Date</label><input type="date" name="provider_since_date"/></div>
            <div class="field"><label>Referring Provider</label><select name="referring_provider"><option>-- Select --</option><option>External referral</option><option>Self-referred</option></select></div>
            <div class="field span3"><label>Preferred Pharmacy</label><input type="text" name="preferred_pharmacy" placeholder="Pharmacy name and address"/></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">💬</span><span class="card-header-title">Communication Permissions</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>HIPAA Notice Received</label><select name="hipaa_notice"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Allow Voice Message</label><select name="allow_voice_message"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Leave Message With</label><input type="text" name="voice_message_with" placeholder="Name of person to leave message with"/></div>
            <div class="field"><label>Allow Mail Message</label><select name="allow_mail_message"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Allow SMS / Text</label><select name="allow_sms_text"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Allow Email</label><select name="allow_email_message"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Allow Patient Portal</label><select name="allow_patient_portal_notification"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Allow Immunization Registry</label><select name="allow_immunization_registry"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Allow Immunization Info Sharing</label><select name="allow_immunization_info_sharing"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>Allow Health Info Exchange</label><select name="allow_health_info_exchange"><option>-- Select --</option><option>Yes</option><option>No</option></select></div>
            <div class="field"><label>CMS Portal Login</label><input type="text" name="cmsportal_login" placeholder="CMS Blue Button login (optional)"/></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 3: EMPLOYER -->
    <div class="section-page" id="section-3">
      <div class="section-header">
        <div class="section-tag">Section 4 of 15</div>
        <div class="section-title">Employer Information</div>
        <div class="section-desc">Current employment details used for insurance and billing purposes.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">💼</span><span class="card-header-title">Occupation & Employer</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field"><label>Occupation</label><select name="occupation"><option>-- Select --</option><option>Healthcare</option><option>Education</option><option>Technology</option><option>Retail</option><option>Construction</option><option>Agriculture</option><option>Unemployed</option><option>Student</option><option>Retired</option><option>Other</option></select></div>
            <div class="field"><label>Industry</label><select name="industry"><option>-- Select --</option><option>Healthcare & Social Assistance</option><option>Education Services</option><option>Agriculture</option><option>Manufacturing</option><option>Retail Trade</option><option>Construction</option><option>Finance & Insurance</option><option>Government</option><option>Other</option></select></div>
            <div class="field"><label>Employer Name</label><input type="text" name="employer_name" placeholder="Employer or company name"/></div>
            <div class="field"><label>Employer Address</label><input type="text" name="employer_street" placeholder="Street address"/></div>
            <div class="field"><label>Employer Address Line 2</label><input type="text" name="employer_street2" placeholder="Suite, floor"/></div>
            <div class="field"><label>City</label><input type="text" name="employer_city" placeholder="City"/></div>
            <div class="field"><label>State</label><select name="employer_state"><option>-- Select --</option><option>CA</option><option>TX</option><option>NY</option><option>Other</option></select></div>
            <div class="field"><label>Postal Code</label><input type="text" name="employer_zip" placeholder="ZIP"/></div>
            <div class="field"><label>Employment Start Date</label><input type="date" name="employment_start_date"/></div>
            <div class="field"><label>Employment End Date <span class="hint">(if no longer employed)</span></label><input type="date" name="employment_end_date"/></div>
            <div class="field"><label>Employment Status</label><select name="employment_status"><option>-- Select --</option><option>Full-time</option><option>Part-time</option><option>Self-employed</option><option>Unemployed</option><option>Student</option><option>Retired</option><option>Disability</option></select></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 4: STATS & SOCIAL -->
    <div class="section-page" id="section-4">
      <div class="section-header">
        <div class="section-tag">Section 5 of 15</div>
        <div class="section-title">Demographics & Social Stats</div>
        <div class="section-desc">Used for quality reporting, FQHC metrics, and care coordination.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🌍</span><span class="card-header-title">Race, Ethnicity & Language</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Primary Language</label><select name="language"><option>-- Select --</option><option>English</option><option>Spanish</option><option>Hmong</option><option>Punjabi</option><option>Arabic</option><option>Vietnamese</option><option>Tagalog</option><option>Other</option></select></div>
            <div class="field"><label>Ethnicity</label><select name="ethnicity"><option>-- Select --</option><option>Declined to Specify</option><option>Hispanic or Latino</option><option>Not Hispanic or Latino</option></select></div>
            <div class="field"><label>Race</label><select name="race"><option>-- Select --</option><option>Declined to Specify</option><option>American Indian or Alaska Native</option><option>Asian</option><option>Black or African American</option><option>Native Hawaiian or Pacific Islander</option><option>White</option><option>Other</option><option>Multi-racial</option></select></div>
            <div class="field"><label>Nationality</label><select name="nationality_country"><option>-- Select --</option><option>United States</option><option>Mexico</option><option>India</option><option>Philippines</option><option>Other</option></select></div>
            <div class="field"><label>Interpreter Needed</label><select name="interpreter_needed"><option>-- Select --</option><option>No</option><option>Yes</option></select></div>
            <div class="field"><label>Interpreter Comments</label><input type="text" name="interpreter_comments" placeholder="Dialect or special notes"/></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">📊</span><span class="card-header-title">Financial & Social Factors</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Financial Review Date</label><input type="date" name="financial_review_date"/></div>
            <div class="field"><label>Monthly Household Income</label><input type="text" name="household_income" placeholder="$ amount"/></div>
            <div class="field"><label>Household / Family Size</label><input type="number" name="household_size" placeholder="# of people" min="1" max="20"/></div>
            <div class="field"><label>Homeless / Unstably Housed</label><input type="text" name="homeless_status" placeholder="Yes / No / Specify"/></div>
            <div class="field"><label>Migrant / Seasonal Worker</label><input type="text" name="migrant_seasonal" placeholder="Yes / No / Seasonal dates"/></div>
            <div class="field"><label>Referral Source</label><select name="referral_source"><option>-- Select --</option><option>Self-referred</option><option>Physician referral</option><option>Hospital</option><option>Community Health Worker</option><option>Social Media</option><option>Other</option></select></div>
            <div class="field"><label>VFC Eligibility (Vaccines for Children)</label><select name="vfc_eligibility"><option>-- Select --</option><option>Not Eligible</option><option>Medicaid</option><option>Uninsured</option><option>American Indian/Alaska Native</option><option>Underinsured</option></select></div>
            <div class="field"><label>Religion</label><select name="religion"><option>-- Select --</option><option>Prefer not to say</option><option>Christian</option><option>Catholic</option><option>Muslim</option><option>Hindu</option><option>Sikh</option><option>Jewish</option><option>Buddhist</option><option>Other</option></select></div>
            <div class="field"><label>Tribal Affiliations</label><select name="tribal_affiliations"><option>-- Select --</option><option>None</option><option>Tribal member — specify below</option></select></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">⚰️</span><span class="card-header-title">Misc</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field"><label>Date Deceased</label><input type="date" name="date_deceased"/></div>
            <div class="field"><label>Reason Deceased</label><input type="text" name="reason_deceased" placeholder="If applicable"/></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 5: INSURANCE -->
    <div class="section-page" id="section-5">
      <div class="section-header">
        <div class="section-tag">Section 6 of 15</div>
        <div class="section-title">Insurance Information</div>
        <div class="section-desc">Primary, secondary, and tertiary insurance coverage. Red fields are required for billing.</div>
        <div class="section-divider"></div>
      </div>

      <div class="notice"><div class="notice-icon">📸</div><div class="notice-text"><strong>Insurance Card Upload</strong>Please have your insurance card ready. You can take a photo with your phone camera to auto-fill fields below.</div></div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🏥</span><span class="card-header-title">Primary Insurance</span><span class="card-header-sub">Main coverage</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field span2"><label>Primary Insurance Provider <span class="req">*</span></label><select name="primary_insurance_provider"><option>-- Select or Search --</option><option>Medi-Cal</option><option>Medicare</option><option>Blue Shield of California</option><option>Anthem Blue Cross</option><option>Covered California</option><option>Kaiser Permanente</option><option>Uninsured / Self-Pay</option><option>Other</option></select></div>
            <div class="field"><label>Plan Name <span class="req">*</span></label><input type="text" name="insurance_plan_name" placeholder="Plan name"/></div>
            <div class="field"><label>Policy Number <span class="req">*</span></label><input type="text" name="insurance_policy_number" placeholder="Member ID / Policy #"/></div>
            <div class="field"><label>Group Number</label><input type="text" name="insurance_group_number" placeholder="Group #"/></div>
            <div class="field"><label>Effective Date <span class="req">*</span></label><input type="date" name="insurance_effective_date"/></div>
          </div>
          <div class="sub-divider"><div class="sub-divider-line"></div><div class="sub-divider-label">Subscriber Information</div><div class="sub-divider-line"></div></div>
          <div class="form-grid g3">
            <div class="field"><label>Subscriber First Name <span class="req">*</span></label><input type="text" name="subscriber_fname" placeholder="First"/></div>
            <div class="field"><label>Subscriber Middle</label><input type="text" name="subscriber_mname" placeholder="Middle"/></div>
            <div class="field"><label>Subscriber Last Name <span class="req">*</span></label><input type="text" name="subscriber_lname" placeholder="Last"/></div>
            <div class="field"><label>Relationship to Patient <span class="req">*</span></label><select name="subscriber_relationship"><option>-- Select --</option><option>Self</option><option>Spouse</option><option>Child</option><option>Other</option></select></div>
            <div class="field"><label>Subscriber DOB</label><input type="date" name="subscriber_dob"/></div>
            <div class="field"><label>Subscriber Sex</label><select name="subscriber_sex"><option>-- Select --</option><option>Male</option><option>Female</option><option>Other</option></select></div>
            <div class="field"><label>Subscriber SSN <span class="hint">(if different)</span></label><input type="text" name="subscriber_ssn" placeholder="XXX-XX-____"/></div>
            <div class="field"><label>Subscriber Phone</label><input type="tel" name="subscriber_phone" placeholder="(___) ___-____"/></div>
            <div class="field"><label>Subscriber Employer (SE)</label><input type="text" name="subscriber_employer" placeholder="If unemployed: Student, PT Student, or leave blank"/></div>
          </div>
          <div class="sub-divider"><div class="sub-divider-line"></div><div class="sub-divider-label">Subscriber Address (if different from patient)</div><div class="sub-divider-line"></div></div>
          <div class="form-grid g3">
            <div class="field"><label>Subscriber Address Line 1</label><input type="text" name="subscriber_street" placeholder="Address line 1"/></div>
            <div class="field"><label>Subscriber Address Line 2</label><input type="text" name="subscriber_street2" placeholder="Address line 2"/></div>
            <div class="field"><label>Subscriber City</label><input type="text" name="subscriber_city" placeholder="City"/></div>
            <div class="field"><label>Subscriber State</label><select name="subscriber_state"><option>-- Select --</option><option>CA</option><option>Other</option></select></div>
            <div class="field"><label>Subscriber Zip Code</label><input type="text" name="subscriber_zip" placeholder="ZIP"/></div>
            <div class="field"><label>Subscriber Country</label><select name="subscriber_country"><option>United States</option><option>Other</option></select></div>
            <div class="field"><label>Co-Pay Amount</label><input type="text" name="copay_amount" placeholder="$ amount"/></div>
            <div class="field"><label>Accept Assignment</label><select name="accept_assignment"><option>Yes</option><option>No</option></select></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🏥</span><span class="card-header-title">Secondary Insurance</span><span class="card-header-sub">If applicable</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field span2"><label>Secondary Insurance Provider</label><select name="secondary_insurance_provider"><option>-- None --</option><option>Medi-Cal</option><option>Medicare</option><option>Other</option></select></div>
            <div class="field"><label>Plan Name</label><input type="text" name="secondary_plan_name" placeholder="Plan name"/></div>
            <div class="field"><label>Policy Number</label><input type="text" name="secondary_policy_number" placeholder="Policy #"/></div>
            <div class="field"><label>Group Number</label><input type="text" name="secondary_group_number" placeholder="Group #"/></div>
            <div class="field"><label>Effective Date</label><input type="date" name="secondary_effective_date"/></div>
            <div class="field"><label>Subscriber</label><input type="text" name="secondary_subscriber" placeholder="Subscriber name"/></div>
            <div class="field"><label>Relationship</label><select name="secondary_relationship"><option>-- Select --</option><option>Self</option><option>Spouse</option><option>Child</option><option>Other</option></select></div>
            <div class="field"><label>Co-Pay</label><input type="text" name="secondary_copay" placeholder="$ amount"/></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🏥</span><span class="card-header-title">Tertiary Insurance</span><span class="card-header-sub">If applicable</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field span2"><label>Tertiary Insurance Provider</label><select name="tertiary_insurance_provider"><option>-- None --</option><option>Medi-Cal</option><option>Medicare</option><option>Other</option></select></div>
            <div class="field"><label>Plan Name</label><input type="text" name="tertiary_plan_name" placeholder="Plan name"/></div>
            <div class="field"><label>Policy Number</label><input type="text" name="tertiary_policy_number" placeholder="Policy #"/></div>
            <div class="field"><label>Group Number</label><input type="text" name="tertiary_group_number" placeholder="Group #"/></div>
            <div class="field"><label>Effective Date</label><input type="date" name="tertiary_effective_date"/></div>
            <div class="field"><label>Co-Pay</label><input type="text" name="tertiary_copay" placeholder="$ amount"/></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 6: CURRENT SYMPTOMS -->
    <div class="section-page" id="section-6">
      <div class="section-header">
        <div class="section-tag">Section 7 of 15</div>
        <div class="section-title">Current Symptoms</div>
        <div class="section-desc">Tell us what's bringing you in today. Please be as specific as possible.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🤒</span><span class="card-header-title">Chief Complaint</span></div>
        <div class="card-body">
          <div class="field"><label>What is the main reason for your visit today? <span class="req">*</span></label><textarea name="chief_complaint" placeholder="Describe your main concern in your own words..." style="min-height:100px;"></textarea></div>
          <div class="form-grid g3" style="margin-top:16px;">
            <div class="field"><label>When did symptoms start? <span class="req">*</span></label><select name="symptom_onset"><option>-- Select --</option><option>Today</option><option>1–3 days ago</option><option>4–7 days ago</option><option>1–2 weeks ago</option><option>2–4 weeks ago</option><option>1–3 months ago</option><option>3–6 months ago</option><option>More than 6 months ago</option><option>Chronic / ongoing</option></select></div>
            <div class="field"><label>How did symptoms start?</label><select name="symptom_onset_type"><option>-- Select --</option><option>Sudden / acute onset</option><option>Gradual over time</option><option>After an injury / event</option><option>After a medication change</option><option>After illness (e.g. COVID, flu)</option></select></div>
            <div class="field"><label>Are symptoms getting?</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="symptom_progress"/><span>Better</span></label><label class="radio-pill"><input type="radio" name="symptom_progress"/><span>Same</span></label><label class="radio-pill"><input type="radio" name="symptom_progress"/><span>Worse</span></label></div></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">📋</span><span class="card-header-title">Symptom Details</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field"><label>Pain Location</label><input type="text" name="pain_location" placeholder="Where does it hurt?"/></div>
            <div class="field"><label>Pain Character</label><select name="pain_character"><option>-- Select --</option><option>Sharp / Stabbing</option><option>Dull / Aching</option><option>Burning</option><option>Throbbing / Pulsating</option><option>Pressure / Squeezing</option><option>Cramping</option><option>Shooting</option><option>Tingling / Numbness</option></select></div>
            <div class="field"><label>Current Pain Level (0-10)</label><input type="number" name="pain_level" min="0" max="10" placeholder="0-10"/></div>
            <div class="field"><label>What makes pain worse?</label><input type="text" name="pain_worse" placeholder="e.g. Movement, eating, stress"/></div>
            <div class="field"><label>What makes pain better?</label><input type="text" name="pain_better" placeholder="e.g. Rest, ice, medication"/></div>
            <div class="field"><label>Does pain radiate / spread?</label><input type="text" name="pain_radiation" placeholder="e.g. Down left arm, into jaw"/></div>
          </div>
          <div class="field" style="margin-top:16px;"><label>Additional Notes</label><textarea name="symptom_notes" placeholder="Any other symptoms or concerns..."></textarea></div>
        </div>
      </div>
    </div>

    <!-- SECTION 7: MEDICAL CONDITIONS -->
    <div class="section-page" id="section-7">
      <div class="section-header">
        <div class="section-tag">Section 8 of 15</div>
        <div class="section-title">Medical Conditions</div>
        <div class="section-desc">Check all conditions that apply. This information is used to guide your care.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">❤️</span><span class="card-header-title">Cardiovascular</span></div>
        <div class="card-body">
          <div class="check-group">
            <label class="check-pill"><input type="checkbox" name="condition_hypertension"/><span>Hypertension</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_heart_disease"/><span>Heart Disease (CAD)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_heart_failure"/><span>Heart Failure (CHF)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_afib"/><span>Atrial Fibrillation</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_heart_attack"/><span>History of Heart Attack</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_stroke"/><span>History of Stroke</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_cholesterol"/><span>High Cholesterol (Dyslipidemia)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_pad"/><span>Peripheral Artery Disease</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_dvt_pe"/><span>Deep Vein Thrombosis / PE</span></label>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🩸</span><span class="card-header-title">Metabolic & Endocrine</span></div>
        <div class="card-body">
          <div class="check-group">
            <label class="check-pill"><input type="checkbox" name="condition_diabetes_1"/><span>Type 1 Diabetes</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_diabetes_2"/><span>Type 2 Diabetes</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_prediabetes"/><span>Pre-diabetes</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_obesity"/><span>Obesity</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_thyroid"/><span>Thyroid Disease (Hypo/Hyper)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_gout"/><span>Gout</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_pcos"/><span>Polycystic Ovary Syndrome (PCOS)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_adrenal"/><span>Adrenal Disorders</span></label>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🫁</span><span class="card-header-title">Pulmonary & Respiratory</span></div>
        <div class="card-body">
          <div class="check-group">
            <label class="check-pill"><input type="checkbox" name="condition_asthma"/><span>Asthma</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_copd"/><span>COPD</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_sleep_apnea"/><span>Sleep Apnea</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_bronchitis"/><span>Chronic Bronchitis</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_pulmonary_hypertension"/><span>Pulmonary Hypertension</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_emphysema"/><span>Emphysema</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_pulmonary_fibrosis"/><span>Pulmonary Fibrosis</span></label>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🧠</span><span class="card-header-title">Neurological & Mental Health</span></div>
        <div class="card-body">
          <div class="check-group">
            <label class="check-pill"><input type="checkbox" name="condition_depression"/><span>Depression</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_anxiety"/><span>Anxiety</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_bipolar"/><span>Bipolar Disorder</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_ptsd"/><span>PTSD</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_adhd"/><span>ADHD</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_schizophrenia"/><span>Schizophrenia</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_epilepsy"/><span>Epilepsy / Seizures</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_migraines"/><span>Migraines</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_parkinsons"/><span>Parkinson's Disease</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_dementia"/><span>Dementia / Alzheimer's</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_ms"/><span>Multiple Sclerosis</span></label>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🦴</span><span class="card-header-title">Musculoskeletal & Other</span></div>
        <div class="card-body">
          <div class="check-group">
            <label class="check-pill"><input type="checkbox" name="condition_arthritis"/><span>Arthritis (Osteo)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_ra"/><span>Rheumatoid Arthritis</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_osteoporosis"/><span>Osteoporosis</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_back_pain"/><span>Chronic Back Pain</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_kidney_disease"/><span>Kidney Disease (CKD)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_kidney_stones"/><span>Kidney Stones</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_liver"/><span>Liver Disease / Hepatitis</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_ibd"/><span>Crohn's / Colitis</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_gerd"/><span>GERD / Acid Reflux</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_hiv"/><span>HIV/AIDS</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_cancer"/><span>Cancer (specify below)</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_autoimmune"/><span>Autoimmune Disorder</span></label>
            <label class="check-pill"><input type="checkbox" name="condition_anemia"/><span>Anemia</span></label>
          </div>
          <div class="field" style="margin-top:16px;"><label>Additional Conditions / Cancer Type / Other Details</label><textarea name="additional_conditions" placeholder="Please specify any conditions not listed above, cancer type, or additional details..."></textarea></div>
        </div>
      </div>
    </div>

    <!-- SECTION 8: MEDICATIONS -->
    <div class="section-page" id="section-8">
      <div class="section-header">
        <div class="section-tag">Section 9 of 15</div>
        <div class="section-title">Current Medications</div>
        <div class="section-desc">List all prescription medications, over-the-counter drugs, vitamins, and supplements you currently take.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">💊</span><span class="card-header-title">Medication List</span><span class="card-header-sub">Add all current medications</span></div>
        <div class="card-body">
          <div class="field"><label>Current Medications <span class="hint">(Name, dose, frequency)</span></label><textarea name="current_medications" placeholder="e.g. Metformin 500mg twice daily, Lisinopril 10mg daily..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>OTC Medications / Vitamins / Supplements</label><textarea name="otc_medications" placeholder="e.g. Aspirin 81mg daily, Vitamin D 2000IU, Fish oil..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Herbal Remedies / Alternative Treatments</label><textarea name="herbal_remedies" placeholder="e.g. Turmeric, Ashwagandha, Traditional medicine..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Any medications recently stopped? <span class="hint">(within 3 months)</span></label><textarea name="discontinued_medications" placeholder="List discontinued medications and reason if known..."></textarea></div>
        </div>
      </div>
    </div>

    <!-- SECTION 9: ALLERGIES -->
    <div class="section-page" id="section-9">
      <div class="section-header">
        <div class="section-tag">Section 10 of 15</div>
        <div class="section-title">Allergies</div>
        <div class="section-desc">List all known allergies to medications, foods, environmental triggers, and materials.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">⚠️</span><span class="card-header-title">Drug Allergies</span><span class="card-header-sub">Critical for prescribing safety</span></div>
        <div class="card-body">
          <div class="field"><label>Drug Allergies <span class="hint">(medication, reaction, severity)</span></label><textarea name="drug_allergies" placeholder="e.g. Penicillin — Hives (Severe), NSAIDs — Stomach ulcers (Moderate)..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Food Allergies <span class="hint">(food, reaction, severity)</span></label><textarea name="food_allergies" placeholder="e.g. Peanuts — Anaphylaxis (Life-threatening), Shellfish — Swelling (Severe)..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Environmental / Other Allergies <span class="hint">(latex, pollen, pet dander, contrast dye, etc.)</span></label><textarea name="environmental_allergies" placeholder="List environmental allergies and reactions..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>No Known Drug Allergies (NKDA)</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="nkda" value="yes"/><span>Yes — No known drug allergies</span></label><label class="radio-pill"><input type="radio" name="nkda" value="no"/><span>No — I have drug allergies (listed above)</span></label></div></div>
        </div>
      </div>
    </div>

    <!-- SECTION 10: SURGICAL HISTORY -->
    <div class="section-page" id="section-10">
      <div class="section-header">
        <div class="section-tag">Section 11 of 15</div>
        <div class="section-title">Surgical History</div>
        <div class="section-desc">List all past surgeries, procedures, and hospitalizations in order from most recent.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🔪</span><span class="card-header-title">Past Surgeries & Procedures</span></div>
        <div class="card-body">
          <div class="field"><label>Past Surgeries <span class="hint">(procedure, year, facility, complications)</span></label><textarea name="past_surgeries" placeholder="e.g. Appendectomy (2010), C-section (2012), Knee replacement (2018)..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Anesthesia Complications <span class="hint">(past reactions to anesthesia)</span></label><textarea name="anesthesia_complications" placeholder="Describe any reactions to anesthesia or none..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Hospitalizations <span class="hint">(not surgical — illness, injury, psychiatric)</span></label><textarea name="hospitalizations" placeholder="Year, reason, facility for significant hospitalizations..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Blood Transfusions</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="transfusion" value="yes"/><span>Yes</span></label><label class="radio-pill"><input type="radio" name="transfusion" value="no"/><span>No</span></label></div></div>
        </div>
      </div>
    </div>

    <!-- SECTION 11: FAMILY HEALTH HISTORY -->
    <div class="section-page" id="section-11">
      <div class="section-header">
        <div class="section-tag">Section 12 of 15</div>
        <div class="section-title">Family Health History</div>
        <div class="section-desc">Check conditions that run in your biological family. This helps identify genetic risk factors for your care.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">👨‍👩‍👧</span><span class="card-header-title">Family Conditions</span><span class="card-header-sub">Check all that apply and note which relative</span></div>
        <div class="card-body">
          <div class="field"><label>Family History of Conditions <span class="hint">(condition, relative, age of onset)</span></label><textarea name="family_history" placeholder="e.g. Heart Disease (Father, age 55), Diabetes Type 2 (Mother), Breast Cancer (Sister, age 48)..."></textarea></div>
          <div class="form-grid g2" style="margin-top:16px;">
            <div class="field"><label>Father — Status & Cause of Death <span class="hint">(if deceased)</span></label><input type="text" name="father_status" placeholder="Age, cause of death or 'Living, age X'"/></div>
            <div class="field"><label>Mother — Status & Cause of Death <span class="hint">(if deceased)</span></label><input type="text" name="mother_status" placeholder="Age, cause of death or 'Living, age X'"/></div>
          </div>
          <div class="field" style="margin-top:16px;"><label>Additional Family History Notes</label><textarea name="family_history_notes" placeholder="Any other relevant family health information, genetic testing results, hereditary conditions..."></textarea></div>
          <div class="field" style="margin-top:16px;"><label>Family History Unknown</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="family_history_unknown" value="yes"/><span>Yes — adopted or unknown</span></label><label class="radio-pill"><input type="radio" name="family_history_unknown" value="no"/><span>No — history is known</span></label></div></div>
        </div>
      </div>
    </div>

    <!-- SECTION 12: SOCIAL HISTORY -->
    <div class="section-page" id="section-12">
      <div class="section-header">
        <div class="section-tag">Section 13 of 15</div>
        <div class="section-title">Social History</div>
        <div class="section-desc">Lifestyle and behavioral factors that affect your health. All responses are confidential and used only for your care.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🚬</span><span class="card-header-title">Tobacco Use</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Tobacco Status</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="tobacco_status"/><span>Never</span></label><label class="radio-pill"><input type="radio" name="tobacco_status"/><span>Former</span></label><label class="radio-pill"><input type="radio" name="tobacco_status"/><span>Current</span></label></div></div>
            <div class="field"><label>Product Type</label><select name="tobacco_product"><option>-- Select --</option><option>Cigarettes</option><option>Cigars</option><option>Pipe</option><option>Chewing tobacco</option><option>E-cigarette / Vape</option><option>Hookah</option><option>Multiple</option></select></div>
            <div class="field"><label>Packs per Day / Amount</label><input type="text" name="tobacco_amount" placeholder="e.g. 1 ppd, 5 cigarettes/day"/></div>
            <div class="field"><label>Years Used</label><input type="text" name="tobacco_years" placeholder="Number of years"/></div>
            <div class="field"><label>Quit Date <span class="hint">(if former)</span></label><input type="date" name="tobacco_quit_date"/></div>
            <div class="field"><label>Interested in Cessation?</label><select name="tobacco_cessation"><option>-- Select --</option><option>Yes</option><option>No</option><option>Already quit</option><option>Not applicable</option></select></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🍺</span><span class="card-header-title">Alcohol Use</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Alcohol Use</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="alcohol_use"/><span>Never</span></label><label class="radio-pill"><input type="radio" name="alcohol_use"/><span>Former</span></label><label class="radio-pill"><input type="radio" name="alcohol_use"/><span>Current</span></label></div></div>
            <div class="field"><label>Drinks per Week</label><input type="number" name="alcohol_drinks_week" placeholder="# drinks/week" min="0"/></div>
            <div class="field"><label>Type of Alcohol</label><select name="alcohol_type"><option>-- Select --</option><option>Beer</option><option>Wine</option><option>Spirits / Liquor</option><option>Mixed</option></select></div>
            <div class="field span3"><label>AUDIT-C Screening <span class="hint">(How often do you have 6+ drinks on one occasion?)</span></label><div class="radio-group"><label class="radio-pill"><input type="radio" name="audit_c"/><span>Never</span></label><label class="radio-pill"><input type="radio" name="audit_c"/><span>Less than monthly</span></label><label class="radio-pill"><input type="radio" name="audit_c"/><span>Monthly</span></label><label class="radio-pill"><input type="radio" name="audit_c"/><span>Weekly</span></label><label class="radio-pill"><input type="radio" name="audit_c"/><span>Daily / almost daily</span></label></div></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">💉</span><span class="card-header-title">Substance Use</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field"><label>Recreational Drug Use</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="drug_use"/><span>Never</span></label><label class="radio-pill"><input type="radio" name="drug_use"/><span>Former</span></label><label class="radio-pill"><input type="radio" name="drug_use"/><span>Current</span></label></div></div>
            <div class="field"><label>Substances Used <span class="hint">(confidential)</span></label><input type="text" name="substances" placeholder="e.g. Cannabis, opioids — or 'Decline to specify'"/></div>
            <div class="field"><label>Frequency</label><select name="substance_frequency"><option>-- Select --</option><option>Daily</option><option>Weekly</option><option>Monthly</option><option>Occasionally</option><option>Former use only</option></select></div>
            <div class="field"><label>Interested in Treatment / Support?</label><select name="substance_treatment"><option>-- Select --</option><option>Yes</option><option>No</option><option>Already in treatment</option></select></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🏃</span><span class="card-header-title">Physical Activity & Diet</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Exercise Frequency</label><select name="exercise_frequency"><option>-- Select --</option><option>Daily</option><option>3–5x per week</option><option>1–2x per week</option><option>Rarely</option><option>Never</option></select></div>
            <div class="field"><label>Exercise Type</label><input type="text" name="exercise_type" placeholder="e.g. Walking, gym, swimming"/></div>
            <div class="field"><label>Minutes per Session</label><input type="number" name="exercise_minutes" placeholder="e.g. 30" min="0"/></div>
            <div class="field"><label>Diet Type</label><select name="diet_type"><option>-- Select --</option><option>Standard / No restrictions</option><option>Vegetarian</option><option>Vegan</option><option>Gluten-free</option><option>Diabetic diet</option><option>Low-sodium</option><option>Low-carb / Keto</option><option>Halal</option><option>Kosher</option><option>Other</option></select></div>
            <div class="field"><label>Daily Water Intake</label><select name="water_intake"><option>-- Select --</option><option>Less than 4 cups</option><option>4–8 cups</option><option>8+ cups</option></select></div>
            <div class="field"><label>Sleep Hours per Night</label><input type="number" name="sleep_hours" placeholder="e.g. 7" min="0" max="24"/></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🏠</span><span class="card-header-title">Living Situation & Safety</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Living Situation</label><select name="living_situation"><option>-- Select --</option><option>Lives alone</option><option>Lives with spouse / partner</option><option>Lives with family</option><option>Lives with roommates</option><option>Group home / assisted living</option><option>Homeless / unstably housed</option></select></div>
            <div class="field"><label>Highest Education Level</label><select name="education_level"><option>-- Select --</option><option>Less than high school</option><option>High school / GED</option><option>Some college</option><option>Associate's degree</option><option>Bachelor's degree</option><option>Graduate degree</option></select></div>
            <div class="field"><label>Occupation Category</label><select name="occupation_category"><option>-- Select --</option><option>Healthcare worker</option><option>Teacher / Education</option><option>Manual / Physical labor</option><option>Office / Administrative</option><option>Food service</option><option>Agriculture</option><option>Caregiver</option><option>Unemployed</option><option>Student</option><option>Retired</option></select></div>
            <div class="field"><label>Domestic Violence — Do you feel safe at home?</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="domestic_violence_safety"/><span>Yes</span></label><label class="radio-pill"><input type="radio" name="domestic_violence_safety"/><span>No</span></label><label class="radio-pill"><input type="radio" name="domestic_violence_safety"/><span>Prefer not to say</span></label></div></div>
            <div class="field"><label>Seat Belt Use</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="seatbelt"/><span>Always</span></label><label class="radio-pill"><input type="radio" name="seatbelt"/><span>Sometimes</span></label><label class="radio-pill"><input type="radio" name="seatbelt"/><span>Never</span></label></div></div>
            <div class="field"><label>Firearms in Household</label><div class="radio-group"><label class="radio-pill"><input type="radio" name="firearms"/><span>Yes</span></label><label class="radio-pill"><input type="radio" name="firearms"/><span>No</span></label><label class="radio-pill"><input type="radio" name="firearms"/><span>Decline</span></label></div></div>
          </div>
          <div class="field" style="margin-top:16px;"><label>Additional Social History Notes</label><textarea name="social_history_notes" placeholder="Military service, recent life stressors, caregiver responsibilities, other relevant context..."></textarea></div>
        </div>
      </div>
    </div>

    <!-- SECTION 13: RELATED PERSONS -->
    <div class="section-page" id="section-13">
      <div class="section-header">
        <div class="section-tag">Section 14 of 15</div>
        <div class="section-title">Related Persons</div>
        <div class="section-desc">Guardians, guarantors, authorized contacts, and representatives for this patient.</div>
        <div class="section-divider"></div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">👥</span><span class="card-header-title">Related Person</span></div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="field"><label>Full Name</label><input type="text" name="related_person_name" placeholder="Related person's full name"/></div>
            <div class="field"><label>Relationship</label><select name="related_person_relationship"><option>-- Select --</option><option>Spouse / Partner</option><option>Parent</option><option>Child</option><option>Sibling</option><option>Legal Guardian</option><option>Guarantor</option><option>Power of Attorney</option><option>Caregiver</option><option>Other</option></select></div>
            <div class="field"><label>Sex</label><select name="related_person_sex"><option>-- Select --</option><option>Male</option><option>Female</option><option>Other</option></select></div>
            <div class="field span2"><label>Address</label><input type="text" name="related_person_street" placeholder="Street address"/></div>
            <div class="field"><label>City</label><input type="text" name="related_person_city" placeholder="City"/></div>
            <div class="field"><label>State</label><select name="related_person_state"><option>-- Select --</option><option>CA</option><option>Other</option></select></div>
            <div class="field"><label>Postal Code</label><input type="text" name="related_person_zip" placeholder="ZIP"/></div>
            <div class="field"><label>Country</label><select name="related_person_country"><option>United States</option><option>Other</option></select></div>
            <div class="field"><label>Phone</label><input type="tel" name="related_person_phone" placeholder="(___) ___-____"/></div>
            <div class="field"><label>Work Phone</label><input type="tel" name="related_person_work_phone" placeholder="(___) ___-____"/></div>
            <div class="field"><label>Email</label><input type="email" name="related_person_email" placeholder="email@example.com"/></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 14: CONSENT & AUTHORIZATION -->
    <div class="section-page" id="section-14">
      <div class="section-header">
        <div class="section-tag">Section 15 of 15</div>
        <div class="section-title">Consent & Authorization</div>
        <div class="section-desc">Please review and sign the following consents to complete your registration.</div>
        <div class="section-divider"></div>
      </div>

      <div class="hipaa-notice">
        <div class="hipaa-title">🔒 HIPAA Notice of Privacy Practices</div>
        <div class="hipaa-text">This practice is required by law to protect the privacy of your health information and to provide you with notice of our legal duties and privacy practices with respect to protected health information. We will use and disclose your protected health information only as allowed by law.</div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">📋</span><span class="card-header-title">Consent to Treatment</span></div>
        <div class="card-body">
          <div class="field">
            <p style="font-size:13px;color:var(--body);line-height:1.6;margin-bottom:16px;">I hereby consent to medical treatment by the providers of this practice, including examinations, diagnostic procedures, and treatments deemed necessary. I authorize this practice to use my health information for treatment, payment, and healthcare operations.</p>
            <label class="check-pill" style="display:inline-flex;"><input type="checkbox" name="consent_treatment"/><span>I consent to treatment</span></label>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">💳</span><span class="card-header-title">Financial Responsibility & Insurance Authorization</span></div>
        <div class="card-body">
          <div class="field">
            <p style="font-size:13px;color:var(--body);line-height:1.6;margin-bottom:16px;">I authorize this practice to bill my insurance company on my behalf and agree to be responsible for any balance not covered by insurance.</p>
            <label class="check-pill" style="display:inline-flex;"><input type="checkbox" name="consent_financial"/><span>I authorize insurance billing</span></label>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">📱</span><span class="card-header-title">Digital Communications Consent</span></div>
        <div class="card-body">
          <div class="field">
            <p style="font-size:13px;color:var(--body);line-height:1.6;margin-bottom:16px;">I consent to receive appointment reminders, test results, and health information via the patient portal, email, and/or SMS text messages.</p>
            <label class="check-pill" style="display:inline-flex;"><input type="checkbox" name="consent_digital"/><span>I consent to digital communications</span></label>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">✍️</span><span class="card-header-title">Patient Signature</span></div>
        <div class="card-body">
          <div class="form-grid g3" style="margin-bottom:16px;">
            <div class="field"><label>Printed Name <span class="req">*</span></label><input type="text" name="signature_name" placeholder="Full legal name"/></div>
            <div class="field"><label>Date <span class="req">*</span></label><input type="date" name="signature_date"/></div>
            <div class="field"><label>Relationship to Patient <span class="hint">(if signing for another)</span></label><select name="signature_relationship"><option>Self (Patient)</option><option>Parent / Guardian</option><option>Legal Representative</option><option>Power of Attorney</option></select></div>
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="card-header"><span class="card-header-icon">🩺</span><span class="card-header-title">How did you hear about us?</span></div>
        <div class="card-body">
          <div class="form-grid g2">
            <div class="field"><label>Referral Source</label><select name="how_did_you_hear"><option>-- Select --</option><option>Physician referral</option><option>Friend / Family</option><option>Online search</option><option>Insurance directory</option><option>Social media</option><option>Community Health Worker</option><option>Walk-in</option><option>Other</option></select></div>
            <div class="field"><label>Referring Provider Name <span class="hint">(if applicable)</span></label><input type="text" name="referring_provider_name" placeholder="Referring provider name"/></div>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>

<div class="form-footer">
  <div class="footer-progress">Section <strong id="currentSectionLabel">1</strong> of <strong>15</strong></div>
  <div class="footer-btns">
    <button class="btn-back" id="btnBack" onclick="prevSection()" style="display:none;">← Back</button>
    <button class="btn-next" id="btnNext" onclick="nextSection()">Continue →</button>
    <button class="btn-submit" id="btnSubmit" onclick="submitForm()" style="display:none;">✓ Submit Intake Form</button>
  </div>
</div>

<script>
let currentSection = 0;
const totalSections = 15;

function goToSection(n) {
  document.querySelectorAll('.section-page').forEach((el,i) => el.classList.toggle('active', i===n));
  document.querySelectorAll('.step-btn').forEach((el,i) => {
    el.classList.remove('active');
    if(i===n) el.classList.add('active');
  });
  document.querySelectorAll('.nav-item').forEach((el,i) => el.classList.toggle('active', i===n));
  currentSection = n;
  document.getElementById('currentSectionLabel').textContent = n+1;
  document.getElementById('btnBack').style.display = n===0?'none':'';
  document.getElementById('btnNext').style.display = n===totalSections-1?'none':'';
  document.getElementById('btnSubmit').style.display = n===totalSections-1?'':'none';
  document.getElementById('mainContent').scrollTop = 0;
}

function nextSection() { if(currentSection < totalSections-1) goToSection(currentSection+1); }
function prevSection() { if(currentSection > 0) goToSection(currentSection-1); }

function togglePill(input) {
  input.closest('.check-pill')?.classList.toggle('selected', input.checked);
}

function selectPill(input) {
  input.closest('.radio-pill')?.classList.add('selected');
  document.querySelectorAll(`input[name="${input.name}"]`).forEach(i => {
    if(i !== input) i.closest('.radio-pill')?.classList.remove('selected');
  });
}

function saveProgress() {
  const formData = new FormData(document.querySelector('form') || document.body);
  localStorage.setItem('patientFormData', JSON.stringify(Object.fromEntries(formData)));
  alert('Progress saved!');
}

function submitForm() {
  const form = document.querySelector('form');
  const formInputs = document.querySelectorAll('input, select, textarea');
  const data = {};

  formInputs.forEach(input => {
    if(input.name) {
      if(input.type === 'checkbox') data[input.name] = input.checked;
      else if(input.type === 'radio') { if(input.checked) data[input.name] = input.value || input.checked; }
      else data[input.name] = input.value;
    }
  });

  fetch('/interface/patientAddUpdate/patient_save.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(res => {
    if(res.success) {
      alert('✓ Patient Added Successfully!\nPatient ID: ' + res.pid);
      location.reload();
    } else {
      alert('Error: ' + (res.message || 'Failed to save'));
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

document.addEventListener('change', (e) => {
  if(e.target.matches('.check-pill input')) togglePill(e.target);
  if(e.target.matches('.radio-pill input')) selectPill(e.target);
});

goToSection(0);
</script>
</body>
</html>
