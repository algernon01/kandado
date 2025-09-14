document.addEventListener('DOMContentLoaded', () => {
  const hamburger = document.getElementById('hamburger');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('overlay');

  const setSidebar = (open) => {
    sidebar.classList.toggle('open', open);
    hamburger?.setAttribute('aria-expanded', String(open));
    overlay?.classList.toggle('show', open);
    if (overlay) overlay.hidden = !open;
    document.body.style.overflow = open && window.matchMedia('(max-width: 860px)').matches ? 'hidden' : '';
  };

  hamburger?.addEventListener('click', () => setSidebar(!sidebar.classList.contains('open')));
  overlay?.addEventListener('click', () => setSidebar(false));

  // Close on ESC
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) setSidebar(false);
  });

  // Focus trap (mobile)
  const focusableSel = 'a[href], button:not([disabled])';
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab' || !sidebar.classList.contains('open')) return;
    const nodes = sidebar.querySelectorAll(focusableSel);
    if (!nodes.length) return;
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  // Logout confirm (SweetAlert2) – uses your brand color
  const attachLogoutHandler = (el) => {
    el.addEventListener('click', (event) => {
      event.preventDefault();
      const brand = getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb';
      Swal.fire({
        title: 'Are you sure?',
        text: 'You will be logged out from the admin panel.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: brand,
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel',
        background: '#ffffff',
        color: '#0f172a'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = "/kandado/auth/logout.php";
        }
      });
    });
  };
  document.querySelectorAll('a[href$="logout.php"], a.logout-link').forEach(attachLogoutHandler);

  // Auto-close sidebar when resizing to desktop
  let width = window.innerWidth;
  window.addEventListener('resize', () => {
    const now = window.innerWidth;
    if (width <= 860 && now > 860) setSidebar(false);
    width = now;
  });
});
