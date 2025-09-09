/* mylocker.js — Extend via WALLET only + wallet balance under price
   mylocker.php must include under the price line:
     <p class="hint" id="walletHint">Wallet: ₱0.00</p>
*/
(() => {
  const lockerCard = document.getElementById('lockerCard');
  if (!lockerCard || !window.MYLOCKER) return;

  /* ===== BOOTSTRAP FROM PHP ===== */
  const BOOT = window.MYLOCKER || {};
  const serverNowMs = Number(BOOT.serverNowMs) || Date.now();
  const defaultDurationPHP = BOOT.defaultDuration || '30s';
  const lockerCode   = BOOT.lockER?.code ?? BOOT.locker?.code ?? null;
  const lockerNumber = BOOT.lockER?.number ?? BOOT.locker?.number ?? null;
  let   expiresAtMs  = (() => {
    const v = Number(BOOT.lockER?.expiresAtMs ?? BOOT.locker?.expiresAtMs);
    return Number.isFinite(v) ? v : (Date.now() - 1);
  })();
  if (!lockerCode) return;

  /* ===== CONSTANTS ===== */
  const API_BASE        = `${window.location.origin}/kandado/api`;
  const POLL_ENDPOINT   = `${API_BASE}/locker_api.php`;
  const EXTEND_ENDPOINT = `${API_BASE}/locker_api.php`;
  const WALLET_ENDPOINT = `${API_BASE}/locker_api.php?wallet=1`;
  const TIMEOUT_MS      = 20000;
  const MIN_LOADING_MS  = 1200; // short friendly spinner
  let   isProcessing    = false;

  /* ===== DURATIONS / PRICES (keep in sync with backend) ===== */
  const DURATION_OPTIONS = [
    { value: '30s',     text: '30 Seconds (Test)' },
    { value: '20min',   text: '20 Minutes' },
    { value: '30min',   text: '30 Minutes' },
    { value: '1hour',   text: '1 Hour' },
    { value: '2hours',  text: '2 Hours' },
    { value: '4hours',  text: '4 Hours' },
    { value: '8hours',  text: '8 Hours' },
    { value: '12hours', text: '12 Hours' },
    { value: '24hours', text: '24 Hours' },
    { value: '2days',   text: '2 Days' },
    { value: '7days',   text: '7 Days' }
  ];
  const PRICES = {
    '30s': 0.5,
    '20min': 2,
    '30min': 3,
    '1hour': 5,
    '2hours': 10,
    '4hours': 15,
    '8hours': 20,
    '12hours': 25,
    '24hours': 30,
    '2days': 50,
    '7days': 150
  };

  /* ===== ELEMENTS ===== */
  const statusPill      = document.getElementById('statusPill');
  const remainingTimeEl = document.getElementById('remainingTime');
  const progressTrack   = document.getElementById('timeTrack');
  const progressBar     = document.getElementById('timeBar');
  const priceHint       = document.getElementById('priceHint');
  const walletHint      = document.getElementById('walletHint');  // NEW
  const durationSelect  = document.getElementById('extendDuration');
  const qrImage         = document.getElementById('qrImage');
  const qrZoomBtn       = document.getElementById('qrZoomBtn');
  const copyCodeBtn     = document.getElementById('copyCodeBtn');
  const extendBtn       = document.getElementById('extendBtn');
  const led             = document.getElementById('led');
  const connLabel       = document.getElementById('connLabel');
  const saveBtn         = document.getElementById('saveBtn');
  const qrCodeText      = document.getElementById('qrCodeText');
  const terminateBtn    = document.getElementById('terminateBtn');

  /* ===== TIME SYNC ===== */
  const clientNowMs = Date.now();
  let timeOffset    = serverNowMs - clientNowMs;

  /* ===== STATE ===== */
  let lockerExpired = false;
  let walletBalance = 0;
  const peso = (n)=>`₱${Number(n||0).toFixed(2)}`;

  /* ===== BASELINE PERSIST ===== */
  const baselineKey = () => `lockerBaseline:${lockerCode}:${Math.floor(expiresAtMs)}`;
  function loadBaselineMs(){ try{ const v=parseInt(localStorage.getItem(baselineKey()),10); return Number.isFinite(v)&&v>0?v:null; }catch{ return null; } }
  function saveBaselineMs(ms){ try{ localStorage.setItem(baselineKey(), String(Math.max(1, Math.floor(ms)))); }catch{} }
  function clearBaseline(){ try{ localStorage.removeItem(baselineKey()); }catch{} }

  function nowAligned(){ return Date.now() + timeOffset; }
  let remainingNowMs = Math.max(0, expiresAtMs - nowAligned());
  let initialRemainingMs = (() => {
    const saved = loadBaselineMs();
    if (saved && saved >= remainingNowMs) return saved;
    saveBaselineMs(remainingNowMs);
    return Math.max(1, remainingNowMs);
  })();

  /* ===== HELPERS ===== */
  function setStatus(text, type='active'){
    if (!statusPill) return;
    statusPill.textContent = text;
    statusPill.classList.remove('is-active','is-expired','is-used');
    statusPill.classList.add(type === 'active' ? 'is-active' : (type === 'used' ? 'is-used' : 'is-expired'));
  }

  function buildDurationSelect(){
    if (!durationSelect) return;
    durationSelect.innerHTML = '';
    for (const opt of DURATION_OPTIONS) {
      const o = document.createElement('option');
      o.value = opt.value; o.textContent = opt.text;
      durationSelect.appendChild(o);
    }
    const def =
      localStorage.getItem('lockerDefaultDur') ||
      durationSelect.dataset.default ||
      defaultDurationPHP || '30s';
    durationSelect.value = Object.prototype.hasOwnProperty.call(PRICES, def) ? def : DURATION_OPTIONS[0].value;
    updatePriceHint();
  }

  function updatePriceHint(){
    if (!priceHint || !durationSelect) return;
    const key = durationSelect.value;
    const amt = PRICES[key] ?? 0;
    const labels = Object.fromEntries(DURATION_OPTIONS.map(o => [o.value, o.text]));
    priceHint.textContent = `Selected: ${labels[key] || key} · ${peso(amt)}`;
    // wallet line is separate; we refresh it when wallet loads/changes
  }

  function setOnlineUI(ok){
    if (ok) { led?.classList.remove('led--offline'); led?.classList.add('led--online'); if (connLabel) connLabel.textContent='Online'; }
    else    { led?.classList.remove('led--online');  led?.classList.add('led--offline'); if (connLabel) connLabel.textContent='Offline'; }
  }

  async function jsonFetchWithDate(url, opts = {}, timeoutMs = TIMEOUT_MS) {
    const controller = ('AbortController' in window) ? new AbortController() : null;
    const timer = controller ? setTimeout(() => controller.abort(), timeoutMs) : null;
    try {
      const res = await fetch(url, {
        method: opts.method || 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept':'application/json', ...(opts.headers||{}) },
        body: opts.body || null,
        signal: controller ? controller.signal : undefined
      });
      const ct = res.headers.get('content-type') || '';
      let data;
      if (!res.ok) {
        const text = await res.text().catch(()=> '');
        throw new Error(`HTTP ${res.status} ${res.statusText}${text?` — ${text.slice(0,200)}`:''}`);
      }
      if (!ct.includes('application/json')) {
        const text = await res.text().catch(()=> '');
        try { data = JSON.parse(text); } catch { throw new Error(`Non-JSON response: ${text.slice(0,200)||'(empty)'}`); }
      } else {
        data = await res.json();
      }
      let serverDateMs = null;
      const dateHeader = res.headers.get('date');
      if (dateHeader) {
        const parsed = Date.parse(dateHeader);
        if (!Number.isNaN(parsed)) serverDateMs = parsed;
      }
      return { data, serverDateMs };
    } finally { if (timer) clearTimeout(timer); }
  }
  const wait = (ms)=> new Promise(r=>setTimeout(r, ms));

  /* ===== WALLET ===== */
  function paintWallet(){ if (walletHint) walletHint.textContent = `Wallet: ${peso(walletBalance)}`; }
  async function loadWallet(){
    try{
      const res = await fetch(WALLET_ENDPOINT, { credentials:'same-origin', cache:'no-store' });
      const d = await res.json();
      if (d?.success && typeof d.balance === 'number') {
        walletBalance = Number(d.balance);
        paintWallet();
      }
    }catch{}
  }

  /* ===== PROGRESS ===== */
  function applyStateHueByRemaining(remainingMs){
    if (!progressBar || !progressTrack) return;
    const ratio = Math.max(0, Math.min(1, remainingMs / Math.max(1, initialRemainingMs)));
    const hue = 120 * ratio;
    progressBar.style.backgroundColor   = `hsl(${hue}, 85%, 45%)`;
    progressTrack.style.backgroundColor = `hsla(${hue}, 85%, 45%, .18)`;
  }
  function setInitialBarState(){
    if (!progressBar) return;
    remainingNowMs = Math.max(0, expiresAtMs - nowAligned());
    const ratio = Math.max(0, Math.min(1, remainingNowMs / Math.max(1, initialRemainingMs)));
    progressBar.style.width = (ratio * 100) + '%';
    applyStateHueByRemaining(remainingNowMs);
  }

  /* ===== COUNTDOWN ===== */
  let tickId = null;
  function renderTick(){
    if (!remainingTimeEl || !progressBar || !progressTrack) return;
    const now = nowAligned();
    const diff = expiresAtMs - now;

    if (diff <= 0) {
      lockerExpired = true;
      remainingTimeEl.textContent = 'Expired';
      setStatus('Expired', 'expired');
      progressBar.style.width = '0%';
      progressBar.style.backgroundColor   = 'hsl(0, 85%, 45%)';
      progressTrack.style.backgroundColor = 'hsla(0, 85%, 45%, .18)';
      lockerCard.classList.add('state-expired');
      clearBaseline();
      return;
    }

    const h = Math.floor(diff/3600000);
    const m = Math.floor((diff%3600000)/60000);
    const s = Math.floor((diff%60000)/1000);
    remainingTimeEl.textContent =
      `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

    const ratio = Math.max(0, Math.min(1, diff / Math.max(1, initialRemainingMs)));
    progressBar.style.width = (ratio * 100) + '%';
    applyStateHueByRemaining(diff);
  }
  function startTick(){
    if (tickId) clearInterval(tickId);
    renderTick();
    tickId = setInterval(() => { if (!lockerExpired) renderTick(); }, 1000);
  }

  /* ===== POLLING ===== */
  let pollId = null;
  function startPolling(){
    if (pollId) clearInterval(pollId);
    pollId = setInterval(async () => {
      if (lockerExpired) return;
      try {
        const { data, serverDateMs } = await jsonFetchWithDate(POLL_ENDPOINT);
        setOnlineUI(true);
        if (typeof serverDateMs === 'number') timeOffset = serverDateMs - Date.now();
        let stillOccupied = false;
        for (const locker of Object.values(data || {})) {
          if (locker.code === lockerCode && locker.status === 'occupied') { stillOccupied = true; break; }
        }
        if (!stillOccupied) {
          lockerExpired = true;
          remainingTimeEl && (remainingTimeEl.textContent = 'Used');
          setStatus('Used', 'used');
          if (progressBar && progressTrack) {
            progressBar.style.backgroundColor   = 'hsl(0, 85%, 45%)';
            progressTrack.style.backgroundColor = 'hsla(0, 85%, 45%, .18)';
          }
          lockerCard.classList.add('state-used');
          clearBaseline();
          if (window.Swal) {
            Swal.fire({
              icon:'info', title:'Locker Used', text:'Your QR has been used.',
              confirmButtonColor:'#2563eb', confirmButtonText:'OK',
              customClass:{ confirmButton:'swal-confirm-btn' }
            });
          }
        }
      } catch { setOnlineUI(false); }
    }, 2500);
  }
  function pausePolling(){ if (pollId) { clearInterval(pollId); pollId = null; } }
  function resumePolling(){ startPolling(); }

  /* ===== INIT ===== */
  buildDurationSelect();
  if (durationSelect) {
    durationSelect.addEventListener('change', () => {
      localStorage.setItem('lockerDefaultDur', durationSelect.value);
      updatePriceHint();
    });
  }
  loadWallet();               // NEW: pull balance from API (same as dashboard)
  setInitialBarState();
  startTick();
  startPolling();
  function applyNavigatorOnline(){ setOnlineUI(navigator.onLine); }
  window.addEventListener('online', applyNavigatorOnline);
  window.addEventListener('offline', applyNavigatorOnline);
  applyNavigatorOnline();

  /* ===== ACTIONS (unchanged) ===== */
  if (saveBtn && qrImage) {
    saveBtn.addEventListener('click', () => {
      const a = document.createElement('a');
      a.href = qrImage.src + (qrImage.src.includes('?')?'&':'?') + 'dl=1';
      a.download = `locker_qr_code-${lockerCode}.png`;
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
  }
  if (copyCodeBtn) {
    copyCodeBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(String(lockerCode));
        if (window.Swal) Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Code copied', showConfirmButton:false, timer:1500 });
      } catch {}
    });
  }
  if (qrZoomBtn && qrImage) {
    qrZoomBtn.addEventListener('click', () => {
      if (!window.Swal) return;
      Swal.fire({
        title: `Locker #${lockerNumber}`,
        imageUrl: qrImage.src,
        imageAlt: 'QR Code',
        confirmButtonText:'Close',
        confirmButtonColor:'#2563eb',
        customClass:{ confirmButton:'swal-confirm-btn' }
      });
    });
  }

  /* ===== EXTEND — WALLET ONLY ===== */
  if (extendBtn && durationSelect) {
    extendBtn.addEventListener('click', async () => {
      const duration = durationSelect.value;
      const amount   = PRICES[duration] ?? 0;

      // Make sure we have a fresh wallet balance before confirming
      await loadWallet();

      // If not enough, block and point to topup
      if (walletBalance < amount) {
        return Swal.fire({
          icon: 'warning',
          title: 'Insufficient wallet balance',
          html: `Your wallet has <b>${peso(walletBalance)}</b> but this extension costs <b>${peso(amount)}</b>.<br><br>
                 <a class="btn btn-primary" href="/kandado/public/user/topup.php">Top up now</a>`,
          confirmButtonText: 'Close',
          confirmButtonColor: '#2563eb'
        });
      }

      // Confirm using wallet
      const confirm = await Swal.fire({
        title: 'Pay from Wallet',
        html: `Extend <b>Locker #${lockerNumber}</b><br>
               Amount: <b>${peso(amount)}</b><br>
               Wallet: <b>${peso(walletBalance)}</b> → <b>${peso(walletBalance - amount)}</b>`,
        showCancelButton: true,
        confirmButtonText: 'Confirm',
        confirmButtonColor: '#16a34a',
        cancelButtonText: 'Cancel'
      });
      if (!confirm.isConfirmed) return;

      // Spinner
      Swal.fire({
        title: 'Processing',
        html: `Debiting your wallet…`,
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false,
        showConfirmButton: false
      });

      const loadingTimer = wait(MIN_LOADING_MS);
      const ref = `WXT-${Math.floor(100000000 + Math.random()*900000000)}`;

      const url = new URL(EXTEND_ENDPOINT, window.location.origin);
      url.searchParams.set('extend', String(lockerNumber));
      url.searchParams.set('duration', duration);
      url.searchParams.set('ref', ref);     // idempotency key; backend uses wallet only

      try {
        const [{ data, serverDateMs }] = await Promise.all([
          jsonFetchWithDate(url.toString(), { method: 'GET' }), loadingTimer
        ]);

        if (typeof serverDateMs === 'number') timeOffset = serverDateMs - Date.now();

        if (!data || data.error) {
          if (data?.error === 'insufficient_balance') {
            walletBalance = Number(data.balance || walletBalance);
            paintWallet();
            throw new Error(`Insufficient balance. You have ${peso(walletBalance)}, need ${peso(amount)}.`);
          }
          throw new Error(data?.message || data?.error || 'Unknown error');
        }

        // Update QR/code (usually unchanged for extend)
        if (data?.qr_url && qrImage) qrImage.src = data.qr_url + '?t=' + Date.now();
        if (data?.code && qrCodeText) qrCodeText.textContent = data.code;

        // New expiry
        let newEpochMs = null;
        if (typeof data?.expires_at_ms === 'number') newEpochMs = data.expires_at_ms;
        else if (typeof data?.expires_at_epoch === 'number') newEpochMs = data.expires_at_epoch * 1000;
        else if (typeof data?.expires_at === 'string') {
          const parsed = Date.parse(data.expires_at.replace(' ','T') + '+08:00');
          if (!Number.isNaN(parsed)) newEpochMs = parsed;
        }
        if (newEpochMs) {
          clearBaseline();
          expiresAtMs = newEpochMs;
          initialRemainingMs = Math.max(1, expiresAtMs - nowAligned());
          saveBaselineMs(initialRemainingMs);
          lockerExpired = false;
          setStatus('Active', 'active');
          lockerCard.classList.remove('state-expired','state-used');
          setInitialBarState();
          startTick();
        }

        // Update wallet line from backend new balance (API returns it)
        if (typeof data?.balance === 'number') {
          walletBalance = Number(data.balance);
          paintWallet();
        } else {
          // fallback refresh
          await loadWallet();
        }

        Swal.fire({
          icon: data?.idempotent ? 'info' : 'success',
          title: data?.idempotent ? 'Already processed' : 'Extended',
          html: `Locker until <b>${new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Manila'}).format(new Date(expiresAtMs))}</b>.`,
          confirmButtonColor:'#16a34a',
          confirmButtonText:'OK',
          customClass:{ confirmButton:'swal-confirm-btn' }
        });
      } catch (err) {
        Swal.fire({ icon:'error', title:'Error', text: err?.message || 'Failed to extend locker.' });
      }
    });
  }

  /* ===== TERMINATE (unchanged) ===== */
  async function terminateLocker() {
    if (!window.Swal) return;
    const res = await Swal.fire({
      icon:'warning',
      title:'Terminate locker',
      html:`<div style="text-align:center">
              <p style="margin:0 0 8px"><strong>End locker now?</strong></p>
              <ul style="margin:6px 0 0; line-height:1.4; display:inline-block; text-align:left">
                <li>Remaining time ends <strong>immediately</strong>.</li>
                <li>Payments are <strong>non-refundable</strong>.</li>
                <li>Your QR will be <strong>disabled</strong>.</li>
              </ul>
            </div>`,
      confirmButtonText:'Yes, terminate',
      confirmButtonColor:'#dc2626',
      showCancelButton:true,
      cancelButtonText:'Cancel',
      reverseButtons:true,
      customClass:{ confirmButton:'swal-confirm-btn' }
    });
    if (!res.isConfirmed) return;

    try {
      terminateBtn?.classList.add('is-busy');

      const url = new URL(EXTEND_ENDPOINT, window.location.origin);
      url.searchParams.set('terminate', String(lockerNumber));
      url.searchParams.set('reason', 'user_request');

      const { data, serverDateMs } = await jsonFetchWithDate(url.toString(), { method:'GET' });
      if (typeof serverDateMs === 'number') timeOffset = serverDateMs - Date.now();
      if (!data || data.error || data.success !== true) {
        throw new Error(data?.message || data?.error || 'Could not terminate the locker.');
      }

      lockerExpired = true;
      remainingTimeEl && (remainingTimeEl.textContent = data.status === 'hold' ? 'On Hold' : 'Used');
      setStatus(data.status === 'hold' ? 'On Hold' : 'Used', 'used');
      if (progressBar) progressBar.style.width = '0%';
      applyStateHueByRemaining(0);
      lockerCard.classList.add('state-used');
      clearBaseline();
      pausePolling();

      await Swal.fire({
        icon:'success',
        title:'Locker terminated',
        html: data.status === 'hold'
          ? 'Locker is <b>on hold</b> (item still inside). Please contact the admin.'
          : 'Locker has been released. You can generate a new locker anytime.',
        confirmButtonColor:'#16a34a',
        confirmButtonText:'OK',
        customClass:{ confirmButton:'swal-confirm-btn' }
      });
    } catch (err) {
      Swal.fire({ icon:'error', title:'Failed', text: err?.message || 'Could not terminate the locker.' });
    } finally {
      terminateBtn?.classList.remove('is-busy');
    }
  }
  if (terminateBtn) terminateBtn.addEventListener('click', terminateLocker);
})();
