(function(){
  const root = document.documentElement;

  
  const lockScroll   = ()=>{ root.style.overflow = 'hidden'; };
  const unlockScroll = ()=>{ root.style.overflow = ''; };
  const show = el => { el.classList.add('show'); lockScroll(); };
  const hide = el => { el.classList.remove('show'); unlockScroll(); };

  
  const txModal = document.getElementById('txModal');
  const txForm  = document.getElementById('txForm');
  const type    = document.getElementById('tx_type');
  const method  = document.getElementById('tx_method');
  const dirWrap = document.getElementById('adjustDirWrap');
  const amountLabel= document.getElementById('amountLabel');
  const txAmount = document.getElementById('tx_amount');

  function enableAmount(enabled){
    txAmount.readOnly = !enabled;
    txAmount.disabled = !enabled;
    document.getElementById('qaRow').style.display = enabled ? '' : 'none';
    amountLabel.textContent = enabled ? 'Amount' : 'Amount (auto)';
    if (!enabled) txAmount.value = txAmount.value || '0.00';
  }
  function updateControls(){
    const t = type.value;
    dirWrap.style.display = (t==='adjustment') ? '' : 'none';
    const allowed = {
      'topup': ['GCash','Maya','Admin'],
      'adjustment': ['Admin']
    }[t] || [];
    Array.from(method.options).forEach(opt=> opt.disabled = (allowed.indexOf(opt.value)===-1));
    if (allowed.indexOf(method.value)===-1) method.value = allowed[0] || 'Admin';
    enableAmount(true);
  }
  function openTxModalFrom(el){
    const userId   = el.getAttribute('data-user-id');
    const userName = el.getAttribute('data-user-name') || ('User #' + userId);
    const preType  = el.getAttribute('data-type') || 'topup';
    const preAmount= el.getAttribute('data-amount') || '';

    document.getElementById('tx_user_id').value = userId;
    document.getElementById('tx_user_name').value = userName;
    type.value = preType;
    txAmount.value = preAmount;

    if (preType === 'topup') method.value = 'GCash';
    else { method.value = 'Admin'; } 

    updateControls();
    show(txModal);
  }

  document.addEventListener('click', function(e){
    const t = e.target.closest('[data-open-modal]');
    if (t){ e.preventDefault(); openTxModalFrom(t); }
  });
  document.querySelectorAll('#txModal [data-close-modal]').forEach(b=> b.addEventListener('click', ()=>{ hide(txModal); txForm.reset(); enableAmount(true); }));
  txModal && txModal.addEventListener('click', e=>{ if(e.target===txModal){ hide(txModal); txForm.reset(); enableAmount(true); }});
  window.addEventListener('keydown', e=>{ if(e.key==='Escape' && txModal.classList.contains('show')) hide(txModal); });
  type.addEventListener('change', updateControls);
  method.addEventListener('change', updateControls);
  document.querySelectorAll('.qa').forEach(el=>{
    el.addEventListener('click', ()=>{
      const v = parseFloat(txAmount.value || '0');
      txAmount.value = (v + parseFloat(el.getAttribute('data-qa'))).toFixed(2);
    });
  });

  
  const userModal = document.getElementById('userModal');
  const userBody  = document.getElementById('userModalBody');
  const userTitle = document.getElementById('userTitle');
  const userHeader= document.getElementById('userHeader');
  const userNewTxBtn = document.getElementById('userNewTxBtn');

  function setUserHeaderFromFragment(){
    const meta = userBody.querySelector('#uMeta');
    if(!meta) return;
    const uid = meta.getAttribute('data-user-id');
    const uname = meta.getAttribute('data-user-name') || ('User #'+uid);
    userTitle.innerHTML = '<i class="fa-regular fa-user"></i> Wallet · ' + uname;
    userNewTxBtn.setAttribute('data-user-id', uid);
    userNewTxBtn.setAttribute('data-user-name', uname);
  }
  async function loadUserFragment(urlOrParams){
    let url = typeof urlOrParams==='string' ? urlOrParams : ('wallet.php?'+new URLSearchParams(urlOrParams).toString());
    const u = new URL(url, location.href);
    u.searchParams.set('partial','detail');
    url = u.toString();
    userBody.innerHTML = '<div class="empty">Loading…</div>';
    const res = await fetch(url, {credentials:'same-origin'});
    if (!res.ok){ userBody.innerHTML='<div class="empty">Unable to load. <a href="'+url+'">Open full page</a></div>'; return; }
    userBody.innerHTML = await res.text();
    setUserHeaderFromFragment();
    userBody.scrollTop = 0;
  }
  function openUserModal(userId){ show(userModal); loadUserFragment({partial:'detail', user_id:userId}); }

  document.addEventListener('click', function(e){
    const view = e.target.closest('.js-view-user');
    if (view){ e.preventDefault(); openUserModal(view.getAttribute('data-user-id')); }
  });

  userBody && userBody.addEventListener('submit', function(e){
    const f = e.target.closest('[data-modal-filter]');
    if (f){ e.preventDefault(); const params = new URLSearchParams(new FormData(f)); params.set('partial','detail'); loadUserFragment('wallet.php?'+params.toString()); }
  });
  userBody && userBody.addEventListener('click', function(e){
    const link = e.target.closest('a.m-link');
    if (link){ e.preventDefault(); loadUserFragment(link.getAttribute('href')); }
  });

  document.querySelector('[data-close-user-modal]')?.addEventListener('click', ()=> hide(userModal));
  userModal && userModal.addEventListener('click', e=>{ if(e.target===userModal) hide(userModal); });
  window.addEventListener('keydown', e=>{ if(e.key==='Escape' && userModal.classList.contains('show')) hide(userModal); });

  userBody && userBody.addEventListener('scroll', ()=>{ userHeader.classList.toggle('scrolled', userBody.scrollTop>4); });

  document.addEventListener('DOMContentLoaded', ()=>{
    const u = new URL(location.href);
    const uid = u.searchParams.get('user_id');
    if (uid) openUserModal(uid);
  });

  
})();
