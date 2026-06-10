<?php
/**
 * Comprehensive Patient Add/Update Form - Public Access
 * Similar to interface/new but with modern Synapta UI
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
<title>SyaptaEMR — Comprehensive Patient Intake</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
* {box-sizing:border-box;margin:0;padding:0;}

:root {
  --teal: #1D9E75;
  --teal-d: #0F6E56;
  --teal-deep: #085041;
  --teal-l: #E1F5EE;
  --slate: #1A2030;
  --navy: #0F1117;
  --red: #A32D2D;
  --muted: #888780;
  --border: #D3D1C7;
  --surface: #FAFAF7;
  --card: #FFFFFF;
  --sans: 'Outfit',system-ui,sans-serif;
  --serif: 'DM Serif Display',Georgia,serif;
  --success: #10B981;
}

body { font-family: var(--sans); background: var(--surface); color: #1a1a1a; min-height: 100vh; }
html { scroll-behavior: smooth; }

/* Topbar */
.topbar {
  background: var(--navy);
  border-bottom: 2px solid rgba(29,158,117,.3);
  position: sticky;
  top: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  padding: 0 28px;
  height: 64px;
  gap: 16px;
}

.logo-wrap { display: flex; align-items: center; gap: 10px; }
.logo-hex { width: 36px; height: 36px; }
.logo-text { font-family: var(--serif); font-size: 22px; }
.logo-syn { color: #5DCAA5; }
.logo-apta { color: #7F77DD; }
.topbar-title { font-size: 13px; color: rgba(255,255,255,.6); margin-left: 8px; padding-left: 16px; border-left: 1px solid rgba(255,255,255,.1); }
.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 12px; }
.patient-badge { background: rgba(29,158,117,.15); border: 1px solid rgba(29,158,117,.3); color: #5DCAA5; font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 20px; }
.submit-btn { background: var(--teal); color: #fff; border: none; padding: 8px 20px; border-radius: 8px; font-family: var(--sans); font-size: 13px; font-weight: 600; cursor: pointer; }
.submit-btn:hover { background: var(--teal-d); }
.submit-btn:disabled { background: var(--muted); cursor: not-allowed; }

/* Main Layout */
.layout { display: grid; grid-template-columns: 220px 1fr; gap: 0; min-height: calc(100vh - 64px); }
.sidebar { background: var(--card); border-right: 1px solid var(--border); padding: 20px 0; position: sticky; top: 64px; height: calc(100vh - 64px); overflow-y: auto; }
.sidebar-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; padding: 12px 16px 4px; }
.sidebar-item { display: flex; align-items: center; gap: 10px; padding: 9px 16px; cursor: pointer; border-left: 3px solid transparent; font-size: 13px; color: #444; transition: all .15s; }
.sidebar-item:hover { background: var(--teal-l); color: var(--teal); }
.sidebar-item.active { background: var(--teal-l); color: var(--teal); border-left-color: var(--teal); font-weight: 600; }

/* Main Content */
.main-content { padding: 28px 32px; overflow-y: auto; max-height: calc(100vh - 64px); }
.section-page { display: none; }
.section-page.active { display: block; }

.section-header { margin-bottom: 24px; }
.section-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--teal); margin-bottom: 6px; }
.section-title { font-family: var(--serif); font-size: 28px; color: #1a1a1a; }
.section-desc { font-size: 13px; color: var(--muted); margin-top: 6px; }

/* Form Card */
.form-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 18px; overflow: hidden; }
.card-header { background: linear-gradient(135deg,var(--slate) 0%, #2A3142 100%); padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
.card-header-icon { font-size: 16px; color: #5DCAA5; }
.card-header-title { font-size: 13px; font-weight: 700; color: #fff; }
.card-body { padding: 20px; }

/* Form Grid */
.form-grid { display: grid; gap: 16px; }
.g2 { grid-template-columns: 1fr 1fr; }
.g3 { grid-template-columns: 1fr 1fr 1fr; }
.g4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
.span2 { grid-column: span 2; }
.span3 { grid-column: span 3; }

/* Field */
.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 12px; font-weight: 600; color: #444; }
.field label .req { color: var(--red); margin-left: 2px; }
.field input, .field select, .field textarea {
  padding: 9px 12px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: var(--sans);
  font-size: 13px;
  color: #1a1a1a;
  background: var(--surface);
  width: 100%;
}
.field input:focus, .field select:focus, .field textarea:focus {
  outline: none;
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(29,158,117,.12);
  background: var(--card);
}
.field textarea { resize: vertical; min-height: 80px; }

/* Toast */
.toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
.toast { background: var(--card); border-left: 4px solid var(--success); border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s ease-out; }
.toast.error { border-left-color: var(--red); }
@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* Modal */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 5000; justify-content: center; align-items: center; }
.modal-overlay.active { display: flex; }
.modal { background: var(--card); border-radius: 12px; padding: 32px; text-align: center; min-width: 400px; }
.modal-icon { font-size: 48px; margin-bottom: 16px; }
.modal-title { font-size: 22px; font-weight: 700; margin-bottom: 12px; }
.modal-message { font-size: 14px; color: var(--muted); margin-bottom: 24px; }
.modal-pid { background: var(--teal-l); border: 1px solid var(--teal); color: var(--teal); padding: 12px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; }
.modal-button { background: var(--teal); color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-family: var(--sans); font-size: 14px; font-weight: 600; cursor: pointer; }

/* Responsive */
@media(max-width:900px) {
  .layout { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .g2, .g3, .g4 { grid-template-columns: 1fr; }
}

.required-legend { font-size: 11px; color: var(--muted); margin-bottom: 16px; }
.required-legend span { color: var(--red); }
</style>
</head>
<body>

<!-- Notifications -->
<div class="toast-container" id="toastContainer"></div>

<!-- Success Modal -->
<div class="modal-overlay" id="successModal">
  <div class="modal">
    <div class="modal-icon">✅</div>
    <div class="modal-title">Patient Registered Successfully</div>
    <div class="modal-message">Your information has been saved securely.</div>
    <div class="modal-pid" id="modalPid"></div>
    <button class="modal-button" onclick="location.reload();">Return to Home</button>
  </div>
</div>

<!-- Top Bar -->
<div class="topbar">
  <div class="logo-wrap">
    <svg class="logo-hex" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg">
      <polygon points="40,8 68,24 68,56 40,72 12,56 12,24" fill="none" stroke="#1D9E75" stroke-width="2.5"/>
      <circle cx="40" cy="40" r="8" fill="#1D9E75"/>
    </svg>
    <div class="logo-text"><span class="logo-syn">Synapta</span><span class="logo-apta">EMR</span></div>
    <div class="topbar-title">Patient Registration</div>
  </div>
  <div class="topbar-right">
    <span class="patient-badge">🔒 HIPAA Secure</span>
    <button class="submit-btn" id="submitBtn" onclick="submitForm()">Submit</button>
  </div>
</div>

<!-- Layout -->
<div class="layout">

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-label">Basic Information</div>
    <div class="sidebar-item active" onclick="goToSection(0)">👤 Identity & Demographics</div>
    <div class="sidebar-item" onclick="goToSection(1)">📍 Contact Information</div>
    <div class="sidebar-item" onclick="goToSection(2)">💼 Employment</div>
    <div class="sidebar-label">Health Information</div>
    <div class="sidebar-item" onclick="goToSection(3)">📋 Medical History</div>
    <div class="sidebar-item" onclick="goToSection(4)">💊 Medications & Allergies</div>
    <div class="sidebar-item" onclick="goToSection(5)">🏥 Insurance</div>
    <div class="sidebar-label">Clinical Info</div>
    <div class="sidebar-item" onclick="goToSection(6)">🤒 Chief Complaint</div>
    <div class="sidebar-item" onclick="goToSection(7)">✅ Consent</div>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <form id="patientForm">

      <!-- SECTION 0: Identity & Demographics -->
      <div class="section-page active" id="section-0">
        <div class="section-header">
          <div class="section-tag">Section 1 of 8</div>
          <div class="section-title">Identity & Demographics</div>
          <div class="section-desc">Legal identity and basic demographic information</div>
        </div>
        <div class="required-legend"><span>*</span> Required fields</div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">👤</span><span class="card-header-title">Legal Name</span></div>
          <div class="card-body">
            <div class="form-grid g4">
              <div class="field"><label>Title</label><select name="title"><option value="">Mr./Ms./Dr./Other</option><option>Mr.</option><option>Ms.</option><option>Mrs.</option><option>Dr.</option><option>Prof.</option></select></div>
              <div class="field"><label>First Name <span class="req">*</span></label><input type="text" name="fname" required placeholder="First name"/></div>
              <div class="field"><label>Middle Name</label><input type="text" name="mname" placeholder="Middle name"/></div>
              <div class="field"><label>Last Name <span class="req">*</span></label><input type="text" name="lname" required placeholder="Last name"/></div>
            </div>
          </div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">📅</span><span class="card-header-title">Demographics</span></div>
          <div class="card-body">
            <div class="form-grid g4">
              <div class="field"><label>Date of Birth <span class="req">*</span></label><input type="date" name="DOB" required/></div>
              <div class="field"><label>Sex <span class="req">*</span></label><select name="sex" required><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
              <div class="field"><label>Gender Identity</label><input type="text" name="gender_identity" placeholder="Optional"/></div>
              <div class="field"><label>Race/Ethnicity</label><select name="race"><option value="">Select</option><option>White</option><option>Black/African American</option><option>Hispanic/Latino</option><option>Asian</option><option>Native American</option><option>Pacific Islander</option><option>Multi-racial</option></select></div>
            </div>
            <div class="form-grid g3" style="margin-top: 16px;">
              <div class="field"><label>Primary Language</label><input type="text" name="language" placeholder="e.g. English, Spanish"/></div>
              <div class="field"><label>Social Security (last 4)</label><input type="text" name="ss" placeholder="____" maxlength="4"/></div>
              <div class="field"><label>Driver License #</label><input type="text" name="drivers_license" placeholder="Optional"/></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 1: Contact Information -->
      <div class="section-page" id="section-1">
        <div class="section-header">
          <div class="section-tag">Section 2 of 8</div>
          <div class="section-title">Contact Information</div>
          <div class="section-desc">Where we can reach you</div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">📍</span><span class="card-header-title">Address</span></div>
          <div class="card-body">
            <div class="form-grid g2">
              <div class="field"><label>Street Address <span class="req">*</span></label><input type="text" name="street" required placeholder="Street"/></div>
              <div class="field"><label>Apt/Suite</label><input type="text" name="street_line_2" placeholder="Optional"/></div>
              <div class="field"><label>City <span class="req">*</span></label><input type="text" name="city" required/></div>
              <div class="field"><label>State <span class="req">*</span></label><input type="text" name="state" required maxlength="2"/></div>
              <div class="field"><label>ZIP Code <span class="req">*</span></label><input type="text" name="postal_code" required maxlength="10"/></div>
              <div class="field"><label>Country</label><input type="text" name="country_code" value="USA"/></div>
            </div>
          </div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">📞</span><span class="card-header-title">Phone & Email</span></div>
          <div class="card-body">
            <div class="form-grid g3">
              <div class="field"><label>Home Phone</label><input type="tel" name="phone_home" placeholder="(555) 123-4567"/></div>
              <div class="field"><label>Mobile <span class="req">*</span></label><input type="tel" name="phone_cell" required placeholder="(555) 123-4567"/></div>
              <div class="field"><label>Work Phone</label><input type="tel" name="phone_biz" placeholder="(555) 123-4567"/></div>
              <div class="field"><label>Primary Email <span class="req">*</span></label><input type="email" name="email" required placeholder="your@email.com"/></div>
              <div class="field"><label>Secondary Email</label><input type="email" name="email_alternate" placeholder="alternate@email.com"/></div>
              <div class="field"><label>Preferred Contact</label><select name="phone_preferred_method"><option value="">Select</option><option value="mobile_call">Mobile Call</option><option value="mobile_text">Text Message</option><option value="email">Email</option><option value="home_phone">Home Phone</option></select></div>
            </div>
          </div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">🚨</span><span class="card-header-title">Emergency Contact</span></div>
          <div class="card-body">
            <div class="form-grid g3">
              <div class="field"><label>Contact Name <span class="req">*</span></label><input type="text" name="emergency_contact_name" required/></div>
              <div class="field"><label>Relationship</label><input type="text" name="emergency_contact_relationship" placeholder="e.g., Spouse, Parent"/></div>
              <div class="field"><label>Phone <span class="req">*</span></label><input type="tel" name="emergency_contact_phone" required/></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 2: Employment -->
      <div class="section-page" id="section-2">
        <div class="section-header">
          <div class="section-tag">Section 3 of 8</div>
          <div class="section-title">Employment Information</div>
          <div class="section-desc">Current job and employer details</div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">💼</span><span class="card-header-title">Job Details</span></div>
          <div class="card-body">
            <div class="form-grid g2">
              <div class="field"><label>Occupation</label><input type="text" name="occupation" placeholder="Job title"/></div>
              <div class="field"><label>Industry</label><input type="text" name="industry" placeholder="Industry type"/></div>
              <div class="field"><label>Employer Name</label><input type="text" name="em_name" placeholder="Company name"/></div>
              <div class="field"><label>Employer Phone</label><input type="tel" name="em_phone" placeholder="(555) 123-4567"/></div>
              <div class="field"><label>Employer Address</label><input type="text" name="em_street"/></div>
              <div class="field"><label>Employer City</label><input type="text" name="em_city"/></div>
              <div class="field"><label>Employer State</label><input type="text" name="em_state" maxlength="2"/></div>
              <div class="field"><label>Employer ZIP</label><input type="text" name="em_postal_code"/></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3: Medical History -->
      <div class="section-page" id="section-3">
        <div class="section-header">
          <div class="section-tag">Section 4 of 8</div>
          <div class="section-title">Medical History</div>
          <div class="section-desc">Past medical conditions and treatments</div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">🩺</span><span class="card-header-title">Medical Conditions</span></div>
          <div class="card-body">
            <div class="field"><label>Current Medical Conditions</label><textarea name="medical_history" placeholder="List any current or past medical conditions..."></textarea></div>
            <div class="field" style="margin-top: 16px;"><label>Surgeries</label><textarea name="surgical_history" placeholder="List any surgeries..."></textarea></div>
          </div>
        </div>
      </div>

      <!-- SECTION 4: Medications & Allergies -->
      <div class="section-page" id="section-4">
        <div class="section-header">
          <div class="section-tag">Section 5 of 8</div>
          <div class="section-title">Medications & Allergies</div>
          <div class="section-desc">Current medications and known allergies</div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">💊</span><span class="card-header-title">Current Medications</span></div>
          <div class="card-body">
            <div class="field"><label>List all medications you are currently taking</label><textarea name="current_medications" placeholder="Medication name - Dosage (e.g., Aspirin 325mg daily)..."></textarea></div>
          </div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">⚠️</span><span class="card-header-title">Allergies</span></div>
          <div class="card-body">
            <div class="field"><label>Drug Allergies</label><textarea name="nkda" placeholder="List any medication allergies..."></textarea></div>
            <div class="field" style="margin-top: 16px;"><label>Other Allergies</label><textarea name="allergies" placeholder="Food, environmental, etc..."></textarea></div>
          </div>
        </div>
      </div>

      <!-- SECTION 5: Insurance -->
      <div class="section-page" id="section-5">
        <div class="section-header">
          <div class="section-tag">Section 6 of 8</div>
          <div class="section-title">Insurance Information</div>
          <div class="section-desc">Primary insurance details</div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">🏥</span><span class="card-header-title">Primary Insurance</span></div>
          <div class="card-body">
            <div class="form-grid g3">
              <div class="field"><label>Insurance Company</label><input type="text" name="insurance_provider" placeholder="Company name"/></div>
              <div class="field"><label>Policy Number</label><input type="text" name="insurance_policy" placeholder="Policy #"/></div>
              <div class="field"><label>Group Number</label><input type="text" name="insurance_group" placeholder="Group #"/></div>
              <div class="field"><label>Subscriber Name</label><input type="text" name="insurance_subscriber" placeholder="If different"/></div>
              <div class="field"><label>Relationship</label><select name="insurance_relationship"><option value="">Self</option><option>Spouse</option><option>Child</option><option>Parent</option><option>Other</option></select></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 6: Chief Complaint -->
      <div class="section-page" id="section-6">
        <div class="section-header">
          <div class="section-tag">Section 7 of 8</div>
          <div class="section-title">Reason for Visit</div>
          <div class="section-desc">What brings you in today?</div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">🤒</span><span class="card-header-title">Chief Complaint</span></div>
          <div class="card-body">
            <div class="field"><label>Main Reason for Visit</label><textarea name="chief_complaint" placeholder="Describe what brings you in today..."></textarea></div>
            <div class="field" style="margin-top: 16px;"><label>Current Symptoms</label><textarea name="symptoms" placeholder="List current symptoms..."></textarea></div>
          </div>
        </div>
      </div>

      <!-- SECTION 7: Consent -->
      <div class="section-page" id="section-7">
        <div class="section-header">
          <div class="section-tag">Section 8 of 8</div>
          <div class="section-title">Consent & Authorization</div>
          <div class="section-desc">Permissions and acknowledgments</div>
        </div>

        <div class="form-card">
          <div class="card-header"><span class="card-header-icon">✍️</span><span class="card-header-title">Patient Consent</span></div>
          <div class="card-body">
            <div class="field">
              <label><input type="checkbox" name="consent_treatment" required/> I consent to treatment and medical services</label>
            </div>
            <div class="field">
              <label><input type="checkbox" name="consent_hipaa" required/> I have received and reviewed the HIPAA Privacy Notice</label>
            </div>
            <div class="field">
              <label><input type="checkbox" name="consent_records"/> I authorize release of medical records</label>
            </div>
            <div class="field" style="margin-top: 20px;"><label>Additional Notes</label><textarea name="notes" placeholder="Any other information we should know..."></textarea></div>
          </div>
        </div>
      </div>

    </form>
  </main>

</div>

<script>
let currentSection = 0;
const totalSections = 8;

function goToSection(idx) {
  document.querySelectorAll('.section-page').forEach(s => s.classList.remove('active'));
  document.getElementById(`section-${idx}`).classList.add('active');
  document.querySelectorAll('.sidebar-item').forEach((item, i) => {
    if(i === idx) item.classList.add('active');
    else item.classList.remove('active');
  });
  currentSection = idx;
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function showToast(msg, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<strong>${type === 'success' ? '✓' : '✕'}</strong> ${msg}`;
  document.getElementById('toastContainer').appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

function submitForm() {
  const form = document.getElementById('patientForm');

  // Validate required fields
  if(!form.checkValidity()) {
    showToast('Please fill all required fields', 'error');
    return;
  }

  const submitBtn = document.getElementById('submitBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Submitting...';

  const formData = new FormData(form);
  const data = Object.fromEntries(formData);

  fetch('patient_save.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  })
  .then(res => res.json())
  .then(result => {
    if(result.success) {
      showToast(result.message, 'success');
      document.getElementById('modalPid').textContent = `Patient ID: ${result.pid}`;
      document.getElementById('successModal').classList.add('active');
    } else {
      showToast(result.message || 'Error', 'error');
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

// Initialize
goToSection(0);
</script>

</body>
</html>
