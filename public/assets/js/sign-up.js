// public/assets/js/sign-up.js
// -------------------------------------------------------------
// Lógica de Sign Up (frontend):
// - Toggle de sección Contractor y required dinámicos
// - Validación de confirmación de password
// - UI de validación Bootstrap
// -------------------------------------------------------------
(() => {
  // Elements
  const form = document.getElementById('sign-up-form');
  if (!form) return;

  const hasContractor = document.getElementById('has_contractor');
  const contractorSection = document.getElementById('contractor-section');
  const requiredIfHasContractor = [
    'cac_license_number',
    'company_name',
    'address',
    'city',
    'state_code',
    'zip_code',
  ];

  const passwordEl = document.getElementById('password');
  const confirmEl  = document.getElementById('confirm_password');

  // Toggle contractor section + required fields
  function toggleContractorSection() {
    const active = !!(hasContractor && hasContractor.checked);
    if (contractorSection) {
      contractorSection.classList.toggle('d-none', !active);
    }
    requiredIfHasContractor.forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      if (active) el.setAttribute('required', 'required');
      else el.removeAttribute('required');
    });
  }

  if (hasContractor) {
    hasContractor.addEventListener('change', toggleContractorSection);
    toggleContractorSection();
  }

  // Confirm password must match
  function validateConfirm() {
    if (!passwordEl || !confirmEl) return;
    if (confirmEl.value !== passwordEl.value) {
      confirmEl.setCustomValidity('Passwords do not match');
    } else {
      confirmEl.setCustomValidity('');
    }
  }

  if (passwordEl)  passwordEl.addEventListener('input', validateConfirm);
  if (confirmEl)   confirmEl.addEventListener('input',  validateConfirm);

  // Bootstrap custom validation UI
  form.addEventListener('submit', function (e) {
    validateConfirm();
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    form.classList.add('was-validated');
  });
})();
