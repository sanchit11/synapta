<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Synapta — AI-Powered EHR for Modern Practice</title>
<link rel="icon" type="image/svg+xml" href="favicon.svg" />
<link  rel='stylesheet' href='Home.css'></link>
</head>
<body>

<?php 
    $site_id = '';

    if (!empty($_GET['site'])) {
        $site_id = $_GET['site'];
    } elseif (is_dir("sites/" . ($_SERVER['HTTP_HOST'] ?? 'default'))) {
        $site_id = ($_SERVER['HTTP_HOST'] ?? 'default');
    } else {
        $site_id = 'default';
    }

    if (empty($site_id) || preg_match('/[^A-Za-z0-9\\-.]/', $site_id)) {
        die("Site ID '" . htmlspecialchars($site_id, ENT_NOQUOTES) . "' contains invalid characters.");
    }

?>
<!-- ── NAV ── -->
<nav>
  <a class="nav-logo" href="#">
    <!-- Horizontal logo (light bg version) -->
    <svg width="200" height="38" viewBox="0 0 420 80" xmlns="http://www.w3.org/2000/svg" aria-label="Synapta">
      <line x1="40" y1="29" x2="40" y2="16"  stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="51" y1="35" x2="63" y2="28"  stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="51" y1="45" x2="63" y2="52"  stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="40" y1="51" x2="40" y2="64"  stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="29" y1="45" x2="17" y2="52"  stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round"/>
      <line x1="29" y1="35" x2="17" y2="28"  stroke="#0F6E56" stroke-width="1.8" stroke-linecap="round"/>
      <circle cx="40" cy="40" r="10" fill="#1D9E75"/>
      <circle cx="40" cy="40" r="6"  fill="#085041"/>
      <rect x="37.5" y="33.5" width="5"  height="13" rx="1" fill="white" opacity="0.6"/>
      <rect x="34"   y="37"   width="12" height="5"  rx="1" fill="white" opacity="0.6"/>
      <circle cx="40" cy="12"  r="5" fill="#1D9E75"/><circle cx="40" cy="12"  r="2.5" fill="#085041"/>
      <circle cx="67" cy="25"  r="5" fill="#7F77DD"/><circle cx="67" cy="25"  r="2.5" fill="#3C3489"/>
      <circle cx="67" cy="55"  r="5" fill="#1D9E75"/><circle cx="67" cy="55"  r="2.5" fill="#085041"/>
      <circle cx="40" cy="68"  r="5" fill="#7F77DD"/><circle cx="40" cy="68"  r="2.5" fill="#3C3489"/>
      <circle cx="13" cy="55"  r="5" fill="#1D9E75"/><circle cx="13" cy="55"  r="2.5" fill="#085041"/>
      <circle cx="13" cy="25"  r="5" fill="#7F77DD"/><circle cx="13" cy="25"  r="2.5" fill="#3C3489"/>
      <line x1="82" y1="16" x2="82" y2="64" stroke="#D3D1C7" stroke-width="0.5"/>
      <text x="96" y="47" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="500" font-size="30" letter-spacing="-0.3">
        <tspan fill="#0F6E56">Syn</tspan><tspan fill="#534AB7">apta</tspan>
      </text>
      <text x="96" y="63" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="400" font-size="8.5" letter-spacing="1.6" fill="#888780">AI · EHR · HEALTHCARE INTELLIGENCE</text>
    </svg>
  </a>
  <ul class="nav-links">
    <li><a href="#features">Features</a></li>
    <li><a href="#how-it-works">How it works</a></li>
    <li><a href="#testimonials">Testimonials</a></li>
    <li class="nav-actions">
        <a href="interface/synapta/EarlyAccess.php" class="btn-nav" style="color:#fff;">Request early access</a>
        <a target="_blank" href="interface/login/login.php" class="btn-login" style="color:#fff;">Login</a>
       
    </li>
  </ul>
</nav>

<!-- ── HERO ── -->
<section style="padding: 0; background: white;">
  <div class="hero">
    <div class="hero-content reveal">
      <div class="hero-badge">
        <div class="badge-pulse"></div>
        Now accepting early access
      </div>
      <h1>
        The EHR built for<br/>
        <span class="accent-teal">clinical intelligence</span>,<br/>
        not paperwork
      </h1>
      <p class="hero-desc">
        Synapta is an AI-forward Electronic Health Record platform that helps practitioners spend less time on documentation and more time with patients — without compromising on safety or precision.
      </p>
      <div class="hero-actions">
        <a href="interface/synapta/EarlyAccess.php" class="btn-primary" style="text-decoration:none">Request early access →</a>
        <button class="btn-outline" onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})">See how it works</button>
      </div>
      <div class="hero-trust">
        <div class="trust-item"><div class="trust-check">✓</div> HIPAA-compliant</div>
        <div class="trust-divider"></div>
        <div class="trust-item"><div class="trust-check">✓</div> No setup fees</div>
        <div class="trust-divider"></div>
        <div class="trust-item"><div class="trust-check">✓</div> Onboarding in days</div>
      </div>
    </div>

    <!-- Dark UI card preview -->
    <div class="hero-visual reveal">
      <div class="hero-card">
        <div class="card-header">
          <div class="card-title">Today's patient queue</div>
          <div class="card-status"><div class="status-dot"></div>3 active</div>
        </div>

        <div class="patient-row">
          <div class="patient-avatar" style="background:rgba(239,68,68,0.15);color:#fca5a5;">MR</div>
          <div class="patient-info">
            <div class="patient-name">Maria R., 67</div>
            <div class="patient-detail">Cardiology · 9:00 AM</div>
          </div>
          <div class="patient-tag tag-urgent">Urgent</div>
        </div>

        <div class="patient-row">
          <div class="patient-avatar" style="background:rgba(29,158,117,0.15);color:#5DCAA5;">JK</div>
          <div class="patient-info">
            <div class="patient-name">James K., 44</div>
            <div class="patient-detail">General · 9:30 AM</div>
          </div>
          <div class="patient-tag tag-stable">Stable</div>
        </div>

        <div class="patient-row">
          <div class="patient-avatar" style="background:rgba(83,74,183,0.15);color:#AFA9EC;">SP</div>
          <div class="patient-info">
            <div class="patient-name">Sara P., 29</div>
            <div class="patient-detail">Follow-up · 10:15 AM</div>
          </div>
          <div class="patient-tag tag-review">Review</div>
        </div>

        <div class="ai-insight">
          <div class="ai-icon">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="7" cy="7" r="3" fill="#AFA9EC"/>
              <circle cx="7" cy="2" r="1.5" fill="#534AB7"/>
              <circle cx="11.2" cy="4.5" r="1.5" fill="#534AB7"/>
              <circle cx="11.2" cy="9.5" r="1.5" fill="#AFA9EC"/>
              <circle cx="7" cy="12" r="1.5" fill="#534AB7"/>
              <circle cx="2.8" cy="9.5" r="1.5" fill="#AFA9EC"/>
              <circle cx="2.8" cy="4.5" r="1.5" fill="#534AB7"/>
            </svg>
          </div>
          <div>
            <div class="ai-label">AI insight</div>
            <div class="ai-text">Maria R.'s <strong>last BP reading was elevated</strong>. Suggest reviewing current Lisinopril dosage before consultation.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── STATS BAND ── -->
<div class="stats-band">
  <div class="stats-inner">
    <div class="reveal">
      <div class="stat-num">73%</div>
      <div class="stat-label">Less time on documentation</div>
    </div>
    <div class="reveal">
      <div class="stat-num">2 days</div>
      <div class="stat-label">Average onboarding time</div>
    </div>
    <div class="reveal">
      <div class="stat-num">99.9%</div>
      <div class="stat-label">Uptime guaranteed</div>
    </div>
    <div class="reveal">
      <div class="stat-num">HIPAA</div>
      <div class="stat-label">Fully compliant & audited</div>
    </div>
  </div>
</div>

<!-- ── PROBLEM / SOLUTION ── -->
<section>
  <div class="section-inner">
    <div class="reveal">
      <div class="section-label">The problem</div>
      <h2 class="section-title">Existing EHRs slow<br/>practitioners down</h2>
      <p class="section-sub">Legacy systems were built for billing — not for the people using them at the point of care.</p>
    </div>

    <div class="problem-grid">
      <ul class="problem-list">
        <li class="problem-item reveal">
          <div class="problem-icon pi-orange">⏱</div>
          <div>
            <h4>Hours lost to data entry</h4>
            <p>Physicians spend up to 2 hours per day on documentation for every 1 hour of patient care. That ratio is unsustainable.</p>
          </div>
        </li>
        <li class="problem-item reveal">
          <div class="problem-icon pi-red">⚠️</div>
          <div>
            <h4>Fragmented patient context</h4>
            <p>Critical history is buried across tabs, notes, and disconnected systems — increasing the risk of missed signals.</p>
          </div>
        </li>
        <li class="problem-item reveal">
          <div class="problem-icon pi-blue">🔌</div>
          <div>
            <h4>No AI where it matters</h4>
            <p>Most EHRs offer no intelligent assistance. Every insight still requires manual review of dozens of records.</p>
          </div>
        </li>
      </ul>

      <div class="solution-panel reveal">
        <div class="solution-title">Synapta changes the equation</div>
        <ul class="solution-features">
          <li class="sf-item">
            <div class="sf-dot sf-teal">✓</div>
            <div>
              <h4>AI-generated clinical summaries</h4>
              <p>Walk into every consultation with a concise, AI-prepared brief — surfacing what matters most.</p>
            </div>
          </li>
          <li class="sf-item">
            <div class="sf-dot sf-purple">✓</div>
            <div>
              <h4>Intelligent charting assistance</h4>
              <p>Dictate or type naturally. Synapta structures and codes notes automatically, in real time.</p>
            </div>
          </li>
          <li class="sf-item">
            <div class="sf-dot sf-teal">✓</div>
            <div>
              <h4>Unified patient timeline</h4>
              <p>Every visit, result, and medication in a single adaptive view — no more hunting through records.</p>
            </div>
          </li>
          <li class="sf-item">
            <div class="sf-dot sf-purple">✓</div>
            <div>
              <h4>Proactive alerts, not noise</h4>
              <p>Synapta surfaces clinically relevant signals at the right moment — without alert fatigue.</p>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ── FEATURES ── -->
<section class="features-bg" id="features">
  <div class="section-inner">
    <div class="features-header">
      <div class="reveal">
        <div class="section-label">Built for practitioners</div>
        <h2 class="section-title">Every tool you need.<br/>Nothing you don't.</h2>
      </div>
      <p class="section-sub reveal" style="text-align:right;max-width:320px;">Designed with input from physicians, nurses, and practice managers across specialties.</p>
    </div>

    <div class="features-grid">
      <div class="feature-card reveal">
        <div class="feat-icon fi-teal">🧠</div>
        <h3>AI Clinical Summaries</h3>
        <p>Before each appointment, Synapta prepares a concise brief: recent labs, active medications, flagged trends, and suggested discussion points.</p>
      </div>
      <div class="feature-card purple reveal">
        <div class="feat-icon fi-purple">✍️</div>
        <h3>Smart Charting</h3>
        <p>Speak or type naturally. Synapta auto-structures SOAP notes, applies ICD-10 coding, and suggests follow-up actions based on clinical context.</p>
      </div>
      <div class="feature-card reveal">
        <div class="feat-icon fi-teal">📋</div>
        <h3>Unified Patient Timeline</h3>
        <p>A clean, chronological view of every visit, result, referral, and prescription — across all providers and encounters, in one place.</p>
      </div>
      <div class="feature-card purple reveal">
        <div class="feat-icon fi-purple">🔔</div>
        <h3>Signal-Based Alerts</h3>
        <p>Context-aware alerts trained to reduce false positives. Only surfaces what's clinically meaningful, when it matters most.</p>
      </div>
      <div class="feature-card reveal">
        <div class="feat-icon fi-teal">📊</div>
        <h3>Practice Analytics</h3>
        <p>Understand your practice at a glance — appointment throughput, documentation load, billing efficiency, and population health trends.</p>
      </div>
      <div class="feature-card purple reveal">
        <div class="feat-icon fi-purple">🔒</div>
        <h3>Security & Compliance</h3>
        <p>HIPAA-compliant by design. End-to-end encryption, role-based access control, audit logs, and regular third-party security assessments.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section id="how-it-works">
  <div class="section-inner">
    <div style="text-align:center;" class="reveal">
      <div class="section-label" style="justify-content:center;">How it works</div>
      <h2 class="section-title">From sign-up to seeing patients<br/>in under 48 hours</h2>
      <p class="section-sub" style="margin:0 auto;text-align:center;">We handle migration, training, and configuration — so your practice never skips a beat.</p>
    </div>

    <div class="steps-grid">
      <div class="step reveal">
        <div class="step-num-wrap"><div class="step-num">01</div></div>
        <h3>Discovery call</h3>
        <p>We learn about your practice — specialty, team size, current workflow, and pain points.</p>
      </div>
      <div class="step reveal">
        <div class="step-num-wrap"><div class="step-num">02</div></div>
        <h3>Configuration</h3>
        <p>Synapta is configured to match your templates, codes, and workflows. We import existing records.</p>
      </div>
      <div class="step reveal">
        <div class="step-num-wrap"><div class="step-num">03</div></div>
        <h3>Staff onboarding</h3>
        <p>Live training session for your whole team. Most staff are productive within a single day.</p>
      </div>
      <div class="step reveal">
        <div class="step-num-wrap"><div class="step-num">04</div></div>
        <h3>Go live + support</h3>
        <p>Dedicated support during your first weeks. We're available whenever you need us.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── TESTIMONIALS ── -->
<section class="testimonials-bg" id="testimonials">
  <div class="section-inner">
    <div class="reveal">
      <div class="section-label">From early users</div>
      <h2 class="section-title">Practitioners who got their<br/>time back</h2>
      <p class="section-sub">Synapta is in early access with a select group of practices. Here's what they're experiencing.</p>
    </div>

    <div class="testimonials-grid">
      <div class="testi-card reveal">
        <div class="testi-stars">
          <span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span>
        </div>
        <p class="testi-quote">"The AI summaries alone have changed how I start every appointment. I walk in actually prepared instead of scanning through notes. It's the first EHR that feels like it's working with me."</p>
        <div class="testi-author">
          <div class="testi-avatar" style="background:rgba(29,158,117,0.2);color:#5DCAA5;">DM</div>
          <div>
            <div class="testi-name">Dr. Diana Morales</div>
            <div class="testi-role">Internal Medicine, San Jose</div>
          </div>
        </div>
      </div>

      <div class="testi-card reveal">
        <div class="testi-stars">
          <span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span>
        </div>
        <p class="testi-quote">"We were skeptical of another EHR migration. But onboarding was genuinely painless — our whole practice was up in 36 hours. The smart charting has cut our end-of-day documentation by more than half."</p>
        <div class="testi-author">
          <div class="testi-avatar" style="background:rgba(83,74,183,0.2);color:#AFA9EC;">TC</div>
          <div>
            <div class="testi-name">Dr. Thomas Chen</div>
            <div class="testi-role">Family Practice, Austin</div>
          </div>
        </div>
      </div>

      <div class="testi-card reveal">
        <div class="testi-stars">
          <span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span>
        </div>
        <p class="testi-quote">"As a practice manager, I care about efficiency and compliance. Synapta delivers both — the audit logs and role-based access give us peace of mind, and the analytics help us make smarter scheduling decisions."</p>
        <div class="testi-author">
          <div class="testi-avatar" style="background:rgba(29,158,117,0.2);color:#5DCAA5;">RL</div>
          <div>
            <div class="testi-name">Rachel Lim</div>
            <div class="testi-role">Practice Manager, Portland</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section" id="contact">
  <div class="reveal">
    <div class="section-label cta-label">Get started</div>
    <h2>Ready to transform<br/>your <span>practice</span>?</h2>
    <p>Join the early access program and be among the first practices to run on the EHR built for the AI era.</p>
    <div class="cta-actions">
       <a href="interface/synapta/EarlyAccess.php" class="btn-primary" style="font-size:1rem;padding:0.95rem 2rem; text-decoration:none;" >Request early access →</a>
      <button class="btn-outline" style="font-size:1rem;padding:0.95rem 2rem;" onclick="alert('Book a demo — calendar link coming soon!')">Book a demo</button>
    </div>
    <p class="cta-fine">No credit card required. Typical onboarding in 48 hours.</p>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <!-- Dark logo for dark footer -->
      <svg width="180" height="34" viewBox="0 0 420 80" xmlns="http://www.w3.org/2000/svg" aria-label="Synapta">
        <line x1="40" y1="29" x2="40" y2="16"  stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round"/>
        <line x1="51" y1="35" x2="63" y2="28"  stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round"/>
        <line x1="51" y1="45" x2="63" y2="52"  stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round"/>
        <line x1="40" y1="51" x2="40" y2="64"  stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round"/>
        <line x1="29" y1="45" x2="17" y2="52"  stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round"/>
        <line x1="29" y1="35" x2="17" y2="28"  stroke="#5DCAA5" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="40" cy="40" r="10" fill="#1D9E75"/>
        <circle cx="40" cy="40" r="6"  fill="#04342C"/>
        <rect x="37.5" y="33.5" width="5" height="13" rx="1" fill="#9FE1CB" opacity="0.8"/>
        <rect x="34" y="37" width="12" height="5" rx="1" fill="#9FE1CB" opacity="0.8"/>
        <circle cx="40" cy="12" r="5" fill="#5DCAA5"/><circle cx="40" cy="12" r="2.5" fill="#04342C"/>
        <circle cx="67" cy="25" r="5" fill="#AFA9EC"/><circle cx="67" cy="25" r="2.5" fill="#26215C"/>
        <circle cx="67" cy="55" r="5" fill="#5DCAA5"/><circle cx="67" cy="55" r="2.5" fill="#04342C"/>
        <circle cx="40" cy="68" r="5" fill="#AFA9EC"/><circle cx="40" cy="68" r="2.5" fill="#26215C"/>
        <circle cx="13" cy="55" r="5" fill="#5DCAA5"/><circle cx="13" cy="55" r="2.5" fill="#04342C"/>
        <circle cx="13" cy="25" r="5" fill="#AFA9EC"/><circle cx="13" cy="25" r="2.5" fill="#26215C"/>
        <line x1="82" y1="16" x2="82" y2="64" stroke="#444441" stroke-width="0.5"/>
        <text x="96" y="47" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="500" font-size="30" letter-spacing="-0.3">
          <tspan fill="#5DCAA5">Syn</tspan><tspan fill="#AFA9EC">apta</tspan>
        </text>
        <text x="96" y="63" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="400" font-size="8.5" letter-spacing="1.6" fill="#5F5E5A">AI · EHR · HEALTHCARE INTELLIGENCE</text>
      </svg>
      <p>An AI-forward EHR built for the realities of modern clinical practice.</p>
    </div>

    <div class="footer-col">
      <h5>Product</h5>
      <ul>
        <li><a href="#features">Features</a></li>
        <li><a href="#how-it-works">How it works</a></li>
        <li><a href="#testimonials">Testimonials</a></li>
        <li><a href="#contact">Early access</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h5>Company</h5>
      <ul>
        <li><a href="#">About</a></li>
        <li><a href="#">Careers</a></li>
        <li><a href="#">Blog</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h5>Compliance</h5>
      <ul>
        <li><a href="#">HIPAA</a></li>
        <li><a href="#">Security</a></li>
        <li><a href="#">Privacy policy</a></li>
        <li><a href="#">Terms of service</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="footer-copy">© 2026 Synapta Inc. All rights reserved.</div>
    <div class="footer-legal">
      <a href="#">Privacy</a>
      <a href="#">Terms</a>
      <a href="#">Security</a>
    </div>
  </div>
</footer>

<script src="Home.js"></script>
</body>
</html>
