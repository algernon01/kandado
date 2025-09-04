/* Kandado UI helpers: password toggle, strength meter, client validation */
(function () {
  const byId = (id) => document.getElementById(id);

  /* ===== Show/Hide password (works on Login & Register) =====
     - Supports buttons like: <button class="toggle-btn js-toggle-password" data-target="#id">Show</button>
  */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-toggle-password');
    if (!btn) return;

    const sel = btn.getAttribute('data-target');
    const input = sel ? document.querySelector(sel) : null;
    if (!input) return;

    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.textContent = showing ? 'Show' : 'Hide';
    btn.setAttribute('aria-pressed', String(!showing));
    input.focus({ preventScroll: true });
  });

  /* ===== Password strength (register) ===== */
  const pw = byId('password');
  const bar = byId('strength-bar');
  const label = byId('strength-label');

  function strengthInfo(value) {
    let score = 0;
    if (value.length >= 8) score++;
    if (/[A-Z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    if (score <= 1) return { label: 'Weak', width: '25%', color: '#ef4444', score };
    if (score === 2) return { label: 'Medium', width: '50%', color: '#f59e0b', score };
    if (score === 3) return { label: 'Strong', width: '75%', color: '#3b82f6', score };
    return { label: 'Very strong', width: '100%', color: '#22c55e', score };
  }

  if (pw && bar && label) {
    pw.addEventListener('input', () => {
      const s = strengthInfo(pw.value);
      bar.style.width = s.width;
      bar.style.background = s.color;
      label.textContent = s.label;
      label.style.color = s.color;
    });
  }

  /* ===== Register form validation + SweetAlert for weak/medium ===== */
  const regForm = document.getElementById('registerForm');
  if (regForm) {
    regForm.addEventListener('submit', (e) => {
      const pass = byId('password')?.value || '';
      const confirm = byId('confirm_password')?.value || '';
      const s = strengthInfo(pass);

      if (confirm && pass !== confirm) {
        e.preventDefault();
        Swal.fire({
          icon: 'error',
          title: 'Passwords don’t match',
          text: 'Please re-enter the same password.',
          confirmButtonColor: '#0F2E4F'
        });
        return;
      }

      if (s.score <= 1) {
        e.preventDefault();
        Swal.fire({
          icon: 'error',
          title: 'Weak password',
          text: 'Please use at least 8 characters with uppercase, number and symbol.',
          confirmButtonColor: '#0F2E4F'
        });
      } else if (s.score === 2) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Medium strength',
          text: 'Do you want to continue with a medium-strength password?',
          showCancelButton: true,
          confirmButtonText: 'Proceed',
          cancelButtonText: 'Improve',
          confirmButtonColor: '#0F2E4F',
          cancelButtonColor: '#ef4444'
        }).then((res) => { if (res.isConfirmed) regForm.submit(); });
      }
    });
  }

  /* ===== Auto-fade alerts ===== */
  const alertBox = document.querySelector('.alert');
  if (alertBox) {
    setTimeout(() => { alertBox.style.transition = 'opacity .3s'; alertBox.style.opacity = '0'; }, 3500);
    setTimeout(() => { alertBox.remove(); }, 4000);
  }
})();
