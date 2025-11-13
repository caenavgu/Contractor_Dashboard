// public/assets/js/sign-in.js
// -------------------------------------------------------------
// JS de UX para Sign In:
// - Validación básica antes del submit
// - Desactiva botón al enviar
// - Toggle de visibilidad de contraseña (👁️)
// -------------------------------------------------------------
(() => {
  'use strict';

  const form = document.getElementById('sign-in-form');
  const btn = document.getElementById('btn-sign-in');
  const email = document.getElementById('email');
  const password = document.getElementById('password');
  const toggleBtn = document.getElementById('toggle-password');

  if (!form || !btn) return;

  form.addEventListener('submit', (e) => {
    const emailVal = email ? email.value.trim() : '';
    const passVal = password ? password.value.trim() : '';

    // Validación simple antes de enviar
    if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
      e.preventDefault();
      e.stopPropagation();
      email?.classList.add('is-invalid');
      email?.focus();
      return;
    }

    if (!passVal) {
      e.preventDefault();
      e.stopPropagation();
      password?.classList.add('is-invalid');
      password?.focus();
      return;
    }

    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Signing in...';
    setTimeout(() => {
      btn.disabled = false;
      btn.textContent = originalText;
    }, 8000); // fallback
  });

  if (toggleBtn && password) {
    toggleBtn.addEventListener('click', () => {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      toggleBtn.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
    });
  }
})();
