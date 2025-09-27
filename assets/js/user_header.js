(() => {
  const header   = document.getElementById('proHeader');
  const avatarBtn= document.getElementById('avatarBtn');
  const menu     = document.getElementById('profileMenu');
  const burger   = document.getElementById('hamburgerBtn');
  const drawer   = document.getElementById('drawer');
  const overlay  = document.getElementById('overlay');
  const logoutM  = document.getElementById('logoutBtnMobile');

  const onScroll = () => header.classList.toggle('elevated', (window.scrollY||document.documentElement.scrollTop) > 6);
  onScroll(); window.addEventListener('scroll', onScroll, {passive:true});


  const menuLinks = () => menu ? [...menu.querySelectorAll('a[href], button:not([disabled])')] : [];
  const outsideClose = (e) => { if (menu && avatarBtn && !menu.contains(e.target) && !avatarBtn.contains(e.target)) closeMenu(); };
  const keyClose = (e) => {
    if (e.key === 'Escape') { closeMenu(); avatarBtn?.focus(); }
    if (e.key === 'Tab' && menu?.classList.contains('open')) {
      const items = menuLinks(); if (!items.length) return;
      const first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    }
  };
  function openMenu(){ if (!menu||!avatarBtn) return; menu.classList.add('open'); avatarBtn.classList.add('open'); avatarBtn.setAttribute('aria-expanded','true'); const items=menuLinks(); items.length&&items[0].focus(); document.addEventListener('click',outsideClose,true); document.addEventListener('keydown',keyClose,true); }
  function closeMenu(){ if (!menu||!avatarBtn) return; menu.classList.remove('open'); avatarBtn.classList.remove('open'); avatarBtn.setAttribute('aria-expanded','false'); document.removeEventListener('click',outsideClose,true); document.removeEventListener('keydown',keyClose,true); }
  avatarBtn?.addEventListener('click',(e)=>{ e.stopPropagation(); menu?.classList.contains('open') ? closeMenu() : openMenu(); });

  /* drawer */
  let scrollY = 0;
  const lockScroll   = () => { scrollY = window.scrollY||document.documentElement.scrollTop; document.body.style.position='fixed'; document.body.style.top = `-${scrollY}px`; document.body.style.width='100%'; };
  const unlockScroll = () => { document.body.style.position=''; document.body.style.top=''; document.body.style.width=''; window.scrollTo(0, scrollY); };

  const escDrawer = (e) => { if (e.key === 'Escape') closeDrawer(); };
  const clickOutsideDrawer = (e) => { if (!drawer?.classList.contains('open')) return; if (!drawer.contains(e.target) && !burger.contains(e.target)) closeDrawer(); };

  function openDrawer(){
    drawer.classList.add('open'); drawer.setAttribute('aria-hidden','false');
    burger.classList.add('open'); burger.setAttribute('aria-expanded','true'); burger.setAttribute('aria-label','Close menu');
    overlay.classList.add('show'); overlay.hidden = false; lockScroll();
    document.addEventListener('keydown', escDrawer, true);
    document.addEventListener('click', clickOutsideDrawer, true);
  }
  function closeDrawer(){
    drawer.classList.remove('open'); drawer.setAttribute('aria-hidden','true');
    burger.classList.remove('open'); burger.setAttribute('aria-expanded','false'); burger.setAttribute('aria-label','Open menu');
    overlay.classList.remove('show'); overlay.hidden = true; unlockScroll();
    document.removeEventListener('keydown', escDrawer, true);
    document.removeEventListener('click', clickOutsideDrawer, true);
  }

  burger?.addEventListener('click', () => (drawer.classList.contains('open') ? closeDrawer() : openDrawer()));
  overlay?.addEventListener('click', closeDrawer);
  document.querySelectorAll('.drawer a').forEach(a => a.addEventListener('click', closeDrawer));
  window.addEventListener('resize', () => { closeDrawer(); closeMenu(); });


  function doLogout(){ window.location.href='../../auth/logout.php'; }
  async function confirmLogout(){
    if (window.Swal && typeof Swal.fire === 'function') {
      const res = await Swal.fire({
        title:'Sign out?', text:'You can sign back in anytime.', icon:'warning',
        showCancelButton:true, confirmButtonText:'Logout', cancelButtonText:'Cancel', reverseButtons:true,
        confirmButtonColor:'#ef4444', cancelButtonColor:'#334155', background:'#ffffff', color:'#0f172a'
      });
      if (res.isConfirmed) doLogout();
    } else { if (confirm('Are you sure you want to log out?')) doLogout(); }
  }
  document.getElementById('logoutLink')?.addEventListener('click',(e)=>{ e.preventDefault(); confirmLogout(); });
  logoutM?.addEventListener('click',(e)=>{ e.preventDefault(); confirmLogout(); });
})();
