/* mylocker.js — extracted from inline script
   Depends on a small inline bootstrap:
   window.MYLOCKER = { serverNowMs, defaultDuration, locker: { code, number, expiresAtMs } }
*/
(() => {
  // Guard: only run where the locker UI exists
  const lockerCard = document.getElementById('lockerCard');
  if (!lockerCard || !window.MYLOCKER) return;

  /* ===== CONFIG FROM PHP BOOTSTRAP ===== */
  const BOOT = window.MYLOCKER || {};
  const serverNowMs = Number(BOOT.serverNowMs) || Date.now();
  const defaultDurationPHP = BOOT.defaultDuration || '30s';
  const lockerCode   = BOOT.lockER?.code ?? BOOT.locker?.code ?? null;
  const lockerNumber = BOOT.lockER?.number ?? BOOT.locker?.number ?? null;

  let expiresAtMs = (() => {
    const v = Number(BOOT.lockER?.expiresAtMs ?? BOOT.locker?.expiresAtMs);
    return Number.isFinite(v) ? v : Date.now() - 1;
  })();

  if (!lockerCode) return; // nothing to do

  /* ===== CONSTANTS ===== */
  const API_BASE         = `${window.location.origin}/kandado/api`;
  const POLL_ENDPOINT    = `${API_BASE}/locker_api.php`;
  const EXTEND_ENDPOINT  = `${API_BASE}/locker_api.php`;
  const TIMEOUT_MS       = 20000;
  const MIN_LOADING_MS   = 3000;
  let   isProcessing     = false;

  /* ===== CONFIG ===== */
  const DURATION_OPTIONS = [
    { value: '30s',     text: '30 Seconds (Test)' },
    { value: '20min',   text: '20 Minutes' },     // added to match backend
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

  // Default duration: localStorage -> PHP -> fallback
  const durationSelect = document.getElementById('extendDuration');
  const DEFAULT_DURATION =
    localStorage.getItem('lockerDefaultDur') ||
    (durationSelect ? durationSelect.dataset.default : '') ||
    defaultDurationPHP ||
    '30s';

  /* ===== ELEMENTS ===== */
  const statusPill      = document.getElementById('statusPill');
  const remainingTimeEl = document.getElementById('remainingTime');
  const progressTrack   = document.getElementById('timeTrack');
  const progressBar     = document.getElementById('timeBar');
  const priceHint       = document.getElementById('priceHint');
  const qrImage         = document.getElementById('qrImage');
  const qrZoomBtn       = document.getElementById('qrZoomBtn');
  const copyCodeBtn     = document.getElementById('copyCodeBtn');
  const extendBtn       = document.getElementById('extendBtn');
  const led             = document.getElementById('led');
  const connLabel       = document.getElementById('connLabel');
  const saveBtn         = document.getElementById('saveBtn');
  const qrCodeText      = document.getElementById('qrCodeText');
  const terminateBtn    = document.getElementById('terminateBtn'); // NEW

  /* ===== TIME SYNC ===== */
  const clientNowMs = Date.now();
  let   timeOffset  = serverNowMs - clientNowMs;

  /* ===== LOCKER DATA ===== */
  let   lockerExpired = false;

  /* >>> PERSISTED BASELINE */
  const baselineKey = () => `lockerBaseline:${lockerCode}:${Math.floor(expiresAtMs)}`;
  function loadBaselineMs(){
    try { const v = parseInt(localStorage.getItem(baselineKey()), 10); return Number.isFinite(v) && v > 0 ? v : null; } catch { return null; }
  }
  function saveBaselineMs(ms){
    try { localStorage.setItem(baselineKey(), String(Math.max(1, Math.floor(ms)))); } catch {}
  }
  function clearBaseline(){
    try { localStorage.removeItem(baselineKey()); } catch {}
  }

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
    statusPill.classList.remove('is-active', 'is-expired', 'is-used');
    statusPill.classList.add(type === 'active' ? 'is-active' : (type === 'used' ? 'is-used' : 'is-expired'));
  }

  function buildDurationSelect(defaultValue = DEFAULT_DURATION) {
    if (!durationSelect) return;
    durationSelect.innerHTML = '';
    for (const opt of DURATION_OPTIONS) {
      const o = document.createElement('option');
      o.value = opt.value;
      o.textContent = opt.text;
      durationSelect.appendChild(o);
    }
    durationSelect.value = Object.prototype.hasOwnProperty.call(PRICES, defaultValue)
      ? defaultValue
      : DURATION_OPTIONS[0].value;
    updatePriceHint();
  }

  function updatePriceHint(){
    if (!priceHint || !durationSelect) return;
    const key = durationSelect.value;
    const amt = PRICES[key] ?? PRICES[DEFAULT_DURATION];
    const labels = Object.fromEntries(DURATION_OPTIONS.map(o => [o.value, o.text]));
    priceHint.textContent = `Selected: ${labels[key] || key} · ₱${amt.toFixed(0)}`;
  }

  function setOnlineUI(ok){
    if (ok) { led?.classList.remove('led--offline'); led?.classList.add('led--online'); if (connLabel) connLabel.textContent = 'Online'; }
    else    { led?.classList.remove('led--online');  led?.classList.add('led--offline'); if (connLabel) connLabel.textContent = 'Offline'; }
  }

  async function jsonFetchWithDate(url, opts = {}, timeoutMs = TIMEOUT_MS) {
    const controller = ('AbortController' in window) ? new AbortController() : null;
    const timer = controller ? setTimeout(() => controller.abort(), timeoutMs) : null;

    try {
      const res = await fetch(url, {
        method: opts.method || 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json', ...(opts.headers || {}) },
        body: opts.body || null,
        signal: controller ? controller.signal : undefined
      });

      const ct = res.headers.get('content-type') || '';
      let data;
      if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(`HTTP ${res.status} ${res.statusText}${text ? ` — ${text.slice(0,200)}` : ''}`);
      }
      if (!ct.includes('application/json')) {
        const text = await res.text().catch(() => '');
        try { data = JSON.parse(text); } catch { throw new Error(`Non-JSON response: ${text.slice(0,200) || '(empty)'}`); }
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
    } finally {
      if (timer) clearTimeout(timer);
    }
  }
  async function jsonFetch(url, opts = {}, timeoutMs = TIMEOUT_MS) {
    const r = await jsonFetchWithDate(url, opts, timeoutMs);
    return r.data;
  }

  const wait = (ms)=> new Promise(r=>setTimeout(r, ms));

  /* ===== COLOR STATE ===== */
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
  function renderTick() {
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

    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    remainingTimeEl.textContent =
      `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

    const ratio = Math.max(0, Math.min(1, diff / Math.max(1, initialRemainingMs)));
    progressBar.style.width = (ratio * 100) + '%';
    applyStateHueByRemaining(diff);
  }
  function startTick(){
    if (tickId) clearInterval(tickId);
    renderTick();
    tickId = setInterval(() => {
      if (!lockerExpired) renderTick();
    }, 1000);
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
        if (typeof serverDateMs === 'number') {
          timeOffset = serverDateMs - Date.now();
        }
        let stillOccupied = false;
        for (const locker of Object.values(data || {})) {
          if (locker.code === lockerCode && locker.status === 'occupied') {
            stillOccupied = true; break;
          }
        }
        if (!stillOccupied) {
          lockerExpired = true;
          if (remainingTimeEl) remainingTimeEl.textContent = 'Used';
          setStatus('Used', 'used');
          if (progressBar && progressTrack) {
            progressBar.style.backgroundColor   = 'hsl(0, 85%, 45%)';
            progressTrack.style.backgroundColor = 'hsla(0, 85%, 45%, .18)';
          }
          lockerCard.classList.add('state-used');
          clearBaseline();
          if (window.Swal) {
            Swal.fire({
              icon: 'info',
              title: 'Locker Used',
              text: 'Your QR has been used. Please get a new locker.',
              confirmButtonColor: '#2563eb',
              confirmButtonText: 'OK',
              customClass: { confirmButton: 'swal-confirm-btn' }
            });
          }
        }
      } catch {
        setOnlineUI(false);
      }
    }, 2500);
  }
  function pausePolling(){ if (pollId) { clearInterval(pollId); pollId = null; } }
  function resumePolling(){ startPolling(); }

  /* ===== INIT ===== */
  buildDurationSelect(DEFAULT_DURATION);
  if (durationSelect) {
    durationSelect.addEventListener('change', () => {
      localStorage.setItem('lockerDefaultDur', durationSelect.value);
      updatePriceHint();
    });
  }
  setInitialBarState();
  startTick();
  startPolling();
  function applyNavigatorOnline(){ setOnlineUI(navigator.onLine); }
  window.addEventListener('online', applyNavigatorOnline);
  window.addEventListener('offline', applyNavigatorOnline);
  applyNavigatorOnline();

  /* ===== ACTIONS ===== */
  if (saveBtn && qrImage) {
    saveBtn.addEventListener('click', () => {
      const link = document.createElement('a');
      link.href = qrImage.src + (qrImage.src.includes('?') ? '&' : '?') + 'dl=1';
      link.download = `locker_qr_code-${lockerCode}.png`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
  }

  if (copyCodeBtn) {
    copyCodeBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(String(lockerCode));
        if (window.Swal) {
          Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Code copied', showConfirmButton:false, timer:1500 });
        }
      } catch(_) {}
    });
  }

  if (qrZoomBtn && qrImage) {
    qrZoomBtn.addEventListener('click', () => {
      if (!window.Swal) return;
      Swal.fire({
        title: `Locker #${lockerNumber}`,
        imageUrl: qrImage.src,
        imageAlt: 'QR Code',
        showConfirmButton: true,
        confirmButtonText:'Close',
        confirmButtonColor:'#2563eb',
        customClass:{ confirmButton:'swal-confirm-btn' }
      });
    });
  }

  /* ===== EXTEND + PAYMENT ===== */
  if (extendBtn && durationSelect) {
    extendBtn.addEventListener('click', () => {
      const duration = durationSelect.value;
      const amount = PRICES[duration] ?? PRICES[DEFAULT_DURATION];

      const checkoutHTML = `
        <div class="co">
          <div class="co__top">
            <div class="co__brand">
              <div class="co__logo">K</div>
              <div class="co__store">
                <div class="co__sub">Kandado</div>
                <div class="co__title">Extend Locker #${lockerNumber}</div>
              </div>
            </div>
            <div class="co__amount">₱${amount.toFixed(0)}</div>
          </div>

          <div class="co__sep"></div>

          <div class="co__section">Choose payment</div>
          <div class="co__methods">
            <button class="co__method active" data-method="GCash" id="pm-gcash">
              <img src="/kandado/assets/icon/gcash.png" alt="GCash"><span>GCash</span>
            </button>
            <button class="co__method" data-method="Maya" id="pm-maya">
              <img src="/kandado/assets/icon/maya.png" alt="Maya"><span>Maya</span>
            </button>
          </div>

          <div class="co__sep"></div>

          <div class="co__ref">
            <span class="co__ref-label">Reference</span>
            <span class="co__ref-value" id="fakeRef">—</span>
          </div>

          <div class="co__actions">
            <button class="btn btn-ghost" id="cancelCheckout">Cancel</button>
            <button class="btn btn-primary" id="payNow">Pay</button>
          </div>
        </div>
      `;

      if (!window.Swal) return;
      Swal.fire({
        title: 'Extend Locker',
        html: checkoutHTML,
        width: 560,
        showConfirmButton: false,
        didOpen: () => {
          let selectedMethod = 'GCash';
          const pmG = document.getElementById('pm-gcash');
          const pmM = document.getElementById('pm-maya');
          const payNowBtn = document.getElementById('payNow');
          const cancelBtn = document.getElementById('cancelCheckout');
          const refEl = document.getElementById('fakeRef');

          function newRef(prefix){ return `${prefix}-${Math.floor(100000000 + Math.random()*900000000)}`; }
          function setActive(el){
            pmG.classList.remove('active'); pmM.classList.remove('active');
            el.classList.add('active');
            selectedMethod = el.getAttribute('data-method');
            refEl.textContent = newRef(selectedMethod.slice(0,2).toUpperCase());
          }

          setActive(pmG);
          pmG.addEventListener('click', () => setActive(pmG));
          pmM.addEventListener('click', () => setActive(pmM));
          cancelBtn.addEventListener('click', () => Swal.close());

          payNowBtn.addEventListener('click', async () => {
            if (isProcessing) return;
            isProcessing = true;

            payNowBtn.classList.add('is-busy'); pmG.classList.add('is-busy'); pmM.classList.add('is-busy');

            Swal.fire({
              title: 'Processing',
              html: `Confirming <b>${selectedMethod}</b> ₱${amount.toFixed(2)}...`,
              didOpen: () => Swal.showLoading(),
              allowOutsideClick: false,
              allowEscapeKey: false,
              showConfirmButton: false
            });

            const loadingTimer = wait(MIN_LOADING_MS);
            const finalRef = refEl.textContent;

            const buildUrl = () => {
              const url = new URL(EXTEND_ENDPOINT, window.location.origin);
              url.searchParams.set('extend', String(lockerNumber));
              url.searchParams.set('duration', duration);
              url.searchParams.set('method', selectedMethod);
              url.searchParams.set('amount', String(amount));
              url.searchParams.set('ref', finalRef);
              return url.toString();
            };

            async function callExtend() { return jsonFetchWithDate(buildUrl(), { method: 'GET' }); }
            async function extendWithRetry() {
              try { return await callExtend(); }
              catch (e) { await wait(600); return await callExtend(); }
            }

            try {
              const [{ data, serverDateMs }] = await Promise.all([extendWithRetry(), loadingTimer]);

              if (typeof serverDateMs === 'number') {
                timeOffset = serverDateMs - Date.now();
              }

              if (data && (data.error || data.status === 'error')) {
                throw new Error(data.message || data.error || 'Unknown error');
              }

              if (data?.qr_url && qrImage) qrImage.src = data.qr_url + '?t=' + Date.now();
              if (data?.code && qrCodeText) qrCodeText.textContent = data.code;

              let newEpochMs = null;
              if (typeof data?.expires_at_ms === 'number') newEpochMs = data.expires_at_ms;
              else if (typeof data?.expires_at_epoch === 'number') newEpochMs = data.expires_at_epoch * 1000;
              else if (typeof data?.expires_at === 'string') {
                const iso = data.expires_at.replace(' ', 'T') + '+08:00';
                const parsed = Date.parse(iso);
                if (!Number.isNaN(parsed)) newEpochMs = parsed;
              }

              if (newEpochMs) {
                clearBaseline();
                expiresAtMs = newEpochMs;
                const freshRemaining = Math.max(1, expiresAtMs - nowAligned());
                initialRemainingMs = freshRemaining;
                saveBaselineMs(initialRemainingMs);

                lockerExpired = false;
                setStatus('Active', 'active');
                lockerCard.classList.remove('state-expired','state-used');

                setInitialBarState();
                startTick();
              }

              Swal.fire({
                icon: 'success',
                title: data?.idempotent ? 'Already processed' : 'Extended',
                html: `Locker until <b>${new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Manila'}).format(new Date(expiresAtMs))}</b>.`,
                confirmButtonColor:'#16a34a',
                confirmButtonText:'OK',
                customClass:{ confirmButton:'swal-confirm-btn' }
              });
            } catch (err) {
              console.error('Extend error:', err);
              Swal.fire({ icon:'error', title:'Error', text:'Failed to extend locker.' });
            } finally {
              resumePolling();
              isProcessing = false;
              payNowBtn.classList.remove('is-busy'); pmG.classList.remove('is-busy'); pmM.classList.remove('is-busy');
            }
          });
        }
      });
    });
  }

  /* ===== TERMINATE LOCKER (NEW) ===== */
  async function terminateLocker() {
    if (!window.Swal) return;

    const messageHTML = `
      <div style="text-align:center">
        <p style="margin:0 0 8px"><strong>End locker now?</strong></p>
        <ul style="margin:6px 0 0; line-height:1.4; display:inline-block; text-align:left">
          <li>Your remaining time will end <strong>immediately</strong>.</li>
          <li>Payments are <strong>non-refundable</strong>.</li>
          <li>Your QR code will be <strong>disabled</strong> and can’t be used anymore.</li>
        </ul>
      </div>
    `;

    const res = await Swal.fire({
      icon: 'warning',
      title: 'Terminate locker',
      html: messageHTML,
      confirmButtonText: 'Yes, terminate',
      confirmButtonColor: '#dc2626',
      showCancelButton: true,
      cancelButtonText: 'Cancel',
      reverseButtons: true,
      customClass: { confirmButton: 'swal-confirm-btn' }
    });

    if (!res.isConfirmed) return;

    try {
      terminateBtn?.classList.add('is-busy');

      const url = new URL(EXTEND_ENDPOINT, window.location.origin);
      url.searchParams.set('terminate', String(lockerNumber));
      url.searchParams.set('reason', 'user_request');

      const { data, serverDateMs } = await jsonFetchWithDate(url.toString(), { method: 'GET' });

      if (typeof serverDateMs === 'number') {
        timeOffset = serverDateMs - Date.now();
      }

      if (!data || data.error || data.success !== true) {
        const msg = data?.message || data?.error || 'Could not terminate the locker.';
        throw new Error(msg);
      }

      // success path
      lockerExpired = true;
      remainingTimeEl && (remainingTimeEl.textContent = data.status === 'hold' ? 'On Hold' : 'Used');
      setStatus(data.status === 'hold' ? 'On Hold' : 'Used', 'used');
      if (progressBar) progressBar.style.width = '0%';
      applyStateHueByRemaining(0);
      lockerCard.classList.add('state-used');
      clearBaseline();
      pausePolling();

      await Swal.fire({
        icon: 'success',
        title: 'Locker terminated',
        html: data.status === 'hold'
          ? 'Your locker is now <b>on hold</b> because an item is still inside. Please contact the admin to retrieve it.'
          : 'Your locker has been released. You can generate a new locker anytime.',
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'OK',
        customClass: { confirmButton:'swal-confirm-btn' }
      });
    } catch (err) {
      console.error(err);
      Swal.fire({
        icon: 'error',
        title: 'Failed',
        text: err?.message || 'Could not terminate the locker. Please try again.'
      });
    } finally {
      terminateBtn?.classList.remove('is-busy');
    }
  }

  if (terminateBtn) {
    terminateBtn.addEventListener('click', terminateLocker);
  }
})();
