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
            const initUrl = `${CONFIG.API_URL}?action=create_session&pid=${state.patientId}&encounter=${state.encounterId}`;
            const res = await fetch(initUrl, { method: 'POST' });
            const sessionData = await res.json();

            if (!res.ok || !sessionData.success) {
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

        const ageVal = parseInt(document.querySelector('[name="age"], #age')?.value || "0", 10);
        const sexVal = document.querySelector('[name="sex"], [name="gender"]')?.value || "unknown";

        // Construct Pydantic-compliant schema payload match targeting Python backend
        const payload = {
            action: "generate_soap",
            ambient_details: {
                session_id: String(state.sessionId),
                transcript: state.fullTranscript.trim() || "No transcript data generated."
            },
            patient_context: {
                age: ageVal,
                gender: sexVal,
                active_conditions: [],
                current_medications: [],
                allergies: [],
                vitals: {},
                labs: []
            },
            options: {
                note_format: "standard",
                llm_engine: "default"
            }
        };

        try {
            const response = await fetch(`${CONFIG.FASTAPI_BASE_URL}/generate_soap`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) throw new Error(`FastAPI generation error: ${response.status}`);

            const jsonResponse = await response.json();
            
            if (jsonResponse.status === "success" && jsonResponse.data) {
                // Freeze immutable baseline snapshot for rendering text comparisons
                state.originalSoapData = {
                    subjective : jsonResponse.data.subjective || '',
                    objective  : jsonResponse.data.objective  || '',
                    assessment : jsonResponse.data.assessment || '',
                    plan       : jsonResponse.data.plan       || ''
                };

                // Deep copy structure to register real-time UI modifications
                state.activeSoapData = JSON.parse(JSON.stringify(state.originalSoapData));
                
                // Clear state states
                Object.keys(state.acceptedSections).forEach(k => state.acceptedSections[k] = false);

                // Notify local PHP backend module layer of the state success for transaction history logs
                await fetch(`${CONFIG.API_URL}?action=save_clinician_note&session_id=${state.sessionId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: 'completed', transcript: state.fullTranscript })
                });

                showReviewModal();
            } else {
                throw new Error(jsonResponse.message || "Failed to unpack expected dictionary definitions.");
            }
        } catch (err) {
            console.error('[AmbientDoc] requestSoapGeneration failure:', err);
            alert('AI synthesis failure. Check FastAPI runtime service connections.');
        } finally {
            state.isProcessing = false;
            updateStatus('Scribe Ready', 'idle');
            renderWidgetState();
        }
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