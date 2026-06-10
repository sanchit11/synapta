// ════════════════════════════════
//  STATE
// ════════════════════════════════
var currentStep=1;
var TOTAL_STEPS=4;
var STEP_LABELS=['Practice Information',
'Contact Information',
'Clinical Setup',
'Goals & Program Fit'];
var PROGRESS=['25%',
'50%',
'75%',
'100%'];

var selectedProviders='';
var selectedBooking='Square Appointments';
var selectedEHR='';
var selectedPrescribe='';
var selectedTimeline='';
var selectedChallenges=[];
document.getElementById('submit-error').style.display='none';
// var loiAgreed          = false;

// ════════════════════════════════
//  NAVIGATION
// ════════════════════════════════
function nextStep() {
	if ( !validateStep(currentStep)) return;

	if (currentStep===TOTAL_STEPS) {
		submitForm();
		return;
	}

	currentStep++;
	updateUI();
}

function prevStep() {
	if (currentStep > 1) {
		currentStep--;
		updateUI();
	}
}

function jumpTo(n) {
	if (n <=currentStep) {
		currentStep=n;
		updateUI();
	}
}

function updateUI() {
	document.querySelectorAll('.step-panel').forEach(function(p, i) {
			p.classList.toggle('active', i + 1===currentStep);
		});

	document.querySelectorAll('.step-dot').forEach(function(d, i) {
			d.classList.remove('active', 'done');
			if (i + 1===currentStep) d.classList.add('active');
			else if (i + 1 < currentStep) d.classList.add('done');
		});
	document.getElementById('progress-fill').style.width=PROGRESS[currentStep - 1];
	document.getElementById('step-label').textContent=STEP_LABELS[currentStep - 1];
	document.getElementById('step-count').textContent='Step '+currentStep+' of '+TOTAL_STEPS;

	var back=document.getElementById('btn-back');
	back.classList.toggle('hidden', currentStep===1);

	var btn=document.getElementById('btn-next');

	if (currentStep===TOTAL_STEPS) {
		btn.innerHTML='🌟 Submit Application <span style="font-size:16px;">→</span>';
		btn.className='btn-submit';
		btn.onclick=submitForm;
	}

	else {
		btn.innerHTML='Continue <span style="font-size:16px;">→</span>';
		btn.className='btn-next';
		btn.onclick=nextStep;
	}

	document.getElementById('form-body').scrollTo(0, 0);
	document.getElementById('right-panel').scrollTo(0, 0);
	document.getElementById('server-error').style.display='none';
	 focusFirstField();
}

function focusFirstField() {
  var activeStep = document.querySelector('.step-panel.active');
  if (!activeStep) return;

  // Include your custom clickable elements
  var target = activeStep.querySelector(
    'input, select, textarea, .prov-btn, .ehr-pill, .radio-card, .check-card'
  );

  if (target) {
    setTimeout(function () {

      // Make element focusable if it's not
      if (!target.hasAttribute('tabindex')) {
        target.setAttribute('tabindex', '-1');
      }

      target.focus();

      target.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });

    }, 120);
  }
}

// ════════════════════════════════
//  VALIDATION
// ════════════════════════════════
function validateStep(step) {
	var ok=true;

	if (step===1) {
		if ( !val('practice-name', 'f-practice-name')) ok=false;
		if ( !val('clinic-type', 'f-clinic-type')) ok=false;
		if ( !val('practice-state', 'f-state')) ok=false;
		var pe=document.getElementById('providers-error');

		if ( !selectedProviders) {
			pe.style.display='block';
			ok=false;
		}

		else pe.style.display='none';
	}

	if (step===2) {
		if ( !val('first-name', 'f-first-name')) ok=false;
		if ( !val('last-name', 'f-last-name')) ok=false;
		if ( !val('contact-role', 'f-role')) ok=false;
		if ( !validateEmail()) ok=false;
		if ( !validatePhone()) ok=false;
	}

	if (step===3) {
		var pre=document.getElementById('prescribe-error');

		if ( !selectedPrescribe) {
			pre.style.display='block';
			ok=false;
		}

		else pre.style.display='none';
	}

	if (step===4) {
		var ce=document.getElementById('challenges-error');

		if (selectedChallenges.length===0) {
			ce.style.display='block';
			ok=false;
		}

		else ce.style.display='none';

		var te=document.getElementById('timeline-error');

		if ( !selectedTimeline) {
			te.style.display='block';
			ok=false;
		}

		else te.style.display='none';

		//   var le = document.getElementById('loi-error');
		//   if (!loiAgreed) { le.style.display = 'block'; ok = false; }
		//   else              le.style.display = 'none';
	}

	return ok;
}

function val(inputId, fieldId) {
	var input=document.getElementById(inputId);
	var field=document.getElementById(fieldId);
	if ( !input || !field) return true;

	if ( !input.value.trim()) {
		field.classList.add('has-error');
		input.classList.add('error');
		return false;
	}

	field.classList.remove('has-error');
	input.classList.remove('error');
	return true;
}

function validateEmail() {
	var input=document.getElementById('contact-email');
	var field=document.getElementById('f-email');

	if ( !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim())) {
		field.classList.add('has-error');
		input.classList.add('error');
		return false;
	}

	field.classList.remove('has-error');
	input.classList.remove('error');
	return true;
}

function validatePhone() {
	var input=document.getElementById('contact-phone');
	var field=document.getElementById('f-phone');

	if (input.value.replace(/\D/g, '').length < 10) {
		field.classList.add('has-error');
		input.classList.add('error');
		return false;
	}

	field.classList.remove('has-error');
	input.classList.remove('error');
	return true;
}

// ════════════════════════════════
//  INTERACTIVE CONTROLS
// ════════════════════════════════
function selectProviders(el) {
	document.querySelectorAll('.prov-btn').forEach(function(b) {
			b.classList.remove('active');
		});
	el.classList.add('active');
	selectedProviders=el.dataset.val;
	document.getElementById('provider-count').value=selectedProviders;
	document.getElementById('providers-error').style.display='none';
}

function toggleEHR(el, group) {
	if (group==='booking') {
		document.querySelectorAll('#booking-grid .ehr-pill').forEach(function(p) {
				p.classList.remove('active');
			});
		el.classList.add('active');
		selectedBooking=el.dataset.val;
		document.getElementById('booking-software').value=selectedBooking;
	}

	else {
		el.classList.toggle('active');
		var v=el.dataset.val;

		var parts=(document.getElementById('ehr-system').value || '').split(',').map(function(s) {
				return s.trim();
			}).filter(Boolean);
		var idx=parts.indexOf(v);
		if (idx > -1) parts.splice(idx, 1);
		else parts.push(v);
		document.getElementById('ehr-system').value=parts.join(', ');
		selectedEHR=parts.join(', ');
	}
}

function selectRadio(card, group, val) {
	card.closest('.radio-cards').querySelectorAll('.radio-card').forEach(function(c) {
			c.classList.remove('active');
		});
	card.classList.add('active');

	if (group==='prescribe') {
		selectedPrescribe=val;
		document.getElementById('prescribe-val').value=val;
		document.getElementById('prescribe-error').style.display='none';
	}

	else {
		selectedTimeline=val;
		document.getElementById('timeline-val').value=val;
		document.getElementById('timeline-error').style.display='none';
	}
}

function toggleCheck2(card) {
	card.classList.toggle('active');
	card.querySelector('input[type=checkbox]').checked=card.classList.contains('active');

	if (card.closest('#challenges-grid')) {
		selectedChallenges=Array.from(document.querySelectorAll('#challenges-grid .check-card.active input')).map(function(i) {
				return i.value;
			});
		if (selectedChallenges.length) document.getElementById('challenges-error').style.display='none';
	}

	// if (card.id === 'loi-agree-card') {
	//   loiAgreed = card.classList.contains('active');
	//   if (loiAgreed) document.getElementById('loi-error').style.display = 'none';
	// }
}

function toggleCheck(card) {
	const input=card.querySelector('input[type=checkbox]');

	// Toggle manually (avoid relying on label default behavior)
	input.checked= !input.checked;

	if (input.checked) {
		card.classList.add('active');
	}

	else {
		card.classList.remove('active');
	}

	// Update selectedChallenges
	if (card.closest('#challenges-grid')) {
		selectedChallenges=Array.from(document.querySelectorAll('#challenges-grid input:checked')).map(i=> i.value);

		document.getElementById('challenges-error').style.display=selectedChallenges.length ? 'none': 'block';
	}
}

function formatPhone(input) {
	var v=input.value.replace(/\D/g, '').slice(0, 10);
	if (v.length >=7) input.value='('+v.slice(0, 3)+') '+v.slice(3, 6)+'-'+v.slice(6);
	else if (v.length >=4) input.value='('+v.slice(0, 3)+') '+v.slice(3);
	else if (v.length >=1) input.value='('+v;
}

// ════════════════════════════════
//  AJAX SUBMIT
// ════════════════════════════════
function submitForm() {
	if ( !validateStep(4)) return;

	var btn=document.getElementById('btn-next');
	btn.classList.add('btn-submitting');
	btn.textContent='Submitting…';

	var fd=new FormData();
	//fd.append('csrf_token',       SYNAPTA_CSRF);
	fd.append('practice_name', document.getElementById('practice-name').value);
	fd.append('clinic_type', document.getElementById('clinic-type').value);
	fd.append('practice_state', document.getElementById('practice-state').value);
	fd.append('provider_count', selectedProviders);
	fd.append('first_name', document.getElementById('first-name').value);
	fd.append('last_name', document.getElementById('last-name').value);
	fd.append('contact_role', document.getElementById('contact-role').value);
	fd.append('email', document.getElementById('contact-email').value);
	fd.append('phone', document.getElementById('contact-phone').value);
	fd.append('best_time', document.getElementById('best-time').value);
	fd.append('referral_source', document.getElementById('referral-source').value);
	fd.append('booking_software', selectedBooking);
	fd.append('ehr_system', selectedEHR);
	fd.append('prescribes', selectedPrescribe);
	fd.append('consent_process', document.getElementById('consent-process').value);
	fd.append('challenges', JSON.stringify(selectedChallenges));
	fd.append('timeline', selectedTimeline);
	fd.append('additional_notes', document.getElementById('additional-notes').value);
	//fd.append('loi_agreed',       loiAgreed ? '1' : '0');

	fetch(SYNAPTA_URL, {
		method: 'POST', body: fd

	}) .then(function(r) {
		return r.json().then(function(d) {
				return {
					ok: r.ok, data: d
				}

				;
			});

	}) .then(function(res) {
		if (res.ok && res.data.success) {
			// Show success screen
			document.getElementById('success-ref').textContent='REF: ' + res.data.reference_id;
			document.getElementById('form-body').style.display='none';
			document.getElementById('form-footer').style.display='none';
			document.getElementById('form-topbar').style.display='none';
			document.getElementById('success-panel').classList.add('active');
		}

		else {
			var msg=(res.data && res.data.message) ? res.data.message : 'An error occurred. Please try again.';

			// If field-level errors exist, list them
			if (res.data && res.data.errors) {
				var lines=Object.values(res.data.errors);
				if (lines.length) msg=lines.join(' · ');
			}

			if (res.data && res.data.message && res.data.message.includes('already registered')) {
				showSubmitError(res.data.message);

				// ⏳ Auto-hide after 5 seconds
				setTimeout(function () {
						var el=document.getElementById('submit-error');

						if (el) {
							el.style.display='none';
							el.textContent='';
						}
					}

					, 5000);

			}

			else {
				showBanner(msg);

				// ⏳ Auto-hide banner also (optional but consistent UX)
				setTimeout(function () {
						var b=document.getElementById('server-error');

						if (b) {
							b.style.display='none';
							b.textContent='';
						}
					}

					, 5000);
			}

			btn.classList.remove('btn-submitting');
			updateUI();
		}

	}) .catch(function() {
		showBanner('Network error — please check your connection and try again.');
		btn.classList.remove('btn-submitting');
		updateUI();
	});
}

function showBanner(msg) {
	var b=document.getElementById('server-error');
	b.textContent=msg;
	b.style.display='block';

	b.scrollIntoView({
		behavior: 'smooth', block: 'nearest'
	});
}

// Real-time error clearing
['practice-name',
'clinic-type',
'practice-state',
'first-name',
'last-name',
'contact-role',
'contact-email',
'contact-phone'].forEach(function(id) {
		var el=document.getElementById(id);
		if ( !el) return;

		el.addEventListener('input', function() {
				el.classList.remove('error');
				var f=el.closest('.field');
				if (f) f.classList.remove('has-error');
			});
	});

function showSubmitError(msg) {
	var el=document.getElementById('submit-error');
	el.textContent=msg;
	el.style.display='block';
}