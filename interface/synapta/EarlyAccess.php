<?php
$ignoreAuth = true;                          
require_once __DIR__ . '/../globals.php';


//require_once  '/CsrfUtils.php';

//use OpenEMR\Common\Csrf\CsrfUtils;

//$csrfToken  = CsrfUtils::collectCsrfToken();
$submitUrl  = $GLOBALS['webroot'] . '/interface/synapta/early_access_submit.php';
//$homeUrl    = '/interface/synapta/Home.php';    // back-link

//$homeUrl = $web_root; // back-link
$homeUrl = "https://synaptahealth.ai/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Synapta Health — Request Early Access</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<link  rel='stylesheet' href='<?= $GLOBALS['webroot']; ?>/interface/synapta/EarlyAccess.css'></link>

</head>
<body>

<div class="bg-layer"></div>
<div class="bg-grid"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<div class="page-wrap">

  <!-- ══════════════════════════════
       LEFT PANEL — Value prop
  ══════════════════════════════ -->
  <div class="left-panel">
    <div class="logo-wrap">
      <div class="logo-mark">S</div>
      <div>
        <div class="logo-name">Synapta Health</div>
        <div class="logo-tag">Clinical Intelligence</div>
      </div>
    </div>

    <div class="left-eyebrow">Founding Practice Program</div>

    <h1 class="left-headline">
      The EHR built<br/>for practices that<br/><em>chose independence.</em>
    </h1>

    <p class="left-body">
      Synapta adds <strong>clinical intelligence</strong> to Square Appointments —
      HIPAA-compliant records, ambient AI documentation, digital consent forms, and
      e-prescribing — without changing a single thing about how Square works for you today.
    </p>

    <div class="benefit-list">
      <div class="benefit-item">
        <div class="benefit-icon">🎤</div>
        <div class="benefit-text">
          <strong>Synapta Scribe — Ambient Documentation</strong>
          Listens to your patient consultations and drafts the clinical note in real time.
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">🔒</div>
        <div class="benefit-text">
          <strong>HIPAA-Compliant From Day One</strong>
          Every encounter, consent, and treatment record — legally defensible, audit-ready.
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">🤖</div>
        <div class="benefit-text">
          <strong>AI Billing Assist</strong>
          ICD-10 and CPT codes suggested from the clinical note — recovering lost revenue.
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">🌟</div>
        <div class="benefit-text">
          <strong>Free During MVP — Early Adopter Pricing Locked Forever</strong>
          Founding Practices lock in a permanent discounted rate before public launch.
        </div>
      </div>
    </div>

    <div class="spots-bar">
      <div class="spots-dot"></div>
      <div class="spots-text">
        <strong>Limited Founding Practice spots available.</strong>
        Once the cohort is full, the early adopter program closes.
      </div>
    </div>
  </div><!-- /left-panel -->


  <!-- ══════════════════════════════
       RIGHT PANEL — Form
  ══════════════════════════════ -->
  <div class="right-panel" id="right-panel">

    <!-- Progress bar + step dots -->
    <div class="form-topbar" id="form-topbar">
      <div class="progress-wrap">
        <div class="progress-label">
          <div class="progress-step-label" id="step-label">Practice Information</div>
          <div class="progress-count" id="step-count">Step 1 of 4</div>
        </div>
        <div class="progress-track">
          <div class="progress-fill" id="progress-fill" style="width:25%"></div>
        </div>
      </div>
      <div class="step-dots">
        <button class="step-dot active" id="dot-1" onclick="jumpTo(1)">Practice</button>
        <button class="step-dot"        id="dot-2" onclick="jumpTo(2)">Contact</button>
        <button class="step-dot"        id="dot-3" onclick="jumpTo(3)">Clinical Setup</button>
        <button class="step-dot"        id="dot-4" onclick="jumpTo(4)">Goals &amp; Fit</button>
      </div>
    </div>

    <!-- Form body -->
    <div class="form-body" id="form-body">

      <!-- Server-side error banner (shown if AJAX fails) -->
      <div class="server-error" id="server-error"></div>

      <!-- ══ STEP 1: PRACTICE INFO ══ -->
      <div class="step-panel active" id="step-1">
        <div class="step-title">Tell us about your practice</div>
        <div class="step-subtitle">This helps us confirm you're a strong fit for the Founding Practice program before we reach out.</div>
        <div class="field-group">

          <div class="field" id="f-practice-name">
            <label class="field-label">Practice / Clinic Name <span class="req">*</span></label>
            <input type="text" id="practice-name" placeholder="e.g., Bloom Aesthetics &amp; Wellness" autocomplete="organization"/>
            <div class="field-error">Please enter your practice name.</div>
          </div>

          <div class="field" id="f-clinic-type">
            <label class="field-label">Type of Clinic / Specialty <span class="req">*</span></label>
            <input type="text" id="clinic-type" placeholder="e.g., Med Spa, Direct Primary Care, Hormone Clinic, PT Rehab…" autocomplete="off"/>
            <div class="field-hint">Enter your practice type or specialty — any clinical setting welcome.</div>
            <div class="field-error">Please describe your clinic type or specialty.</div>
          </div>

          <div class="field" id="f-state">
            <label class="field-label">State <span class="req">*</span></label>
            <select id="practice-state">
              <option value="">Select your state</option>
              <option>Alabama</option><option>Alaska</option><option>Arizona</option><option>Arkansas</option>
              <option>California</option><option>Colorado</option><option>Connecticut</option><option>Delaware</option>
              <option>Florida</option><option>Georgia</option><option>Hawaii</option><option>Idaho</option>
              <option>Illinois</option><option>Indiana</option><option>Iowa</option><option>Kansas</option>
              <option>Kentucky</option><option>Louisiana</option><option>Maine</option><option>Maryland</option>
              <option>Massachusetts</option><option>Michigan</option><option>Minnesota</option><option>Mississippi</option>
              <option>Missouri</option><option>Montana</option><option>Nebraska</option><option>Nevada</option>
              <option>New Hampshire</option><option>New Jersey</option><option>New Mexico</option><option>New York</option>
              <option>North Carolina</option><option>North Dakota</option><option>Ohio</option><option>Oklahoma</option>
              <option>Oregon</option><option>Pennsylvania</option><option>Rhode Island</option><option>South Carolina</option>
              <option>South Dakota</option><option>Tennessee</option><option>Texas</option><option>Utah</option>
              <option>Vermont</option><option>Virginia</option><option>Washington</option><option>West Virginia</option>
              <option>Wisconsin</option><option>Wyoming</option>
            </select>
            <div class="field-error">Please select your state.</div>
          </div>

          <div class="field" id="f-providers">
            <label class="field-label">Number of Providers <span class="req">*</span></label>
            <div class="provider-grid">
              <button type="button" class="prov-btn" data-val="1"        onclick="selectProviders(this)">1</button>
              <button type="button" class="prov-btn" data-val="2"        onclick="selectProviders(this)">2</button>
              <button type="button" class="prov-btn" data-val="3"        onclick="selectProviders(this)">3</button>
              <button type="button" class="prov-btn" data-val="4"        onclick="selectProviders(this)">4</button>
              <button type="button" class="prov-btn" data-val="5"        onclick="selectProviders(this)">5</button>
              <button type="button" class="prov-btn" data-val="6-10"     onclick="selectProviders(this)">6–10</button>
              <button type="button" class="prov-btn" data-val="11-20"    onclick="selectProviders(this)">11–20</button>
              <button type="button" class="prov-btn" data-val="21+"      onclick="selectProviders(this)">21+</button>
              <button type="button" class="prov-btn" data-val="Not sure" onclick="selectProviders(this)" style="grid-column:span 2;">Not sure yet</button>
            </div>
            <input type="hidden" id="provider-count"/>
            <div class="field-error" id="providers-error" style="display:none;">Please select your number of providers.</div>
          </div>

        </div>
      </div><!-- /step-1 -->

      <!-- ══ STEP 2: CONTACT INFO ══ -->
      <div class="step-panel" id="step-2">
        <div class="step-title">Your contact information</div>
        <div class="step-subtitle">Who should we reach out to about your Founding Practice invitation?</div>
        <div class="field-group">

          <div class="field-row">
            <div class="field" id="f-first-name">
              <label class="field-label">First Name <span class="req">*</span></label>
              <input type="text" id="first-name" placeholder="First" autocomplete="given-name"/>
              <div class="field-error">Required.</div>
            </div>
            <div class="field" id="f-last-name">
              <label class="field-label">Last Name <span class="req">*</span></label>
              <input type="text" id="last-name" placeholder="Last" autocomplete="family-name"/>
              <div class="field-error">Required.</div>
            </div>
          </div>

          <div class="field" id="f-role">
            <label class="field-label">Title / Role <span class="req">*</span></label>
            <select id="contact-role">
              <option value="">Select your role</option>
              <option>Practice Owner / Operator</option><option>Medical Director</option>
              <option>Physician (MD / DO)</option><option>Nurse Practitioner (NP)</option>
              <option>Physician Assistant (PA)</option><option>Practice Manager / Administrator</option>
              <option>Registered Nurse (RN)</option><option>Aesthetician / Injector</option>
              <option>Office Manager</option><option>Partner / Investor</option><option>Other</option>
            </select>
            <div class="field-error">Please select your role.</div>
          </div>

          <div class="field" id="f-email">
            <label class="field-label">Work Email <span class="req">*</span></label>
            <input type="email" id="contact-email" placeholder="you@yourpractice.com" autocomplete="email"/>
            <div class="field-error">Please enter a valid email address.</div>
          </div>

          <div class="field" id="f-phone">
            <label class="field-label">Phone Number <span class="req">*</span></label>
            <input type="tel" id="contact-phone" placeholder="(555) 000-0000" autocomplete="tel"
                   oninput="formatPhone(this)"/>
            <div class="field-error">Please enter a valid phone number.</div>
            <div class="field-hint">We'll use this to confirm your spot — we don't spam.</div>
          </div>

          <div class="field">
            <label class="field-label">Best Time to Reach You</label>
            <select id="best-time">
              <option value="">Select a preference</option>
              <option>Morning (8am – 12pm)</option><option>Afternoon (12pm – 4pm)</option>
              <option>Late Afternoon (4pm – 6pm)</option><option>Any time — just email first</option>
            </select>
          </div>

          <div class="field">
            <label class="field-label">How Did You Hear About Synapta?</label>
            <select id="referral-source">
              <option value="">Select one</option>
              <option>Phone call from Synapta team</option><option>Email from Synapta team</option>
              <option>Text message from Synapta team</option><option>Square App Marketplace</option>
              <option>Referred by another practice</option><option>LinkedIn</option>
              <option>Instagram / Social Media</option><option>Google Search</option>
              <option>Healthcare Conference or Event</option><option>Other</option>
            </select>
          </div>

        </div>
      </div><!-- /step-2 -->

      <!-- ══ STEP 3: CLINICAL SETUP ══ -->
      <div class="step-panel" id="step-3">
        <div class="step-title">Your current clinical setup</div>
        <div class="step-subtitle">Helps us understand where the gaps are so we can tailor onboarding to your practice.</div>
        <div class="field-group">

          <div class="field">
            <label class="field-label">Booking &amp; Scheduling Software <span class="req">*</span></label>
            <div class="ehr-grid" id="booking-grid">
              <div class="ehr-pill active" data-val="Square Appointments" onclick="toggleEHR(this,'booking')">Square Appointments</div>
              <div class="ehr-pill" data-val="Vagaro"            onclick="toggleEHR(this,'booking')">Vagaro</div>
              <div class="ehr-pill" data-val="Mindbody"          onclick="toggleEHR(this,'booking')">Mindbody</div>
              <div class="ehr-pill" data-val="Jane App"          onclick="toggleEHR(this,'booking')">Jane App</div>
              <div class="ehr-pill" data-val="Acuity / Calendly" onclick="toggleEHR(this,'booking')">Acuity / Calendly</div>
              <div class="ehr-pill" data-val="Phone only"        onclick="toggleEHR(this,'booking')">Phone only</div>
              <div class="ehr-pill" data-val="Other"             onclick="toggleEHR(this,'booking')">Other</div>
            </div>
            <input type="hidden" id="booking-software" value="Square Appointments"/>
          </div>

          <div class="field">
            <label class="field-label">Current Clinical Documentation System</label>
            <div class="ehr-grid" id="ehr-grid">
              <div class="ehr-pill" data-val="Paper charts"              onclick="toggleEHR(this,'ehr')">Paper Charts</div>
              <div class="ehr-pill" data-val="Google Forms / Docs"       onclick="toggleEHR(this,'ehr')">Google Forms/Docs</div>
              <div class="ehr-pill" data-val="Practice Fusion"           onclick="toggleEHR(this,'ehr')">Practice Fusion</div>
              <div class="ehr-pill" data-val="Jane App"                  onclick="toggleEHR(this,'ehr')">Jane App</div>
              <div class="ehr-pill" data-val="AestheticRecord"           onclick="toggleEHR(this,'ehr')">AestheticRecord</div>
              <div class="ehr-pill" data-val="Nextech"                   onclick="toggleEHR(this,'ehr')">Nextech</div>
              <div class="ehr-pill" data-val="Tebra / Kareo"             onclick="toggleEHR(this,'ehr')">Tebra / Kareo</div>
              <div class="ehr-pill" data-val="eClinicalWorks"            onclick="toggleEHR(this,'ehr')">eClinicalWorks</div>
              <div class="ehr-pill" data-val="No system — we manage manually" onclick="toggleEHR(this,'ehr')">No system</div>
              <div class="ehr-pill" data-val="Other"                     onclick="toggleEHR(this,'ehr')">Other</div>
            </div>
            <input type="hidden" id="ehr-system"/>
          </div>

          <div class="field" id="f-prescribe">
            <label class="field-label">Does your practice prescribe medications? <span class="req">*</span></label>
            <div class="radio-cards">
              <label class="radio-card" onclick="selectRadio(this,'prescribe','Yes — we prescribe regularly')">
                <input type="radio" name="prescribe"/><div class="radio-dot"></div>
                <div class="radio-card-body">
                  <div class="radio-card-title">Yes — we prescribe regularly</div>
                  <div class="radio-card-desc">GLP-1, hormones, neurotoxins (where applicable), antibiotics, or other Rx</div>
                </div>
              </label>
              <label class="radio-card" onclick="selectRadio(this,'prescribe','Occasionally, for certain services')">
                <input type="radio" name="prescribe"/><div class="radio-dot"></div>
                <div class="radio-card-body">
                  <div class="radio-card-title">Occasionally, for certain services</div>
                  <div class="radio-card-desc">Some services require Rx but it's not the core of what we do</div>
                </div>
              </label>
              <label class="radio-card" onclick="selectRadio(this,'prescribe','No — we do not prescribe')">
                <input type="radio" name="prescribe"/><div class="radio-dot"></div>
                <div class="radio-card-body">
                  <div class="radio-card-title">No — we do not prescribe</div>
                  <div class="radio-card-desc">All services are non-prescription aesthetic or wellness treatments</div>
                </div>
              </label>
            </div>
            <input type="hidden" id="prescribe-val"/>
            <div class="field-error" id="prescribe-error" style="display:none;">Please select an option.</div>
          </div>

          <div class="field">
            <label class="field-label">Consent Form Process</label>
            <select id="consent-process">
              <option value="">How do you handle consent forms today?</option>
              <option>Paper forms — signed at intake</option>
              <option>Digital forms via a separate tool (Jotform, DocuSign, etc.)</option>
              <option>Integrated into our EMR</option>
              <option>We don't have a formal consent process yet</option>
              <option>Other</option>
            </select>
          </div>

        </div>
      </div><!-- /step-3 -->

      <!-- ══ STEP 4: GOALS & FIT ══ -->
      <div class="step-panel" id="step-4">
        <div class="step-title">Your goals and program fit</div>
        <div class="step-subtitle">Last step. We want to understand what you're hoping to solve — so we can make sure Synapta is genuinely the right fit for you.</div>
        <div class="field-group">

          <div class="field">
            <label class="field-label">What's your biggest clinical documentation challenge? <span class="req">*</span></label>
            <div class="check-cards" id="challenges-grid">
              <label class="check-card" onclick="event.preventDefault(); toggleCheck(this);">
                <input type="checkbox" value="Too much time charting after hours"/>
                <div class="check-box">✓</div>
                <div class="check-card-text">Too much time charting — I'm documenting after hours</div>
              </label>
              <label class="check-card" onclick="event.preventDefault(); toggleCheck(this);">
                <input type="checkbox" value="HIPAA compliance concerns"/>
                <div class="check-box">✓</div>
                <div class="check-card-text">HIPAA compliance — I'm not confident our records are fully compliant</div>
              </label>
              <label class="check-card" onclick="event.preventDefault(); toggleCheck(this);">
                <input type="checkbox" value="Paper / manual consent forms are a burden"/>
                <div class="check-box">✓</div>
                <div class="check-card-text">Paper or manual consent forms — it's a burden before every procedure</div>
              </label>
              <label class="check-card" onclick="event.preventDefault(); toggleCheck(this);">
                <input type="checkbox" value="No structured patient history or treatment record"/>
                <div class="check-box">✓</div>
                <div class="check-card-text">No structured patient history — hard to find records when a patient calls</div>
              </label>
              <label class="check-card" onclick="event.preventDefault(); toggleCheck(this);">
                <input type="checkbox" value="Missing revenue from undocumented or underbilled services"/>
                <div class="check-box">✓</div>
                <div class="check-card-text">Missing revenue — services aren't being properly documented or billed</div>
              </label>
              <label class="check-card" onclick="event.preventDefault(); toggleCheck(this);">
                <input type="checkbox" value="Using multiple disconnected tools"/>
                <div class="check-box">✓</div>
                <div class="check-card-text">Too many systems — Square, a separate EMR, paper, and something else</div>
              </label>
              <label class="check-card" onclick="event.preventDefault(); toggleCheck(this);">
                <input type="checkbox" value="Staff spending too much time on admin"/>
                <div class="check-box">✓</div>
                <div class="check-card-text">Staff admin overload — clinical staff spending too much time on paperwork</div>
              </label>
            </div>
            <div class="field-error" id="challenges-error" style="display:none;">Please select at least one challenge.</div>
          </div>

          <div class="field" id="f-timeline">
            <label class="field-label">How soon are you looking to make a change? <span class="req">*</span></label>
            <div class="radio-cards">
              <label class="radio-card" onclick="selectRadio(this,'timeline','ASAP — we need this now')">
                <input type="radio" name="timeline"/><div class="radio-dot"></div>
                <div class="radio-card-body">
                  <div class="radio-card-title">ASAP — we need this now</div>
                  <div class="radio-card-desc">Our current setup is not working and we're ready to move</div>
                </div>
              </label>
              <label class="radio-card" onclick="selectRadio(this,'timeline','Within the next 1-3 months')">
                <input type="radio" name="timeline"/><div class="radio-dot"></div>
                <div class="radio-card-body">
                  <div class="radio-card-title">Within the next 1–3 months</div>
                  <div class="radio-card-desc">Evaluating options and planning to transition soon</div>
                </div>
              </label>
              <label class="radio-card" onclick="selectRadio(this,'timeline','Exploring — no immediate timeline')">
                <input type="radio" name="timeline"/><div class="radio-dot"></div>
                <div class="radio-card-body">
                  <div class="radio-card-title">Exploring — no immediate timeline</div>
                  <div class="radio-card-desc">Gathering information to plan ahead</div>
                </div>
              </label>
            </div>
            <input type="hidden" id="timeline-val"/>
            <div class="field-error" id="timeline-error" style="display:none;">Please select a timeline.</div>
          </div>

          <div class="field">
            <label class="field-label">Anything else you'd like us to know?</label>
            <textarea id="additional-notes" placeholder="Tell us about your practice, specific features you need, or any questions…"></textarea>
            <div class="field-hint">This goes directly to the Synapta founding team — not a support queue.</div>
          </div>

          <!-- LOI Agreement -->
          <!-- <div style="padding:16px 18px;background:var(--gold-l);border:1.5px solid rgba(184,120,42,.25);border-radius:10px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);margin-bottom:8px;">📄 Letter of Intent — Early Adopter Program</div>
            <div style="font-size:13px;color:#6B4A10;line-height:1.7;margin-bottom:12px;">
              By submitting this form, you are expressing interest in joining Synapta's <strong>Founding Practice program</strong>.
              This is not a binding contract. It serves as a <strong>Letter of Intent</strong> — at <strong>no cost during our MVP phase</strong>,
              with early adopter pricing locked permanently when paid plans launch.
            </div>
            <label class="check-card" onclick="toggleCheck(this)" id="loi-agree-card" style="background:white;border-color:rgba(184,120,42,.3);">
              <input type="checkbox" id="loi-agree" value="agreed"/>
              <div class="check-box" style="border-color:var(--gold);">✓</div>
              <div class="check-card-text" style="font-size:12.5px;">
                <strong>I understand this is a Letter of Intent</strong> — expressing my interest in the free Early Adopter program.
                No payment required and no binding commitment is made.
              </div>
            </label>
            <div class="field-error" id="loi-error" style="display:none;margin-top:8px;">Please acknowledge the Letter of Intent to continue.</div>
          </div> -->

        </div>
      </div><!-- /step-4 -->

    </div><!-- /form-body -->

    <!-- SUCCESS PANEL -->
    <div class="success-panel" id="success-panel">
      <div class="success-ring">🎉</div>
      <div class="success-headline">You're on the list,<br/><em style="font-style:italic;color:var(--teal);">officially.</em></div>
      <p class="success-body">Your early access request has been received. The Synapta founding team will review your submission and reach out personally within <strong>1 business day</strong>.</p>
      <div class="success-ref" id="success-ref">REF: SYN-EA-000000</div>
      <div class="success-next">
        <div class="success-next-item">
          <div class="success-next-icon">📧</div>
          <div class="success-next-text"><strong>Check your inbox</strong><span>A confirmation email is on its way.</span></div>
        </div>
        <div class="success-next-item">
          <div class="success-next-icon">📞</div>
          <div class="success-next-text"><strong>Expect a call within 1 business day</strong><span>The Synapta team will reach out to confirm your spot.</span></div>
        </div>
        <div class="success-next-item">
          <div class="success-next-icon">🔒</div>
          <div class="success-next-text"><strong>Your early adopter pricing is reserved</strong><span>Founding Practice pricing is locked from the moment we confirm your LOI.</span></div>
        </div>
      </div>
      <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="btn-home">← Back to Home</a>
    </div>

    <!-- FORM FOOTER -->
    <div class="form-footer" id="form-footer">
      <button class="btn-back hidden" id="btn-back" onclick="prevStep()">← Back</button>
      <div style="font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px;">
        <span>🔒</span> HIPAA-safe · No spam · No credit card
      </div>
      <button class="btn-next" id="btn-next" onclick="nextStep()">
        Continue <span style="font-size:16px;">→</span>
      </button><br>
       <div id="submit-error" style="
  display:none;
  margin-top:10px;
  font-size:13px;
  color:#C0392B;
  text-align:right;
"></div>
    </div>
   

  </div><!-- /right-panel -->
</div><!-- /page-wrap -->

<!-- ═══════════════════════════════════════════════════════
     PHP-injected config (CSRF token + submit URL)
     These are the only server-side values JS needs.
══════════════════════════════════════════════════════════ -->
 <script>
//var SYNAPTA_CSRF   = < ?php echo json_encode($csrfToken); ?>;
var SYNAPTA_URL    = <?php echo json_encode($submitUrl); ?>;
</script> 

<script>


</script>
<script src="<?= $GLOBALS['webroot']; ?>/interface/synapta/EarlyAccess.js"></script>
</body>
</html>
