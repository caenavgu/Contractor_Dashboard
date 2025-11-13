// public/assets/js/sign-up.js
// -------------------------------------------------------------
// Lógica de Sign Up (frontend):
// - Validaciones Bootstrap no intrusivas
// - Confirm password + medidor de fuerza
// - EPA file (extensión + tamaño <= 2MB)
// - Email, phone, zip
// - Toggle contractor + required dinámico
// - Normalización de "Company website": auto https://
// - Máscara de teléfono US: (XXX)XXX-XXXX y bloqueo de letras
// -------------------------------------------------------------
(() => {
  'use strict';

  const form = document.getElementById('sign-up-form');
  if (!form) return;

  // ------- refs
  const hasContractor = document.getElementById('has_contractor');
  const contractorSection = document.getElementById('contractor-section');
  const requiredIfHasContractor = ['cac_license_number', 'company_name', 'address', 'city', 'state_code', 'zip_code'];

  const passwordEl = document.getElementById('password');
  const confirmEl  = document.getElementById('confirm_password');
  const emailEl    = document.getElementById('email');

  const phoneEl        = document.getElementById('phone_number');   // Technician phone (REQUIRED)
  const companyPhoneEl = document.getElementById('company_phone');  // Company phone (OPTIONAL)

  const zipEl      = document.getElementById('zip_code');
  const epaFile    = document.getElementById('epa_photo');
  const websiteEl  = document.getElementById('company_website');

  // ============================================================
  // Toggle contractor requireds
  // ============================================================
  function toggleContractorSection() {
    const active = !!(hasContractor && hasContractor.checked);
    if (contractorSection) contractorSection.classList.toggle('d-none', !active);
    requiredIfHasContractor.forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      active ? el.setAttribute('required', 'required') : el.removeAttribute('required');
    });
  }
  if (hasContractor) {
    hasContractor.addEventListener('change', toggleContractorSection);
    toggleContractorSection();
  }

  // ============================================================
  // Confirm password
  // ============================================================
  function validateConfirm() {
    if (!passwordEl || !confirmEl) return;
    confirmEl.setCustomValidity(confirmEl.value !== passwordEl.value ? 'Passwords do not match' : '');
  }
  if (passwordEl) passwordEl.addEventListener('input', validateConfirm);
  if (confirmEl)  confirmEl.addEventListener('input', validateConfirm);

  // ============================================================
  // Email
  // ============================================================
  if (emailEl) {
    emailEl.addEventListener('blur', () => {
      const val = emailEl.value.trim();
      const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
      emailEl.setCustomValidity(valid ? '' : 'Invalid email format');
    });
  }

  // ============================================================
  // Phone masking & validation (US: (XXX)XXX-XXXX, digits only)
  // ============================================================
  function onlyDigits(str) {
    return (str || '').replace(/\D/g, '');
  }
  function formatUSPhone(d) {
    // d = solo dígitos (máx 10)
    d = d.slice(0, 10);
    const len = d.length;
    if (len === 0) return '';
    if (len <= 3) return `(${d}`;
    if (len <= 6) return `(${d.slice(0,3)})${d.slice(3)}`;
    return `(${d.slice(0,3)})${d.slice(3,6)}-${d.slice(6)}`;
  }
  function attachPhoneMask(inputEl, required = false) {
    if (!inputEl) return;

    // Sugerir teclado numérico en móviles
    inputEl.setAttribute('inputmode', 'numeric');
    inputEl.setAttribute('autocomplete', 'tel');

    const updateMask = () => {
      const digits = onlyDigits(inputEl.value);
      inputEl.value = formatUSPhone(digits);
      // Validación: exactamente 10 dígitos si es requerido; si no es requerido, solo valida si hay algo
      const mustValidate = required || digits.length > 0;
      const ok = !mustValidate || digits.length === 10;
      inputEl.setCustomValidity(ok ? '' : 'Enter a 10-digit phone number');
    };

    // Bloquea letras en tiempo real (dejamos sólo dígitos, backspace, arrows, tab)
    inputEl.addEventListener('keydown', (e) => {
      const allowedKeys = [
        'Backspace','Delete','ArrowLeft','ArrowRight','Home','End','Tab',
      ];
      if (allowedKeys.includes(e.key)) return;
      // permitimos dígitos del teclado y del numpad
      if (/^\d$/.test(e.key)) return;
      // permitimos pegar (se limpia en input)
      if ((e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V')) return;
      e.preventDefault();
    });

    // Formatea tras pegar/escribir
    inputEl.addEventListener('input', updateMask);
    inputEl.addEventListener('blur', updateMask);

    // Inicial
    updateMask();
  }

  attachPhoneMask(phoneEl, true);        // requerido
  attachPhoneMask(companyPhoneEl, false); // opcional

  // ============================================================
  // ZIP (US 12345 o 12345-6789)
  // ============================================================
  if (zipEl) {
    zipEl.addEventListener('blur', () => {
      const val = zipEl.value.trim();
      const valid = /^\d{5}(-\d{4})?$/.test(val);
      zipEl.setCustomValidity(valid ? '' : 'Invalid ZIP code');
    });
  }

  // ============================================================
  // EPA file (extensión + tamaño <= 2MB)
  // ============================================================
  if (epaFile) {
    epaFile.addEventListener('change', () => {
      const file = epaFile.files[0];
      if (!file) return;
      const validExt  = /\.(jpg|jpeg|png|pdf)$/i.test(file.name);
      const validSize = file.size <= 2 * 1024 * 1024;
      if (!validExt) {
        epaFile.setCustomValidity('Only JPG, PNG or PDF allowed');
      } else if (!validSize) {
        epaFile.setCustomValidity('File exceeds 2MB');
      } else {
        epaFile.setCustomValidity('');
      }
    });
  }

  // ============================================================
  // Company website: UX -> auto-prepend https:// si falta
  // ============================================================
  function normalizeWebsiteInput() {
    if (!websiteEl) return;
    let v = websiteEl.value.trim();
    if (!v) { websiteEl.setCustomValidity(''); return; } // opcional

    if (!/^https?:\/\//i.test(v)) {
      v = 'https://' + v;
      websiteEl.value = v;
    }
    try {
      new URL(v);
      websiteEl.setCustomValidity('');
    } catch {
      websiteEl.setCustomValidity('Invalid website URL');
    }
  }
  if (websiteEl) {
    websiteEl.addEventListener('blur', normalizeWebsiteInput);
  }

  // ============================================================
  // Medidor de fuerza de contraseña (Bootstrap progress)
  // ============================================================
  let strengthWrap, strengthBar, strengthHint;

  function ensureStrengthUI() {
    if (!passwordEl || strengthWrap) return;
    strengthWrap = document.createElement('div');
    strengthWrap.id = 'pw-strength';
    strengthWrap.className = 'mt-1';

    const progress = document.createElement('div');
    progress.className = 'progress';
    strengthBar = document.createElement('div');
    strengthBar.className = 'progress-bar';
    strengthBar.setAttribute('role', 'progressbar');
    strengthBar.style.width = '0%';
    progress.appendChild(strengthBar);

    strengthHint = document.createElement('small');
    strengthHint.id = 'pw-hint';
    strengthHint.className = 'form-text text-muted';

    strengthWrap.appendChild(progress);
    strengthWrap.appendChild(strengthHint);

    passwordEl.closest('.col-md-3, .mb-3, .form-group')?.appendChild(strengthWrap);
  }

  function evaluateStrength(pw) {
    let score = 0;
    const len = pw.length;
    const hasLower  = /[a-z]/.test(pw);
    const hasUpper  = /[A-Z]/.test(pw);
    const hasDigit  = /\d/.test(pw);
    const hasSymbol = /[^A-Za-z0-9]/.test(pw);
    const common = /^(password|123456|qwerty|111111|letmein|welcome)$/i.test(pw);

    if (len >= 8) score++;
    if (len >= 12) score++;
    if ([hasLower, hasUpper, hasDigit, hasSymbol].filter(Boolean).length >= 3) score++;
    if ([hasLower, hasUpper, hasDigit, hasSymbol].every(Boolean)) score++;
    if (common) score = Math.max(0, score - 2);

    const width = [10, 35, 60, 80, 100][score];
    const label = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'][score];
    const cls   = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-success', 'bg-success'][score];

    return { score, label, width, className: cls };
  }

  function updateStrengthUI() {
    if (!passwordEl) return;
    ensureStrengthUI();
    const pw = passwordEl.value || '';
    const { width, label, className } = evaluateStrength(pw);
    strengthBar.className = 'progress-bar ' + className;
    strengthBar.style.width = width + '%';
    strengthBar.setAttribute('aria-valuenow', String(width));
    strengthHint.textContent = pw ? `Password strength: ${label}` : '';
  }

  if (passwordEl) {
    passwordEl.addEventListener('input', updateStrengthUI);
    updateStrengthUI();
  }

  // ============================================================
  // Submit
  // ============================================================
  form.addEventListener('submit', (e) => {
    validateConfirm();
    normalizeWebsiteInput();

    // Revalida teléfonos justo antes de enviar (por si quedaron parciales)
    const recheckPhone = (el, required) => {
      if (!el) return true;
      const digits = (el.value || '').replace(/\D/g, '');
      const mustValidate = required || digits.length > 0;
      const ok = !mustValidate || digits.length === 10;
      el.setCustomValidity(ok ? '' : 'Enter a 10-digit phone number');
      return ok;
    };
    const phoneOK  = recheckPhone(phoneEl, true);
    const cphoneOK = recheckPhone(companyPhoneEl, false);

    if (!form.checkValidity() || !phoneOK || !cphoneOK) {
      e.preventDefault();
      e.stopPropagation();
    } else {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
      }
    }
    form.classList.add('was-validated');
  });
})();
