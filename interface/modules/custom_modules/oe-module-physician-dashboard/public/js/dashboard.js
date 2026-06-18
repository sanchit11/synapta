// ═══════════════════════════════════════════════════════
// SYNAPTA PROVIDER DASHBOARD — COMPLETE JS REWRITE
// Matches original synapta_provider_dashboard_V7 behaviour
// ═══════════════════════════════════════════════════════

(function injectVitalsTrendStyles() {
  const style = document.createElement('style');
  style.textContent = `
    .syd-vtai-card {
      min-width: 0;
      overflow: hidden;
    }

    .syd-vtai-tbl-wrap {
      display: block;
      overflow-x: auto;
      min-width: 0;
      -webkit-overflow-scrolling: touch;
    }

    .syd-vtai-tbl {
      width: max-content;
      min-width: 100%;
      border-collapse: collapse;
    }

    #syd-ai-panel {
      min-width: 0;
      overflow-x: hidden;
    }
  `;
  document.head.appendChild(style);
})();

// ── Global state ──────────────────────────────────────
  // Inject Hourglass Styles dynamically into the document head
(function injectHourglassStyles() {
  const style = document.createElement('style');
  style.textContent = `
    .syd-loading-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 30px 10px;
      width: 100%;
    }
    .syd-loading-container.minor {
      flex-direction: row;
      justify-content: flex-start;
      padding: 8px 0;
      gap: 8px;
    }
    .syd-loading-text {
      font-size: 13px;
      color: #888780;
      font-weight: 500;
      margin-top: 12px;
    }
    .syd-loading-container.minor .syd-loading-text {
      margin-top: 0;
      font-size: 12px;
    }
    .syd-hourglass-spinner {
      width: 32px;
      height: 32px;
      fill: none;
      stroke: #0F6E56;
      stroke-width: 1.5;
      stroke-linecap: round;
      stroke-linejoin: round;
      animation: hourglassFlip 3s cubic-bezier(0.77, 0, 0.175, 1) infinite;
    }
    .syd-loading-container.minor .syd-hourglass-spinner {
      width: 16px;
      height: 16px;
      stroke-width: 2;
    }
    .hourglass-glass { fill: rgba(15, 110, 86, 0.05); }
    .hourglass-frame { fill: #0F6E56; }
    .hourglass-sand-top {
      fill: #C77A0A;
      stroke: none;
      transform-origin: 12px 12px;
      animation: sandDisappear 3s ease-in-out infinite;
    }
    .hourglass-sand-bottom {
      fill: #C77A0A;
      stroke: none;
      transform-origin: 12px 12px;
      animation: sandAccumulate 3s ease-in-out infinite;
    }
    .hourglass-stream {
      stroke: #C77A0A;
      stroke-width: 1;
      stroke-dasharray: 2 4;
      animation: sandStream 0.5s linear infinite;
    }
    @keyframes hourglassFlip {
      0%, 85% { transform: rotate(0deg); }
      95%, 100% { transform: rotate(180deg); }
    }
    @keyframes sandDisappear {
      0% { transform: scaleY(1); opacity: 1; }
      75%, 100% { transform: scaleY(0); opacity: 0; }
    }
    @keyframes sandAccumulate {
      0%, 15% { transform: scaleY(0.1); }
      80%, 100% { transform: scaleY(1); }
    }
    @keyframes sandStream {
      0% { stroke-dashoffset: 0; }
      100% { stroke-dashoffset: -6; }
    }
  `;
  document.head.appendChild(style);
})();
let sydScribeActive  = false;
let sydCurrentPanel  = null;
let sydAIPanelOpen   = true;

function sydRenderVitals(data) {

  if (!data || !data.current) {
    return `<div class="syd-empty">No vitals found</div>`;
  }

  const v = data.current;
  const history = data.history || [];

  // Previous BP
  let prevBP = '';
  if (history.length > 1) {
    prevBP = history[history.length - 2].bp;
  }

  // Build BP graph
  let points = '';
let circles = '';
let labels = '';
let dates = '';

const startX = 45;
const gap = 38;

// Get systolic values
const systolicValues = history.map(h => Number(h.bps) || 0);

// Dynamic min/max range
const minBP = Math.min(...systolicValues, 110);
const maxBP = Math.max(...systolicValues, 150);

// SVG chart area
const topY = 10;
const bottomY = 60;
const chartHeight = bottomY - topY;

// Convert BP value to SVG Y coordinate
function bpToY(bp) {

  return bottomY - (
    ((bp - minBP) / (maxBP - minBP || 1))
    * chartHeight
  );
}

// Build SVG points
history.forEach((h, i) => {

  const x = startX + (i * gap);

  const systolic = Number(h.bps) || 0;

  const y = bpToY(systolic);

  points += `${x},${y} `;

  // Color logic
  let color = '#0F6E56';

  if (systolic >= 140) {
    color = '#A32D2D';
  }
  else if (systolic >= 130) {
    color = '#C77A0A';
  }

  // Dots
  circles += `
    <circle
      cx="${x}"
      cy="${y}"
      r="4"
      fill="${color}" />
  `;

  // Value labels
  labels += `
    <text
      x="${x}"
      y="${y - 8}"
      text-anchor="middle"
      font-size="8"
      fill="${color}"
      font-weight="700">
      ${systolic}
    </text>
  `;

  // Dates
  dates += `
    <text
      x="${x}"
      y="80"
      text-anchor="middle"
      font-size="7.5"
      fill="#888780">
      ${h.short_date}
    </text>
  `;
});

  // History rows
  let rows = '';

  [...history].reverse().forEach(h => {

    let cls = 'lv-ok';

    if (h.bps >= 140) {
      cls = 'lv-hi';
    } else if (h.bps >= 130) {
      cls = 'lv-wa';
    }

    rows += `
      <div class="lrow">
        <div class="lname" style="font-size:10px;">${h.date}</div>
        <div class="lval ${cls}">
          ${h.bp}
        </div>
        <div class="lref">HR ${h.hr || '—'}</div>
        <div class="ldate">SpO₂ ${h.spo2 || '—'}%</div>
      </div>
    `;
  });

  return `

    <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
      <button onclick="sydOpenVitalsForm()" style="
        background:#0F6E56;color:#fff;border:none;border-radius:8px;
        padding:6px 14px;font-size:11px;font-weight:700;cursor:pointer;
        display:flex;align-items:center;gap:5px;letter-spacing:.3px;">
        ＋ Add Vitals
      </button>
    </div>

    <div class="syd-section-label">
      Current Encounter — ${v.date}
    </div>

    <div class="vgrid">

      <div class="vi">
        <div class="vi-lbl">BP</div>
        <div class="vi-val">
          ${v.bp} <small class="vi-unit">mmHg</small>
        </div>
       
        ${
          prevBP
            ? `<div class="vi-trend">↑ from ${prevBP}</div>`
            : ''
        }
      </div>

      <div class="vi">
        <div class="vi-lbl">HR</div>
        <div class="vi-val">${v.hr} <small class="vi-unit">bpm</small></div>
        
      </div>

      <div class="vi">
        <div class="vi-lbl">SpO₂</div>
        <div class="vi-val">${v.spo2}% <small class="vi-unit">Room air</small></div>
        
      </div>

      <div class="vi">
        <div class="vi-lbl">Temp</div>
        <div class="vi-val">${v.temp}°F  <small class="vi-unit">Oral</small></div>
       
      </div>

      <div class="vi">
        <div class="vi-lbl">RR</div>
        <div class="vi-val">${v.rr}  <small class="vi-unit">breaths/min</small></div>
       
      </div>

      <div class="vi">
        <div class="vi-lbl">BMI</div>
        <div class="vi-val">${v.bmi} <small class="vi-unit">kg/m²</small></div>
        
      </div>

    </div>

    <div class="syd-section-label">
      Blood Pressure Systolic — Last 6 Visits
    </div>

    <svg viewBox="0 0 280 90"
     xmlns="http://www.w3.org/2000/svg"
     style="width:100%;height:90px;overflow:visible;">

  <!-- Grid lines -->

  <line x1="30" y1="10" x2="270" y2="10"
        stroke="#D3D1C7"
        stroke-width="0.5"
        stroke-dasharray="3,3"/>

  <line x1="30" y1="35" x2="270" y2="35"
        stroke="#D3D1C7"
        stroke-width="0.5"
        stroke-dasharray="3,3"/>

  <line x1="30" y1="60" x2="270" y2="60"
        stroke="#D3D1C7"
        stroke-width="0.5"
        stroke-dasharray="3,3"/>

  <!-- 130 reference line -->

  <line x1="30"
        y1="${bpToY(130)}"
        x2="270"
        y2="${bpToY(130)}"
        stroke="#0F6E56"
        stroke-width="1"
        stroke-dasharray="4,3"
        opacity="0.5"/>

  <text x="272"
        y="${bpToY(130) + 3}"
        font-size="7"
        fill="#0F6E56">
    130
  </text>

  <!-- Trend line -->

  <polyline
    points="${points}"
    fill="none"
    stroke="#A32D2D"
    stroke-width="2.5"
    stroke-linejoin="round"
    stroke-linecap="round"/>

  <!-- Dots -->
  ${circles}

  <!-- Labels -->
  ${labels}

  <!-- Dates -->
  ${dates}

  <!-- Bottom axis -->

  <line x1="30"
        y1="68"
        x2="270"
        y2="68"
        stroke="#D3D1C7"
        stroke-width="1"/>

</svg>

    <div class="syd-section-label">
      Past Visit Readings
    </div>

    ${rows}
  `;
}

// ── Panel data config ─────────────────────────────────
// ── Panel data config ─────────────────────────────────
const SYD_PANELS = {

  vitals: {
  title: '📊 Vitals — History & Trends',

  render: () => `
    <div id="syd-vitals-panel">
      <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
        <button onclick="sydOpenVitalsForm()" style="
          background:#0F6E56;color:#fff;border:none;border-radius:8px;
          padding:6px 14px;font-size:11px;font-weight:700;cursor:pointer;
          display:flex;align-items:center;gap:5px;letter-spacing:.3px;">
          ＋ Add Vitals
        </button>
      </div>
      <div class="syd-loading-container">
        <svg class="syd-hourglass-spinner" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path class="hourglass-frame" d="M5 2h14v2H5V2zm0 18h14v2H5v-2z" />
          <path class="hourglass-glass" d="M6 4v1.5c0 3 2.5 5.5 5.5 6.5C8.5 13 6 15.5 6 18.5V20h12v-1.5c0-3-2.5-5.5-5.5-6.5 3-1 5.5-3.5 5.5-6.5V4H6zm10 2v.5c0 1.9-1.6 3.5-3.5 4.5V11h-1v-2c-1.9-1-3.5-2.6-3.5-4.5V6h8z" />
          <path class="hourglass-sand-top" d="M8 7h8c0 1.5-1.5 3-4 3S8 8.5 8 7z" />
          <path class="hourglass-sand-bottom" d="M12 14c2.5 0 4 1.5 4 3h-8c0-1.5 1.5-3 4-3z" />
          <line class="hourglass-stream" x1="12" y1="10" x2="12" y2="17" />
        </svg>
        <div class="syd-loading-text">Loading Vital Trends...</div>
      </div>
    </div>
  `
},

  vitals2: {
    title: '📊 Vitals — History & Trends',
    render: () => `
      <div style="font-size:10px;color:var(--syd-muted);margin-bottom:8px;
                  font-weight:700;text-transform:uppercase;letter-spacing:.7px;">
        Current Encounter
      </div>
      <div class="syd-dp-grid" id="syd-dp-vitals-grid">
        <div class="syd-dp-item">
          <div class="syd-dp-label">Blood Pressure</div>
          <div class="syd-dp-value" id="dp-bp">—</div>
          <div class="syd-dp-unit">mmHg</div>
        </div>
        <div class="syd-dp-item">
          <div class="syd-dp-label">Heart Rate</div>
          <div class="syd-dp-value" id="dp-hr">—</div>
          <div class="syd-dp-unit">bpm</div>
        </div>
        <div class="syd-dp-item">
          <div class="syd-dp-label">Temperature</div>
          <div class="syd-dp-value" id="dp-temp">—</div>
          <div class="syd-dp-unit">°F</div>
        </div>
        <div class="syd-dp-item">
          <div class="syd-dp-label">SpO₂</div>
          <div class="syd-dp-value" id="dp-spo2">—</div>
          <div class="syd-dp-unit">%</div>
        </div>
        <div class="syd-dp-item">
          <div class="syd-dp-label">Respirations</div>
          <div class="syd-dp-value" id="dp-rr">—</div>
          <div class="syd-dp-unit">breaths/min</div>
        </div>
        <div class="syd-dp-item">
          <div class="syd-dp-label">BMI</div>
          <div class="syd-dp-value" id="dp-bmi">—</div>
          <div class="syd-dp-unit">kg/m²</div>
        </div>
      </div>`
  },

  problems: {
    title: '📋 Problem List',
    render: () => `
      <div id="syd-dp-problems-body">
        <div class="syd-loading-container minor">
          <svg class="syd-hourglass-spinner" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path class="hourglass-glass" d="M6 4v1.5c0 3 2.5 5.5 5.5 6.5C8.5 13 6 15.5 6 18.5V20h12v-1.5c0-3-2.5-5.5-5.5-6.5 3-1 5.5-3.5 5.5-6.5V4H6z"/>
          </svg>
          <span class="syd-loading-text">Loading Problems...</span>
        </div>
      </div>`
  },

  medications: {
    title: '💊 Active Medications',
    render: () => `
      <div id="syd-dp-meds-body">
        <div class="syd-loading-container minor">
          <svg class="syd-hourglass-spinner" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path class="hourglass-glass" d="M6 4v1.5c0 3 2.5 5.5 5.5 6.5C8.5 13 6 15.5 6 18.5V20h12v-1.5c0-3-2.5-5.5-5.5-6.5 3-1 5.5-3.5 5.5-6.5V4H6z"/>
          </svg>
          <span class="syd-loading-text">Loading Medications...</span>
        </div>
      </div>`
  },

  labs: {
    title: '🧪 Labs & Studies',
    render: () => `
      <div id="syd-dp-labs-body">
        <div class="syd-loading-container minor">
          <svg class="syd-hourglass-spinner" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path class="hourglass-glass" d="M6 4v1.5c0 3 2.5 5.5 5.5 6.5C8.5 13 6 15.5 6 18.5V20h12v-1.5c0-3-2.5-5.5-5.5-6.5 3-1 5.5-3.5 5.5-6.5V4H6z"/>
          </svg>
          <span class="syd-loading-text">Loading Labs...</span>
        </div>
      </div>`
  },

  allergies: {
    title: '⚠️ Allergies & Adverse Reactions',
    render: () => `
      <div id="syd-dp-allergies-body">
        <div class="syd-loading-container minor">
          <svg class="syd-hourglass-spinner" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path class="hourglass-glass" d="M6 4v1.5c0 3 2.5 5.5 5.5 6.5C8.5 13 6 15.5 6 18.5V20h12v-1.5c0-3-2.5-5.5-5.5-6.5 3-1 5.5-3.5 5.5-6.5V4H6z"/>
          </svg>
          <span class="syd-loading-text">Loading Allergies...</span>
        </div>
      </div>`
  },
  surgical_history: {
    title: '🔪 Surgical History',
    render: () => `
      <div id="syd-dp-surgical-history-body">
        <div class="syd-loading-container minor">
          <svg class="syd-hourglass-spinner" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path class="hourglass-glass" d="M6 4v1.5c0 3 2.5 5.5 5.5 6.5C8.5 13 6 15.5 6 18.5V20h12v-1.5c0-3-2.5-5.5-5.5-6.5 3-1 5.5-3.5 5.5-6.5V4H6z"/>
          </svg>
          <span class="syd-loading-text">Loading History...</span>
        </div>
      </div>`
  }
};

// ── Open a detail panel into the right column ─────────
function sydOpenPanel(panelKey) {
  
  const config  = SYD_PANELS[panelKey];
  if (!config) return;

  // Toggle: click same card again to close
  if (sydCurrentPanel === panelKey) {
    sydClosePanel();
    return;
  }

  // Deactivate all cards
  document.querySelectorAll('.syd-card')
    .forEach(c => c.classList.remove('syd-card-active'));

  // Activate this card
  const card = document.getElementById('syd-card-' + panelKey);
  if (card) card.classList.add('syd-card-active');

  // Show detail header
  const hdr = document.getElementById('syd-detail-header');
  const ttl = document.getElementById('syd-detail-ttl');
  if (hdr) hdr.style.display = 'flex';
  if (ttl) ttl.textContent   = config.title;

  // Render into right column body
  const inner = document.getElementById('syd-detail-inner');
  if (inner) {
    inner.innerHTML = '<div class="syd-fade">' + config.render() + '</div>';
  }

  sydCurrentPanel = panelKey;

  // Load real data via AJAX
  sydLoadPanelData(panelKey);
}

// ── Close detail panel ────────────────────────────────
function sydClosePanel() {
  document.querySelectorAll('.syd-card')
    .forEach(c => c.classList.remove('syd-card-active'));

  const hdr   = document.getElementById('syd-detail-header');
  const inner = document.getElementById('syd-detail-inner');

  if (hdr) hdr.style.display = 'none';
  if (inner) {
    inner.innerHTML = `
      <div class="syd-right-placeholder">
        <div class="syd-rp-icon">☝️</div>
        <div class="syd-rp-text">
          Click any card above —<br>
          <strong>Vitals · Problem List · Medications<br>
          Labs & Studies · Allergies</strong><br><br>
          — to view full history here.
        </div>
      </div>`;
  }
  sydCurrentPanel = null;
}

// ── AJAX load panel data ──────────────────────────────
function sydLoadPanelData(panelKey) {
  fetch(SYD.webroot
    + '/interface/modules/custom_modules'
    + '/oe-module-physician-dashboard/public/ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action:    panelKey,
        pid:       SYD.pid,
        encounter: SYD.encounter,
        csrf:      SYD.csrf
      })
    })
    .then(r => r.json())
    .then(data => sydRenderPanelData(panelKey, data))
    .catch(err => console.error('Synapta panel error:', err));
}

// ── Render panel data into right column ───────────────
function sydRenderPanelData(panelKey, data) {

  // if (panelKey === 'vitals' && data.vitals) {
  //   const v = data.vitals;
  //   ['bp','hr','temp','spo2','rr','bmi'].forEach(k => {
  //     const el = document.getElementById('dp-' + k);
  //     if (el) el.textContent = v[k] || '—';
  //   });
  //   return;
  // }

   if (panelKey === 'vitals') {

    const el = document.getElementById('syd-vitals-panel');

    if (el) {
      el.innerHTML = sydRenderVitals(data);
    }

    return;
  }

  if (panelKey === 'problems' && data.rows !== undefined) {
    const el = document.getElementById('syd-dp-problems-body');
    if (!el) return;
    if (!data.rows.length) {
      el.innerHTML = '<p style="color:#888;font-size:12px;">No active problems.</p>';
      return;
    }
    el.innerHTML = `<table class="syd-dp-table">
      <thead><tr><th>Problem</th><th>ICD-10</th><th>Since</th><th>Status</th></tr></thead>
      <tbody>${data.rows.map(r => `<tr>
        <td><strong>${r.title}</strong></td>
        <td style="font-family:var(--syd-mono);font-size:10.5px;color:var(--syd-purple);">
          ${r.diagnosis || '—'}
        </td>
        <td style="color:var(--syd-muted);font-size:11px;">${r.begdate || '—'}</td>
        <td><span class="syd-status-badge syd-status-complete">Active</span></td>
      </tr>`).join('')}</tbody>
    </table>`;
    return;
  }

  if (panelKey === 'medications' && data.rows !== undefined) {
    const el = document.getElementById('syd-dp-meds-body');
    if (!el) return;
    if (!data.rows.length) {
      el.innerHTML = '<p style="color:#888;font-size:12px;">No active medications.</p>';
      return;
    }
    el.innerHTML = `<table class="syd-dp-table">
      <thead><tr><th>Medication</th><th>Dose</th><th>Route</th><th>Frequency</th><th>Refills</th></tr></thead>
      <tbody>${data.rows.map(r => `<tr>
        <td><strong>${r.drug}</strong></td>
        <td>${r.dosage}</td>
        
        <td>${r.route    || '—'}</td>
        <td>${r.interval || '—'}</td>
        <td>${r.refills  || '0'}</td>
      </tr>`).join('')}</tbody>
    </table>`;
    return;
  }

  if (panelKey === 'labs' && data.rows !== undefined) {
    const el = document.getElementById('syd-dp-labs-body');
    if (!el) return;
    if (!data.rows.length) {
      el.innerHTML = '<p style="color:#888;font-size:12px;">No recent lab results.</p>';
      return;
    }
    el.innerHTML = `<table class="syd-dp-table">
      <thead><tr><th>Test</th><th>Result</th><th>Reference</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>${data.rows.map(r => `<tr>
        <td>${r.test_name || 'Lab'}</td>
        <td style="${r.abnormal ? 'color:var(--syd-amber);font-weight:600;' : ''}">
          ${r.result_text || '—'} ${r.units || ''}
        </td>
        <td style="color:var(--syd-muted);font-size:11px;">${r.range || '—'}</td>
        <td>${r.abnormal
              ? '<span class="syd-status-badge syd-status-warn">Abnormal</span>'
              : '<span class="syd-status-badge syd-status-complete">Normal</span>'
            }</td>
        <td style="color:var(--syd-muted);font-size:11px;">${r.date_ordered || '—'}</td>
      </tr>`).join('')}</tbody>
    </table>`;
    return;
  }

  if (panelKey === 'allergies' && data.rows !== undefined) {
    const el = document.getElementById('syd-dp-allergies-body');
    if (!el) return;
    if (!data.rows.length) {
      el.innerHTML = `<div style="background:var(--syd-teal-l);border:1px solid rgba(29,158,117,.2);
        border-radius:8px;padding:12px 14px;font-size:13px;font-weight:600;color:var(--syd-teal-d);">
        ✅ No Known Drug Allergies (NKDA)</div>`;
      return;
    }
    el.innerHTML = `<table class="syd-dp-table">
      <thead><tr><th>Allergen</th><th>Reaction</th><th>Severity</th><th>Documented</th></tr></thead>
      <tbody>${data.rows.map(r => {
        const sev = (r.severity || '').toLowerCase();
        const col = sev.includes('sev') || sev.includes('high')
                    ? 'syd-status-warn'
                    : 'syd-status-pending';
        return `<tr>
          <td><strong>${r.title}</strong></td>
          <td>${r.reaction  || '—'}</td>
          <td><span class="syd-status-badge ${col}">${r.severity || 'Unknown'}</span></td>
          <td style="color:var(--syd-muted);font-size:11px;">${r.begdate || '—'}</td>
        </tr>`;
      }).join('')}</tbody>
    </table>`;
    return;
  }

  if (panelKey === 'surgical_history' && data.rows !== undefined) {
    const el = document.getElementById('syd-dp-surgical-history-body');
    if (!el) return;
    if (!data.rows.length) {
      el.innerHTML = `<div style="background:var(--syd-teal-l);border:1px solid rgba(29,158,117,.2);
        border-radius:8px;padding:12px 14px;font-size:13px;font-weight:600;color:var(--syd-teal-d);">
        ✅ No Known surgical history</div>`;
      return;
    }
    el.innerHTML = `<table class="syd-dp-table">
      <thead><tr><th>Procedure</th><th>Date</th><th>Discharge</th><th>Notes</th></tr></thead>
      <tbody>${data.rows.map(r => {
       
        return `<tr>
          <td><strong>${r.title}</strong></td>
          <td>${r.begdate || '—'}</td>
          <td>${r.enddate}</td>
          <td>${r.comments}</td>
        </tr>`;
      }).join('')}</tbody>
    </table>`;
    return;
  }
}

// ═══════════════════════════════════════════════════════
//  TAB SWITCHING
// ═══════════════════════════════════════════════════════
function sydSwitchTab(tabKey, el) {
  document.querySelectorAll('.syd-tab-panel')
    .forEach(p => { p.classList.remove('active'); p.style.display = 'none'; });
  document.querySelectorAll('.syd-tab')
    .forEach(t => t.classList.remove('active'));

  const panel = document.getElementById('syd-tab-' + tabKey);
  if (panel) {
    panel.style.display = 'flex';
    panel.classList.add('active');
  }
  if (el) el.classList.add('active');

  // Animate risk meter on summary tab
  if (tabKey === 'summary') {
    setTimeout(() => {
      const fill = document.querySelector('.syd-risk-meter-fill');
      if (!fill) return;
      const target = fill.style.width;
      fill.style.width    = '0%';
      fill.style.transition = 'none';
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          fill.style.transition = 'width .8s ease';
          fill.style.width = target;
        });
      });
    }, 100);
  }
}

// ═══════════════════════════════════════════════════════
//  SOAP NOTE EDITOR
// ═══════════════════════════════════════════════════════
const SYD_TEMPLATES = {
  s: `CC: \nHPI: Patient is a [age]-year-old [sex] presenting with [complaint] for [duration].\nOnset: [onset]\nCharacter: [character]\nAssociated symptoms: [symptoms]\nAlleviating: [factors]\nROS: [review of systems]`,
  o: `General: Alert, NAD, well-appearing.\nVS: See above\nHEENT: [findings]\nCardiovascular: RRR, no murmurs\nPulmonary: CTA bilaterally\nAbdomen: Soft, NT/ND\nExtremities: No edema\nNeuro: A&Ox3`,
  a: `1. [Primary Diagnosis] — [ICD-10]\n2. [Secondary Diagnosis] — [ICD-10]\nClinical reasoning: [reasoning]`,
  p: `1. [Medication/intervention]\n2. Labs ordered: [labs]\n3. Imaging: [imaging]\n4. Referrals: [referrals]\n5. Patient education: [education]\n6. Follow up: [timeframe]\n7. Return precautions discussed.`
};

function sydToggleSection(key) {
  const body    = document.getElementById('syd-body-' + key);
  const chevron = document.getElementById('syd-chevron-' + key);
  if (!body) return;
  const collapsed = body.classList.contains('collapsed');
  body.classList.toggle('collapsed', !collapsed);
  if (chevron) chevron.classList.toggle('collapsed', !collapsed);
}

function sydExpandAll() {
  ['s','o','a','p'].forEach(k => {
    const body    = document.getElementById('syd-body-' + k);
    const chevron = document.getElementById('syd-chevron-' + k);
    if (body)    body.classList.remove('collapsed');
    if (chevron) chevron.classList.remove('collapsed');
  });
}

function sydMarkUnsaved() {
  const badge = document.getElementById('syd-save-status');
  if (badge) { badge.textContent = 'Unsaved'; badge.className = 'syd-soap-badge'; }
  ['s','o','a','p'].forEach(k => {
    const ta = document.getElementById('syd-soap-' + k);
    const ct = document.getElementById('syd-count-' + k);
    if (ta && ct) ct.textContent = ta.value.length + ' chars';
  });
}

function sydInsertTemplate(key) {
  const ta = document.getElementById('syd-soap-' + key);
  if (!ta) return;
  if (ta.value.trim() && !confirm('Replace current content with template?')) return;
  ta.value = SYD_TEMPLATES[key] || '';
  sydMarkUnsaved();
  ta.focus();
}

function sydInsertVitals() {
  const ta    = document.getElementById('syd-soap-o');
  if (!ta) return;
  const items = document.querySelectorAll('.syd-vb-item');
  let str = 'VS: ';
  items.forEach(item => {
    const lbl = item.querySelector('.syd-vb-label');
    const val = item.querySelector('.syd-vb-val');
    if (lbl && val) str += lbl.textContent + ' ' + val.textContent + '  ';
  });
  ta.value = str.trim() + '\n\n' + ta.value;
  sydMarkUnsaved();
  sydShowToast('✅ Vitals inserted into Objective', '#1D9E75');
}

function sydInsertProblem(title, code) {
  const ta = document.getElementById('syd-soap-a');
  if (!ta) return;
  ta.value += (ta.value ? '\n' : '') + (code ? title + ' (' + code + ')' : title);
  sydMarkUnsaved();
  ta.focus();
}

function sydAcceptAI(key) {
  const txt   = document.getElementById('syd-ai-' + key + '-text');
  const ta    = document.getElementById('syd-soap-' + key);
  const box   = document.getElementById('syd-ai-' + key);
  if (!txt || !ta) return;
  ta.value = txt.textContent + (ta.value ? '\n\n' + ta.value : '');
  if (box) box.style.display = 'none';
  sydMarkUnsaved();
  sydShowToast('✅ AI draft accepted into ' + key.toUpperCase(), '#1D9E75');
}

function sydSaveNote() {
  const s = document.getElementById('syd-soap-s')?.value || '';
  const o = document.getElementById('syd-soap-o')?.value || '';
  const a = document.getElementById('syd-soap-a')?.value || '';
  const p = document.getElementById('syd-soap-p')?.value || '';
  const btn = document.getElementById('syd-save-btn');
  if (btn) { btn.textContent = '⏳ Saving…'; btn.disabled = true; }

  fetch(SYD.webroot
    + '/interface/modules/custom_modules'
    + '/oe-module-physician-dashboard/public/ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action:'save_soap', pid:SYD.pid,
        encounter:SYD.encounter, csrf:SYD.csrf,
        subjective:s, objective:o, assessment:a, plan:p
      })
    })
    .then(r => r.json())
    .then(data => {
      if (btn) { btn.textContent = '💾 Save Note'; btn.disabled = false; }
      if (data.success) {
        const badge = document.getElementById('syd-save-status');
        if (badge) { badge.textContent = '✓ Saved'; badge.className = 'syd-soap-badge saved'; }
        sydShowToast('✅ Note saved successfully', '#0F6E56');
      } else {
        sydShowToast('❌ Save failed: ' + (data.error || 'Unknown error'), '#A32D2D');
      }
    })
    .catch(() => {
      if (btn) { btn.textContent = '💾 Save Note'; btn.disabled = false; }
      sydShowToast('❌ Network error — note not saved', '#A32D2D');
    });
}

function sydSignNote() {
  if (!confirm('Sign and close this encounter note?')) return;
  sydSaveNote();
  const badge = document.getElementById('syd-save-status');
  if (badge) { badge.textContent = '✓ Signed'; badge.className = 'syd-soap-badge signed'; }
  const status = document.getElementById('syd-enc-status');
  if (status) {
    status.textContent = '✓ Signed';
    status.style.background  = 'rgba(15,110,86,.25)';
    status.style.color       = '#4ADE80';
    status.style.borderColor = 'rgba(15,110,86,.4)';
  }
  sydShowToast('✅ Note signed — encounter closed', '#0F6E56');
}

function sydPrintNote() {
  const s = document.getElementById('syd-soap-s')?.value || '';
  const o = document.getElementById('syd-soap-o')?.value || '';
  const a = document.getElementById('syd-soap-a')?.value || '';
  const p = document.getElementById('syd-soap-p')?.value || '';
  const w = window.open('', '_blank');
  w.document.write(`<html><head><title>SOAP Note</title>
    <style>body{font-family:sans-serif;padding:40px;font-size:14px;line-height:1.7;}
    h3{margin:18px 0 6px;color:#333;}pre{white-space:pre-wrap;background:#f5f5f5;
    padding:12px;border-radius:6px;}</style></head><body>
    <h2>SOAP Note — ${new Date().toLocaleDateString()}</h2>
    <h3>S — Subjective</h3><pre>${s||'(empty)'}</pre>
    <h3>O — Objective</h3><pre>${o||'(empty)'}</pre>
    <h3>A — Assessment</h3><pre>${a||'(empty)'}</pre>
    <h3>P — Plan</h3><pre>${p||'(empty)'}</pre>
    </body></html>`);
  w.document.close(); w.print();
}

function sydAddendum() {
  const ta = document.getElementById('syd-soap-p');
  if (!ta) return;
  ta.value += '\n\nADDENDUM [' + new Date().toLocaleString() + ']:\n';
  ta.focus();
  sydMarkUnsaved();
}

function sydClearNote() {
  if (!confirm('Clear all SOAP note fields?')) return;
  ['s','o','a','p'].forEach(k => {
    const ta = document.getElementById('syd-soap-' + k);
    if (ta) ta.value = '';
  });
  sydMarkUnsaved();
}

// ═══════════════════════════════════════════════════════
//  SCRIBE — WebRTC RECORDING
//  Connects to oe-module-ambient-docs api.php:
//    create_session → upload_chunk (every 30s) →
//    process_session → sydReceiveDraft()
// ═══════════════════════════════════════════════════════

// Recording state
let _scribeRecorder   = null;
let _scribeStream     = null;
let _scribeChunks     = [];
let _scribeSessionId  = null;
let _scribeChunkTimer = null;
let _scribeTimerInt   = null;
let _scribeElapsed    = 0;
const SCRIBE_CHUNK_MS = 30000; // upload every 30 seconds

function _scribeApiUrl() {
  return SYD.webroot
    + '/interface/modules/custom_modules'
    + '/oe-module-ambient-docs/public/api.php';
}

// Entry point — called by both the header Scribe button
// and the "Start Recording" button in the AI Draft panel
async function sydToggleScribe() {
  if (!sydScribeActive) {
    await _scribeStart();
  } else {
    await _scribeStop();
  }
}

// ── Start recording ───────────────────────────────────
async function _scribeStart() {
  // Guard: need both a patient and an encounter to record against
  if (!SYD.pid || !SYD.encounter) {
    sydShowToast(
      '❌ No active encounter — open or create an encounter first',
      '#A32D2D'
    );
    return;
  }

  // ── Pre-flight: check all backend services are up ─────
  // This gives a specific diagnostic error BEFORE recording starts
  // so the user knows exactly what to fix (e.g. start Whisper server)
  try {
    const badge = document.getElementById('syd-draft-badge');
    if (badge) badge.textContent = 'Checking services…';

    const setupRes  = await fetch(_scribeApiUrl() + '?action=check_setup', {
      method : 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const setupData = await setupRes.json();

    if (!setupData.ok) {
      // Find the first failing check and report it
      const failing = Object.entries(setupData.checks || {})
        .filter(([, c]) => !c.ok)
        .map(([name, c]) => `${name}: ${c.message}`)
        .join('\n');

      sydShowToast('❌ Not ready to record:\n' + failing, '#A32D2D', 8000);
      console.warn('Scribe setup check failed:', setupData.checks);
      if (badge) badge.textContent = 'Setup required — see alert';

      // Show detailed breakdown in console for easy debugging
      console.group('Scribe setup details');
      Object.entries(setupData.checks || {}).forEach(([name, c]) => {
        const icon = c.ok ? '✅' : '❌';
        console.log(icon, name + ':', c.message);
      });
      console.groupEnd();
      return;
    }

    if (badge) badge.textContent = 'Waiting for recording 🎙️';
  } catch (prefErr) {
    // If check_setup itself fails (network error etc.) just warn and continue
    console.warn('Pre-flight check failed (continuing anyway):', prefErr);
  }

  // Request microphone
  let stream;
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        channelCount    : 1,
        sampleRate      : 16000,
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl : true,
      }
    });
  } catch (err) {
    sydShowToast('❌ Microphone access denied — check browser settings', '#A32D2D');
    console.error('Scribe mic error:', err);
    return;
  }

  // Create backend session
  try {
    const res  = await fetch(_scribeApiUrl() + '?action=create_session', {
      method : 'POST',
      headers: {
        'Content-Type'    : 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        action      : 'create_session',
        encounter_id: SYD.encounter,
        patient_id  : SYD.pid,
      }),
    });
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    _scribeSessionId = data.session_id;
  } catch (err) {
    stream.getTracks().forEach(t => t.stop());
    sydShowToast('❌ Could not start session: ' + err.message, '#A32D2D');
    console.error('Scribe create_session error:', err);
    return;
  }

  // Set up MediaRecorder (webm/opus preferred — best for Whisper)
  _scribeStream = stream;
  _scribeChunks = [];
  const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
    ? 'audio/webm;codecs=opus'
    : 'audio/webm';

  _scribeRecorder = new MediaRecorder(stream, { mimeType });
  _scribeRecorder.ondataavailable = (e) => {
    if (e.data && e.data.size > 0) _scribeChunks.push(e.data);
  };
  _scribeRecorder.onerror = (e) => {
    console.error('MediaRecorder error:', e.error);
    sydShowToast('❌ Recording error — ' + e.error.message, '#A32D2D');
  };
  _scribeRecorder.start(1000); // collect a data event every 1s

  // Upload chunk every 30 seconds while recording
  _scribeChunkTimer = setInterval(() => _scribeUploadChunk(false), SCRIBE_CHUNK_MS);

  // Live elapsed-time counter — updates both the header button and the panel display
  _scribeElapsed = 0;
  _scribeTimerInt = setInterval(() => {
    _scribeElapsed++;
    const m   = String(Math.floor(_scribeElapsed / 60)).padStart(2, '0');
    const s   = String(_scribeElapsed % 60).padStart(2, '0');
    const timeStr = `${m}:${s}`;
    const headerBtn = document.getElementById('syd-scribe-btn');
    if (headerBtn) headerBtn.innerHTML = `<span class="syd-live-dot"></span> ${timeStr}`;
    const elapsed = document.getElementById('syd-elapsed-display');
    if (elapsed) elapsed.textContent = timeStr;
  }, 1000);

  // Update header button + status indicator
  sydScribeActive = true;
  const headerBtn = document.getElementById('syd-scribe-btn');
  const status    = document.getElementById('syd-aip-status');
  const badge     = document.getElementById('syd-draft-badge');
  if (headerBtn) headerBtn.classList.add('syd-recording');
  if (status) status.innerHTML =
    '<span class="syd-live-dot"></span>'
    + ' <span style="color:#F4B0B0;">Recording…</span>';
  if (badge) badge.textContent = 'Recording in progress…';

  // Swap placeholder → recording-active panel (shows Stop button + timer)
  const placeholder     = document.getElementById('syd-draft-placeholder');
  const recordingActive = document.getElementById('syd-recording-active');
  if (placeholder)     placeholder.style.display     = 'none';
  if (recordingActive) recordingActive.style.display  = 'block';

  sydShowToast('🎙️ Recording started', '#1D9E75');
}

// ── Stop recording and generate SOAP note ────────────
async function _scribeStop() {
  clearInterval(_scribeChunkTimer);
  clearInterval(_scribeTimerInt);

  if (_scribeRecorder && _scribeRecorder.state !== 'inactive') {
    _scribeRecorder.stop();
  }
  if (_scribeStream) {
    _scribeStream.getTracks().forEach(t => t.stop());
  }

  sydScribeActive = false;

  // Update header button
  const headerBtn = document.getElementById('syd-scribe-btn');
  const badge     = document.getElementById('syd-draft-badge');
  if (headerBtn) { headerBtn.classList.remove('syd-recording'); headerBtn.innerHTML = '<span class="syd-live-dot"></span> Processing…'; }
  if (badge) badge.textContent = 'Generating note…';

  // Switch recording panel to "processing" state — hide Stop button, show spinner text
  const recordingActive = document.getElementById('syd-recording-active');
  if (recordingActive) {
    recordingActive.innerHTML =
      '<div style="text-align:center; padding:16px 8px;">'
      + '<div style="font-size:1.6rem; margin-bottom:8px;">⏳</div>'
      + '<div style="font-size:0.85rem; color:#9CA3AF;">Transcribing &amp; generating note…</div>'
      + '</div>';
  }

  // Wait for final ondataavailable to fire
  await new Promise(r => setTimeout(r, 800));

  // Upload the last chunk (marked as final)
  await _scribeUploadChunk(true);

  sydShowToast('🤖 Generating AI SOAP note…', '#534AB7');

  // Generate SOAP note from full transcript
  await _scribeProcessSession();

  // Hide recording panel — sydReceiveDraft() will show draft sections
  if (recordingActive) recordingActive.style.display = 'none';

  // Reset header button
  if (headerBtn) headerBtn.innerHTML = '<span class="syd-live-dot"></span> Scribe — Ready';
}

// ── Upload current audio buffer to api.php ────────────
async function _scribeUploadChunk(isFinal) {
  if (!_scribeChunks.length || !_scribeSessionId) return;

  const blob    = new Blob(_scribeChunks, { type: 'audio/webm' });
  _scribeChunks = []; // reset buffer for next chunk

  // Skip near-silent chunks (< 1 KB)
  if (blob.size < 1024) return;

  const form = new FormData();
  form.append('audio',      blob, 'chunk.webm');
  form.append('session_id', _scribeSessionId);
  form.append('is_final',   isFinal ? '1' : '0');

  try {
    const res  = await fetch(_scribeApiUrl() + '?action=upload_chunk', {
      method : 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body   : form,
    });

    const data = await res.json();

    // ── Server returned an error ──────────────────────────
    if (!res.ok || data.error) {
      // Show the real error message from PHP (visible in APP_ENV=local)
      const detail = data.detail || data.error || `HTTP ${res.status}`;
      console.error('upload_chunk failed:', detail, data);
      sydShowToast('❌ Transcription failed: ' + detail, '#A32D2D');

      // Update badge so clinician knows something went wrong
      const badge = document.getElementById('syd-draft-badge');
      if (badge) badge.textContent = 'Transcription error';
      return;
    }

    // ── Success — update word count ───────────────────────
    if (data.total_words) {
      const badge = document.getElementById('syd-draft-badge');
      if (badge) badge.textContent = data.total_words + ' words transcribed…';
      const wordsDisplay = document.getElementById('syd-words-display');
      if (wordsDisplay) wordsDisplay.textContent = data.total_words + ' words transcribed';
    }

  } catch (err) {
    // Network-level failure (server unreachable, JSON parse error, etc.)
    console.error('upload_chunk network error:', err);
    sydShowToast('❌ Upload failed — check console for details', '#A32D2D');
  }
}

// ── Call process_session → receive SOAP note ─────────
async function _scribeProcessSession() {
  try {
    const res  = await fetch(_scribeApiUrl() + '?action=process_session', {
      method : 'POST',
      headers: {
        'Content-Type'    : 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        action    : 'process_session',
        session_id: _scribeSessionId,
      }),
    });
    const data = await res.json();

    if (data.error) {
      sydShowToast('❌ AI error: ' + data.error, '#A32D2D');
      const badge = document.getElementById('syd-draft-badge');
      if (badge) badge.textContent = 'Error — try again';
      console.error('process_session error:', data);
      return;
    }

    // Flatten SOAP sections from structured objects → plain text strings
    // so sydReceiveDraft() can put them straight into the display divs
    const soap = data.soap_note || {};
    const note = {};

    ['subjective', 'objective', 'assessment', 'plan'].forEach(key => {
      const val = soap[key];
      if (!val) return;
      if (typeof val === 'string') {
        note[key] = val;
      } else if (typeof val === 'object') {
        const lines = [];
        Object.entries(val).forEach(([k, v]) => {
          if (!v) return;
          if (Array.isArray(v)) {
            v.forEach(item => {
              if (!item) return;
              if (typeof item === 'object') {
                lines.push('• ' + Object.values(item).filter(Boolean).join(' '));
              } else {
                lines.push('• ' + item);
              }
            });
          } else if (typeof v === 'string' && v.trim()) {
            const label = k.replace(/_/g, ' ')
                           .replace(/\b\w/g, c => c.toUpperCase());
            lines.push(label + ': ' + v);
          }
        });
        note[key] = lines.join('\n');
      }
    });

    // Prepend chief complaint into subjective
    if (soap.chief_complaint) {
      note.subjective = 'Chief Complaint: ' + soap.chief_complaint
        + (note.subjective ? '\n\n' + note.subjective : '');
    }

    // Hand off to the AI Draft Note panel
    sydReceiveDraft(note);

    // Render drug_interaction from API response into the Drug Alerts card
    if (data.drug_interaction !== undefined && data.drug_interaction !== null) {
      const drugAlertsEl = document.getElementById('syd-drug-alerts');
      if (drugAlertsEl) {
        const html = sydRenderDrugInteraction(data.drug_interaction);
        drugAlertsEl.innerHTML = html || '<div class="syd-aip-ok"><span>✅</span> No interactions detected.</div>';
      }
    }

  } catch (err) {
    sydShowToast('❌ Processing failed: ' + err.message, '#A32D2D');
    const badge = document.getElementById('syd-draft-badge');
    if (badge) badge.textContent = 'Error — try again';
    console.error('Scribe process_session error:', err);
  }
}

// ── Render drug_interaction API response into #syd-drug-alerts ────────────────
function sydRenderDrugInteraction(di) {
  if (!di || !di.data) return '';
  const d = di.data;
  const parts = [];

  function sevClass(sev) {
    const s = (sev || '').toUpperCase();
    if (s === 'HIGH' || s === 'CONTRAINDICATED') return 'danger';
    if (s === 'MODERATE')                         return 'warn';
    return '';
  }
  function sevIcon(sev) {
    const s = (sev || '').toUpperCase();
    if (s === 'HIGH' || s === 'CONTRAINDICATED') return '🚨';
    if (s === 'MODERATE')                         return '⚠️';
    return 'ℹ️';
  }
  function sevColor(cls) {
    if (cls === 'danger') return 'var(--red)';
    if (cls === 'warn')   return 'var(--amber)';
    return 'var(--body)';
  }
  function label(text) {
    return `<div style="font-size:9px;font-weight:700;letter-spacing:1px;text-transform:uppercase;`
         + `color:var(--body);margin:8px 0 5px;">${text}</div>`;
  }

  // ── Summary bar ──────────────────────────────────────────
  const safe = d.overall_safety_level === 'SAFE';
  parts.push(
    `<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;font-size:11px;">` +
      `<span>${safe ? '🛡️' : '⚠️'}</span>` +
      `<strong>${d.overall_safety_level || ''}</strong>` +
      `<span style="color:var(--body);">· ${di.active_alerts_count ?? 0} active alert${di.active_alerts_count !== 1 ? 's' : ''}</span>` +
      (d.report_confidence ? `<span style="margin-left:auto;font-size:10px;color:var(--body);">Confidence: ${Math.round(d.report_confidence * 100)}%</span>` : '') +
    `</div>`
  );

  if (d.summary) {
    parts.push(`<div style="font-size:11px;color:var(--body);margin-bottom:10px;line-height:1.5;">${d.summary}</div>`);
  }

  // ── Drug-Drug Interactions (deduplicated) ────────────────
  if (d.drug_drug_interactions && d.drug_drug_interactions.length) {
    const seen = new Set();
    const unique = d.drug_drug_interactions.filter(ix => {
      const key = `${ix.drug_a}|${ix.drug_b}|${ix.mechanism}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
    parts.push(label('Drug-Drug Interactions'));
    unique.forEach(ix => {
      const cls = sevClass(ix.severity);
      parts.push(
        `<div class="syd-drug-alert ${cls}" style="margin-bottom:6px;border-radius:7px;">` +
          `<div class="syd-da-header" style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">` +
            `<span class="syd-da-icon">${sevIcon(ix.severity)}</span>` +
            `<span style="font-weight:700;font-size:12px;flex:1;">${ix.drug_a} + ${ix.drug_b}</span>` +
            `<span style="font-size:9px;font-weight:700;text-transform:uppercase;color:${sevColor(cls)};">${ix.severity}</span>` +
          `</div>` +
          `<div class="syd-da-text">` +
            (ix.clinical_effect  ? `<div><strong>Effect:</strong> ${ix.clinical_effect}</div>` : '') +
            (ix.mechanism        ? `<div><strong>Mechanism:</strong> ${ix.mechanism}</div>` : '') +
            (ix.patient_context_note ? `<div style="margin-top:2px;"><strong>Context:</strong> ${ix.patient_context_note}</div>` : '') +
            (ix.recommendation   ? `<div style="margin-top:3px;font-style:italic;">${ix.recommendation}</div>` : '') +
          `</div>` +
        `</div>`
      );
    });
  }

  // ── Allergy Alerts ───────────────────────────────────────
  if (d.allergy_alerts && d.allergy_alerts.length) {
    parts.push(label('Allergy Alerts'));
    d.allergy_alerts.forEach(al => {
      const cls = sevClass(al.severity);
      parts.push(
        `<div class="syd-drug-alert ${cls}" style="margin-bottom:6px;border-radius:7px;">` +
          `<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">` +
            `<span>${sevIcon(al.severity)}</span>` +
            `<span style="font-weight:700;font-size:12px;flex:1;">${al.drug} × ${al.allergen_matched}</span>` +
            `<span style="font-size:9px;font-weight:700;text-transform:uppercase;color:${sevColor(cls)};">${al.severity || ''}</span>` +
          `</div>` +
          `<div class="syd-da-text">` +
            (al.reaction_type  ? `<div><strong>Reaction:</strong> ${al.reaction_type}</div>` : '') +
            (al.mechanism      ? `<div><strong>Mechanism:</strong> ${al.mechanism}</div>` : '') +
            (al.recommendation ? `<div style="margin-top:3px;font-style:italic;">${al.recommendation}</div>` : '') +
          `</div>` +
        `</div>`
      );
    });
  }

  // ── Side Effects ─────────────────────────────────────────
  if (d.side_effects && d.side_effects.length) {
    parts.push(label('Side Effects'));
    d.side_effects.forEach(se => {
      parts.push(
        `<div class="syd-drug-alert" style="margin-bottom:6px;border-radius:7px;background:var(--purple-l);border:1px solid rgba(83,74,183,.2);">` +
          `<div style="font-weight:700;font-size:12px;margin-bottom:3px;">💊 ${se.drug}</div>` +
          `<div class="syd-da-text">` +
            (se.boxed_warning                 ? `<div style="color:var(--red);font-weight:700;margin-bottom:2px;">⬛ ${se.boxed_warning}</div>` : '') +
            (se.common  && se.common.length   ? `<div><strong>Common:</strong> ${se.common.join(', ')}</div>` : '') +
            (se.serious && se.serious.length  ? `<div><strong>Serious:</strong> ${se.serious.join(', ')}</div>` : '') +
            (se.recommendation                ? `<div style="margin-top:3px;font-style:italic;">${se.recommendation}</div>` : '') +
          `</div>` +
        `</div>`
      );
    });
  }

  // ── Dose Warnings ────────────────────────────────────────
  if (d.dose_warnings && d.dose_warnings.length) {
    parts.push(label('Dose Check'));
    d.dose_warnings.forEach(dw => {
      const ok  = dw.status === 'WITHIN_RANGE';
      const bg  = ok ? 'background:var(--green-l);border:1px solid rgba(15,110,86,.2);' : '';
      const col = ok ? 'var(--green)' : 'var(--amber)';
      parts.push(
        `<div class="syd-drug-alert ${ok ? '' : 'warn'}" style="margin-bottom:6px;border-radius:7px;${bg}">` +
          `<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">` +
            `<span>${ok ? '✅' : '⚠️'}</span>` +
            `<span style="font-weight:700;font-size:12px;flex:1;">${dw.drug}</span>` +
            `<span style="font-size:9px;font-weight:700;text-transform:uppercase;color:${col};">${(dw.status || '').replace(/_/g, ' ')}</span>` +
          `</div>` +
          `<div class="syd-da-text">` +
            `<div><strong>Prescribed:</strong> ${dw.prescribed_dose || '—'} <span style="color:var(--body);">(range: ${dw.standard_range || '—'})</span></div>` +
            (dw.toxicity_profile ? `<div style="margin-top:2px;font-style:italic;">${dw.toxicity_profile}</div>` : '') +
          `</div>` +
        `</div>`
      );
    });
  }

  // ── Special Population Flags ─────────────────────────────
  if (d.special_population_flags && d.special_population_flags.length) {
    parts.push(label('Special Populations'));
    d.special_population_flags.forEach(sp => {
      parts.push(
        `<div class="syd-drug-alert warn" style="margin-bottom:6px;border-radius:7px;">` +
          `<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">` +
            `<span>🏥</span>` +
            `<span style="font-weight:700;font-size:12px;">${sp.drug}</span>` +
          `</div>` +
          `<div class="syd-da-text">` +
            (sp.flag_type      ? `<div style="font-size:9px;font-weight:700;color:var(--amber);margin-bottom:2px;">${sp.flag_type}</div>` : '') +
            (sp.detail         ? `<div>${sp.detail}</div>` : '') +
            (sp.recommendation ? `<div style="margin-top:3px;font-style:italic;">${sp.recommendation}</div>` : '') +
          `</div>` +
        `</div>`
      );
    });
  }

  return parts.join('');
}

// ═══════════════════════════════════════════════════════
//  AI PANEL
// ═══════════════════════════════════════════════════════
function sydToggleAIPanel() {
  sydAIPanelOpen = !sydAIPanelOpen;
  const panel = document.getElementById('syd-ai-panel');
  const root  = document.getElementById('synapta-dashboard');
  if (!panel) return;
  if (sydAIPanelOpen) {
    panel.style.display = 'flex';
    if (root) root.style.gridTemplateColumns = '62px 1fr 300px';
  } else {
    panel.style.display = 'none';
    if (root) root.style.gridTemplateColumns = '62px 1fr 0';
  }
}

function sydDismissAlert(btn) {
  const el = btn.closest('.syd-drug-alert');
  if (el) {
    el.style.transition = 'opacity .3s, max-height .3s';
    el.style.opacity    = '0';
    el.style.maxHeight  = '0';
    setTimeout(() => el.remove(), 350);
  }
}

function sydRefreshIntelligence() {
  const btn = document.querySelector('.syd-aip-refresh');
  if (btn) {
    btn.style.transition  = 'transform .5s ease';
    btn.style.transform   = 'rotate(360deg)';
    setTimeout(() => { btn.style.transform = ''; }, 500);
  }
  // If clinical_summary is available from the last SOAP generation, re-render it
  if (window._sydLastClinicalSummary) {
    sydUpdatePVI(window._sydLastClinicalSummary);
    sydShowToast('🔄 Pre-Visit Intelligence updated from AI analysis', '#1D9E75');
  } else {
    sydShowToast('🔄 Intelligence refreshed', '#1D9E75');
  }
}

/**
 * Populate Pre-Visit Intelligence from clinical_summary returned by generate_soap.
 * Called from ambient-recorder.js after SOAP generation completes.
 *
 * @param {Object} cs  clinical_summary object with:
 *   current_vitals, past_problem_list, negative_lab_results, complaints
 */
function sydUpdatePVI(cs) {
  if (!cs) return;
  window._sydLastClinicalSummary = cs;  // cache for refresh button

  const container = document.getElementById('syd-pvi-content');
  if (!container) return;

  // Helper: sanitise strings for innerHTML
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

  // Flag → CSS class
  const flagCls = f => {
    if (!f) return '';
    const fl = f.toUpperCase();
    if (fl === 'NORMAL') return 'syd-pvi-flag-ok';
    if (fl === 'NOT RECORDED') return 'syd-pvi-flag-missing';
    return 'syd-pvi-flag-warn';
  };

  // ── Helpers ──────────────────────────────────────────────────────────────
  const numVal = v => {
    if (v === null || v === undefined || v === '' || v === 'None') return null;
    const n = parseFloat(v); return isNaN(n) ? null : n;
  };
  const cmpTr = (p, n) => {
    const pv = numVal(p), nv = numVal(n);
    if (pv === null || nv === null) return null;
    return nv > pv ? 'up' : nv < pv ? 'down' : 'same';
  };
  const delta = (p, n) => {
    const pv = numVal(p), nv = numVal(n);
    if (pv === null || nv === null) return null;
    const d = Math.round((nv - pv) * 10) / 10;
    return (d > 0 ? '+' : '') + d;
  };
  const trendArrow = t => t === 'up' ? '▲' : t === 'down' ? '▼' : t === 'same' ? '→' : '';
  const chipHtml = (d, t) => d !== null
    ? `<span class="syd-pvi-vt-chip syd-pvi-vt-chip-${t||''}">${esc(String(d))}</span>` : '';
  const arrowHtml = t => t ? `<span class="syd-pvi-vt-arrow syd-pvi-vt-${t}">${trendArrow(t)}</span>` : '';

  let html = '';

  // ── 0. Vitals Trend — AI card (preferred) or static DB fallback ──────────
  const vtAI = (typeof SYD !== 'undefined' && SYD.vitalTrendsAI) ? SYD.vitalTrendsAI : null;
  const dbV  = (typeof SYD !== 'undefined' && SYD.dbVitals)       ? SYD.dbVitals      : null;

  if (vtAI && vtAI.clinical_analysis) {
    // ── AI-powered card (delegate to shared builder) ───────────────────────
    html += sydBuildVtaiCardHtml(vtAI);

  } else if (dbV && dbV.latest) {
    // ── Static DB comparison fallback ─────────────────────────────────────
    const lat  = dbV.latest;
    const prev = dbV.previous;

    const fmtDate = d => {
      if (!d) return '';
      const dt = new Date(d.replace(' ', 'T'));
      return isNaN(dt) ? String(d).slice(0,10)
        : dt.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
    };

    const latDate  = fmtDate(lat.date);
    const prevDate = prev ? fmtDate(prev.date) : null;
    const subtitle = prevDate ? `${prevDate} → ${latDate}` : `Latest: ${latDate}`;

    const bpLat  = (lat.bps  && lat.bpd)  ? `${lat.bps}/${lat.bpd} mmHg` : '—';
    const bpPrev = prev && prev.bps && prev.bpd ? `${prev.bps}/${prev.bpd} mmHg` : (prev ? '—' : null);
    const bpTr   = prev ? cmpTr(prev.bps, lat.bps) : null;
    const bpDel  = prev ? delta(prev.bps, lat.bps) : null;
    const bpAlert  = (numVal(lat.bps) ?? 0) >= 130;
    const o2Alert  = (numVal(lat.oxygen_saturation) ?? 100) < 95;
    const bmiAlert = (numVal(lat.BMI) ?? 0) >= 30;
    const hasAlert = bpAlert || o2Alert || bmiAlert;

    const vitRows = [
      { label:'Blood Pressure', prevVal: bpPrev !== null ? bpPrev : '—', latVal: bpLat, tr: bpTr, del: bpDel, alert: bpAlert, showPrev: prev !== null },
      { label:'Pulse',          prevVal: prev ? `${prev.pulse ?? '—'} bpm` : null,  latVal: `${lat.pulse ?? '—'} bpm`,  tr: prev ? cmpTr(prev.pulse, lat.pulse) : null, del: prev ? delta(prev.pulse, lat.pulse) : null, alert: false, showPrev: !!prev },
      { label:'Respiration',    prevVal: prev ? `${prev.respiration ?? '—'} br/min` : null, latVal: `${lat.respiration ?? '—'} br/min`, tr: prev ? cmpTr(prev.respiration, lat.respiration) : null, del: prev ? delta(prev.respiration, lat.respiration) : null, alert: false, showPrev: !!prev },
      { label:'Temperature',    prevVal: prev ? `${prev.temperature ?? '—'} °F` : null, latVal: `${lat.temperature ?? '—'} °F`, tr: prev ? cmpTr(prev.temperature, lat.temperature) : null, del: prev ? delta(prev.temperature, lat.temperature) : null, alert: (numVal(lat.temperature) ?? 0) > 100.4, showPrev: !!prev },
      { label:'O₂ Saturation',  prevVal: prev ? `${prev.oxygen_saturation ?? '—'} %` : null, latVal: `${lat.oxygen_saturation ?? '—'} %`, tr: prev ? cmpTr(prev.oxygen_saturation, lat.oxygen_saturation) : null, del: prev ? delta(prev.oxygen_saturation, lat.oxygen_saturation) : null, alert: o2Alert, showPrev: !!prev },
      { label:'Weight',         prevVal: prev ? `${prev.weight ?? '—'} lbs` : null, latVal: `${lat.weight ?? '—'} lbs`, tr: prev ? cmpTr(prev.weight, lat.weight) : null, del: prev ? delta(prev.weight, lat.weight) : null, alert: false, showPrev: !!prev },
      { label:'BMI',            prevVal: prev ? esc(String(prev.BMI ?? '—')) : null, latVal: esc(String(lat.BMI ?? '—')), tr: prev ? cmpTr(prev.BMI, lat.BMI) : null, del: prev ? delta(prev.BMI, lat.BMI) : null, alert: bmiAlert, showPrev: !!prev },
    ];

    html += `<div class="syd-pvi-vt-card${hasAlert ? ' syd-pvi-vt-card-alert' : ''}">`;
    html += `<div class="syd-pvi-vt-title">📈 Vitals Trend — DB Comparison<span class="syd-pvi-vt-sub">${esc(subtitle)}</span></div>`;
    html += `<div class="syd-pvi-vt-table">`;
    html += `<div class="syd-pvi-vt-row syd-pvi-vt-hdr"><span>Vital</span><span>${prev ? 'Previous' : '—'}</span><span>Latest</span><span>Δ</span></div>`;
    vitRows.forEach(r => {
      html += `<div class="syd-pvi-vt-row${r.alert ? ' syd-pvi-vt-alert' : ''}">`;
      html += `<span>${esc(r.label)}</span>`;
      html += `<span class="syd-pvi-vt-prev">${r.showPrev ? esc(r.prevVal || '—') : '—'}</span>`;
      html += `<span class="syd-pvi-vt-lat${r.alert ? ' syd-pvi-vt-warn' : ''}">${esc(r.latVal)}</span>`;
      html += `<span class="syd-pvi-vt-delta">${chipHtml(r.del, r.tr)}${arrowHtml(r.tr)}</span>`;
      html += `</div>`;
    });
    html += `</div></div>`;
  }

  // ── 1. Current Vitals ────────────────────────────────────────────────────
  const v = cs.current_vitals;
  const nv = cs.new_vitals; // separate object with only new/changed vitals since last encounter (if supported by API)
  if (v) {
    const rows = [
      { label:'Blood Pressure', val: v.blood_pressure?.display,  unit: v.blood_pressure?.unit  || 'mmHg', flag: v.blood_pressure?.flag  },
      { label:'Pulse',          val: v.pulse?.value,             unit: v.pulse?.unit            || 'bpm',  flag: v.pulse?.flag           },
      { label:'Respiration',    val: v.respiration?.value,       unit: v.respiration?.unit      || 'breaths/min', flag: v.respiration?.flag },
      { label:'Temperature',    val: v.temperature?.value,       unit: v.temperature?.unit      || '°F',   flag: v.temperature?.flag     },
      { label:'O₂ Saturation',  val: v.oxygen_saturation?.value, unit: v.oxygen_saturation?.unit|| '%',    flag: v.oxygen_saturation?.flag},
      { label:'Height',         val: v.height?.value,            unit: v.height?.unit           || 'in' },
      { label:'Weight',         val: v.weight?.value,            unit: v.weight?.unit           || 'lbs' },
      { label:'BMI',
        val: v.bmi?.value != null ? esc(v.bmi.value) + (v.bmi.status ? ' (' + esc(v.bmi.status) + ')' : '') : null,
        unit: '', flag: v.bmi?.flag },
    ].filter(r => r.val !== null && r.val !== undefined && r.val !== '' && r.val !== 0);

    if (rows.length) {
      html += '<div class="syd-pvi-card">'
            + '<div class="syd-pvi-card-title">🩺 Current Vitals'
            + (v.captured_at ? '<span class="syd-pvi-sub"> · ' + esc(v.captured_at) + '</span>' : '')
            + '</div>';
      rows.forEach(r => {
        const flagHtml = r.flag
          ? '<span class="syd-pvi-flag ' + flagCls(r.flag) + '">' + esc(r.flag) + '</span>'
          : '';
        html += '<div class="syd-pvi-vital-row">'
              + '<span class="syd-pvi-vital-label">' + esc(r.label) + '</span>'
              + '<span class="syd-pvi-vital-val">' + esc(String(r.val)) + (r.unit ? ' <span class="syd-pvi-unit">' + esc(r.unit) + '</span>' : '') + '</span>'
              + flagHtml
              + '</div>';
      });
      html += '</div>';
    }
  }

  // ── 2. Complaints ────────────────────────────────────────────────────────
  const comp = cs.complaints;
  if (comp) {
    html += '<div class="syd-pvi-card">'
          + '<div class="syd-pvi-card-title">💬 Complaints</div>';
    if (comp.chief_complaint) {
      html += '<div class="syd-pvi-item syd-pvi-chief">'
            + '<span class="syd-pvi-item-label">Chief Complaint</span>'
            + '<span class="syd-pvi-item-val">' + esc(comp.chief_complaint) + '</span>'
            + '</div>';
    }
    if (comp.pain_scale !== null && comp.pain_scale !== undefined) {
      html += '<div class="syd-pvi-item">'
            + '<span class="syd-pvi-item-label">Pain Scale</span>'
            + '<span class="syd-pvi-item-val">' + esc(String(comp.pain_scale)) + '/10</span>'
            + '</div>';
    }
    if (comp.patient_symptoms && comp.patient_symptoms.length) {
      html += '<div class="syd-pvi-item">'
            + '<span class="syd-pvi-item-label">Symptoms</span>'
            + '<span class="syd-pvi-item-val">' + comp.patient_symptoms.map(esc).join(', ') + '</span>'
            + '</div>';
    }
    html += '</div>';
  }

  // ── 3. Past Problem List ─────────────────────────────────────────────────
  const ppl = cs.past_problem_list;
  if (ppl && ppl.total > 0) {
    html += '<div class="syd-pvi-card">'
          + '<div class="syd-pvi-card-title">📋 Past Problems <span class="syd-pvi-sub">(' + esc(String(ppl.total)) + ')</span></div>';
    (ppl.active || []).forEach(p => {
      const icd = p.icd10 ? ' <span class="syd-pvi-code">' + esc(p.icd10) + '</span>' : '';
      html += '<div class="syd-pvi-item">'
            + '<span class="syd-pvi-badge syd-pvi-active">active</span> '
            + '<span class="syd-pvi-item-val">' + esc(p.display) + '</span>' + icd
            + '</div>';
    });
    (ppl.resolved || []).forEach(p => {
      html += '<div class="syd-pvi-item">'
            + '<span class="syd-pvi-badge syd-pvi-resolved">resolved</span> '
            + '<span class="syd-pvi-item-val syd-pvi-dim">' + esc(p.display) + '</span>'
            + '</div>';
    });
    html += '</div>';
  }

  // ── 4. Negative Lab Results ──────────────────────────────────────────────
  const labs = cs.negative_lab_results;
  const labItems = [...(labs?.from_records || []), ...(labs?.from_soap_text || [])];
  html += '<div class="syd-pvi-card">'
        + '<div class="syd-pvi-card-title">🧪 Negative Lab Results'
        + (labs?.total ? ' <span class="syd-pvi-sub">(' + esc(String(labs.total)) + ')</span>' : '')
        + '</div>';
  if (labItems.length) {
    labItems.forEach(l => {
      const name = l.test || l.name || String(l);
      const val  = l.result !== undefined ? ' — ' + l.result : '';
      html += '<div class="syd-pvi-item">'
            + '<span class="syd-pvi-badge syd-pvi-neg">NEG</span> '
            + '<span class="syd-pvi-item-val">' + esc(name + val) + '</span>'
            + '</div>';
    });
  } else {
    html += '<div class="syd-pvi-item syd-pvi-dim">No negative lab results on record.</div>';
  }
  html += '</div>';

  container.innerHTML = html;

  // Inject styles once
  if (!document.getElementById('syd-pvi-styles')) {
    const st = document.createElement('style');
    st.id = 'syd-pvi-styles';
    st.textContent = `
      #syd-pvi-content { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; padding: 4px 0; }
      .syd-pvi-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; font-size: 12px; }
      .syd-pvi-card-title { background: #f1f5fa; padding: 7px 10px; font-weight: 700; color: #212529; border-bottom: 1px solid #dee2e6; }
      .syd-pvi-sub { font-weight: 400; color: #6c757d; font-size: 11px; }
      .syd-pvi-vital-row { display: flex; align-items: center; gap: 4px; padding: 4px 10px; border-bottom: 1px solid #f1f3f5; }
      .syd-pvi-vital-row:last-child { border-bottom: none; }
      .syd-pvi-vital-label { flex: 0 0 110px; color: #6c757d; }
      .syd-pvi-vital-val { flex: 1; font-weight: 500; color: #212529; }
      .syd-pvi-unit { font-size: 10px; color: #868e96; }
      .syd-pvi-flag { font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 8px; white-space: nowrap; }
      .syd-pvi-flag-ok      { background: #d4edda; color: #155724; }
      .syd-pvi-flag-warn    { background: #f8d7da; color: #721c24; }
      .syd-pvi-flag-missing { background: #e2e3e5; color: #383d41; }
      .syd-pvi-item { display: flex; align-items: center; gap: 6px; padding: 5px 10px; border-bottom: 1px solid #f1f3f5; flex-wrap: wrap; }
      .syd-pvi-item:last-child { border-bottom: none; }
      .syd-pvi-item.syd-pvi-chief { background: #fffbf0; }
      .syd-pvi-item-label { font-weight: 600; color: #495057; flex: 0 0 auto; min-width: 90px; }
      .syd-pvi-item-val { color: #212529; }
      .syd-pvi-dim { color: #adb5bd; font-style: italic; }
      .syd-pvi-code { font-size: 10px; background: #e9ecef; padding: 1px 4px; border-radius: 4px; font-family: monospace; }
      .syd-pvi-badge { font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 8px; white-space: nowrap; }
      .syd-pvi-active   { background: #cce5ff; color: #004085; }
      .syd-pvi-resolved { background: #e2e3e5; color: #383d41; }
      .syd-pvi-neg      { background: #d4edda; color: #155724; }
      .syd-pvi-vt-card { background:#fff; border:1px solid #b8d4f5; border-radius:7px; overflow:hidden; margin-bottom:10px; font-size:12px; }
      .syd-pvi-vt-card.syd-pvi-vt-card-alert { border-color:#f5c6cb; }
      .syd-pvi-vt-title { background:linear-gradient(135deg,#e8f4fd,#dceeff); padding:7px 11px; font-weight:700; font-size:12px; color:#1a5fa8; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }
      .syd-pvi-vt-sub { font-weight:400; font-size:10px; color:#4a7fb5; }
      .syd-pvi-vt-table { width:100%; }
      .syd-pvi-vt-row { display:grid; grid-template-columns:1.1fr 1fr 1fr 56px; align-items:center; padding:4px 10px; border-bottom:1px solid #f1f3f5; font-size:11.5px; gap:4px; }
      .syd-pvi-vt-row:last-child { border-bottom:none; }
      .syd-pvi-vt-hdr { background:#f8f9fa; font-weight:700; font-size:10px; color:#6c757d; text-transform:uppercase; letter-spacing:.4px; }
      .syd-pvi-vt-alert { background:#fff5f5; }
      .syd-pvi-vt-prev { color:#6c757d; }
      .syd-pvi-vt-lat { font-weight:600; color:#212529; }
      .syd-pvi-vt-warn { color:#a32d2d; font-weight:700; }
      .syd-pvi-vt-delta { display:flex; align-items:center; gap:3px; }
      .syd-pvi-vt-chip { font-size:10px; font-weight:700; padding:1px 5px; border-radius:4px; background:#e9ecef; color:#495057; white-space:nowrap; }
      .syd-pvi-vt-chip-up   { background:#f8d7da; color:#721c24; }
      .syd-pvi-vt-chip-down { background:#d4edda; color:#155724; }
      .syd-pvi-vt-chip-same { background:#e9ecef; color:#6c757d; }
      .syd-pvi-vt-arrow { font-size:11px; font-weight:700; }
      .syd-pvi-vt-up   { color:#a32d2d; }
      .syd-pvi-vt-down { color:#1a6f3b; }
      .syd-pvi-vt-same { color:#6c757d; }
    `;
    document.head.appendChild(st);
  }
}

// ── Receive AI draft from scribe ──────────────────────
function sydReceiveDraft(note) {
  const placeholder = document.getElementById('syd-draft-placeholder');
  const sections    = document.getElementById('syd-draft-sections');
  const badge       = document.getElementById('syd-draft-badge');
  if (placeholder) placeholder.style.display = 'none';
  if (sections)    sections.style.display    = 'block';
  if (badge) { badge.textContent = 'Ready to review'; badge.className = 'syd-aip-badge ready'; }
  const map = { s:'subjective', o:'objective', a:'assessment', p:'plan' };
  Object.entries(map).forEach(([key, field]) => {
    const el = document.getElementById('syd-ds-' + key + '-text');
    if (el && note[field]) el.textContent = note[field];
  });
  sydShowToast('🤖 AI draft note ready — review in the AI panel', '#534AB7');
}

function sydAcceptDraftSection(key) {
  const txt = document.getElementById('syd-ds-' + key + '-text')?.textContent;
  const ta  = document.getElementById('syd-soap-' + key);
  if (!txt || !ta) return;
  ta.value = txt + (ta.value ? '\n\n' + ta.value : '');
  sydMarkUnsaved();
  const sec = document.getElementById('syd-ds-' + key);
  if (sec) {
    sec.style.opacity = '.5';
    const acceptBtn = sec.querySelector('.syd-ds-accept');
    if (acceptBtn) { acceptBtn.textContent = '✓ Accepted'; acceptBtn.disabled = true; }
  }
  sydShowToast('✅ ' + key.toUpperCase() + ' section accepted', '#1D9E75');
}

function sydAcceptAllDraft() {
  ['s','o','a','p'].forEach(key => {
    const txt = document.getElementById('syd-ds-' + key + '-text')?.textContent;
    const ta  = document.getElementById('syd-soap-' + key);
    if (txt && ta && txt.trim()) ta.value = txt;
  });
  sydMarkUnsaved();
  document.querySelectorAll('.syd-draft-section').forEach(s => s.style.opacity = '.5');
  document.querySelectorAll('.syd-ds-accept').forEach(b => { b.textContent = '✓ Accepted'; b.disabled = true; });
  sydShowToast('✅ All sections accepted from AI draft', '#1D9E75');
}

function sydDiscardDraft() {
  if (!confirm('Discard the AI draft note?')) return;
  const ph  = document.getElementById('syd-draft-placeholder');
  const sec = document.getElementById('syd-draft-sections');
  const bdg = document.getElementById('syd-draft-badge');
  if (ph)  ph.style.display  = 'flex';
  if (sec) sec.style.display = 'none';
  if (bdg) { bdg.textContent = 'Waiting for recording'; bdg.className = 'syd-aip-badge'; }
}

// ── AI suggest billing codes ──────────────────────────
function sydSuggestCodes() {
  const btn  = document.getElementById('syd-suggest-codes-btn');
  const out  = document.getElementById('syd-suggested-codes');
  const assm = document.getElementById('syd-soap-a')?.value || '';
  const plan = document.getElementById('syd-soap-p')?.value || '';
  if (!assm && !plan) {
    sydShowToast('⚠️ Complete Assessment and Plan first', '#A32D2D');
    return;
  }
  if (btn) { btn.textContent = '⏳ Analysing…'; btn.disabled = true; }
  fetch(SYD.webroot
    + '/interface/modules/custom_modules'
    + '/oe-module-physician-dashboard/public/ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action:'suggest_codes', pid:SYD.pid,
        encounter:SYD.encounter, csrf:SYD.csrf,
        assessment:assm, plan:plan
      })
    })
    .then(r => r.json())
    .then(data => {
      if (btn) { btn.textContent = '🤖 AI Suggest Codes'; btn.disabled = false; }
      if (out && data.codes?.length) {
        out.innerHTML = data.codes.map(c =>
          `<div class="syd-chip-row" style="margin-bottom:4px;">
             <span class="syd-ck ${c.type==='ICD-10'?'dx':'cpt'}"
                   onclick="navigator.clipboard?.writeText('${c.code}');sydShowToast('Copied: ${c.code}','#534AB7')">
               ${c.code}
             </span>
             <span style="font-size:11px;color:var(--syd-muted);">${c.description}</span>
           </div>`
        ).join('');
        sydShowToast('✅ ' + data.codes.length + ' codes suggested', '#534AB7');
      }
    })
    .catch(() => {
      if (btn) { btn.textContent = '🤖 AI Suggest Codes'; btn.disabled = false; }
    });
}

// ═══════════════════════════════════════════════════════
//  VISIT HISTORY FILTERS
// ═══════════════════════════════════════════════════════
function sydFilterHistory(term) {
  const rows = document.querySelectorAll('.syd-vrow[data-search]');
  const q    = term.toLowerCase().trim();
  rows.forEach(row => {
    const txt = row.dataset.search || '';
    row.style.display = (!q || txt.includes(q)) ? '' : 'none';
  });
}

function sydFilterOrders(filter, btn) {
  document.querySelectorAll('#syd-order-filters .syd-filter-pill')
    .forEach(p => p.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('.syd-order-row').forEach(row => {
    const st = row.dataset.status || '';
    if (filter === 'all') { row.style.display = ''; return; }
    if (filter === 'referral') { row.style.display = st === 'referral' ? '' : 'none'; return; }
    row.style.display = st === filter ? '' : 'none';
  });
}

function sydFilterBilling(filter, btn) {
  document.querySelectorAll('.syd-bill-filters .syd-filter-pill')
    .forEach(p => p.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('.syd-bill-row').forEach(row => {
    const type   = row.dataset.type   || '';
    const billed = row.dataset.billed || '0';
    let show = true;
    if (filter === 'cpt')      show = type === 'cpt';
    if (filter === 'icd')      show = type === 'icd';
    if (filter === 'unbilled') show = billed === '0';
    row.style.display = show ? '' : 'none';
  });
}

function sydViewEncounter(id) {
  window.open(SYD.webroot
    + '/interface/patient_file/encounter/encounter_top.php?set_encounter=' + id,
    '_blank');
}

// ═══════════════════════════════════════════════════════
//  CHART MODAL
// ═══════════════════════════════════════════════════════
let sydChartCurrentTab = 'demographics';

function sydOpenChart() {
  const modal   = document.getElementById('syd-chart-modal');
  const overlay = document.getElementById('syd-modal-overlay');
  if (!modal || !overlay) return;
  modal.classList.add('open');
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
  sydChartTab('demographics',
    document.getElementById('syd-ctab-demographics'));
}

function sydCloseChart() {
  document.getElementById('syd-chart-modal')?.classList.remove('open');
  document.getElementById('syd-modal-overlay')?.classList.remove('open');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') sydCloseChart();
});

function sydChartTab(tabKey, el) {
  document.querySelectorAll('.syd-cm-tab')
    .forEach(t => t.classList.remove('active'));
  if (el) el.classList.add('active');
  sydChartCurrentTab = tabKey;

  const loading = document.getElementById('syd-cm-loading');
  const panel   = document.getElementById('syd-cm-panel');
  if (loading) loading.style.display = 'flex';
  if (panel)   panel.style.display   = 'none';

  fetch(SYD.webroot
    + '/interface/modules/custom_modules'
    + '/oe-module-physician-dashboard/public/chart_ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ tab:tabKey, pid:SYD.pid, csrf:SYD.csrf })
    })
    .then(r => r.json())
    .then(data => {
      if (loading) loading.style.display = 'none';
      if (panel) {
        panel.style.display = 'block';
        panel.innerHTML = data.html
          ? '<div class="syd-fade">' + data.html + '</div>'
          : '<p style="color:red;padding:14px;">' + (data.error || 'Error') + '</p>';
      }
    })
    .catch(() => {
      if (loading) loading.style.display = 'none';
      if (panel) {
        panel.style.display = 'block';
        panel.innerHTML = '<p style="color:red;padding:14px;">Failed to load. Try again.</p>';
      }
    });
}

// ═══════════════════════════════════════════════════════
//  TOAST
// ═══════════════════════════════════════════════════════
let _toastTimer;
function sydShowToast(msg, bg = '#1D9E75', ms = 2800) {
  clearTimeout(_toastTimer);
  let t = document.getElementById('_syd_toast');
  if (!t) {
    t    = document.createElement('div');
    t.id = '_syd_toast';
    t.className = 'syd-toast';
    document.getElementById('synapta-dashboard')?.appendChild(t);
  }
  // Allow multi-line messages (newline → line break)
  t.style.whiteSpace = 'pre-line';
  t.textContent = msg;
  t.style.background = bg;
  t.style.opacity    = '1';
  _toastTimer = setTimeout(() => { t.style.opacity = '0'; }, ms);
}

// ═══════════════════════════════════════════════════════
//  VITALS FORM POPUP — open & refresh PVI on close
// ═══════════════════════════════════════════════════════
function sydOpenVitalsForm() {
  if (typeof SYD === 'undefined' || !SYD.pid || !SYD.encounter) {
    sydShowToast('❌ No active patient / encounter', '#A32D2D');
    return;
  }

  const url = SYD.webroot
    + '/interface/forms/vitals/new.php'
    + '?set_encounter=' + encodeURIComponent(SYD.encounter)
    + '&pid='           + encodeURIComponent(SYD.pid);

  const popup = window.open(
    url,
    'sydAddVitals',
    'width=860,height=680,scrollbars=yes,resizable=yes,toolbar=no,menubar=no'
  );

  if (!popup) {
    sydShowToast('❌ Popup blocked — please allow popups for this site', '#A32D2D');
    return;
  }

  sydShowToast('📋 Vitals form opened — save to refresh Pre-Visit Intelligence', '#1D9E75');

  // Poll every 600 ms; when the popup closes, reload vitals panel + PVI
  const _poll = setInterval(() => {
    if (!popup || popup.closed) {
      clearInterval(_poll);

      // 1. Reload the vitals panel (right-column detail)
      if (typeof sydCurrentPanel !== 'undefined' && sydCurrentPanel === 'vitals') {
        sydLoadPanelData('vitals');
      }

      // 2. Re-run vital_trends AI and re-render PVI
      sydShowToast('🔄 Vitals saved — updating Pre-Visit Intelligence…', '#534AB7');
      sydLoadVitalTrendsAI();
    }
  }, 600);
}

// ═══════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  console.log('Synapta Dashboard v1.0 | PID:', SYD?.pid, '| Enc:', SYD?.encounter);
  // Ensure first tab is visible
  const firstPanel = document.getElementById('syd-tab-encounter');
  if (firstPanel) { firstPanel.style.display = 'flex'; firstPanel.classList.add('active'); }
  // Load AI vitals analysis asynchronously (non-blocking)
  sydLoadVitalTrendsAI();
});

// ── AI Vitals Trend Analysis — async loader ───────────────────────────────
function sydLoadVitalTrendsAI() {
  const container  = document.getElementById('syd-vtai-container');
  const pviContent = document.getElementById('syd-pvi-content');
  if (!container || typeof SYD === 'undefined' || !SYD.pid) return;

  // Clear everything — show ONLY the loading notice until the API responds
  container.innerHTML = '';
  if (pviContent) {
    // Remove any leftover static content so nothing shows except the loader
    pviContent.querySelectorAll(':scope > *:not(#syd-vtai-container)').forEach(el => el.remove());
  }

  // Insert loading notice at the top of pviContent (above the container)
  const notice = document.createElement('div');
  notice.id = 'syd-vtai-loading-notice';
  notice.className = 'syd-vtai-loading-notice';
  notice.innerHTML = `
    <div class="syd-vtai-ln-spinner"></div>
    <div class="syd-vtai-ln-text">
      <div class="syd-vtai-ln-title">Loading Vital Trends</div>
      <div class="syd-vtai-ln-sub">Please wait for a few minutes — Fetching Vitals Trend</div>
    </div>`;
  if (pviContent) {
    pviContent.insertBefore(notice, pviContent.firstChild);
  } else {
    container.appendChild(notice);
  }

  const fd = new FormData();
  fd.append('action',    'vital_trends');
  fd.append('pid',       SYD.pid);
  fd.append('encounter', SYD.encounter || 0);
  fd.append('csrf',      SYD.csrf);

  const ajaxUrl = (SYD.webroot || '') +
    '/interface/modules/custom_modules/oe-module-physician-dashboard/public/ajax.php';

  fetch(ajaxUrl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(resp => {
      document.getElementById('syd-vtai-loading-notice')?.remove();
      if (resp.success && resp.data) {
        SYD.vitalTrendsAI = resp.data;
        container.innerHTML = sydBuildVtaiCardHtml(resp.data);
      }
    })
    .catch(() => {
      document.getElementById('syd-vtai-loading-notice')?.remove();
    });
}

// ── Build AI Vitals Trend Card HTML (used by loader + sydUpdatePVI) ────────
function sydBuildVtaiCardHtml(data) {
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const num = v => (v === null || v === undefined || v === '') ? null : Number(v);
  const fmt = v => {
    if (v === null || v === undefined || v === '') return '—';
    const n = Number(v);
    return isNaN(n) ? String(v) : (Number.isInteger(n) ? String(n) : parseFloat(n.toFixed(2)).toString());
  };

  const ca  = data.clinical_analysis || {};
  const rec = data.recommendations   || ca.recommendations || {};

  // Status — handle compound strings like "Monitoring Required - Concerning Trend"
  const rawStatus = ca.status || data.status || 'Stable';
  const caStatus  = rawStatus.split(' - ')[0].trim();  // take first part
  const urgency   = rec.urgency || 'Routine';

  // Per-vital colour palette
  const vitalColors = {
    'Blood Pressure':   { color: '#c0392b', bg: '#fff5f5' },
    'Pulse':            { color: '#e67e22', bg: '#fff8f0' },
    'Heart Rate':       { color: '#e67e22', bg: '#fff8f0' },
    'Temperature':      { color: '#8e44ad', bg: '#faf5ff' },
    'Respiration Rate': { color: '#2980b9', bg: '#f0f7ff' },
    'Respiration':      { color: '#2980b9', bg: '#f0f7ff' },
    'Oxygen Saturation':{ color: '#16a085', bg: '#f0faf8' },
    'SpO2':             { color: '#16a085', bg: '#f0faf8' },
    'Weight':           { color: '#27ae60', bg: '#f2fbf4' },
    'BMI':              { color: '#1a6a8a', bg: '#edf6fb' },
    'Height':           { color: '#5d6d7e', bg: '#f5f6fa' },
  };
  const fallbackColors = ['#7d3c98','#1a5276','#117a65','#784212','#1f618d','#922b21'];
  let colorIdx = 0;
  const getVitalColor = name => {
    const k = Object.keys(vitalColors).find(k => name.toLowerCase().includes(k.toLowerCase()));
    if (k) return vitalColors[k];
    const c = fallbackColors[colorIdx % fallbackColors.length]; colorIdx++;
    return { color: c, bg: '#f8f9fa' };
  };

  const statusMap = {
    'Normal':              { cls: 'syd-vtai-ok',       icon: '✓', label: 'Normal' },
    'Stable':              { cls: 'syd-vtai-stable',   icon: '●', label: 'Stable' },
    'Monitoring Required': { cls: 'syd-vtai-warn',     icon: '⚠', label: 'Monitor' },
    'Concerning Trend':    { cls: 'syd-vtai-concern',  icon: '↑', label: 'Concerning' },
    'Critical':            { cls: 'syd-vtai-critical', icon: '!', label: 'Critical' },
  };
  const sMeta = statusMap[caStatus] || { cls: 'syd-vtai-warn', icon: '⚠', label: caStatus };

  const urgMap = {
    'Routine':                     { cls: 'syd-vtai-urg-r', dot: '#27ae60' },
    'Within 1 Week':               { cls: 'syd-vtai-urg-w', dot: '#2980b9' },
    'Monitoring Required':         { cls: 'syd-vtai-urg-w', dot: '#2980b9' },
    'Prompt (Within 24-48 Hours)': { cls: 'syd-vtai-urg-p', dot: '#e67e22' },
    'Urgent (Same Day)':           { cls: 'syd-vtai-urg-u', dot: '#c0392b' },
    'Emergency':                   { cls: 'syd-vtai-urg-e', dot: '#7b241c' },
  };
  const uMeta = urgMap[urgency] || { cls: 'syd-vtai-urg-p', dot: '#e67e22' };

  // ── Header ────────────────────────────────────────────────────────────────
  let h = `<div class="syd-vtai-card">
  <div class="syd-vtai-header">
    <div class="syd-vtai-header-left">
      <span class="syd-vtai-header-icon">🩺</span>
      <span class="syd-vtai-title">AI Vitals Analysis</span>
    </div>
    <span class="syd-vtai-badge ${sMeta.cls}">${sMeta.icon} ${esc(sMeta.label)}</span>
  </div>`;

  // ── Clinical Interpretation callout (expandable) ────────────────────────
  if (ca.title && ca.overall_interpretation) {
    const interpId = 'syd-vtai-interp-' + Math.random().toString(36).slice(2, 8);
    h += `<div class="syd-vtai-interp">
      <div class="syd-vtai-interp-bar"></div>
      <div class="syd-vtai-interp-body">
        <div class="syd-vtai-interp-toggle"
             onclick="(function(btn){
               var body = document.getElementById('${interpId}');
               var arrow = btn.querySelector('.syd-vtai-interp-arrow');
               if (!body) return;
               var expanded = body.style.display !== 'none';
               body.style.display = expanded ? 'none' : 'block';
               if (arrow) arrow.textContent = expanded ? '▶' : '▼';
             })(this)"
             style="cursor:pointer;display:flex;align-items:center;gap:6px;user-select:none;">
          <span class="syd-vtai-interp-label" style="flex:1;">${esc(ca.title)}</span>
          <span class="syd-vtai-interp-arrow" style="font-size:9px;color:var(--syd-muted);">▶</span>
        </div>
        <div id="${interpId}" class="syd-vtai-interp-text" style="display:none;margin-top:6px;">
          ${esc(ca.overall_interpretation)}
        </div>
      </div>
    </div>`;
  }

  // ── Vitals Table ─────────────────────────────────────────────────────────
  if (ca.vitals_trends && ca.vitals_trends.length) {
    h += `<div class="syd-vtai-tbl-wrap" style="overflow-x:auto;">;
      <div class="syd-vtai-section-label">Vitals Trend</div>
      <table class="syd-vtai-tbl" style="width:250px;overflow-x:auto;">
        <thead style="overflow-x:auto;">
          <tr style="overflow-x:auto;">
            <th class="syd-vtai-th syd-vtai-th-vital">Vital Sign</th>
            <th class="syd-vtai-th">Previous</th>
            <th class="syd-vtai-th">Latest</th>
            <th class="syd-vtai-th">Change</th>
            <th class="syd-vtai-th syd-vtai-th-pct">%</th>
            <th class="syd-vtai-th">Conclusion</th>
          </tr>
        </thead>
        <tbody style="overflow-x:auto;width:100%">`;

    ca.vitals_trends.forEach(vtr => {
      const prev = vtr.previous_value ?? vtr.previous;
      const lat  = vtr.latest_value   ?? vtr.latest;
      const vc   = getVitalColor(vtr.vital_sign || '');

      // Delta
      let deltaNum = null;
      if (vtr.delta_systolic !== undefined && vtr.delta_systolic !== null) {
        deltaNum = Number(vtr.delta_systolic);
      } else if (vtr.delta !== undefined && vtr.delta !== null && !isNaN(Number(vtr.delta))) {
        deltaNum = Number(vtr.delta);
      } else if (vtr.delta_value !== undefined && vtr.delta_value !== null && !isNaN(Number(vtr.delta_value))) {
        deltaNum = Number(vtr.delta_value);
      }

      let pctNum = null;
      if (vtr.percent_change !== undefined && vtr.percent_change !== null) pctNum = Number(vtr.percent_change);

      let deltaTxt = '—', deltaCls = 'syd-vtai-chg-neutral';
      if (deltaNum !== null) {
        if (vtr.delta_systolic !== undefined && vtr.delta_diastolic !== undefined) {
          const dd = Number(vtr.delta_diastolic ?? 0);
          deltaTxt = (deltaNum > 0 ? '+' : '') + parseFloat(deltaNum.toFixed(1))
                   + ' / ' + (dd > 0 ? '+' : '') + parseFloat(dd.toFixed(1));
        } else {
          deltaTxt = (deltaNum > 0 ? '+' : '') + parseFloat(deltaNum.toFixed(1));
        }
        if (deltaNum > 0)      deltaCls = 'syd-vtai-chg-up';
        else if (deltaNum < 0) deltaCls = 'syd-vtai-chg-down';
      }

      const pctTxt = pctNum !== null
        ? (pctNum > 0 ? '+' : '') + parseFloat(pctNum.toFixed(1)) + '%'
        : '—';

      const arrow = deltaNum > 0 ? ' ↑' : (deltaNum < 0 ? ' ↓' : '');

      // Significance tooltip
      const sig = esc(vtr.clinical_significance || '');

      // Conclusion badge styling
      const conclusionMap = {
        'Normal':       { bg: '#1b8a4c', color: '#ffffff', shadow: 'rgba(27,138,76,.35)' },
        'Elevated':     { bg: '#e07b00', color: '#ffffff', shadow: 'rgba(224,123,0,.35)' },
        'Stage 1 HTN':  { bg: '#d95c00', color: '#ffffff', shadow: 'rgba(217,92,0,.35)' },
        'Stage 2 HTN':  { bg: '#c0392b', color: '#ffffff', shadow: 'rgba(192,57,43,.35)' },
        'Tachycardia':  { bg: '#8e24aa', color: '#ffffff', shadow: 'rgba(142,36,170,.35)' },
        'Fever':        { bg: '#f57c00', color: '#ffffff', shadow: 'rgba(245,124,0,.35)' },
        'Critical':     { bg: '#b71c1c', color: '#ffffff', shadow: 'rgba(183,28,28,.45)' },
      };
      const conclusion    = vtr.conclusion || '';
      const cStyle        = conclusionMap[conclusion]
        || { bg: '#6b7280', color: '#ffffff', shadow: 'rgba(107,114,128,.3)' };
      const conclusionHtml = conclusion
        ? `<span style="display:inline-block;padding:3px 9px;border-radius:12px;font-size:10px;
                        font-weight:700;background:${cStyle.bg};color:${cStyle.color};
                        box-shadow:0 2px 6px ${cStyle.shadow};white-space:nowrap;
                        letter-spacing:.3px;">
             ${esc(conclusion)}
           </span>`
        : '—';

      h += `<tr class="syd-vtai-tr" title="${sig}" style="width:100%;overflow-x:auto;" >
        <td class="syd-vtai-td syd-vtai-td-vital" style="color:${vc.color};border-left:3px solid ${vc.color};background:${vc.bg}">
          ${esc(vtr.vital_sign || '')}
        </td>
        <td class="syd-vtai-td syd-vtai-td-num">${esc(fmt(prev))}</td>
        <td class="syd-vtai-td syd-vtai-td-num syd-vtai-td-latest" style="color:${vc.color}">${esc(fmt(lat))}</td>
        <td class="syd-vtai-td syd-vtai-td-num"><span class="${deltaCls}">${esc(deltaTxt)}${arrow}</span></td>
        <td class="syd-vtai-td syd-vtai-td-num syd-vtai-td-pct">${esc(pctTxt)}</td>
        <td class="syd-vtai-td" style="text-align:center;">${conclusionHtml}</td>
      </tr>`;

      // Significance note row — only if non-trivial
      if (sig && !sig.match(/^stable\s/i)) {
        h += `<tr class="syd-vtai-sig-row">
          <td colspan="6" class="syd-vtai-sig-td" style="border-left:3px solid ${vc.color}">
            <span class="syd-vtai-sig-icon">ℹ</span> ${sig}
          </td>
        </tr>`;
      }
    });

    h += `</tbody></table></div>`;
  }

  // ── Recommendations ───────────────────────────────────────────────────────
  if (rec.action_items && rec.action_items.length) {
    h += `<div class="syd-vtai-rec">
      <div class="syd-vtai-rec-hdr">
        <span class="syd-vtai-section-label">Recommendations</span>
        <span class="syd-vtai-urg ${uMeta.cls}">
          <span class="syd-vtai-urg-dot" style="background:${uMeta.dot}"></span>
          ${esc(urgency)}
        </span>
      </div>
      <div class="syd-vtai-actions">`;

    rec.action_items.forEach((item, i) => {
      h += `<div class="syd-vtai-action-item">
        <span class="syd-vtai-action-num">${i + 1}</span>
        <span class="syd-vtai-action-text">${esc(item)}</span>
      </div>`;
    });

    h += `</div>`;

    if (rec.follow_up_timeframe) {
      h += `<div class="syd-vtai-followup">
        <span class="syd-vtai-followup-icon">🗓</span>
        <span><strong>Follow-up:</strong> ${esc(rec.follow_up_timeframe)}</span>
      </div>`;
    }

    h += `</div>`;
  }

  h += `</div>`;
  return h;
}

// ═══════════════════════════════════════════════════════
// ADD THESE FUNCTIONS to the END of dashboard.js
// Fixes: sidebar nav click, body overflow lock,
//        AI panel grid positioning
// ═══════════════════════════════════════════════════════

// ── Sidebar nav item click ────────────────────────────
function sydNavClick(el) {
  document.querySelectorAll('.syd-nav-item')
    .forEach(n => n.classList.remove('active'));
  if (el) el.classList.add('active');
}

// ── Toggle AI panel (updated for grid layout) ─────────
function sydToggleAIPanel() {
  sydAIPanelOpen = !sydAIPanelOpen;
  const panel = document.getElementById('syd-ai-panel');
  const root  = document.getElementById('synapta-dashboard');
  const btn   = document.getElementById('syd-aip-tab');
  if (!panel) return;

  if (sydAIPanelOpen) {
    panel.style.display = 'flex';
    if (root) root.style.gridTemplateColumns = 'var(--syd-sw) 1fr var(--syd-aw)';
    if (btn)  btn.style.display = 'none';
  } else {
    panel.style.display = 'none';
    if (root) root.style.gridTemplateColumns = 'var(--syd-sw) 1fr 0px';
    if (btn)  btn.style.display = 'flex';
  }
}

// ── On DOMContentLoaded — lock body scroll ────────────
document.addEventListener('DOMContentLoaded', () => {
  // Lock body to prevent OpenEMR scroll fighting the dashboard
  document.documentElement.style.height   = '100%';
  document.documentElement.style.overflow = 'hidden';
  document.body.style.height              = '100%';
  document.body.style.overflow            = 'hidden';
  document.body.style.margin              = '0';
  document.body.style.padding             = '0';

  // Ensure encounter tab is active on load
  const enc = document.getElementById('syd-tab-encounter');
  if (enc) {
    enc.style.display = 'flex';
    enc.classList.add('active');
  }

  console.log(
    '%cSynapta Dashboard v1.0',
    'color:#1D9E75;font-weight:700;font-size:14px;'
  );
  console.log('PID:', SYD?.pid, '| Encounter:', SYD?.encounter);
});


// ── Mobile floating buttons ───────────────────────────
function sydInjectMobileControls() {
  const root = document.getElementById('synapta-dashboard');
  if (!root) return;

  // Mobile overlay (backdrop for panels)
  if (!document.getElementById('syd-mob-overlay')) {
    const overlay = document.createElement('div');
    overlay.id        = 'syd-mob-overlay';
    overlay.className = 'syd-mobile-overlay';
    overlay.onclick   = sydCloseMobilePanels;
    root.appendChild(overlay);
  }

  // AI toggle button (tablet)
  if (!document.getElementById('syd-mob-ai-btn')) {
    const aiBtn = document.createElement('button');
    aiBtn.id        = 'syd-mob-ai-btn';
    aiBtn.className = 'syd-mobile-ai-toggle';
    aiBtn.innerHTML = '🤖';
    aiBtn.title     = 'Synapta AI Panel';
    aiBtn.onclick   = sydToggleMobileAI;
    root.appendChild(aiBtn);
  }

  // Nav toggle button (mobile)
  if (!document.getElementById('syd-mob-nav-btn')) {
    const navBtn = document.createElement('button');
    navBtn.id        = 'syd-mob-nav-btn';
    navBtn.className = 'syd-mobile-nav-toggle';
    navBtn.innerHTML = '☰';
    navBtn.title     = 'Navigation';
    navBtn.onclick   = sydToggleMobileNav;
    root.appendChild(navBtn);
  }
}

// ── Toggle AI panel on tablet/mobile ─────────────────
function sydToggleMobileAI() {
  const panel   = document.getElementById('syd-ai-panel');
  const overlay = document.getElementById('syd-mob-overlay');
  if (!panel) return;

  const isOpen = panel.classList.contains('open');
  sydCloseMobilePanels();

  if (!isOpen) {
    panel.classList.add('open');
    panel.style.display = 'flex';
    if (overlay) overlay.classList.add('open');
  }
}

// ── Toggle sidebar on mobile ──────────────────────────
function sydToggleMobileNav() {
  const sidebar = document.querySelector('.syd-sidebar, .sidebar');
  const overlay = document.getElementById('syd-mob-overlay');
  if (!sidebar) return;

  const isOpen = sidebar.classList.contains('mob-open');
  sydCloseMobilePanels();

  if (!isOpen) {
    sidebar.style.cssText = [
      'display:flex',
      'position:fixed',
      'left:0','top:0','bottom:0',
      'z-index:401',
      'width:200px',
      'flex-direction:column',
      'align-items:flex-start',
      'padding:16px 8px',
      'background:var(--navy)',
      'border-right:1px solid rgba(255,255,255,.08)',
      'box-shadow:4px 0 20px rgba(0,0,0,.3)',
    ].join(';');
    sidebar.classList.add('mob-open');

    // Widen nav items for mobile sidebar
    sidebar.querySelectorAll('.syd-nav-item,.ni').forEach(item => {
      item.style.width      = '100%';
      item.style.borderRadius = '8px';
      item.style.justifyContent = 'flex-start';
      item.style.padding    = '10px 14px';
      item.style.gap        = '10px';
    });
    // Show tooltips inline
    sidebar.querySelectorAll('.syd-nav-tip,.ntip').forEach(tip => {
      tip.style.position = 'static';
      tip.style.opacity  = '1';
      tip.style.fontSize = '13px';
    });

    if (overlay) overlay.classList.add('open');
  }
}

// ── Close all mobile panels ───────────────────────────
function sydCloseMobilePanels() {
  const overlay = document.getElementById('syd-mob-overlay');
  const panel   = document.getElementById('syd-ai-panel');
  const sidebar = document.querySelector('.syd-sidebar, .sidebar');

  if (overlay) overlay.classList.remove('open');

  if (panel && panel.classList.contains('open')) {
    panel.classList.remove('open');
    // Restore display based on screen size
    if (window.innerWidth >= 1024) {
      panel.style.display = 'flex';
    } else {
      panel.style.display = 'none';
    }
  }

  if (sidebar && sidebar.classList.contains('mob-open')) {
    sidebar.classList.remove('mob-open');
    sidebar.removeAttribute('style');
    // Restore nav items
    sidebar.querySelectorAll('.syd-nav-item,.ni').forEach(item => {
      item.removeAttribute('style');
    });
    sidebar.querySelectorAll('.syd-nav-tip,.ntip').forEach(tip => {
      tip.removeAttribute('style');
    });
    // Re-apply correct display based on screen size
    if (window.innerWidth <= 767) {
      sidebar.style.display = 'none';
    }
  }
}

// ── Handle right-col detail panel on mobile ───────────
const _sydOpenPanelOrig = window.sydOpenPanel;
window.sydOpenPanel = function(panelKey) {
  _sydOpenPanelOrig && _sydOpenPanelOrig(panelKey);

  // On tablet (< 1024px), show detail as slide-in panel
  if (window.innerWidth < 1024) {
    const rightCol = document.getElementById('syd-right-col');
    const overlay  = document.getElementById('syd-mob-overlay');
    if (!rightCol) return;

    rightCol.style.cssText = [
      'display:flex',
      'position:fixed',
      'right:0','top:0','bottom:0',
      'width:min(340px,90vw)',
      'z-index:395',
      'flex-direction:column',
      'background:var(--surface)',
      'box-shadow:-4px 0 20px rgba(0,0,0,.15)',
    ].join(';');

    if (overlay) overlay.classList.add('open');

    // Add close handler
    overlay.onclick = () => {
      rightCol.removeAttribute('style');
      sydCloseMobilePanels();
      sydClosePanel && sydClosePanel();
    };
  }
};

// ── Handle resize — show/hide panels correctly ────────
let _sydResizeTimer;
function sydHandleResize() {
  clearTimeout(_sydResizeTimer);
  _sydResizeTimer = setTimeout(() => {
    const w       = window.innerWidth;
    const root    = document.getElementById('synapta-dashboard');
    const panel   = document.getElementById('syd-ai-panel');
    const sidebar = document.querySelector('.syd-sidebar, .sidebar');

    // Close all mobile panels first
    sydCloseMobilePanels();

    if (w >= 1024) {
      // Desktop + tablet landscape — show AI panel, show sidebar
      if (panel)   { panel.style.display   = 'flex'; }
      if (sidebar) { sidebar.style.display = 'flex'; }
      if (root)    {
        root.style.gridTemplateColumns = w >= 1280
          ? 'var(--sw) 1fr var(--aw)'
          : 'var(--sw) 1fr 260px';
        root.style.gridTemplateAreas = '"T T T" "S M A"';
      }
    } else if (w >= 768) {
      // Tablet portrait — hide AI, show sidebar
      if (panel)   { panel.style.display   = 'none'; }
      if (sidebar) { sidebar.style.display = 'flex'; }
      if (root)    {
        root.style.gridTemplateColumns = 'var(--sw) 1fr';
        root.style.gridTemplateAreas  = '"T T" "S M"';
      }
    } else {
      // Mobile — hide both
      if (panel)   { panel.style.display   = 'none'; }
      if (sidebar) { sidebar.style.display = 'none'; }
      if (root)    {
        root.style.gridTemplateColumns = '0 1fr';
        root.style.gridTemplateAreas  = '"T T" "M M"';
      }
    }
  }, 120);
}

// ── Update sydToggleAIPanel for responsive context ─────
window.sydToggleAIPanel = function() {
  if (window.innerWidth < 1024) {
    sydToggleMobileAI();
    return;
  }
  // Desktop toggle
  sydAIPanelOpen = !sydAIPanelOpen;
  const panel = document.getElementById('syd-ai-panel');
  const root  = document.getElementById('synapta-dashboard');
  if (!panel) return;
  if (sydAIPanelOpen) {
    panel.style.display = 'flex';
    if (root) root.style.gridTemplateColumns = 'var(--sw) 1fr var(--aw)';
  } else {
    panel.style.display = 'none';
    if (root) root.style.gridTemplateColumns = 'var(--sw) 1fr 0px';
  }
};

// ── Cards horizontal scroll touch ─────────────────────
function sydInitCardScroll() {
  const row = document.querySelector('.syd-cards-row, .cc-row');
  if (!row) return;

  let startX, startScrollLeft, isDragging = false;

  row.addEventListener('mousedown', e => {
    isDragging    = true;
    startX        = e.pageX - row.offsetLeft;
    startScrollLeft = row.scrollLeft;
    row.style.cursor = 'grabbing';
  });
  row.addEventListener('mouseleave', () => { isDragging = false; row.style.cursor = ''; });
  row.addEventListener('mouseup',    () => { isDragging = false; row.style.cursor = ''; });
  row.addEventListener('mousemove',  e => {
    if (!isDragging) return;
    e.preventDefault();
    row.scrollLeft = startScrollLeft - (e.pageX - row.offsetLeft - startX);
  });
}

// ── Tab strip touch scroll ─────────────────────────────
function sydInitTabScroll() {
  const strip = document.querySelector('.syd-tabs-row, .stabs');
  if (!strip) return;
  let startX, startSL, active = false;
  strip.addEventListener('touchstart',  e => { active=true; startX=e.touches[0].clientX; startSL=strip.scrollLeft; }, {passive:true});
  strip.addEventListener('touchmove',   e => { if(!active)return; strip.scrollLeft=startSL-(e.touches[0].clientX-startX); }, {passive:true});
  strip.addEventListener('touchend',    () => { active=false; });
}

// ── Keyboard shortcuts ─────────────────────────────────
document.addEventListener('keydown', e => {
  // Escape — close chart modal or mobile panels
  if (e.key === 'Escape') {
    sydCloseChart && sydCloseChart();
    sydCloseMobilePanels();
  }
  // Ctrl+S — save note
  if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault();
    sydSaveNote && sydSaveNote();
  }
  // Ctrl+E — expand all SOAP sections
  if (e.key === 'e' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault();
    sydExpandAll && sydExpandAll();
  }
});

// ── Init on DOM ready ──────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Lock body scroll
  document.documentElement.style.cssText = 'height:100%;overflow:hidden;margin:0;padding:0;';
  document.body.style.cssText            = 'height:100%;overflow:hidden;margin:0;padding:0;';

  // Inject mobile controls
  sydInjectMobileControls();

  // Init card + tab scroll
  sydInitCardScroll();
  sydInitTabScroll();

  // Listen for resize
  window.addEventListener('resize', sydHandleResize, { passive: true });

  // Set initial state based on screen width
  sydHandleResize();

  // Ensure first tab visible
  const enc = document.getElementById('syd-tab-encounter');
  if (enc) {
    enc.style.display = 'flex';
    enc.classList.add('active');
  }

  console.log(
    '%cSynapta Dashboard v3.0',
    'color:#1D9E75;font-weight:700;font-size:14px;background:#0F1117;padding:4px 10px;border-radius:5px;'
  );
  if (typeof SYD !== 'undefined') {
    console.log('PID:', SYD.pid, '| Encounter:', SYD.encounter);
  }
});

function sydShowOrderTab(tab, btn){

  document.querySelectorAll('.syd-orders-tab')
    .forEach(t => t.classList.remove('active'));

  btn.classList.add('active');

  document.querySelectorAll('.syd-orders-panel')
    .forEach(p => p.style.display = 'none');

  const panel = document.getElementById(
    'syd-orders-panel-' + tab
  );

  if(panel){
    panel.style.display = 'block';
  }
}


(function () {
    var TOTAL  = 7;   /* total number of cards */
    var VISIBLE = 5;  /* cards shown at once   */
    var STEPS  = TOTAL - VISIBLE;  /* = 2  */
    var step   = 0;

    function getCardWidth() {
        var vp    = document.getElementById('syd-viewport');
        var row   = document.getElementById('syd-cards-row');
        if (!vp || !row) return 0;
        /* card width = (viewport width - gaps) / VISIBLE */
        /* gaps = (VISIBLE - 1) * 8px = 32px              */
        var gapPx = 8;
        return (vp.offsetWidth - gapPx * (VISIBLE - 1)) / VISIBLE;
    }

    function applySlide() {
        var row  = document.getElementById('syd-cards-row');
        var prev = document.getElementById('syd-prev');
        var next = document.getElementById('syd-next');
        if (!row) return;

        var cardW  = getCardWidth();
        var gapPx  = 8;
        var offset = step * (cardW + gapPx);

        row.style.transform = 'translateX(-' + offset + 'px)';

        prev.disabled = (step === 0);
        next.disabled = (step >= STEPS);

        /* dots */
        var dots = document.querySelectorAll('.syd-slider-dot');
        dots.forEach(function (d, i) {
            d.classList.toggle('active', i === step);
        });
    }

    window.sydSlide = function (dir) {
        /* jump 2 cards per click; clamp so we never overshoot */
        step = Math.min(Math.max(step + dir * 2, 0), STEPS);
        applySlide();
    };

    /* Recalculate on resize (handles flex reflow) */
    window.addEventListener('resize', function () { applySlide(); });

    /* Initial state */
    applySlide();
})();


function sydChangeEncounter(value)
{
    // Create new encounter
    if (value === '__new__') {

        window.location.href =
            "<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/encounter/encounter_top.php?pid=<?php echo $pid; ?>";

        return;
    }

    // Reload dashboard with selected encounter
    const url = new URL(window.location.href);

    url.searchParams.set('set_pid', '<?php echo (int)$pid; ?>');
    url.searchParams.set('set_encounter', value);

    window.location.href = url.toString();
}