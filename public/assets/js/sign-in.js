// public/assets/js/sign-in.js
// -------------------------------------------------------------
// JS de UX para Sign In:
// - Desactiva el botón en submit
// - Toggle de visibilidad de contraseña (👁️)
// - Foco en primer error
// -------------------------------------------------------------
(function () {
  'use strict';

  function onSubmitDisableButton() {
    const form = document.getElementById('sign-in-form');
    const btn = document.getElementById('btn-sign-in');
    if (!form || !btn) return;

    form.addEventListener('submit', function () {
      btn.disabled = true;
      const original = btn.textContent;
      btn.textContent = 'Signing in...';
      setTimeout(() => { btn.disabled = false; btn.textContent = original; }, 8000); // fallback
    });
  }

  function focusFirstInvalid() {
    const invalid = document.querySelector('.is-invalid');
    if (invalid) invalid.focus();
  }

  function togglePasswordVisibility() {
    const toggleBtn = document.getElementById('toggle-password');
    const input = document.getElementById('password');
    if (!toggleBtn || !input) return;

    toggleBtn.addEventListener('click', function () {
      const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
      input.setAttribute('type', type);
      // Cambia el aria-label por accesibilidad
      toggleBtn.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    onSubmitDisableButton();
    focusFirstInvalid();
    togglePasswordVisibility();
  });
})();
