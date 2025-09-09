<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="color-scheme" content="light" />
  <meta name="theme-color" content="#ffffff" />
  <title>Top Up Wallet • Kandado</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/kandado/assets/css/users_dashboard.css">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    .topup-container{ max-width: 720px; margin: 0 auto; padding: clamp(12px, 3vw, 24px); display: grid; gap: 16px; }
    .topup-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .topup-title{ font-weight:800; letter-spacing:-.02em; font-size: clamp(20px, 3.5vw, 28px); margin:0; }
    .topup-card{ background: var(--surface); border:1px solid var(--border); border-radius: 16px; padding: 16px; box-shadow: var(--shadow); }
    .balance-row{ display:flex; align-items:center; justify-content:space-between; padding: 12px 14px; border:1px dashed var(--border); border-radius: 12px; background: #fafcff; margin-bottom: 10px; }
    .balance-row .label{ color: var(--muted); font-weight:700; }
    .balance-row .value{ font-weight:800; font-variant-numeric: tabular-nums; }
    .amount-grid{ display:grid; grid-template-columns: 1fr; gap: 12px; }
    @media (min-width: 560px){ .amount-grid{ grid-template-columns: 1.2fr .8fr; } }
    .input-row{ display:flex; gap:10px; }
    .input-row input[type="number"]{ flex:1; padding:12px; border-radius:12px; border:1px solid var(--border); font-weight:700; min-height:44px; }
    .preset-grid{ display:grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .preset-btn{ padding:10px 12px; border-radius:10px; border:1px solid var(--border); background: #f8fafc; font-weight:800; cursor:pointer; transition: transform 160ms ease, box-shadow 160ms ease, background 160ms ease, border-color 160ms ease; }
    .preset-btn:hover{ background:#eef2ff; border-color: rgba(37,99,235,.35); transform: translateY(-1px); }
    .preset-btn:focus-visible{ outline:2px solid var(--ring); outline-offset:2px; }
    .pm-title{ font-weight:700; color: var(--muted); margin: 12px 0 8px; }
    .pay-methods{ display:flex; gap:10px; flex-wrap:wrap; }
    .method{ flex:1; min-width:160px; background:#f8fafc; border-radius:10px; padding:10px; border:1px solid transparent; cursor:pointer; display:flex; align-items:center; gap:10px; min-height:48px; transition: transform 160ms, box-shadow 160ms, background 160ms, border-color 160ms; }
    .method img{ width: 42px; height: 28px; object-fit: contain; }
    .method:hover{ transform:translateY(-1px); box-shadow:0 6px 16px rgba(2,6,23,0.08); }
    .method.active{ border-color: rgba(37,99,235,0.35); background:#ecf2ff; }
    .method:focus-visible{ outline:2px solid var(--ring); outline-offset:2px; }
    .actions{ display:flex; gap:10px; justify-content:flex-end; margin-top: 12px; }
    .btn.cancel{ background:#f1f5f9; color:#475569; border:none }
    .btn.cancel:hover{ background:#e2e8f0 }
    .btn.pay{ background:var(--primary); color:#fff; border:none }
    .btn.pay:hover{ background:var(--primary-600) }
    .note{ font-size:12px; color: var(--muted); margin-top:6px; }
  </style>
</head>
<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/kandado/includes/user_header.php'; ?>

  <main class="container" role="main">
    <div class="topup-container">
      <header class="topup-header">
        <h2 class="topup-title">Top Up Wallet</h2>
        <a class="btn" href="/kandado/public/user/dashboard.php">← Back to Dashboard</a>
      </header>

      <section class="topup-card" aria-label="Wallet balance">
        <div class="balance-row">
          <div class="label">Current Balance</div>
          <div id="walletBalanceTopup" class="value">₱0.00</div>
        </div>
        <div class="note">Top up your wallet using GCash or Maya. Funds will be available immediately.</div>
      </section>

      <section class="topup-card" aria-label="Add funds">
        <div class="amount-grid">
          <div>
            <div class="pm-title">Amount</div>
            <div class="input-row">
              <label for="amount" class="sr-only">Amount</label>
              <input id="amount" type="number" min="1" step="1" placeholder="Enter amount (₱)" />
            </div>
            <div class="preset-grid" style="margin-top:8px">
              <button class="preset-btn" data-val="50">₱50</button>
              <button class="preset-btn" data-val="100">₱100</button>
              <button class="preset-btn" data-val="200">₱200</button>
              <button class="preset-btn" data-val="500">₱500</button>
            </div>
          </div>

          <div>
            <div class="pm-title">Choose Payment</div>
            <div class="pay-methods" role="group" aria-label="Select a payment method">
              <button class="method active" data-method="GCash" id="pm-gcash" type="button" aria-pressed="true">
                <img src="/kandado/assets/icon/gcash.png" alt="GCash Logo" loading="lazy" decoding="async" />
                <span>GCash</span>
              </button>
              <button class="method" data-method="Maya" id="pm-maya" type="button" aria-pressed="false">
                <img src="/kandado/assets/icon/maya.png" alt="Maya Logo" loading="lazy" decoding="async" />
                <span>Maya</span>
              </button>
            </div>

            <div class="actions">
              <button class="btn cancel" id="cancelBtn" type="button">Cancel</button>
              <button class="btn pay" id="topupBtn" type="button">Top Up Now</button>
            </div>
          </div>
        </div>
      </section>

      <section class="topup-card" aria-label="How it works">
        <div class="pm-title">How it works</div>
        <ol style="margin:0; padding-left:18px; line-height:1.7;">
          <li>Enter the amount you want to add.</li>
          <li>Select GCash or Maya, then click <b>Top Up Now</b>.</li>
          <li>We’ll securely process the payment for a moment.</li>
          <li>On success, your wallet balance updates instantly.</li>
          <li>Use your wallet on the dashboard to reserve or extend your locker.</li>
        </ol>
      </section>
    </div>
  </main>

  <script>
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
  </script>
</body>
</html>
