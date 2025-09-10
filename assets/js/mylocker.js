  // mylocker.js — My Locker page with Wallet + Extend modal (same UI as Reserve)
  (() => {
    const el = (id) => document.getElementById(id);

    /* ===== BOOTSTRAP FROM PHP ===== */
    const BOOT = window.MYLOCKER || {};
    const lockerObj    = BOOT.lockER || BOOT.locker || {};
    const serverNowMs  = Number(BOOT.serverNowMs) || Date.now();
    const lockerNumber = lockerObj.number ?? BOOT.lockerNumber;
    const lockerCode   = lockerObj.code;
    let   expiresAtMs  = Number(lockerObj.expiresAtMs) || (Date.now() - 1);
    const DEFAULT_DURATION = BOOT.defaultDuration || '30min';

    if (!lockerCode) return; // page not ready

    /* ===== CONSTANTS / API ===== */
    const API_BASE        = `${window.location.origin}/kandado/api`;
    const POLL_ENDPOINT   = `${API_BASE}/locker_api.php`;
    const EXTEND_ENDPOINT = `${API_BASE}/locker_api.php`;
    const WALLET_ENDPOINT = `${API_BASE}/locker_api.php?wallet=1`;
    const PRICES_ENDPOINT = `${API_BASE}/locker_api.php?prices=1`;
    const TOPUP_URL       = `/kandado/public/user/topup.php`;
    const TIMEOUT_MS      = 20000;

    /* ===== DURATIONS (keep in sync with backend) ===== */
    const durationOptions = [
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
    let prices = {
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

    /* ===== HELPERS ===== */
    const peso = (n)=>`₱${Number(n||0).toFixed(2)}`;
    const wait = (ms)=> new Promise(r=>setTimeout(r, ms));
    async function jsonFetch(url, opts = {}, timeoutMs = TIMEOUT_MS){
      const controller = 'AbortController' in window ? new AbortController() : null;
      const timer = controller ? setTimeout(()=>controller.abort(), timeoutMs) : null;
      try{
        const res = await fetch(url, { credentials:'same-origin', cache:'no-store', ...opts, signal: controller?.signal });
        const ct = res.headers.get('content-type')||'';
        const data = ct.includes('application/json') ? await res.json() : JSON.parse(await res.text());
        if (!res.ok) throw new Error(data?.message || data?.error || `HTTP ${res.status}`);
        return { data, headers: res.headers };
      } finally { if (timer) clearTimeout(timer); }
    }

    /* ===== TIME SYNC / PROGRESS ===== */
    const lockerCard      = el('lockerCard');
    const statusPill      = el('statusPill');
    const remainingTimeEl = el('remainingTime');
    const progressTrack   = el('timeTrack');
    const progressBar     = el('timeBar');
    const led             = el('led');
    const connLabel       = el('connLabel');

    let timeOffset = (Number(serverNowMs) || Date.now()) - Date.now();
    const nowAligned = () => Date.now() + timeOffset;

    // Initial baseline to color progress hue consistently
    const baselineKey = () => `lockerBaseline:${lockerCode}:${Math.floor(expiresAtMs)}`;
    function loadBaselineMs(){ try{ const v=parseInt(localStorage.getItem(baselineKey()),10); return Number.isFinite(v)&&v>0?v:null; }catch{ return null; } }
    function saveBaselineMs(ms){ try{ localStorage.setItem(baselineKey(), String(Math.max(1, Math.floor(ms)))); }catch{} }
    function clearBaseline(){ try{ localStorage.removeItem(baselineKey()); }catch{} }

    let initialRemainingMs = (() => {
      const remaining = Math.max(0, expiresAtMs - nowAligned());
      const saved = loadBaselineMs();
      if (saved && saved >= remaining) return saved;
      saveBaselineMs(remaining);
      return Math.max(1, remaining);
    })();

    function setStatus(text, kind='active'){
      if (!statusPill) return;
      statusPill.textContent = text;
      statusPill.classList.remove('is-active','is-expired','is-used');
      statusPill.classList.add(kind === 'active' ? 'is-active' : (kind === 'used' ? 'is-used' : 'is-expired'));
    }
    function applyHueByRemaining(ms){
      if (!progressBar || !progressTrack) return;
      const ratio = Math.max(0, Math.min(1, ms / Math.max(1, initialRemainingMs)));
      const hue = 120 * ratio;
      progressBar.style.backgroundColor   = `hsl(${hue}, 85%, 45%)`;
      progressTrack.style.backgroundColor = `hsla(${hue}, 85%, 45%, .18)`;
    }
    function renderInitialBar(){
      const left = Math.max(0, expiresAtMs - nowAligned());
      const ratio = Math.max(0, Math.min(1, left / Math.max(1, initialRemainingMs)));
      if (progressBar) progressBar.style.width = (ratio * 100) + '%';
      applyHueByRemaining(left);
    }

    let lockerExpired = false;
    let tickId = null;
    function renderTick(){
      const diff = expiresAtMs - nowAligned();
      if (diff <= 0){
        lockerExpired = true;
        remainingTimeEl && (remainingTimeEl.textContent = 'Expired');
        setStatus('Expired', 'expired');
        if (progressBar) progressBar.style.width = '0%';
        applyHueByRemaining(0);
        lockerCard?.classList.add('state-expired');
        clearBaseline();
        return;
      }
      const h = Math.floor(diff/3600000);
      const m = Math.floor((diff%3600000)/60000);
      const s = Math.floor((diff%60000)/1000);
      remainingTimeEl && (remainingTimeEl.textContent =
        `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`);
      const ratio = Math.max(0, Math.min(1, diff / Math.max(1, initialRemainingMs)));
      if (progressBar) progressBar.style.width = (ratio * 100) + '%';
      applyHueByRemaining(diff);
    }
    function startTick(){ clearInterval(tickId); renderTick(); tickId = setInterval(()=>{ if (!lockerExpired) renderTick(); }, 1000); }

    /* ===== ONLINE/OFFLINE + POLLING ===== */
    function setOnlineUI(ok){
      if (ok) { led?.classList.remove('led--offline'); led?.classList.add('led--online'); connLabel && (connLabel.textContent='Online'); }
      else    { led?.classList.remove('led--online');  led?.classList.add('led--offline'); connLabel && (connLabel.textContent='Offline'); }
    }
    let pollId = null;
    function startPolling(){
      clearInterval(pollId);
      pollId = setInterval(async () => {
        if (lockerExpired) return;
        try{
          const { data, headers } = await jsonFetch(POLL_ENDPOINT);
          setOnlineUI(true);
          const dateHeader = headers.get('date');
          if (dateHeader){
            const parsed = Date.parse(dateHeader);
            if (!Number.isNaN(parsed)) timeOffset = parsed - Date.now();
          }
          // If locker no longer occupied, mark used
          const list = Object.values(data || {});
          const still = list.some(l => l?.code === lockerCode && l?.status === 'occupied');
          if (!still){
            lockerExpired = true;
            remainingTimeEl && (remainingTimeEl.textContent = 'Used');
            setStatus('Used', 'used');
            applyHueByRemaining(0);
            lockerCard?.classList.add('state-used');
            clearBaseline();
          }
        }catch{ setOnlineUI(false); }
      }, 2500);
    }

    /* ===== WALLET ===== */
    let walletBalance = 0;
    const walletHint = el('walletHint');    // optional line under price on page
    function paintWallet(){ if (walletHint) walletHint.textContent = `Wallet: ${peso(walletBalance)}`; }
    async function loadWallet(){
      try{
        const { data } = await jsonFetch(WALLET_ENDPOINT);
        if (data?.success && typeof data.balance === 'number'){
          walletBalance = Number(data.balance);
          paintWallet();
        }
      }catch{}
    }
    async function getWalletBalance(){ await loadWallet(); return walletBalance; }

    /* ===== PRICES SYNC ===== */
    async function syncPricesFromAPI(){
      try{
        const { data } = await jsonFetch(PRICES_ENDPOINT);
        if (data?.success && data?.prices && typeof data.prices === 'object'){
          prices = { ...prices, ...data.prices };
        }
      }catch{}
    }

    /* ===== EXTEND MODAL (same design as Reserve modal) ===== */
    function labelForDuration(v){ return durationOptions.find(o => o.value === v)?.text || v; }
    function optionCardHTML(value, text){
      const price = prices[value] ?? 0;
      const id = `ext-${value.replace(/[^a-zA-Z0-9_-]/g, '')}`;
      return `
        <label class="option-card" data-value="${value}">
          <input id="${id}" type="radio" name="extend-duration" value="${value}">
          <div class="opt-main">
            <span class="opt-title">${text}</span>
            <span class="opt-price">${peso(price)}</span>
          </div>
        </label>
      `;
    }

    async function openExtendModal(){
      const balance = await getWalletBalance();
      const initial = durationOptions.some(o => o.value === DEFAULT_DURATION) ? DEFAULT_DURATION : '30min';
      const optionsHtml = durationOptions.map(o => optionCardHTML(o.value, o.text)).join('');

      return Swal.fire({
        title: `Extend Locker ${lockerNumber}`,
        html: `
          <div class="reserve-modal">
            <div class="wallet-inline" aria-live="polite">
              <span>Wallet</span>
              <span id="em-balance" class="amount">${peso(balance)}</span>
            </div>

            <div class="section-title" style="text-align:center; font-weight:800; color:#64748b; letter-spacing:.12em;">Choose time duration</div>

            <div class="duration-grid" id="em-grid" role="radiogroup" aria-label="Select extension duration">
              ${optionsHtml}
            </div>

            <div class="wallet-warning" id="em-warning"></div>

            <div class="summary-row" aria-live="polite">
              <div id="em-selected">Selected: <b>${labelForDuration(initial)}</b></div>
              <div class="total">Total: <span id="em-total">${peso(prices[initial] ?? 0)}</span></div>
            </div>

            <div class="reference" style="margin-top:6px">
              <span class="ref-label">Need more funds?</span>
              <a class="ref-value" href="${TOPUP_URL}" style="text-decoration:none;">Top Up</a>
            </div>
          </div>
        `,
        customClass: { popup: 'reserve-popup' },
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Pay Now',
        confirmButtonColor: '#2563eb',
        preConfirm: () => {
          const checked = document.querySelector('input[name="extend-duration"]:checked');
          if (!checked) {
            Swal.showValidationMessage('Please choose a time duration');
            return false;
          }
          return checked.value;
        },
        didOpen: () => {
          const grid       = document.getElementById('em-grid');
          const totalEl    = document.getElementById('em-total');
          const warnEl     = document.getElementById('em-warning');
          const selectedEl = document.getElementById('em-selected');
          const confirmBtn = Swal.getConfirmButton();

          // initial selection
          const initEl = document.getElementById(`ext-${CSS.escape(initial)}`);
          if (initEl) initEl.checked = true;

          function markAffordability(){
            grid.querySelectorAll('.option-card').forEach(card => {
              const value = card.getAttribute('data-value');
              const price = Number(prices[value] || 0);
              card.classList.toggle('insufficient', price > balance);
            });
          }

          function update(){
            const checked = grid.querySelector('input[name="extend-duration"]:checked');
            if (!checked) { confirmBtn.disabled = true; return; }
            const val   = checked.value;
            const price = Number(prices[val] || 0);
            const short = Math.max(0, price - balance);

            totalEl.textContent = peso(price);
            selectedEl.innerHTML = `Selected: <b>${labelForDuration(val)}</b>`;

            if (short > 0){
              warnEl.innerHTML = `Insufficient balance. Short by <b>${peso(short)}</b>. <a class="ref-value" href="${TOPUP_URL}">Top Up</a>`;
              warnEl.classList.add('show'); confirmBtn.disabled = true;
            }else{
              warnEl.classList.remove('show'); warnEl.textContent = ''; confirmBtn.disabled = false;
            }
          }

          grid.addEventListener('click', (e) => {
            const card = e.target.closest('.option-card');
            if (!card) return;
            const input = card.querySelector('input[type="radio"]');
            if (input && !input.checked){
              input.checked = true;
              input.dispatchEvent(new Event('change', { bubbles: true }));
            }
          });

          grid.addEventListener('change', update);
          markAffordability();
          update();
        }
      });
    }

    /* ===== EXTEND VIA WALLET ===== */
    async function extendWithWallet(duration){
      try{
        Swal.fire({
          title: 'Extending…',
          html: `Debiting your wallet (${labelForDuration(duration)})`,
          didOpen: () => Swal.showLoading(),
          allowOutsideClick: false,
          showConfirmButton: false
        });

        const ref = `WXT-${Math.floor(100000000 + Math.random()*900000000)}`;
        const url = new URL(EXTEND_ENDPOINT, window.location.origin);
        url.searchParams.set('extend', String(lockerNumber));
        url.searchParams.set('duration', duration);
        url.searchParams.set('ref', ref);

        const [{ data, headers }] = await Promise.all([
          jsonFetch(url.toString(), { method:'GET' }),
          wait(900) // small friendly spinner
        ]);

        const dateHeader = headers.get('date');
        if (dateHeader){
          const parsed = Date.parse(dateHeader);
          if (!Number.isNaN(parsed)) timeOffset = parsed - Date.now();
        }

        if (data?.error){
          if (data.error === 'insufficient_balance'){
            walletBalance = Number(data.balance || walletBalance);
            paintWallet();
            throw new Error(`Insufficient balance. You have ${peso(walletBalance)}.`);
          }
          throw new Error(data.message || data.error || 'Extend failed');
        }

        // New expiry
        let newMs = null;
        if (typeof data?.expires_at_ms === 'number') newMs = data.expires_at_ms;
        else if (typeof data?.expires_at_epoch === 'number') newMs = data.expires_at_epoch * 1000;
        else if (typeof data?.expires_at === 'string'){
          const p = Date.parse(data.expires_at.replace(' ','T') + '+08:00');
          if (!Number.isNaN(p)) newMs = p;
        }
        if (newMs){
          clearBaseline();
          expiresAtMs = newMs;
          initialRemainingMs = Math.max(1, expiresAtMs - nowAligned());
          saveBaselineMs(initialRemainingMs);
          lockerExpired = false;
          setStatus('Active', 'active');
          lockerCard?.classList.remove('state-expired','state-used');
          renderInitialBar();
          startTick();
        }

        // Update wallet line from backend new balance (API returns it)
        if (typeof data?.balance === 'number'){
          walletBalance = Number(data.balance);
          paintWallet();
        } else {
          await loadWallet();
        }

        const expStr = new Intl.DateTimeFormat('en-PH', { dateStyle:'medium', timeStyle:'short', timeZone:'Asia/Manila' })
          .format(new Date(expiresAtMs));

        Swal.fire({
          icon: data?.idempotent ? 'info' : 'success',
          title: data?.idempotent ? 'Already processed' : 'Extended',
          html: `Locker extended until <b>${expStr}</b>.`,
          confirmButtonColor:'#16a34a',
          confirmButtonText:'OK'
        });
      }catch(err){
        Swal.fire({ icon:'error', title:'Error', text: err?.message || 'Failed to extend locker.' });
      }
    }

    /* ===== QR + actions ===== */
    const qrImage   = el('qrImage');
    const qrZoomBtn = el('qrZoomBtn');
    const copyBtn   = el('copyCodeBtn');
    const saveBtn   = el('saveBtn');
    const qrCodeTxt = el('qrCodeText');

    if (qrZoomBtn && qrImage){
      qrZoomBtn.addEventListener('click', () => {
        Swal.fire({
          title: `Locker #${lockerNumber}`,
          imageUrl: qrImage.src,
          imageAlt: 'QR Code',
          confirmButtonText:'Close',
          confirmButtonColor:'#2563eb'
        });
      });
    }
    if (copyBtn){
      copyBtn.addEventListener('click', async () => {
        try{
          await navigator.clipboard.writeText(String(lockerCode));
          Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Code copied', showConfirmButton:false, timer:1500 });
        }catch{}
      });
    }
    if (saveBtn && qrImage){
      saveBtn.addEventListener('click', () => {
        const a = document.createElement('a');
        a.href = qrImage.src + (qrImage.src.includes('?')?'&':'?') + 'dl=1';
        a.download = `locker_qr_code-${lockerCode}.png`;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
      });
    }

    /* ===== TERMINATE ===== */
    const terminateBtn = el('terminateBtn');
    if (terminateBtn){
      terminateBtn.addEventListener('click', async () => {
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
          reverseButtons:true
        });
        if (!res.isConfirmed) return;

        try{
          terminateBtn.classList.add('is-busy');
          const url = new URL(EXTEND_ENDPOINT, window.location.origin);
          url.searchParams.set('terminate', String(lockerNumber));
          url.searchParams.set('reason', 'user_request');
          const { data, headers } = await jsonFetch(url.toString(), { method:'GET' });
          const dateHeader = headers.get('date');
          if (dateHeader){
            const parsed = Date.parse(dateHeader);
            if (!Number.isNaN(parsed)) timeOffset = parsed - Date.now();
          }
          if (!data || data.error || data.success !== true) throw new Error(data?.message || data?.error || 'Could not terminate.');
          lockerExpired = true;
          remainingTimeEl && (remainingTimeEl.textContent = data.status === 'hold' ? 'On Hold' : 'Used');
          setStatus(data.status === 'hold' ? 'On Hold' : 'Used', 'used');
          if (progressBar) progressBar.style.width = '0%';
          applyHueByRemaining(0);
          lockerCard?.classList.add('state-used');
          clearBaseline();
          Swal.fire({
            icon:'success',
            title:'Locker terminated',
            html: data.status === 'hold'
              ? 'Locker is <b>on hold</b> (item still inside). Please contact the admin.'
              : 'Locker has been released. You can generate a new locker anytime.',
            confirmButtonColor:'#16a34a',
            confirmButtonText:'OK'
          });
        }catch(err){
          Swal.fire({ icon:'error', title:'Failed', text: err?.message || 'Could not terminate the locker.' });
        }finally{
          terminateBtn.classList.remove('is-busy');
        }
      });
    }

    /* ===== INIT ===== */
    (async () => {
      await syncPricesFromAPI();
      await loadWallet();
      renderInitialBar();
      startTick();
      startPolling();
      setOnlineUI(navigator.onLine);
      window.addEventListener('online',  () => setOnlineUI(true));
      window.addEventListener('offline', () => setOnlineUI(false));
    })();

    // Hook Extend button -> modal UI
    const extendBtn = el('extendBtn');
    if (extendBtn){
      extendBtn.addEventListener('click', async () => {
        const result = await openExtendModal();
        if (result.isConfirmed && result.value){
          const chosen = result.value;
          localStorage.setItem('lockerDefaultDur', chosen);
          await extendWithWallet(chosen);
        }
      });
    }
  })();
