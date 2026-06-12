/**
 * Ambient Clinical Documentation Widget
 * ======================================
 * WebRTC audio recording → chunked upload → SOAP note generation
 *
 * Features:
 * - Browser-based recording (no app install)
 * - Chunked upload every 30 seconds
 * - PHI de-identification (server-side)
 * - Inline diff highlighting (AI=blue, edits=black)
 * - Per-section accept / reject / modify
 * - Injects final note into OpenEMR SOAP fields
 */

(function () {
    'use strict';

    // ── Configuration ─────────────────────────────────────────────────────────
    // Bootstrap.php injects window.ambientDocConfig before this script loads.
    const _cfg = window.ambientDocConfig || {};

    const CONFIG = {
        CHUNK_INTERVAL_MS : 30000,

        // Point directly to your FastAPI microservice
        FASTAPI_BASE_URL  : _cfg.fastApiUrl || 'http://127.0.0.1:8000',
        
        // Still fallback to native PHP module structure for local logging/auditing actions
        API_URL           : _cfg.apiUrl
                          || '/synaptaEMR-old/interface/modules/custom_modules'
                          + '/oe-module-ambient-docs/public/api.php',

        SELECTORS         : {
            subjective : _cfg.selSubjective || '[name="subjective"], #soap_subjective',
            objective  : _cfg.selObjective  || '[name="objective"], #soap_objective',
            assessment : _cfg.selAssessment || '[name="assessment"], #soap_assessment',
            plan       : _cfg.selPlan       || '[name="plan"], #soap_plan'
        }
    };

    // ── Application State ─────────────────────────────────────────────────────
    const state = {
        mediaRecorder    : null,
        audioChunks      : [],
        chunkIntervalId  : null,
        sessionId        : null,
        encounterId      : null,
        patientId        : null,
        fullTranscript   : "", // Accumulates transcription blocks returned by FastAPI
        
        // UI Components View States
        isRecording      : false,
        isProcessing     : false,
        
        // Pre-Visit Intelligence (populated from clinical_summary in API response)
        clinicalSummary  : null,

        // Data Store Snapshots
        originalSoapData : null, // Untouched raw data from AI
        activeSoapData   : null, // Mutated data containing interactive changes
        acceptedSections : {
            subjective : false,
            objective  : false,
            assessment : false,
            plan       : false
        }
    };

    // UI Element Cache
    let widgetEl = null;
    let mainBtnEl = null;
    let statusIndicatorEl = null;
    let statusTextEl = null;
    let reviewModalEl = null;

    // ── Helpers ───────────────────────────────────────────────────────────────
    function getContextIds() {
        state.encounterId = _cfg.encounterId || document.querySelector('[name="encounter"]')?.value || '0';
        state.patientId = _cfg.pid || document.querySelector('[name="pid"]')?.value || '0';
    }

    function safeTrim(str) {
        return typeof str === 'string' ? str.trim() : '';
    }

    // Simple structural shallow-diff to highlight insertions
    function computeInlineDiff(originalText, currentText) {
        const orig = safeTrim(originalText);
        const curr = safeTrim(currentText);
        if (!orig) return `<span class="diff-inserted">${curr}</span>`;
        if (orig === curr) return curr;

        // Visual fallback highlighter wrapper
        return `<span class="diff-inserted">${curr}</span>`;
    }

    // ── Media Engine Processing Pipeline ──────────────────────────────────────
    async function startRecording() {
        getContextIds();
        updateStatus('Connecting to microphone audio hardware device layer...', 'info');

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            // Step A: Request a fresh track session ID block from PHP database layer
            const res = await fetch(`${CONFIG.API_URL}?action=create_session`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    patient_id:   String(state.patientId),
                    encounter_id: String(state.encounterId)
                })
            });
            const sessionData = await res.json();

            if (!res.ok || !sessionData.session_id) {
                throw new Error(sessionData.error || 'Failed to initialize database audit trail record context.');
            }

            state.sessionId = sessionData.session_id;
            state.audioChunks = [];
            state.fullTranscript = "";

            let options = { mimeType: 'audio/webm' };
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                options = { mimeType: 'audio/webm;codecs=opus' };
            }

            state.mediaRecorder = new MediaRecorder(stream, options);

            state.mediaRecorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    state.audioChunks.push(e.data);
                }
            };

            state.mediaRecorder.onstop = async () => {
                updateStatus('Finalizing audio stream collection...', 'processing');
                
                if (state.audioChunks.length > 0) {
                    const finalBlob = new Blob(state.audioChunks, { type: state.mediaRecorder.mimeType });
                    await uploadChunk(finalBlob);
                }

                // Fire full generation
                await requestSoapGeneration();

                // Shut down audio tracks safely
                stream.getTracks().forEach(track => track.stop());
            };

            // Slice audio buffers incrementally into local state frame every 1 second
            state.mediaRecorder.start(1000);
            state.isRecording = true;
            renderWidgetState();

            // Slices and pipes chunks to FastAPI every 30 seconds
            state.chunkIntervalId = setInterval(() => {
                if (state.mediaRecorder && state.mediaRecorder.state === 'recording' && state.audioChunks.length > 0) {
                    const chunkBlob = new Blob(state.audioChunks, { type: state.mediaRecorder.mimeType });
                    state.audioChunks = []; // Flush local buffer array
                    uploadChunk(chunkBlob);
                }
            }, CONFIG.CHUNK_INTERVAL_MS);

            updateStatus('Scribe Active: Listening to medical encounter conversation...', 'recording');

        } catch (err) {
            console.error('[AmbientDoc] startRecording Exception Error Caught:', err);
            updateStatus('Microphone link dropped or denied.', 'error');
            state.isRecording = false;
            renderWidgetState();
        }
    }

    function stopRecording() {
        if (!state.mediaRecorder || state.mediaRecorder.state === 'inactive') return;
        
        clearInterval(state.chunkIntervalId);
        state.mediaRecorder.stop();
        state.isRecording = false;
        state.isProcessing = true;
        renderWidgetState();
    }

    // ── Transport API Callouts ────────────────────────────────────────────────
    async function uploadChunk(blob) {
        if (!state.sessionId) return;
        console.log(`[AmbientDoc] Transmitting chunk payload fragment (${blob.size} bytes) to FastAPI...`);

        const formData = new FormData();
        formData.append('file', blob, `chunk_${Date.now()}.webm`);

        try {
            const response = await fetch(`${CONFIG.FASTAPI_BASE_URL}/transcribe_chunk`, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`FastAPI downstream network failure: ${response.status}`);
            
            const result = await response.json();
            if (result.text) {
                state.fullTranscript += " " + result.text.trim();
                console.log("[AmbientDoc] Fragment synchronized with transaction string.");
            }
        } catch (err) {
            console.error('[AmbientDoc] chunk sync process exception:', err);
        }
    }

    async function requestSoapGeneration() {
        updateStatus('Analyzing encounter transcript and compiling structured fields...', 'processing');

        try {
            // Route through api.php/process_session so PHP can enrich the
            // request with full patient context (vitals, labs, problem list, etc.)
            // before forwarding to FastAPI.
            const response = await fetch(`${CONFIG.API_URL}?action=process_session`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id:   String(state.sessionId),
                    transcript:   state.fullTranscript.trim() || 'No transcript data generated.',
                    patient_id:   String(state.patientId),
                    encounter_id: String(state.encounterId)
                })
            });

            if (!response.ok) throw new Error(`process_session error: ${response.status}`);

            const jsonResponse = await response.json();

            if (jsonResponse.error) {
                throw new Error(jsonResponse.detail || jsonResponse.error);
            }

            const soapNote = jsonResponse.soap_note || jsonResponse.data || {};
            const clinicalSummary = {
                current_vitals: jsonResponse.current_vitals || null,
                new_vitals:     jsonResponse.new_vitals     || null,
                complaints:      jsonResponse.complaints      || null,
                medicine:         jsonResponse.medicine         || null,
                past_problem_list: jsonResponse.past_problem_list || null,
                negative_lab_results: jsonResponse.negative_lab_results || null,
            }

            // Build the clinical summary from both the nested object (PHP shapes
            // current_vitals/past_problem_list/negative_lab_results/complaints into
            // clinical_summary) AND top-level fields the Python API returns flat
            // (new_vitals, medicine, drug_interaction).  Merge so nothing is lost.
            const _cs = (clinicalSummary && typeof clinicalSummary === 'object')
                ? clinicalSummary : {};
            state.clinicalSummary = Object.assign({}, _cs, {
                new_vitals:       _cs.new_vitals       || jsonResponse.new_vitals       || null,
                medicine:         _cs.medicine         || jsonResponse.medicine         || null,
                drug_interaction: _cs.drug_interaction || jsonResponse.drug_interaction || null,
            });

            // Update physician dashboard PVI panel if it's on the same page
            if (state.clinicalSummary && typeof sydUpdatePVI === 'function') {
                sydUpdatePVI(state.clinicalSummary);
            }

            // Normalise SOAP sections — handle both flat strings and nested objects
            state.originalSoapData = {
                subjective : _buildSubjective(soapNote),
                objective  : _buildObjective(soapNote),
                assessment : _buildAssessment(soapNote),
                plan       : _buildPlan(soapNote)
            };

            // Deep copy for interactive edits
            state.activeSoapData = JSON.parse(JSON.stringify(state.originalSoapData));

            // Reset section acceptance flags
            Object.keys(state.acceptedSections).forEach(k => state.acceptedSections[k] = false);

            showReviewModal();
        } catch (err) {
            console.error('[AmbientDoc] requestSoapGeneration failure:', err);
            alert('AI synthesis failure: ' + err.message);
        } finally {
            state.isProcessing = false;
            updateStatus('Scribe Ready', 'idle');
            renderWidgetState();
        }
    }

    // ── SOAP section formatters ───────────────────────────────────────────────

    function _buildSubjective(note) {
        const s = note.subjective;
        if (typeof s === 'string') return s;
        if (s && typeof s === 'object') {
            const parts = [];
            const cc = note.chief_complaint || s.chief_complaint;
            if (cc) parts.push('CC: ' + cc);
            if (s.hpi)            parts.push('HPI: ' + s.hpi);
            if (s.ros)            parts.push('ROS: ' + s.ros);
            if (s.pmh)            parts.push('PMH: ' + s.pmh);
            if (s.social_history) parts.push('Social Hx: ' + s.social_history);
            if (s.family_history) parts.push('Family Hx: ' + s.family_history);
            if (Array.isArray(s.medications) && s.medications.length)
                parts.push('Medications: ' + s.medications.join(', '));
            if (Array.isArray(s.allergies) && s.allergies.length)
                parts.push('Allergies: ' + s.allergies.join(', '));
            return parts.join('\n\n');
        }
        return '';
    }

    function _buildObjective(note) {
        const o = note.objective;
        if (typeof o === 'string') return o;
        if (o && typeof o === 'object') {
            const parts = [];
            if (o.vital_signs)        parts.push('Vitals: ' + o.vital_signs);
            if (o.physical_exam)      parts.push('Exam: ' + o.physical_exam);
            if (o.diagnostic_results) parts.push('Results: ' + o.diagnostic_results);
            return parts.join('\n\n');
        }
        return '';
    }

    function _buildAssessment(note) {
        const a = note.assessment;
        if (typeof a === 'string') return a;
        if (a && typeof a === 'object') {
            const parts = [];
            if (a.clinical_summary) parts.push(a.clinical_summary);
            if (Array.isArray(a.diagnoses) && a.diagnoses.length) {
                const dxLines = a.diagnoses.map((d, i) => {
                    const label = d.description || d.diagnosis || d;
                    const code  = d.icd10_code  || d.icd10    || '';
                    return `${i + 1}. ${label}${code ? ' (' + code + ')' : ''}`;
                });
                parts.push(dxLines.join('\n'));
            }
            return parts.join('\n\n');
        }
        return '';
    }

    function _buildPlan(note) {
        const p = note.plan;
        if (typeof p === 'string') return p;
        if (p && typeof p === 'object') {
            const parts = [];
            if (Array.isArray(p.treatments)  && p.treatments.length)
                parts.push('Treatment:\n' + p.treatments.map(t => '- ' + t).join('\n'));
            if (Array.isArray(p.medications) && p.medications.length)
                parts.push('Medications:\n' + p.medications.map(m => '- ' + (m.drug_name || m)).join('\n'));
            if (Array.isArray(p.orders)      && p.orders.length)
                parts.push('Orders:\n' + p.orders.map(o => '- ' + o).join('\n'));
            if (Array.isArray(p.referrals)   && p.referrals.length)
                parts.push('Referrals:\n' + p.referrals.map(r => '- ' + r).join('\n'));
            if (p.follow_up)         parts.push('Follow-up: ' + p.follow_up);
            if (p.patient_education) parts.push('Patient Education: ' + p.patient_education);
            return parts.join('\n\n');
        }
        return '';
    }

    // ── Pre-Visit Intelligence Panel Builder ──────────────────────────────────

    /**
     * Parse a numeric value that may arrive as a number, string, or "None".
     * Returns null when the value is meaningless.
     */
    function _numVal(v) {
        if (v === null || v === undefined || v === 'None' || v === '') return null;
        const n = parseFloat(v);
        return isNaN(n) ? null : n;
    }

    /**
     * Compare two numeric vital values.
     * Returns 'up', 'down', or 'same' (null if either value is unavailable).
     */
    function _compareNum(pastRaw, newRaw) {
        const p = _numVal(pastRaw);
        const n = _numVal(newRaw);
        if (p === null || n === null) return null;
        if (n > p) return 'up';
        if (n < p) return 'down';
        return 'same';
    }

    /**
     * Build one row of the vitals comparison table.
     * trend: 'up' | 'down' | 'same' | null
     * isAlerted: true → paint the row red
     */
    function _cmpRow(label, pastDisplay, newDisplay, trend, isAlerted) {
        const row = document.createElement('div');
        row.className = 'pvi-cmp-row' + (isAlerted ? ' pvi-cmp-alert' : '');

        const trendIcon = trend === 'up'   ? '<span class="pvi-trend pvi-trend-up">▲</span>'
                        : trend === 'down' ? '<span class="pvi-trend pvi-trend-down">▼</span>'
                        : trend === 'same' ? '<span class="pvi-trend pvi-trend-same">→</span>'
                        : '';

        row.innerHTML = '<span class="pvi-cmp-label">' + _esc(label) + '</span>'
            + '<span class="pvi-cmp-past">'    + (pastDisplay ? _esc(pastDisplay) : '—') + '</span>'
            + '<span class="pvi-cmp-present">' + (newDisplay  ? _esc(newDisplay)  : '—') + '</span>'
            + '<span class="pvi-cmp-trend">'   + trendIcon + '</span>';
        return row;
    }

    function _buildPreVisitPanel(cs) {
        const panel = document.createElement('div');
        panel.className = 'pvi-panel';

        // Collapsible header
        const header = document.createElement('div');
        header.className = 'pvi-header';
        header.innerHTML = '<span class="pvi-title">⚡ Pre-Visit Intelligence</span>'
            + '<span class="pvi-toggle-icon">▼</span>';
        let collapsed = false;
        header.onclick = () => {
            collapsed = !collapsed;
            body.style.display = collapsed ? 'none' : 'grid';
            header.querySelector('.pvi-toggle-icon').innerText = collapsed ? '▶' : '▼';
        };
        panel.appendChild(header);

        const body = document.createElement('div');
        body.className = 'pvi-body';
        panel.appendChild(body);

        const pastV    = cs.current_vitals; // "current" in the API = baseline / prior reading
        const presentV = cs.new_vitals;     // "new" in the API = current encounter reading

        // ── 1. Vitals Comparison (when both past and present exist) ───────────
        if (pastV && presentV) {
            // Determine if any critical elevation exists
            const bpSysTrend  = _compareNum(pastV.blood_pressure?.systolic,    presentV.blood_pressure?.systolic);
            const bpDiaTrend  = _compareNum(pastV.blood_pressure?.diastolic,  presentV.blood_pressure?.diastolic);
            const pulseTrend  = _compareNum(pastV.pulse?.value,               presentV.pulse?.value);
            const respTrend   = _compareNum(pastV.respiration?.value,         presentV.respiration?.value);
            // temperature / oxygen_saturation can be null objects in new_vitals
            const tempTrend   = _compareNum(pastV.temperature?.value,         presentV.temperature?.value         ?? null);
            const o2Trend     = _compareNum(pastV.oxygen_saturation?.value,   presentV.oxygen_saturation?.value   ?? null);
            // O₂ sat: lower is worse; flag it as "up" concern when it goes DOWN
            const o2Alert     = o2Trend === 'down';

            const bpElevated  = bpSysTrend === 'up' || bpDiaTrend === 'up';
            const hasAnyAlert = bpElevated || o2Alert
                             || tempTrend === 'up' || pulseTrend === 'up';

            const subtitle = hasAnyAlert ? '⚠ Abnormal changes detected' : 'No significant changes';
            const card = _pviCard('📊 Vitals Comparison — Past vs Present', subtitle);
            if (hasAnyAlert) card.classList.add('pvi-card-alert');

            // Optional banner for BP spike
            if (bpElevated) {
                const banner = document.createElement('div');
                banner.className = 'pvi-alert-banner';
                const newBP = presentV.blood_pressure?.display || '—';
                const oldBP = pastV.blood_pressure?.display    || '—';
                banner.innerHTML = '🚨 <strong>Elevated Blood Pressure</strong>: '
                    + 'Past <strong>' + _esc(oldBP) + '</strong> → Present <strong>' + _esc(newBP) + '</strong> '
                    + '<span class="pvi-flag pvi-flag-abnormal">' + _esc(presentV.blood_pressure?.flag || 'HIGH') + '</span>';
                card.appendChild(banner);
            }
            if (o2Alert) {
                const banner = document.createElement('div');
                banner.className = 'pvi-alert-banner pvi-alert-banner-o2';
                const newO2  = _numVal(presentV.oxygen_saturation?.value);
                const oldO2  = _numVal(pastV.oxygen_saturation?.value);
                banner.innerHTML = '⚠️ <strong>O₂ Saturation Drop</strong>: '
                    + (oldO2 !== null ? 'Past <strong>' + oldO2 + '%</strong> → ' : '')
                    + 'Present <strong>' + (newO2 !== null ? newO2 + '%' : '—') + '</strong> '
                    + '<span class="pvi-flag pvi-flag-abnormal">' + _esc(presentV.oxygen_saturation?.flag || 'LOW') + '</span>';
                card.appendChild(banner);
            }

            // Column headers
            const colHdr = document.createElement('div');
            colHdr.className = 'pvi-cmp-row pvi-cmp-header';
            colHdr.innerHTML = '<span class="pvi-cmp-label">Vital</span>'
                + '<span class="pvi-cmp-past">Past</span>'
                + '<span class="pvi-cmp-present">Present</span>'
                + '<span class="pvi-cmp-trend">△</span>';
            card.appendChild(colHdr);

            // ── Blood Pressure (always show — both sets always have it) ─────────
            const pastBpDisp    = pastV.blood_pressure?.display
                                  ? pastV.blood_pressure.display + ' ' + (pastV.blood_pressure.unit || 'mmHg')
                                  : null;
            const presentBpDisp = presentV.blood_pressure?.display
                                  ? presentV.blood_pressure.display + ' ' + (presentV.blood_pressure.unit || 'mmHg')
                                  : null;
            // Append flag badge to present BP value so severity is visible inline
            const presentBpFlag = presentV.blood_pressure?.flag
                                  ? ' [' + presentV.blood_pressure.flag + ']' : '';
            card.appendChild(_cmpRow(
                'Blood Pressure',
                pastBpDisp,
                presentBpDisp ? presentBpDisp + presentBpFlag : null,
                bpSysTrend,
                bpElevated
            ));

            // ── Pulse ─────────────────────────────────────────────────────────
            const pPulse = _numVal(pastV.pulse?.value);
            const nPulse = _numVal(presentV.pulse?.value);
            if (pPulse !== null || nPulse !== null) {
                card.appendChild(_cmpRow(
                    'Pulse',
                    pPulse !== null ? pPulse + ' ' + (pastV.pulse?.unit    || 'bpm') : null,
                    nPulse !== null ? nPulse + ' ' + (presentV.pulse?.unit || 'bpm') : null,
                    pulseTrend,
                    false
                ));
            }

            // ── Respiration ───────────────────────────────────────────────────
            const pResp = _numVal(pastV.respiration?.value);
            const nResp = _numVal(presentV.respiration?.value);
            if (pResp !== null || nResp !== null) {
                card.appendChild(_cmpRow(
                    'Respiration',
                    pResp !== null ? pResp + ' ' + (pastV.respiration?.unit    || 'br/min') : null,
                    nResp !== null ? nResp + ' ' + (presentV.respiration?.unit || 'br/min') : null,
                    respTrend,
                    false
                ));
            }

            // ── Temperature (new_vitals.temperature may be null) ──────────────
            const pTemp = _numVal(pastV.temperature?.value);
            const nTemp = presentV.temperature ? _numVal(presentV.temperature?.value) : null;
            if (pTemp !== null || nTemp !== null) {
                card.appendChild(_cmpRow(
                    'Temperature',
                    pTemp !== null ? pTemp + ' ' + (pastV.temperature?.unit || '°F') : null,
                    nTemp !== null ? nTemp + ' ' + (presentV.temperature?.unit || '°F') : null,
                    tempTrend,
                    tempTrend === 'up'
                ));
            }

            // ── O₂ Saturation (new_vitals.oxygen_saturation may be null) ─────
            const pO2 = _numVal(pastV.oxygen_saturation?.value);
            const nO2 = presentV.oxygen_saturation ? _numVal(presentV.oxygen_saturation?.value) : null;
            if (pO2 !== null || nO2 !== null
                    || pastV.oxygen_saturation?.flag === 'NOT RECORDED') {
                card.appendChild(_cmpRow(
                    'O₂ Saturation',
                    pO2 !== null ? pO2 + '%'
                        : (pastV.oxygen_saturation?.flag === 'NOT RECORDED' ? 'Not recorded' : null),
                    nO2 !== null ? nO2 + ' ' + (presentV.oxygen_saturation?.unit || '%') : null,
                    o2Trend,
                    o2Alert
                ));
            }

            // ── Weight (new_vitals.weight may be null) ────────────────────────
            const pWt = _numVal(pastV.weight?.value);
            const nWt = presentV.weight ? _numVal(presentV.weight?.value ?? presentV.weight) : null;
            if (pWt !== null || nWt !== null) {
                card.appendChild(_cmpRow(
                    'Weight',
                    pWt !== null ? pWt + ' ' + (pastV.weight?.unit || 'lbs') : null,
                    nWt !== null ? nWt + ' lbs' : null,
                    _compareNum(pWt, nWt),
                    false
                ));
            }

            // ── BMI (past only, AI doesn't recalculate) ───────────────────────
            const pBmi = _numVal(pastV.bmi?.value);
            if (pBmi !== null) {
                const bmiLabel = pastV.bmi?.status ? pBmi + ' (' + pastV.bmi.status + ')' : String(pBmi);
                card.appendChild(_cmpRow('BMI', bmiLabel, null, null, pastV.bmi?.flag === 'HIGH'));
            }

            body.appendChild(card);

        } else if (pastV) {
            // ── Fallback: only past vitals available ──────────────────────────
            const card = _pviCard('🩺 Past Vitals', pastV.captured_at ? 'Captured: ' + pastV.captured_at : '');
            const rows = [
                _vitalRow('Blood Pressure', pastV.blood_pressure?.display,
                    pastV.blood_pressure?.unit || 'mmHg', pastV.blood_pressure?.flag),
                _vitalRow('Pulse',          pastV.pulse?.value,
                    pastV.pulse?.unit || 'bpm',           pastV.pulse?.flag),
                _vitalRow('Respiration',    pastV.respiration?.value,
                    pastV.respiration?.unit || 'breaths/min', pastV.respiration?.flag),
                _vitalRow('Temperature',    pastV.temperature?.value,
                    pastV.temperature?.unit || '°F',      pastV.temperature?.flag),
                _vitalRow('O₂ Saturation',  pastV.oxygen_saturation?.value,
                    pastV.oxygen_saturation?.unit || '%', pastV.oxygen_saturation?.flag),
                _vitalRow('Height',         pastV.height?.value,        pastV.height?.unit  || 'in'),
                _vitalRow('Weight',         pastV.weight?.value,        pastV.weight?.unit  || 'lbs'),
                _vitalRow('BMI',
                    pastV.bmi?.value + (pastV.bmi?.status ? ' (' + pastV.bmi.status + ')' : ''),
                    '', pastV.bmi?.flag),
            ];
            rows.forEach(r => { if (r) card.appendChild(r); });
            body.appendChild(card);

        } else if (presentV) {
            // ── Fallback: only present vitals available ───────────────────────
            const card = _pviCard('🩺 Present Vitals', '');
            const rows = [
                _vitalRow('Blood Pressure', presentV.blood_pressure?.display,
                    presentV.blood_pressure?.unit || 'mmHg', presentV.blood_pressure?.flag),
                _vitalRow('Pulse',          presentV.pulse?.value,
                    presentV.pulse?.unit || 'bpm',           presentV.pulse?.flag),
                _vitalRow('Respiration',    presentV.respiration?.value,
                    presentV.respiration?.unit || 'breaths/min', presentV.respiration?.flag),
                _vitalRow('Temperature',    presentV.temperature?.value,
                    presentV.temperature?.unit || '°F',      presentV.temperature?.flag),
                _vitalRow('O₂ Saturation',  presentV.oxygen_saturation?.value,
                    presentV.oxygen_saturation?.unit || '%', presentV.oxygen_saturation?.flag),
            ];
            rows.forEach(r => { if (r) card.appendChild(r); });
            body.appendChild(card);
        }

        // ── 1b. DB Vitals Trend — last 2 readings from the database ──────────
        const dbV = (typeof SYD !== 'undefined' && SYD.dbVitals) ? SYD.dbVitals : null;
        if (dbV && dbV.latest) {
            const prev   = dbV.previous;  // may be null if only 1 reading on record
            const latest = dbV.latest;

            // Format date helper (YYYY-MM-DD HH:MM:SS → "Mon DD")
            function _fmtDate(d) {
                if (!d) return '';
                const dt = new Date(d.replace(' ', 'T'));
                return isNaN(dt) ? String(d).slice(0, 10)
                    : dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }

            const prevDate   = _fmtDate(prev   && prev.date);
            const latestDate = _fmtDate(latest.date);

            const subtitle = prev
                ? 'Previous (' + prevDate + ') → Latest (' + latestDate + ')'
                : 'Latest reading: ' + latestDate;
            const dbCard = _pviCard('📈 Vitals Trend — DB Comparison', subtitle);

            // Column header row
            const colHdr = document.createElement('div');
            colHdr.className = 'pvi-cmp-row pvi-cmp-header';
            colHdr.innerHTML = '<span class="pvi-cmp-label">Vital</span>'
                + '<span class="pvi-cmp-past">'    + (prev ? 'Previous' : '—') + '</span>'
                + '<span class="pvi-cmp-present">Latest</span>'
                + '<span class="pvi-cmp-trend">Δ</span>';
            dbCard.appendChild(colHdr);

            // Helper: compute delta string  e.g. "+12" / "–5" / "—"
            function _delta(pRaw, nRaw) {
                const p = _numVal(pRaw), n = _numVal(nRaw);
                if (p === null || n === null) return null;
                const d = Math.round((n - p) * 10) / 10;
                return (d > 0 ? '+' : '') + d;
            }

            // Blood Pressure
            const latBP  = latest.bps && latest.bpd ? latest.bps + '/' + latest.bpd : null;
            const prevBP  = prev && prev.bps && prev.bpd ? prev.bps + '/' + prev.bpd : null;
            const bpSysTr = _compareNum(prev && prev.bps, latest.bps);
            const bpDiaTr = _compareNum(prev && prev.bpd, latest.bpd);
            const dbBpTrend   = (bpSysTr === 'up' || bpDiaTr === 'up')   ? 'up'
                              : (bpSysTr === 'down' && bpDiaTr === 'down') ? 'down'
                              : (bpSysTr === 'same' && bpDiaTr === 'same') ? 'same' : null;
            const dbBpAlert   = _numVal(latest.bps) >= 130;
            const bpDeltaStr  = prev ? (_delta(prev.bps, latest.bps) !== null
                ? _delta(prev.bps, latest.bps) + '/' + _delta(prev.bpd, latest.bpd) + ' mmHg' : '—') : '—';
            const bpRow = _cmpRow(
                'Blood Pressure',
                prevBP  ? prevBP  + ' mmHg' : (prev ? '—' : null),
                latBP   ? latBP   + ' mmHg' : '—',
                dbBpTrend,
                dbBpAlert
            );
            // Append delta chip
            if (bpDeltaStr !== '—' && prev) {
                const chip = document.createElement('span');
                chip.className = 'pvi-delta' + (dbBpTrend === 'up' ? ' pvi-delta-up' : dbBpTrend === 'down' ? ' pvi-delta-down' : '');
                chip.textContent = bpDeltaStr;
                bpRow.querySelector('.pvi-cmp-trend').appendChild(chip);
            }
            dbCard.appendChild(bpRow);

            // Pulse
            const pulseTr = _compareNum(prev && prev.pulse, latest.pulse);
            const pulseRow = _cmpRow(
                'Pulse',
                prev ? (_numVal(prev.pulse) !== null ? prev.pulse + ' bpm' : '—') : null,
                _numVal(latest.pulse) !== null ? latest.pulse + ' bpm' : '—',
                pulseTr, false
            );
            if (prev && _delta(prev.pulse, latest.pulse) !== null) {
                const chip = document.createElement('span');
                chip.className = 'pvi-delta' + (pulseTr === 'up' ? ' pvi-delta-up' : pulseTr === 'down' ? ' pvi-delta-down' : '');
                chip.textContent = _delta(prev.pulse, latest.pulse) + ' bpm';
                pulseRow.querySelector('.pvi-cmp-trend').appendChild(chip);
            }
            dbCard.appendChild(pulseRow);

            // Temperature
            const tempTr = _compareNum(prev && prev.temperature, latest.temperature);
            const tempRow = _cmpRow(
                'Temperature',
                prev ? (_numVal(prev.temperature) !== null ? prev.temperature + ' °F' : '—') : null,
                _numVal(latest.temperature) !== null ? latest.temperature + ' °F' : '—',
                tempTr, false
            );
            if (prev && _delta(prev.temperature, latest.temperature) !== null) {
                const chip = document.createElement('span');
                chip.className = 'pvi-delta' + (tempTr === 'up' ? ' pvi-delta-up' : tempTr === 'down' ? ' pvi-delta-down' : '');
                chip.textContent = _delta(prev.temperature, latest.temperature) + ' °F';
                tempRow.querySelector('.pvi-cmp-trend').appendChild(chip);
            }
            dbCard.appendChild(tempRow);

            // Respiration
            const respTr = _compareNum(prev && prev.respiration, latest.respiration);
            const respRow = _cmpRow(
                'Respiration',
                prev ? (_numVal(prev.respiration) !== null ? prev.respiration + ' br/min' : '—') : null,
                _numVal(latest.respiration) !== null ? latest.respiration + ' br/min' : '—',
                respTr, false
            );
            if (prev && _delta(prev.respiration, latest.respiration) !== null) {
                const chip = document.createElement('span');
                chip.className = 'pvi-delta' + (respTr === 'up' ? ' pvi-delta-up' : respTr === 'down' ? ' pvi-delta-down' : '');
                chip.textContent = _delta(prev.respiration, latest.respiration) + ' br/min';
                respRow.querySelector('.pvi-cmp-trend').appendChild(chip);
            }
            dbCard.appendChild(respRow);

            // O₂ Saturation
            const o2Tr    = _compareNum(prev && prev.oxygen_saturation, latest.oxygen_saturation);
            const o2Alert = _numVal(latest.oxygen_saturation) !== null && _numVal(latest.oxygen_saturation) < 95;
            const o2Row   = _cmpRow(
                'O₂ Saturation',
                prev ? (_numVal(prev.oxygen_saturation) !== null ? prev.oxygen_saturation + ' %' : '—') : null,
                _numVal(latest.oxygen_saturation) !== null ? latest.oxygen_saturation + ' %' : '—',
                o2Tr, o2Alert
            );
            if (prev && _delta(prev.oxygen_saturation, latest.oxygen_saturation) !== null) {
                const chip = document.createElement('span');
                chip.className = 'pvi-delta' + (o2Tr === 'down' ? ' pvi-delta-down' : o2Tr === 'up' ? ' pvi-delta-up' : '');
                chip.textContent = _delta(prev.oxygen_saturation, latest.oxygen_saturation) + ' %';
                o2Row.querySelector('.pvi-cmp-trend').appendChild(chip);
            }
            dbCard.appendChild(o2Row);

            // Weight
            const wtTr  = _compareNum(prev && prev.weight, latest.weight);
            const wtRow = _cmpRow(
                'Weight',
                prev ? (_numVal(prev.weight) !== null ? prev.weight + ' lbs' : '—') : null,
                _numVal(latest.weight) !== null ? latest.weight + ' lbs' : '—',
                wtTr, false
            );
            if (prev && _delta(prev.weight, latest.weight) !== null) {
                const chip = document.createElement('span');
                chip.className = 'pvi-delta' + (wtTr === 'up' ? ' pvi-delta-up' : wtTr === 'down' ? ' pvi-delta-down' : '');
                chip.textContent = _delta(prev.weight, latest.weight) + ' lbs';
                wtRow.querySelector('.pvi-cmp-trend').appendChild(chip);
            }
            dbCard.appendChild(wtRow);

            // BMI
            const bmiTr  = _compareNum(prev && prev.BMI, latest.BMI);
            const bmiAlert = _numVal(latest.BMI) >= 30;
            const bmiRow = _cmpRow(
                'BMI',
                prev ? (_numVal(prev.BMI) !== null ? String(prev.BMI) : '—') : null,
                _numVal(latest.BMI) !== null ? String(latest.BMI) : '—',
                bmiTr, bmiAlert
            );
            if (prev && _delta(prev.BMI, latest.BMI) !== null) {
                const chip = document.createElement('span');
                chip.className = 'pvi-delta' + (bmiTr === 'up' ? ' pvi-delta-up' : bmiTr === 'down' ? ' pvi-delta-down' : '');
                chip.textContent = _delta(prev.BMI, latest.BMI);
                bmiRow.querySelector('.pvi-cmp-trend').appendChild(chip);
            }
            dbCard.appendChild(bmiRow);

            // Alert banner if any elevation detected
            const hasElevation = dbBpAlert || o2Alert || bmiAlert;
            if (hasElevation) {
                dbCard.classList.add('pvi-card-alert');
                const banner = document.createElement('div');
                banner.className = 'pvi-alert-banner';
                const alerts = [];
                if (dbBpAlert)  alerts.push('🚨 BP ' + (latBP || '') + ' mmHg — Elevated');
                if (o2Alert)    alerts.push('⚠️ O₂ ' + latest.oxygen_saturation + '% — Low');
                if (bmiAlert)   alerts.push('⚠️ BMI ' + latest.BMI + ' — Obese');
                banner.innerHTML = alerts.join(' &nbsp;|&nbsp; ');
                dbCard.insertBefore(banner, dbCard.firstChild.nextSibling); // after card header
            }

            body.appendChild(dbCard);
        }

        // ── 2. Complaints ─────────────────────────────────────────────────────
        const complaints = cs.complaints;
        if (complaints) {
            const card = _pviCard('💬 Chief Complaint & Symptoms', '');
            if (complaints.chief_complaint && complaints.chief_complaint !== 'None') {
                const cc = document.createElement('div');
                cc.className = 'pvi-item pvi-chief';
                cc.innerHTML = '<span class="pvi-label">Chief Complaint:</span> '
                    + '<span class="pvi-value">' + _esc(complaints.chief_complaint) + '</span>';
                card.appendChild(cc);
            }
            if (complaints.pain_scale !== null && complaints.pain_scale !== undefined
                    && complaints.pain_scale !== 'None') {
                const ps = document.createElement('div');
                ps.className = 'pvi-item';
                ps.innerHTML = '<span class="pvi-label">Pain Scale:</span> '
                    + '<span class="pvi-value">' + _esc(String(complaints.pain_scale)) + '/10</span>';
                card.appendChild(ps);
            }
            if (complaints.patient_symptoms && complaints.patient_symptoms.length) {
                const sym = document.createElement('div');
                sym.className = 'pvi-item';
                sym.innerHTML = '<span class="pvi-label">Symptoms:</span> '
                    + '<span class="pvi-value">' + complaints.patient_symptoms.map(_esc).join(', ') + '</span>';
                card.appendChild(sym);
            }
            if (complaints.source) {
                const src = document.createElement('div');
                src.className = 'pvi-item pvi-dim';
                src.innerHTML = '<span class="pvi-label">Source:</span> '
                    + '<span class="pvi-value pvi-dim">' + _esc(complaints.source) + '</span>';
                card.appendChild(src);
            }
            body.appendChild(card);
        }

        // ── 3. Past Problem List ──────────────────────────────────────────────
        const ppl = cs.past_problem_list;
        if (ppl && (ppl.total > 0 || (ppl.active && ppl.active.length))) {
            const card = _pviCard('📋 Past Problem List',
                ppl.total ? ppl.total + ' problem(s)' : '');
            const active = ppl.active || [];
            if (active.length) {
                active.forEach(p => {
                    const row = document.createElement('div');
                    row.className = 'pvi-item';
                    const icd = p.icd10 && p.icd10 !== 'None'
                        ? ' <span class="pvi-code">' + _esc(p.icd10) + '</span>' : '';
                    const yr  = p.onset_year && p.onset_year !== 'None'
                        ? ' <span class="pvi-meta">since ' + p.onset_year + '</span>' : '';
                    row.innerHTML = '<span class="pvi-badge pvi-badge-active">active</span> '
                        + '<span class="pvi-value">' + _esc(p.display) + '</span>' + icd + yr;
                    card.appendChild(row);
                });
            }
            const resolved = ppl.resolved || [];
            if (resolved.length) {
                resolved.forEach(p => {
                    const row = document.createElement('div');
                    row.className = 'pvi-item';
                    row.innerHTML = '<span class="pvi-badge pvi-badge-resolved">resolved</span> '
                        + '<span class="pvi-value pvi-dim">' + _esc(p.display) + '</span>';
                    card.appendChild(row);
                });
            }
            body.appendChild(card);
        }

        // ── 4. Medications ────────────────────────────────────────────────────
        const med = cs.medicine;
        if (med) {
            const fromPlan     = med.from_plan     || [];
            const currentMeds  = med.current_meds  || [];
            const allMeds      = [...fromPlan, ...currentMeds];
            const card = _pviCard('💊 Medications',
                med.total > 0 ? med.total + ' medication(s)' : (allMeds.length ? allMeds.length + ' medication(s)' : ''));
            if (allMeds.length) {
                allMeds.forEach(m => {
                    const row = document.createElement('div');
                    row.className = 'pvi-item';
                    const name = m.drug_name || m.name || (typeof m === 'string' ? m : null);
                    const dose = m.dosage || m.dose || m.sig || '';
                    if (!name) return;
                    row.innerHTML = '<span class="pvi-badge pvi-badge-med">Rx</span> '
                        + '<span class="pvi-value">' + _esc(name) + '</span>'
                        + (dose ? ' <span class="pvi-meta">' + _esc(dose) + '</span>' : '');
                    card.appendChild(row);
                });
            } else {
                const empty = document.createElement('div');
                empty.className = 'pvi-item pvi-dim';
                empty.innerText = 'No medications recorded.';
                card.appendChild(empty);
            }
            body.appendChild(card);
        }

        // ── 5. Drug Interaction Alerts ────────────────────────────────────────
        const di = cs.drug_interaction;
        if (di) {
            const d            = di.data || {};
            const alertCount   = di.active_alerts_count ?? 0;
            const safetyLevel  = (d.overall_safety_level || '').toUpperCase();
            const isSafe       = safetyLevel === 'SAFE';

            const cardSubtitle = isSafe
                ? '✅ No active alerts'
                : '⚠ ' + alertCount + ' active alert' + (alertCount !== 1 ? 's' : '');
            const card = _pviCard('💊 Drug Alerts', cardSubtitle);
            if (!isSafe && alertCount > 0) card.classList.add('pvi-card-alert');

            // Safety level summary bar
            const safetyBar = document.createElement('div');
            safetyBar.className = 'pvi-da-summary';
            safetyBar.innerHTML = (isSafe ? '🛡️' : '⚠️') + ' <strong>' + _esc(d.overall_safety_level || '') + '</strong>'
                + (d.report_confidence
                    ? ' <span class="pvi-meta" style="margin-left:auto;">Confidence: '
                      + Math.round(d.report_confidence * 100) + '%</span>'
                    : '');
            card.appendChild(safetyBar);

            if (d.summary) {
                const sumEl = document.createElement('div');
                sumEl.className = 'pvi-da-text pvi-dim';
                sumEl.style.padding = '4px 12px 6px';
                sumEl.style.fontSize = '11px';
                sumEl.innerText = d.summary;
                card.appendChild(sumEl);
            }

            // Helper: severity class/icon
            function _daSevClass(sev) {
                const s = (sev || '').toUpperCase();
                if (s === 'HIGH' || s === 'CONTRAINDICATED') return 'pvi-da-danger';
                if (s === 'MODERATE') return 'pvi-da-warn';
                return '';
            }
            function _daSevIcon(sev) {
                const s = (sev || '').toUpperCase();
                if (s === 'HIGH' || s === 'CONTRAINDICATED') return '🚨';
                if (s === 'MODERATE') return '⚠️';
                return 'ℹ️';
            }

            // Drug-Drug Interactions
            const ddis = d.drug_drug_interactions || [];
            if (ddis.length) {
                const secLbl = document.createElement('div');
                secLbl.className = 'pvi-da-section-label';
                secLbl.innerText = 'Drug-Drug Interactions';
                card.appendChild(secLbl);

                const seen = new Set();
                ddis.forEach(ix => {
                    const key = (ix.drug_a || '') + '|' + (ix.drug_b || '') + '|' + (ix.mechanism || '');
                    if (seen.has(key)) return;
                    seen.add(key);
                    const row = document.createElement('div');
                    row.className = 'pvi-da-row ' + _daSevClass(ix.severity);
                    row.innerHTML = '<span class="pvi-da-icon">' + _daSevIcon(ix.severity) + '</span>'
                        + '<div class="pvi-da-body">'
                        + '<div class="pvi-da-title">' + _esc(ix.drug_a) + ' + ' + _esc(ix.drug_b)
                        + ' <span class="pvi-da-sev">' + _esc(ix.severity || '') + '</span></div>'
                        + (ix.clinical_effect  ? '<div class="pvi-da-text"><strong>Effect:</strong> '    + _esc(ix.clinical_effect)       + '</div>' : '')
                        + (ix.mechanism        ? '<div class="pvi-da-text"><strong>Mechanism:</strong> ' + _esc(ix.mechanism)             + '</div>' : '')
                        + (ix.recommendation   ? '<div class="pvi-da-text pvi-da-rec">'                  + _esc(ix.recommendation)        + '</div>' : '')
                        + '</div>';
                    card.appendChild(row);
                });
            }

            // Allergy Alerts
            const allergies = d.allergy_alerts || [];
            if (allergies.length) {
                const secLbl = document.createElement('div');
                secLbl.className = 'pvi-da-section-label';
                secLbl.innerText = 'Allergy Alerts';
                card.appendChild(secLbl);
                allergies.forEach(al => {
                    const row = document.createElement('div');
                    row.className = 'pvi-da-row ' + _daSevClass(al.severity);
                    row.innerHTML = '<span class="pvi-da-icon">' + _daSevIcon(al.severity) + '</span>'
                        + '<div class="pvi-da-body">'
                        + '<div class="pvi-da-title">' + _esc(al.drug) + ' × ' + _esc(al.allergen_matched || '')
                        + ' <span class="pvi-da-sev">' + _esc(al.severity || '') + '</span></div>'
                        + (al.reaction_type  ? '<div class="pvi-da-text"><strong>Reaction:</strong> '    + _esc(al.reaction_type)  + '</div>' : '')
                        + (al.recommendation ? '<div class="pvi-da-text pvi-da-rec">'                    + _esc(al.recommendation) + '</div>' : '')
                        + '</div>';
                    card.appendChild(row);
                });
            }

            // Dose Warnings
            const doseWarnings = d.dose_warnings || [];
            if (doseWarnings.length) {
                const secLbl = document.createElement('div');
                secLbl.className = 'pvi-da-section-label';
                secLbl.innerText = 'Dose Check';
                card.appendChild(secLbl);
                doseWarnings.forEach(dw => {
                    const ok = dw.status === 'WITHIN_RANGE';
                    const row = document.createElement('div');
                    row.className = 'pvi-da-row ' + (ok ? '' : 'pvi-da-warn');
                    row.innerHTML = '<span class="pvi-da-icon">' + (ok ? '✅' : '⚠️') + '</span>'
                        + '<div class="pvi-da-body">'
                        + '<div class="pvi-da-title">' + _esc(dw.drug || '')
                        + ' <span class="pvi-da-sev">' + _esc((dw.status || '').replace(/_/g, ' ')) + '</span></div>'
                        + '<div class="pvi-da-text"><strong>Prescribed:</strong> ' + _esc(dw.prescribed_dose || '—')
                        + ' <span class="pvi-meta">(range: ' + _esc(dw.standard_range || '—') + ')</span></div>'
                        + (dw.toxicity_profile ? '<div class="pvi-da-text pvi-da-rec">' + _esc(dw.toxicity_profile) + '</div>' : '')
                        + '</div>';
                    card.appendChild(row);
                });
            }

            // Side Effects (summary only — keep it compact)
            const sideEffects = d.side_effects || [];
            if (sideEffects.length) {
                const secLbl = document.createElement('div');
                secLbl.className = 'pvi-da-section-label';
                secLbl.innerText = 'Side Effect Alerts';
                card.appendChild(secLbl);
                sideEffects.forEach(se => {
                    const row = document.createElement('div');
                    row.className = 'pvi-da-row pvi-da-info';
                    const serious = se.serious && se.serious.length ? se.serious.join(', ') : '';
                    row.innerHTML = '<span class="pvi-da-icon">💊</span>'
                        + '<div class="pvi-da-body">'
                        + '<div class="pvi-da-title">' + _esc(se.drug || '') + '</div>'
                        + (se.boxed_warning ? '<div class="pvi-da-text" style="color:#721c24;font-weight:700;">⬛ ' + _esc(se.boxed_warning) + '</div>' : '')
                        + (serious          ? '<div class="pvi-da-text"><strong>Serious:</strong> ' + _esc(serious) + '</div>' : '')
                        + '</div>';
                    card.appendChild(row);
                });
            }

            if (!ddis.length && !allergies.length && !doseWarnings.length && !sideEffects.length) {
                const ok = document.createElement('div');
                ok.className = 'pvi-item pvi-dim';
                ok.innerHTML = '✅ No drug interactions or alerts detected.';
                card.appendChild(ok);
            }

            body.appendChild(card);
        }

        // ── 6. Negative Lab Results ───────────────────────────────────────────
        const labs = cs.negative_lab_results;
        const labsFromRecords = labs?.from_records    || [];
        const labsFromSoap    = labs?.from_soap_text  || [];
        if (labsFromRecords.length || labsFromSoap.length) {
            const card = _pviCard('🧪 Negative Lab Results', (labs.total || 0) + ' result(s)');
            [...labsFromRecords, ...labsFromSoap].forEach(l => {
                const row = document.createElement('div');
                row.className = 'pvi-item';
                const name = l.test || l.name || l;
                const val  = l.result !== undefined ? ' — ' + l.result : '';
                row.innerHTML = '<span class="pvi-badge pvi-badge-normal">NEG</span> '
                    + '<span class="pvi-value">' + _esc(String(name)) + _esc(val) + '</span>';
                card.appendChild(row);
            });
            body.appendChild(card);
        } else if (labs) {
            const card = _pviCard('🧪 Negative Lab Results', '');
            const empty = document.createElement('div');
            empty.className = 'pvi-item pvi-dim';
            empty.innerText = 'No negative lab results on record.';
            card.appendChild(empty);
            body.appendChild(card);
        }

        return panel;
    }

    function _pviCard(title, subtitle) {
        const card = document.createElement('div');
        card.className = 'pvi-card';
        const hdr = document.createElement('div');
        hdr.className = 'pvi-card-header';
        hdr.innerHTML = '<span class="pvi-card-title">' + title + '</span>'
            + (subtitle ? '<span class="pvi-card-sub">' + _esc(subtitle) + '</span>' : '');
        card.appendChild(hdr);
        return card;
    }

    function _vitalRow(label, value, unit, flag) {
        if (value === null || value === undefined || value === '' || value === 0) {
            if (flag !== 'NOT RECORDED' && !flag) return null;
        }
        const row = document.createElement('div');
        row.className = 'pvi-vital-row';
        const flagClass = flag
            ? (flag === 'NORMAL' ? 'pvi-flag-normal'
               : flag === 'NOT RECORDED' ? 'pvi-flag-missing'
               : 'pvi-flag-abnormal')
            : '';
        const flagHtml = flag
            ? '<span class="pvi-flag ' + flagClass + '">' + _esc(flag) + '</span>'
            : '';
        const displayVal = (value !== null && value !== undefined && value !== '') ? _esc(String(value)) : '—';
        const unitHtml = unit ? '<span class="pvi-unit">' + _esc(unit) + '</span>' : '';
        row.innerHTML = '<span class="pvi-vital-label">' + _esc(label) + '</span>'
            + '<span class="pvi-vital-value">' + displayVal + ' ' + unitHtml + '</span>'
            + flagHtml;
        return row;
    }

    function _esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Advanced Review Modal Presentation Interface ──────────────────────────
    function showReviewModal() {
        if (reviewModalEl) reviewModalEl.remove();

        reviewModalEl = document.createElement('div');
        reviewModalEl.id = 'ambient-review-modal';
        injectModalStyles(reviewModalEl);

        const innerContainer = document.createElement('div');
        innerContainer.className = 'modal-content-wrapper';

        // Header Structure Layout
        const header = document.createElement('div');
        header.className = 'modal-header-block';
        header.innerHTML = `
            <h3>Clinical Document Audit Verification Panel</h3>
            <span class="session-badge">Session Ref: ${state.sessionId}</span>
        `;
        innerContainer.appendChild(header);

        // Body Elements Mapping
        const bodySpace = document.createElement('div');
        bodySpace.className = 'modal-body-scroll';

        // ── Pre-Visit Intelligence Panel ──────────────────────────────────────
        if (state.clinicalSummary) {
            bodySpace.appendChild(_buildPreVisitPanel(state.clinicalSummary));
        }

        const clinicalSections = ['subjective', 'objective', 'assessment', 'plan'];
        clinicalSections.forEach(secKey => {
            const rowBox = document.createElement('div');
            rowBox.className = `section-card-row ${state.acceptedSections[secKey] ? 'approved' : 'pending'}`;
            rowBox.id = `card-row-${secKey}`;

            // Segment Label Metadata Bar
            const bar = document.createElement('div');
            bar.className = 'section-meta-bar';
            bar.innerHTML = `
                <span class="section-label-text">${secKey.toUpperCase()}</span>
                <span class="status-pill" id="pill-${secKey}">${state.acceptedSections[secKey] ? 'Checked' : 'Awaiting Review'}</span>
            `;
            rowBox.appendChild(bar);

            // Flex Panel Setup: Left column (Interactive text Editor), Right column (Live highlight validation diff tracking window)
            const workspace = document.createElement('div');
            workspace.className = 'section-workspace-split';

            const editCol = document.createElement('div');
            editCol.className = 'workspace-column';
            const textarea = document.createElement('textarea');
            textarea.value = state.activeSoapData[secKey];
            textarea.oninput = (e) => {
                state.activeSoapData[secKey] = e.target.value;
                diffCol.innerHTML = computeInlineDiff(state.originalSoapData[secKey], e.target.value);
            };
            editCol.appendChild(textarea);

            const diffCol = document.createElement('div');
            diffCol.className = 'workspace-column diff-preview-box';
            diffCol.innerHTML = computeInlineDiff(state.originalSoapData[secKey], state.activeSoapData[secKey]);

            workspace.appendChild(editCol);
            workspace.appendChild(diffCol);
            rowBox.appendChild(workspace);

            // Local Confirmation Toolbar Controls
            const actionRow = document.createElement('div');
            actionRow.className = 'section-action-row';

            const toggleBtn = document.createElement('button');
            toggleBtn.className = `btn-action-toggle ${state.acceptedSections[secKey] ? 'btn-success' : 'btn-primary'}`;
            toggleBtn.innerText = state.acceptedSections[secKey] ? "↩️ Re-open Component" : "🎯 Confirm Component Accuracy";
            toggleBtn.onclick = () => {
                state.acceptedSections[secKey] = !state.acceptedSections[secKey];
                
                // Refresh UI rows dynamically
                const row = document.getElementById(`card-row-${secKey}`);
                const pill = document.getElementById(`pill-${secKey}`);
                if (state.acceptedSections[secKey]) {
                    row.className = "section-card-row approved";
                    pill.className = "status-pill approved";
                    pill.innerText = "Verified";
                    toggleBtn.innerText = "↩️ Re-open Component";
                    toggleBtn.className = "btn-action-toggle btn-success";
                } else {
                    row.className = "section-card-row pending";
                    pill.className = "status-pill";
                    pill.innerText = "Awaiting Review";
                    toggleBtn.innerText = "🎯 Confirm Component Accuracy";
                    toggleBtn.className = "btn-action-toggle btn-primary";
                }
            };

            actionRow.appendChild(toggleBtn);
            rowBox.appendChild(actionRow);
            bodySpace.appendChild(rowBox);
        });

        innerContainer.appendChild(bodySpace);

        // Global Modal Action Controls Footer Toolbar
        const footer = document.createElement('div');
        footer.className = 'modal-footer-toolbar';

        const abortBtn = document.createElement('button');
        abortBtn.className = 'btn-secondary';
        abortBtn.innerText = "Close and Abort Changes";
        abortBtn.onclick = () => { reviewModalEl.remove(); };

        const syncBtn = document.createElement('button');
        syncBtn.className = 'btn-commit-run';
        syncBtn.innerText = "Synchronize Verified Blocks to Chart Forms";
        syncBtn.onclick = () => {
            commitDataChangesToOpenEMRFields();
            reviewModalEl.remove();
        };

        footer.appendChild(abortBtn);
        footer.appendChild(syncBtn);
        innerContainer.appendChild(footer);
        reviewModalEl.appendChild(innerContainer);
        document.body.appendChild(reviewModalEl);
    }

    function commitDataChangesToOpenEMRFields() {
        let count = 0;
        for (const [key, selector] of Object.entries(CONFIG.SELECTORS)) {
            const inputField = document.querySelector(selector);
            if (inputField) {
                inputField.value = state.activeSoapData[key];
                inputField.dispatchEvent(new Event('input', { bubbles: true }));
                inputField.dispatchEvent(new Event('change', { bubbles: true }));
                count++;
            }
        }
        alert(`Integration Sync Complete. Successfully injected ${count} verified content blocks.`);
    }

    // ── Floating Widget DOM Construction and Rendering Loops ──────────────────
    function buildWidget() {
        widgetEl = document.createElement('div');
        widgetEl.id = 'ambient-doc-widget';
        injectWidgetStyles(widgetEl);

        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.gap = '10px';

        statusIndicatorEl = document.createElement('div');
        statusIndicatorEl.className = 'status-indicator-dot idle';

        statusTextEl = document.createElement('span');
        statusTextEl.className = 'status-label-string';
        statusTextEl.innerText = "Ambient Scribe Idle";

        mainBtnEl = document.createElement('button');
        mainBtnEl.className = 'scribe-trigger-btn c-start';
        mainBtnEl.innerText = "🔴 Start Scribe Session";

        mainBtnEl.onclick = () => {
            if (state.isProcessing) return;
            if (!state.isRecording) {
                startRecording();
            } else {
                stopRecording();
            }
        };

        row.appendChild(statusIndicatorEl);
        row.appendChild(statusTextEl);
        row.appendChild(mainBtnEl);
        widgetEl.appendChild(row);
        document.body.appendChild(widgetEl);
    }

    function updateStatus(msg, classification) {
        if (!statusTextEl || !statusIndicatorEl) return;
        statusTextEl.innerText = msg;
        statusIndicatorEl.className = `status-indicator-dot ${classification}`;
    }

    function renderWidgetState() {
        if (!mainBtnEl) return;
        if (state.isRecording) {
            mainBtnEl.innerText = "⏹️ Stop and Generate Note";
            mainBtnEl.className = "scribe-trigger-btn c-stop";
            mainBtnEl.disabled = false;
        } else if (state.isProcessing) {
            mainBtnEl.innerText = "🔄 Compiling Document...";
            mainBtnEl.className = "scribe-trigger-btn c-disabled";
            mainBtnEl.disabled = true;
        } else {
            mainBtnEl.innerText = "🔴 Start Scribe Session";
            mainBtnEl.className = "scribe-trigger-btn c-start";
            mainBtnEl.disabled = false;
        }
    }

    // ── Native Dynamic CSS Styling Injection Engines ───────────────────────────
    function injectWidgetStyles(el) {
        el.style.position = 'fixed';
        el.style.bottom = '24px';
        el.style.right = '24px';
        el.style.padding = '12px 18px';
        el.style.backgroundColor = '#ffffff';
        el.style.border = '1px solid #ced4da';
        el.style.borderRadius = '32px';
        el.style.boxShadow = '0 6px 20px rgba(0,0,0,0.12)';
        el.style.zIndex = '999999';
        el.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';

        const styleSheet = document.createElement("style");
        styleSheet.innerText = `
            .status-indicator-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
            .status-indicator-dot.idle { background-color: #6c757d; }
            .status-indicator-dot.info { background-color: #17a2b8; animation: pulse 1.5s infinite; }
            .status-indicator-dot.recording { background-color: #dc3545; animation: pulse 1.2s infinite; }
            .status-indicator-dot.processing { background-color: #ffc107; animation: pulse 1s infinite; }
            .status-label-string { font-size: 13px; color: #495057; font-weight: 500; min-width: 120px; }
            .scribe-trigger-btn { border: none; padding: 8px 16px; font-weight: 600; font-size: 13px; border-radius: 20px; cursor: pointer; transition: background 0.2s; }
            .scribe-trigger-btn.c-start { background-color: #007bff; color: white; }
            .scribe-trigger-btn.c-start:hover { background-color: #0056b3; }
            .scribe-trigger-btn.c-stop { background-color: #212529; color: white; }
            .scribe-trigger-btn.c-stop:hover { background-color: #000000; }
            .scribe-trigger-btn.c-disabled { background-color: #e9ecef; color: #adb5bd; cursor: not-allowed; }
            @keyframes pulse { 0% { opacity: 0.4; } 50% { opacity: 1; } 100% { opacity: 0.4; } }
        `;
        document.head.appendChild(styleSheet);
    }

    function injectModalStyles(el) {
        el.style.position = 'fixed';
        el.style.top = '0'; el.style.left = '0'; el.style.width = '100vw'; el.style.height = '100vh';
        el.style.backgroundColor = 'rgba(33, 37, 41, 0.5)';
        el.style.zIndex = '9999999';
        el.style.display = 'flex'; el.style.alignItems = 'center'; el.style.justifyContent = 'center';
        el.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

        const styleSheet = document.createElement("style");
        styleSheet.innerText = `
            .modal-content-wrapper { width: 85%; height: 85%; background: white; border-radius: 12px; display: flex; flex-direction: column; box-shadow: 0 15px 40px rgba(0,0,0,0.25); overflow: hidden; }
            .modal-header-block { padding: 18px 24px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
            .modal-header-block h3 { margin: 0; font-size: 18px; color: #212529; }
            .session-badge { font-size: 11px; background: #e2e3e5; padding: 4px 10px; border-radius: 12px; font-family: monospace; }
            .modal-body-scroll { flex: 1; padding: 24px; overflow-y: auto; background: #f1f3f5; }
            .section-card-row { background: white; border-radius: 8px; border: 1px solid #dee2e6; padding: 18px; margin-bottom: 20px; transition: border 0.2s; }
            .section-card-row.approved { border-left: 6px solid #28a745; background-color: #f8fff9; }
            .section-card-row.pending { border-left: 6px solid #007bff; }
            .section-meta-bar { display: flex; justify-content: space-between; margin-bottom: 12px; }
            .section-label-text { font-weight: 700; font-size: 14px; color: #495057; }
            .status-pill { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: #ffeeba; color: #856404; }
            .status-pill.approved { background: #d4edda; color: #155724; }
            .section-workspace-split { display: flex; gap: 16px; margin-bottom: 14px; }
            .workspace-column { flex: 1; min-height: 140px; }
            .workspace-column textarea { width: 100%; height: 100%; min-height: 140px; border: 1px solid #ced4da; border-radius: 6px; padding: 10px; font-size: 13px; font-family: inherit; resize: vertical; }
            .diff-preview-box { border: 1px solid #dee2e6; background: #fafafa; border-radius: 6px; padding: 10px; font-size: 13px; white-space: pre-wrap; overflow-y: auto; height: 140px; }
            .diff-inserted { color: #0056b3; background-color: #e6f2ff; font-weight: 500; }
            .section-action-row { text-align: right; }
            .btn-action-toggle { border: none; padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 4px; cursor: pointer; }
            .btn-action-toggle.btn-primary { background: #007bff; color: white; }
            .btn-action-toggle.btn-success { background: #6c757d; color: white; }
            .modal-footer-toolbar { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #dee2e6; text-align: right; }
            .modal-footer-toolbar button { border: none; padding: 10px 20px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; margin-left: 12px; }
            .modal-footer-toolbar .btn-secondary { background: #e2e3e5; color: #383d41; }
            .modal-footer-toolbar .btn-commit-run { background: #28a745; color: white; }
            /* ── Pre-Visit Intelligence ── */
            .pvi-panel { background: #fff; border-radius: 8px; border: 1px solid #b8d4f5; margin-bottom: 20px; overflow: hidden; }
            .pvi-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; background: linear-gradient(135deg, #e8f4fd, #dceeff); cursor: pointer; user-select: none; }
            .pvi-title { font-weight: 700; font-size: 14px; color: #1a5fa8; }
            .pvi-toggle-icon { font-size: 11px; color: #1a5fa8; }
            .pvi-body { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; padding: 16px; background: #f7fbff; }
            .pvi-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; }
            .pvi-card.pvi-card-alert { border-color: #f5c6cb; }
            .pvi-card-header { background: #f1f5fa; padding: 8px 12px; display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid #dee2e6; }
            .pvi-card.pvi-card-alert .pvi-card-header { background: #fff5f5; }
            .pvi-card-title { font-weight: 600; font-size: 13px; color: #212529; }
            .pvi-card-sub { font-size: 11px; color: #6c757d; }
            .pvi-card.pvi-card-alert .pvi-card-sub { color: #c0392b; font-weight: 600; }
            /* Alert banners inside a card */
            .pvi-alert-banner { margin: 8px 12px 4px; padding: 7px 10px; background: #fde8e8; border-left: 3px solid #e74c3c; border-radius: 4px; font-size: 12px; color: #922b21; }
            .pvi-alert-banner-o2 { background: #fff3cd; border-left-color: #f39c12; color: #7d6608; }
            /* Vitals comparison table */
            .pvi-cmp-row { display: grid; grid-template-columns: 1fr 1fr 1fr 36px; align-items: center; gap: 4px; padding: 5px 12px; border-bottom: 1px solid #f1f3f5; font-size: 12px; }
            .pvi-cmp-row:last-child { border-bottom: none; }
            .pvi-cmp-header { background: #f8f9fa; font-weight: 700; font-size: 11px; color: #6c757d; }
            .pvi-cmp-row.pvi-cmp-alert { background: #fff5f5; }
            .pvi-cmp-label { color: #6c757d; font-size: 12px; }
            .pvi-cmp-past { color: #6c757d; }
            .pvi-cmp-present { font-weight: 600; color: #212529; }
            .pvi-cmp-trend { text-align: center; font-weight: 700; font-size: 13px; }
            .pvi-trend { padding: 1px 4px; border-radius: 4px; font-size: 11px; font-weight: 700; }
            .pvi-trend-up   { background: #f8d7da; color: #721c24; }
            .pvi-trend-down { background: #d4edda; color: #155724; }
            .pvi-trend-same { background: #e9ecef; color: #495057; }
            /* Standard vital rows (single-set fallback) */
            .pvi-vital-row { display: flex; align-items: center; gap: 6px; padding: 5px 12px; border-bottom: 1px solid #f1f3f5; font-size: 13px; }
            .pvi-vital-row:last-child { border-bottom: none; }
            .pvi-vital-label { flex: 0 0 120px; color: #6c757d; font-size: 12px; }
            .pvi-vital-value { flex: 1; font-weight: 500; color: #212529; }
            .pvi-unit { font-size: 11px; color: #868e96; }
            .pvi-flag { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; white-space: nowrap; }
            .pvi-flag-normal { background: #d4edda; color: #155724; }
            .pvi-flag-abnormal { background: #f8d7da; color: #721c24; }
            .pvi-flag-missing { background: #e2e3e5; color: #383d41; }
            .pvi-item { padding: 6px 12px; border-bottom: 1px solid #f1f3f5; font-size: 13px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
            .pvi-item:last-child { border-bottom: none; }
            .pvi-item.pvi-chief { background: #fffbf0; }
            .pvi-label { font-weight: 600; color: #495057; font-size: 12px; flex: 0 0 auto; }
            .pvi-value { color: #212529; }
            .pvi-dim { color: #adb5bd; font-style: italic; }
            .pvi-code { font-size: 11px; background: #e9ecef; padding: 1px 5px; border-radius: 4px; font-family: monospace; color: #495057; }
            .pvi-meta { font-size: 11px; color: #868e96; }
            .pvi-badge { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; white-space: nowrap; }
            .pvi-badge-active   { background: #cce5ff; color: #004085; }
            .pvi-badge-resolved { background: #e2e3e5; color: #383d41; }
            .pvi-badge-normal   { background: #d4edda; color: #155724; }
            .pvi-badge-med      { background: #e2d9f3; color: #4a235a; }
            /* Drug Alert card */
            .pvi-da-summary { display: flex; align-items: center; gap: 6px; padding: 7px 12px; font-size: 12px; border-bottom: 1px solid #f1f3f5; }
            .pvi-da-section-label { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #6c757d; padding: 6px 12px 3px; }
            .pvi-da-row { display: flex; align-items: flex-start; gap: 8px; padding: 6px 12px; border-bottom: 1px solid #f1f3f5; font-size: 12px; }
            .pvi-da-row:last-child { border-bottom: none; }
            .pvi-da-row.pvi-da-danger { background: #fff5f5; }
            .pvi-da-row.pvi-da-warn   { background: #fffdf0; }
            .pvi-da-row.pvi-da-info   { background: #f7f0ff; }
            .pvi-da-icon { font-size: 13px; flex-shrink: 0; margin-top: 1px; }
            .pvi-da-body { flex: 1; min-width: 0; }
            .pvi-da-title { font-weight: 700; color: #212529; font-size: 12px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
            .pvi-da-sev { font-size: 9px; font-weight: 700; text-transform: uppercase; padding: 1px 5px; border-radius: 4px; background: #e9ecef; color: #495057; }
            .pvi-da-row.pvi-da-danger .pvi-da-sev { background: #f8d7da; color: #721c24; }
            .pvi-da-row.pvi-da-warn   .pvi-da-sev { background: #fff3cd; color: #856404; }
            .pvi-da-text { color: #495057; font-size: 11px; margin-top: 2px; }
            .pvi-da-rec  { font-style: italic; margin-top: 2px; }
            /* DB vitals delta chip */
            .pvi-delta { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 4px; margin-left: 4px; background: #e9ecef; color: #495057; }
            .pvi-delta-up   { background: #f8d7da; color: #721c24; }
            .pvi-delta-down { background: #d4edda; color: #155724; }
        `;
        document.head.appendChild(styleSheet);
    }

    // ── Initialization Engine Hooks ───────────────────────────────────────────
    function init() {
        if (document.getElementById('ambient-doc-widget')) return;

        const validationFields = document.querySelector(
            '[name="subjective"], [name="objective"], [name="assessment"], [name="plan"], #soap_subjective, #soap_objective'
        );

        if (!validationFields) return;

        if (!navigator.mediaDevices || !window.MediaRecorder) {
            console.warn('[AmbientDoc] WebRTC framework extensions missing on host browser context.');
            return;
        }

        buildWidget();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();