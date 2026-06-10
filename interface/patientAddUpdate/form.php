<?php
/**
 * Patient Add/Update Form Wrapper
 * Displays the Synapta patient intake form with AJAX submission
 * Accessible without login
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
<title>Synapta — Patient Intake Form</title>
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
  --purple:#534AB7;
  --purple-mid:#7F77DD;
  --purple-l:#EEEDFE;
  --gold:#C8852A;
  --gold-l:#FDF3E6;
  --red:#A32D2D;
  --red-l:#FBEAEA;
  --amber:#C77A0A;
  --ink:#1a1a1a;
  --body:#444441;
  --muted:#888780;
  --border:#D3D1C7;
  --border-l:#F1EFE8;
  --surface:#FAFAF7;
  --card:#FFFFFF;
  --sans:'Outfit',system-ui,sans-serif;
  --serif:'DM Serif Display',Georgia,serif;
  --success:#10B981;
  --error:#EF4444;
}

html{scroll-behavior:smooth;}
body{font-family:var(--sans);background:var(--surface);color:var(--ink);min-height:100vh;}

/* Toast Notification */
.toast-container{position:fixed;top:20px;right:20px;z-index:9999;width:90%;max-width:400px;}
.toast{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);animation:slideIn 0.3s ease-out;}
.toast.success{border-left:4px solid var(--success);background:#f0fdf4;}
.toast.error{border-left:4px solid var(--error);background:#fef2f2;}
.toast-title{font-weight:700;margin-bottom:4px;font-size:14px;}
.toast.success .toast-title{color:var(--success);}
.toast.error .toast-title{color:var(--error);}
.toast-message{font-size:13px;color:var(--body);line-height:1.5;}
@keyframes slideIn{from{transform:translateX(400px);opacity:0;}to{transform:translateX(0);opacity:1;}}

/* Modal Overlay */
.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:5000;justify-content:center;align-items:center;}
.modal-overlay.active{display:flex;}
.modal{background:var(--card);border-radius:12px;padding:32px;text-align:center;min-width:400px;box-shadow:0 20px 25px rgba(0,0,0,0.15);}
.modal-icon{font-size:48px;margin-bottom:16px;}
.modal-title{font-size:22px;font-weight:700;color:var(--ink);margin-bottom:12px;}
.modal-message{font-size:14px;color:var(--muted);line-height:1.6;margin-bottom:24px;}
.modal-pid{background:var(--teal-l);border:1px solid var(--teal);color:var(--teal);padding:12px;border-radius:8px;margin-bottom:24px;font-weight:600;font-family:monospace;}
.modal-button{background:var(--teal);color:#fff;border:none;padding:12px 28px;border-radius:8px;font-family:var(--sans);font-size:14px;font-weight:600;cursor:pointer;transition:background 0.15s;}
.modal-button:hover{background:var(--teal-d);}

/* TOP NAV */
.topbar{
  background:var(--navy);
  border-bottom:2px solid rgba(29,158,117,.3);
  position:sticky;top:0;z-index:100;
  display:flex;align-items:center;
  padding:0 28px;height:64px;gap:16px;
}
.logo-wrap{display:flex;align-items:center;gap:10px;}
.logo-hex{width:36px;height:36px;}
.logo-text{font-family:var(--serif);font-size:22px;letter-spacing:-.3px;}
.logo-syn{color:var(--teal-mid);}
.logo-apta{color:var(--purple-mid);}
.topbar-title{font-size:13px;color:rgba(255,255,255,.45);margin-left:8px;padding-left:16px;border-left:1px solid rgba(255,255,255,.1);}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:12px;}
.patient-badge{background:rgba(29,158,117,.15);border:1px solid rgba(29,158,117,.3);color:var(--teal-mid);font-size:12px;font-weight:600;padding:5px 12px;border-radius:20px;}
.save-btn{background:var(--teal);color:#fff;border:none;padding:8px 20px;border-radius:8px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;}
.save-btn:hover{background:var(--teal-d);}
.save-btn:disabled{background:var(--muted);cursor:not-allowed;}

/* PROGRESS BAR */
.progress-wrap{background:var(--card);border-bottom:1px solid var(--border);padding:16px 28px;position:sticky;top:64px;z-index:90;}
.progress-steps{display:flex;gap:0;overflow-x:auto;scrollbar-width:none;}
.progress-steps::-webkit-scrollbar{display:none;}
.step-item{display:flex;align-items:center;flex:1;min-width:0;}
.step-btn{
  display:flex;align-items:center;gap:8px;
  padding:8px 12px;border-radius:8px;border:none;
  background:transparent;font-family:var(--sans);
  font-size:12px;font-weight:500;color:var(--muted);
  cursor:pointer;transition:all .18s;white-space:nowrap;
  width:100%;
}
.step-btn:hover{background:var(--teal-l);color:var(--teal);}
.step-btn.active{background:var(--teal);color:#fff;font-weight:600;}
.step-btn.done{color:var(--teal-d);}
.step-btn.done .step-num{background:var(--teal);color:#fff;}
.step-num{
  width:22px;height:22px;border-radius:50%;
  background:var(--border-l);color:var(--muted);
  font-size:11px;font-weight:700;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  transition:all .18s;
}
.step-btn.active .step-num{background:rgba(255,255,255,.25);color:#fff;}
.step-connector{width:20px;height:1px;background:var(--border);flex-shrink:0;margin:0 2px;}

/* LAYOUT */
.layout{display:grid;grid-template-columns:220px 1fr;gap:0;min-height:calc(100vh - 130px);}

/* SIDEBAR NAV */
.side-nav{background:var(--card);border-right:1px solid var(--border);padding:20px 0;position:sticky;top:130px;height:calc(100vh - 130px);overflow-y:auto;}
.nav-section-label{font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;padding:12px 16px 4px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;transition:all .15s;border-left:3px solid transparent;font-size:13px;color:var(--body);}
.nav-item:hover{background:var(--teal-l);color:var(--teal);}
.nav-item.active{background:var(--teal-l);color:var(--teal);border-left-color:var(--teal);font-weight:600;}
.nav-item.complete{color:var(--teal-d);}
.nav-icon{font-size:15px;width:18px;text-align:center;flex-shrink:0;}
.nav-check{margin-left:auto;color:var(--teal);font-size:12px;}

/* MAIN CONTENT */
.main-content{padding:28px 32px;overflow-y:auto;max-height:calc(100vh - 130px);}
.section-page{display:none;}
.section-page.active{display:block;}

/* SECTION HEADER */
.section-header{margin-bottom:24px;}
.section-tag{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--teal);margin-bottom:6px;}
.section-title{font-family:var(--serif);font-size:28px;color:var(--ink);line-height:1.2;}
.section-desc{font-size:13px;color:var(--muted);margin-top:6px;line-height:1.5;}
.section-divider{height:2px;background:linear-gradient(to right,var(--teal),transparent);margin-top:14px;border-radius:2px;}

/* FORM CARD */
.form-card{background:var(--card);border:1px solid var(--border);border-radius:12px;margin-bottom:18px;overflow:hidden;}
.card-header{background:linear-gradient(135deg,var(--slate) 0%,var(--slate-l) 100%);padding:12px 20px;display:flex;align-items:center;gap:10px;}
.card-header-icon{font-size:16px;color:var(--teal-mid);}
.card-header-title{font-size:13px;font-weight:700;color:#fff;letter-spacing:.02em;}
.card-body{padding:20px;}

/* FORM GRID */
.form-grid{display:grid;gap:16px;}
.g2{grid-template-columns:1fr 1fr;}
.g3{grid-template-columns:1fr 1fr 1fr;}
.g4{grid-template-columns:1fr 1fr 1fr 1fr;}
.span2{grid-column:span 2;}
.span3{grid-column:span 3;}
.span4{grid-column:span 4;}

/* FIELD */
.field{display:flex;flex-direction:column;gap:5px;}
.field label{font-size:12px;font-weight:600;color:var(--body);letter-spacing:.01em;}
.field label .req{color:var(--red);margin-left:2px;}
.field label .hint{font-weight:400;color:var(--muted);font-size:11px;margin-left:4px;}
.field input,.field select,.field textarea{
  padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;
  font-family:var(--sans);font-size:13px;color:var(--ink);
  background:var(--surface);transition:border-color .15s,box-shadow .15s;
  width:100%;
}
.field input:focus,.field select:focus,.field textarea:focus{
  outline:none;border-color:var(--teal);
  box-shadow:0 0 0 3px rgba(29,158,117,.12);
  background:var(--card);
}
.field input::placeholder,.field textarea::placeholder{color:#BBBBB0;font-size:12px;}
.field textarea{resize:vertical;min-height:80px;}
.field select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23888780'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;}

/* SECTION DIVIDER */
.sub-divider{display:flex;align-items:center;gap:10px;margin:20px 0 14px;}
.sub-divider-line{flex:1;height:1px;background:var(--border);}
.sub-divider-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;}

/* FOOTER ACTIONS */
.form-footer{
  background:var(--card);border-top:1px solid var(--border);
  padding:16px 32px;display:flex;align-items:center;
  justify-content:space-between;position:sticky;bottom:0;z-index:80;
}
.footer-progress{font-size:12px;color:var(--muted);}
.footer-progress strong{color:var(--teal);font-weight:700;}
.footer-btns{display:flex;gap:10px;}
.btn-back{background:var(--surface);color:var(--body);border:1.5px solid var(--border);padding:10px 22px;border-radius:9px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;}
.btn-back:hover{border-color:var(--teal);color:var(--teal);}
.btn-next{background:var(--teal);color:#fff;border:none;padding:10px 24px;border-radius:9px;font-family:var(--sans);font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;display:flex;align-items:center;gap:8px;}
.btn-next:hover{background:var(--teal-d);}
.btn-submit{background:var(--teal-deep);color:#fff;border:none;padding:10px 28px;border-radius:9px;font-family:var(--sans);font-size:13px;font-weight:700;cursor:pointer;transition:background .15s;}
.btn-submit:hover{background:#052d25;}
.btn-submit:disabled{background:var(--muted);cursor:not-allowed;}

/* RESPONSIVE */
@media(max-width:900px){
  .layout{grid-template-columns:1fr;}
  .side-nav{display:none;}
  .g2,.g3,.g4{grid-template-columns:1fr;}
  .span2,.span3,.span4{grid-column:span 1;}
}

.required-legend{font-size:11px;color:var(--muted);margin-bottom:16px;}
.required-legend span{color:var(--red);}
</style>
</head>
<body>

<!-- Toast Notifications -->
<div class="toast-container" id="toastContainer"></div>

<!-- Success Modal -->
<div class="modal-overlay" id="successModal">
  <div class="modal">
    <div class="modal-icon">✅</div>
    <div class="modal-title" id="modalTitle">Patient Added Successfully</div>
    <div class="modal-message">Your patient record has been created in the system.</div>
    <div class="modal-pid" id="modalPid"></div>
    <button class="modal-button" onclick="resetForm()">Add Another Patient</button>
  </div>
</div>

<!-- TOP BAR -->
<div class="topbar">
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
    <button class="save-btn" id="submitBtn" onclick="submitForm()">Submit</button>
  </div>
</div>

<!-- PROGRESS STEPS -->
<div class="progress-wrap">
  <div class="progress-steps" id="progressSteps">
    <div class="step-item"><button class="step-btn active" onclick="goToSection(0)"><span class="step-num">1</span>Who</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(1)"><span class="step-num">2</span>Contact</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(2)"><span class="step-num">3</span>Choices</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(3)"><span class="step-num">4</span>Employer</button><div class="step-connector"></div></div>
    <div class="step-item"><button class="step-btn" onclick="goToSection(4)"><span class="step-num">5</span>Stats</button><div class="step-connector"></div></div>
  </div>
</div>

<!-- MAIN LAYOUT -->
<div class="layout">

  <!-- Side Nav (Keep as is from original) -->
  <nav class="side-nav">
    <div class="nav-section-label">Demographics</div>
    <div class="nav-item active" onclick="goToSection(0)"><span class="nav-icon">👤</span>Who (Identity)</div>
    <div class="nav-item" onclick="goToSection(1)"><span class="nav-icon">📍</span>Contact</div>
    <div class="nav-item" onclick="goToSection(2)"><span class="nav-icon">⚙️</span>Choices & Preferences</div>
    <div class="nav-item" onclick="goToSection(3)"><span class="nav-icon">💼</span>Employer</div>
    <div class="nav-item" onclick="goToSection(4)"><span class="nav-icon">📊</span>Stats & Social</div>
  </nav>

  <!-- MAIN CONTENT -->
  <main class="main-content" id="mainContent">
    <form id="patientForm">

      <!-- SECTION 0 — WHO (IDENTITY) -->
      <div class="section-page active" id="section-0">
        <div class="section-header">
          <div class="section-tag">Section 1 of 5</div>
          <div class="section-title">Who are you?</div>
          <div class="section-desc">Legal identity and demographic information. Fields marked in <span style="color:var(--red)">red</span> are required.</div>
          <div class="section-divider"></div>
        </div>
        <div class="required-legend"><span>*</span> Required fields</div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">👤</span><span class="card-header-title">Legal Name & Identity</span></div>
          <div class="card-body">
            <div class="form-grid g4" style="margin-bottom:16px;">
              <div class="field"><label>Title</label><select name="title"><option value="">-- Select --</option><option>Mr.</option><option>Ms.</option><option>Mrs.</option><option>Dr.</option><option>Mx.</option><option>Prof.</option></select></div>
              <div class="field"><label>First Name <span class="req">*</span></label><input type="text" name="fname" required placeholder="Legal first name"/></div>
              <div class="field"><label>Middle Name</label><input type="text" name="mname" placeholder="Middle name"/></div>
              <div class="field"><label>Last Name <span class="req">*</span></label><input type="text" name="lname" required placeholder="Legal last name"/></div>
            </div>
            <div class="form-grid g3">
              <div class="field"><label>Name Suffix</label><select name="suffix"><option value="">-- None --</option><option>Jr.</option><option>Sr.</option><option>II</option><option>III</option><option>IV</option></select></div>
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
              <div class="field"><label>Date of Birth <span class="req">*</span></label><input type="date" name="DOB" required/></div>
              <div class="field"><label>Birth Sex <span class="req">*</span></label><select name="sex" required><option value="">-- Select --</option><option value="Male">Male</option><option value="Female">Female</option><option value="Intersex">Intersex</option><option value="Unknown">Unknown</option></select></div>
              <div class="field"><label>Gender Identity</label><select name="gender_identity"><option value="">-- Select --</option><option>Man</option><option>Woman</option><option>Transgender Man</option><option>Transgender Woman</option><option>Non-binary</option><option>Genderqueer</option><option>Prefer not to say</option><option>Other</option></select></div>
              <div class="field"><label>Pronouns</label><select name="pronoun"><option value="">-- Select --</option><option>He/Him/His</option><option>She/Her/Hers</option><option>They/Them/Theirs</option><option>Ze/Zir/Zirs</option><option>Prefer not to say</option></select></div>
            </div>
            <div class="form-grid g4" style="margin-top:16px;">
              <div class="field"><label>Sexual Orientation</label><select name="sexual_orientation"><option value="">-- Select --</option><option>Straight/Heterosexual</option><option>Gay or Lesbian</option><option>Bisexual</option><option>Queer</option><option>Asexual</option><option>Prefer not to say</option><option>Other</option></select></div>
              <div class="field"><label>Sex (Administrative)</label><select name="sex_identified"><option value="">-- Select --</option><option>Male</option><option>Female</option><option>Other</option><option>Unknown</option></select></div>
              <div class="field"><label>Social Security # <span class="hint">(last 4)</span></label><input type="text" name="ss" placeholder="XXX-XX-____" maxlength="11"/></div>
              <div class="field"><label>Billing Note</label><input type="text" name="billing_note" placeholder="Note for billing staff"/></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 1 — CONTACT -->
      <div class="section-page" id="section-1">
        <div class="section-header">
          <div class="section-tag">Section 2 of 5</div>
          <div class="section-title">Contact Information</div>
          <div class="section-desc">Home address, phone numbers, and emergency contacts.</div>
          <div class="section-divider"></div>
        </div>
        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">📍</span><span class="card-header-title">Home Address</span></div>
          <div class="card-body">
            <div class="form-grid g2">
              <div class="field"><label>Address Line 1 <span class="req">*</span></label><input type="text" name="street" required placeholder="Street address"/></div>
              <div class="field"><label>Address Line 2</label><input type="text" name="street_line_2" placeholder="Apt, Suite, Unit"/></div>
              <div class="field"><label>City <span class="req">*</span></label><input type="text" name="city" required placeholder="City"/></div>
              <div class="field"><label>State <span class="req">*</span></label><input type="text" name="state" required placeholder="State"/></div>
              <div class="field"><label>Postal Code <span class="req">*</span></label><input type="text" name="postal_code" required placeholder="ZIP code" maxlength="10"/></div>
              <div class="field"><label>County</label><input type="text" name="county" placeholder="County"/></div>
              <div class="field"><label>Country</label><select name="country_code"><option value="USA">United States</option><option value="Mexico">Mexico</option><option value="Other">Other</option></select></div>
              <div class="field"><label>Mother's Name</label><input type="text" name="mothersname" placeholder="Mother's full name"/></div>
            </div>
          </div>
        </div>
        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">📞</span><span class="card-header-title">Phone & Email</span></div>
          <div class="card-body">
            <div class="form-grid g3">
              <div class="field"><label>Home Phone</label><input type="tel" name="phone_home" placeholder="(___) ___-____"/></div>
              <div class="field"><label>Mobile Phone <span class="req">*</span></label><input type="tel" name="phone_cell" required placeholder="(___) ___-____"/></div>
              <div class="field"><label>Work Phone</label><input type="tel" name="phone_biz" placeholder="(___) ___-____"/></div>
              <div class="field"><label>Trusted Email <span class="req">*</span></label><input type="email" name="email" required placeholder="your@email.com"/></div>
              <div class="field"><label>Contact Email <span class="hint">(if different)</span></label><input type="email" name="email_alternate" placeholder="alternate@email.com"/></div>
              <div class="field"><label>Preferred Contact Method</label><select name="phone_preferred_method"><option value="">-- Select --</option><option value="mobile_call">Mobile Phone (Call)</option><option value="mobile_text">Mobile Phone (Text)</option><option value="email">Email</option><option value="home_phone">Home Phone</option><option value="patient_portal">Patient Portal</option></select></div>
            </div>
          </div>
        </div>
        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">🚨</span><span class="card-header-title">Emergency Contact</span></div>
          <div class="card-body">
            <div class="form-grid g3">
              <div class="field"><label>Emergency Contact Name <span class="req">*</span></label><input type="text" name="emergency_contact_name" required placeholder="Full name"/></div>
              <div class="field"><label>Relationship</label><select name="emergency_contact_relationship"><option value="">-- Select --</option><option value="spouse">Spouse/Partner</option><option value="parent">Parent</option><option value="child">Child</option><option value="sibling">Sibling</option><option value="friend">Friend</option><option value="other">Other</option></select></div>
              <div class="field"><label>Emergency Phone <span class="req">*</span></label><input type="tel" name="emergency_contact_phone" required placeholder="(___) ___-____"/></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 2 — CHOICES -->
      <div class="section-page" id="section-2">
        <div class="section-header">
          <div class="section-tag">Section 3 of 5</div>
          <div class="section-title">Choices & Preferences</div>
          <div class="section-desc">Communication preferences and privacy choices.</div>
          <div class="section-divider"></div>
        </div>
        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">📞</span><span class="card-header-title">Communication Permissions</span></div>
          <div class="card-body">
            <div class="form-grid g3">
              <div class="field"><label>HIPAA Notice Received</label><select name="hipaa_notice"><option value="">-- Select --</option><option value="YES">Yes</option><option value="NO">No</option></select></div>
              <div class="field"><label>Allow Voice Message</label><select name="allow_voice_message"><option value="">-- Select --</option><option value="Y">Yes</option><option value="N">No</option></select></div>
              <div class="field"><label>Leave Message With</label><input type="text" name="voice_message_with" placeholder="Name of person"/></div>
              <div class="field"><label>Allow Mail Message</label><select name="allow_mail_message"><option value="">-- Select --</option><option value="Y">Yes</option><option value="N">No</option></select></div>
              <div class="field"><label>Allow SMS / Text</label><select name="allow_sms_text"><option value="">-- Select --</option><option value="Y">Yes</option><option value="N">No</option></select></div>
              <div class="field"><label>Allow Email</label><select name="allow_email_message"><option value="">-- Select --</option><option value="Y">Yes</option><option value="N">No</option></select></div>
              <div class="field"><label>Allow Patient Portal Notifications</label><select name="allow_patient_portal_notification"><option value="">-- Select --</option><option value="Y">Yes</option><option value="N">No</option></select></div>
              <div class="field"><label>HIPAA Notice Received Date</label><input type="date" name="hipaa_notice_received_date"/></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3 — EMPLOYER -->
      <div class="section-page" id="section-3">
        <div class="section-header">
          <div class="section-tag">Section 4 of 5</div>
          <div class="section-title">Employer Information</div>
          <div class="section-desc">Current employment details.</div>
          <div class="section-divider"></div>
        </div>
        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">💼</span><span class="card-header-title">Occupation & Employer</span></div>
          <div class="card-body">
            <div class="form-grid g2">
              <div class="field"><label>Occupation</label><input type="text" name="occupation" placeholder="Occupation"/></div>
              <div class="field"><label>Industry</label><input type="text" name="industry" placeholder="Industry"/></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 4 — STATS -->
      <div class="section-page" id="section-4">
        <div class="section-header">
          <div class="section-tag">Section 5 of 5</div>
          <div class="section-title">Demographics & Social</div>
          <div class="section-desc">Race, ethnicity, language, and social information.</div>
          <div class="section-divider"></div>
        </div>
        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">🌍</span><span class="card-header-title">Race, Ethnicity & Language</span></div>
          <div class="card-body">
            <div class="form-grid g3">
              <div class="field"><label>Primary Language</label><select name="language"><option value="">-- Select --</option><option>English</option><option>Spanish</option><option>Hmong</option><option>Other</option></select></div>
              <div class="field"><label>Ethnicity</label><select name="ethnicity"><option value="">-- Select --</option><option>Declined to Specify</option><option>Hispanic or Latino</option><option>Not Hispanic or Latino</option></select></div>
              <div class="field"><label>Race</label><select name="race"><option value="">-- Select --</option><option>Declined to Specify</option><option>American Indian or Alaska Native</option><option>Asian</option><option>Black or African American</option><option>Native Hawaiian or Pacific Islander</option><option>White</option><option>Other</option><option>Multi-racial</option></select></div>
              <div class="field"><label>Nationality</label><input type="text" name="nationality_country" placeholder="Nationality"/></div>
              <div class="field"><label>Interpreter Needed</label><select name="interpreter_needed"><option value="">-- Select --</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
            </div>
          </div>
        </div>
      </div>

    </form>
  </main>
</div>

<!-- FOOTER -->
<div class="form-footer">
  <div class="footer-progress">
    Section <strong id="currentSection">1</strong> of <strong>5</strong>
  </div>
  <div class="footer-btns">
    <button class="btn-back" onclick="previousSection()" id="backBtn" style="display:none;">← Back</button>
    <button class="btn-next" onclick="nextSection()" id="nextBtn">Next →</button>
  </div>
</div>

<script>
let currentSection = 0;
const totalSections = 5;

function goToSection(index) {
  if (index < 0 || index >= totalSections) return;

  // Hide all sections
  document.querySelectorAll('.section-page').forEach(s => s.classList.remove('active'));

  // Show selected section
  document.getElementById(`section-${index}`).classList.add('active');

  // Update nav items
  document.querySelectorAll('.nav-item').forEach((item, idx) => {
    if (idx === index) item.classList.add('active');
    else item.classList.remove('active');
  });

  // Update progress buttons
  document.querySelectorAll('.step-btn').forEach((btn, idx) => {
    if (idx === index) btn.classList.add('active');
    else btn.classList.remove('active');
  });

  currentSection = index;
  document.getElementById('currentSection').textContent = index + 1;

  // Update button visibility
  document.getElementById('backBtn').style.display = index > 0 ? 'block' : 'none';
  document.getElementById('nextBtn').style.display = index < totalSections - 1 ? 'block' : 'none';
}

function nextSection() {
  if (currentSection < totalSections - 1) {
    goToSection(currentSection + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function previousSection() {
  if (currentSection > 0) {
    goToSection(currentSection - 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <div class="toast-title">${type === 'success' ? '✓ Success' : '✗ Error'}</div>
    <div class="toast-message">${message}</div>
  `;
  container.appendChild(toast);

  setTimeout(() => toast.remove(), 4000);
}

function submitForm() {
  const form = document.getElementById('patientForm');

  // Validate required fields
  if (!form.checkValidity()) {
    showToast('Please fill in all required fields', 'error');
    return;
  }

  // Collect form data
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);

  // Disable submit button
  const submitBtn = document.getElementById('submitBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Submitting...';

  // Send to backend
  fetch('patient_save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(result => {
    if (result.success) {
      showToast(result.message, 'success');

      // Show modal
      document.getElementById('modalTitle').textContent = result.message;
      document.getElementById('modalPid').textContent = `Patient ID: ${result.pid}`;
      document.getElementById('successModal').classList.add('active');
    } else {
      showToast(result.message || 'An error occurred', 'error');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit';
    }
  })
  .catch(err => {
    showToast('Network error: ' + err.message, 'error');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit';
  });
}

function resetForm() {
  document.getElementById('patientForm').reset();
  document.getElementById('successModal').classList.remove('active');
  document.getElementById('submitBtn').disabled = false;
  document.getElementById('submitBtn').textContent = 'Submit';
  goToSection(0);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Initialize
goToSection(0);
</script>
</body>
</html>
