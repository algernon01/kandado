const API_BASE = `${window.location.origin}/kandado/api`;

// helpers
const $ = (s, c=document)=>c.querySelector(s);
const $$ = (s, c=document)=>Array.from(c.querySelectorAll(s));
const peso = (n)=>`₱${Number(n||0).toFixed(2)}`;
const sleep = (ms) => new Promise((r)=>setTimeout(r, ms));
const randomRef = (prefix)=> `${prefix}-${Math.floor(100000000 + Math.random()*900000000)}`;

// Keep a single reference while retrying (so API merges rows)
let currentTopupRef = localStorage.getItem('KD_TOPUP_REF') || null;

async function loadBalance(){
  try{
    const r = await fetch(`${API_BASE}/locker_api.php?wallet=1`, { credentials:'same-origin', cache:'no-store' });
    const d = await r.json();
    if (d?.success){ $('#walletBalanceTopup').textContent = peso(d.balance); }
  }catch(e){ /* ignore */ }
}

function setMethodActive(btn){
  $$('.method').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
  btn.classList.add('active'); btn.setAttribute('aria-pressed','true');
}

async function doTopup(){
  const raw = $('#amount').value;
  const amt = Math.floor(Number(raw || 0));
  if (!amt || amt < 1){
    return Swal.fire({ icon:'warning', title:'Enter a valid amount', text:'Minimum top-up is ₱1.' });
  }
  const active = $('.method.active');
  const method = active ? active.getAttribute('data-method') : 'GCash';

  if (!currentTopupRef) {
    currentTopupRef = randomRef(method.slice(0,2).toUpperCase());
    localStorage.setItem('KD_TOPUP_REF', currentTopupRef);
  }

  Swal.fire({
    title: 'Processing Top Up',
    html: `Confirming <b>${method}</b> ${peso(amt)}…`,
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  const t0 = Date.now();
  try{
    const url = `${API_BASE}/locker_api.php?wallet_topup=1&method=${encodeURIComponent(method)}&amount=${encodeURIComponent(amt)}&ref=${encodeURIComponent(currentTopupRef)}`;
    const r = await fetch(url, { credentials:'same-origin', cache:'no-store' });
    const d = await r.json();
    const elapsed = Date.now() - t0;
    if (elapsed < 5000) await sleep(5000 - elapsed); // keep a short consistent wait

    if (!r.ok || d?.error){
      const msg = d?.message || d?.error || `HTTP ${r.status}`;
      return Swal.fire({ icon:'error', title:'Top Up Failed', text: msg });
    }

    await loadBalance();
    Swal.fire({
      icon:'success',
      title:'Top Up Successful',
      html:`Added <b>${peso(amt)}</b> via <b>${method}</b><br><small>Reference: <code>${currentTopupRef}</code></small>`,
      confirmButtonText:'Back to Dashboard',
      confirmButtonColor:'#2563eb',
      showCancelButton:true,
      cancelButtonText:'Stay Here'
    }).then((res)=>{
      // Reset ref AFTER a confirmed successful top-up (new top-up will create a new row)
      localStorage.removeItem('KD_TOPUP_REF');
      currentTopupRef = null;
      if (res.isConfirmed) window.location = '/kandado/public/user/dashboard.php';
    });

  }catch(e){
    const elapsed = Date.now() - t0;
    if (elapsed < 5000) await sleep(5000 - elapsed);
    Swal.fire({ icon:'error', title:'Network Error', text: e.message });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadBalance();

  $$('.preset-btn').forEach(b=>{
    b.addEventListener('click', ()=> { $('#amount').value = b.dataset.val; });
  });

  $('#pm-gcash').addEventListener('click', ()=> setMethodActive($('#pm-gcash')));
  $('#pm-maya').addEventListener('click', ()=> setMethodActive($('#pm-maya')));

  $('#cancelBtn').addEventListener('click', ()=> history.back());
  $('#topupBtn').addEventListener('click', doTopup);
});
