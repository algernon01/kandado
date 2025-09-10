// user_dashboard.js — Wallet-enabled, modal-based duration picker (no selects on cards)

/* ------------------ Config ------------------ */
const API_BASE = `${window.location.origin}/kandado/api`;
const TOPUP_URL = `/kandado/public/user/topup.php`;

// Client price map (synced from API /prices at boot)
let prices = {
  '30s': 0.5,     // for testing
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

// Initial selection when opening the modal
const DEFAULT_DURATION = localStorage.getItem('kd_last_duration') || '30min';

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

/* ------------------ Utilities ------------------ */
const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const peso = (n) => `₱${Number(n || 0).toFixed(2)}`;
const nowStr = () => new Date().toLocaleTimeString();
const debounce = (fn, ms=150) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };
const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

function updateLastUpdated() { $('#lastUpdated').textContent = `Updated: ${nowStr()}`; }
function statusClasses(el, status){
  el.classList.remove('available','occupied','hold','item','no-item','maintenance');
  el.classList.add(status);
}
function setBusy(isBusy){ const g = $('#lockerGrid'); if (!g) return; g.setAttribute('aria-busy', isBusy ? 'true':'false'); g.classList.toggle('grid-busy', isBusy); }
function setOffline(isOffline){ $('#offlineBanner')?.classList.toggle('hidden', !isOffline); }

function formatDuration(ms){
  if (ms <= 0) return '0s';
  const s = Math.floor(ms/1000);
  const d = Math.floor(s/86400);
  const h = Math.floor((s%86400)/3600);
  const m = Math.floor((s%3600)/60);
  const sec = s%60;
  const parts = [];
  if (d) parts.push(`${d}d`);
  if (h) parts.push(`${h}h`);
  if (m) parts.push(`${m}m`);
  parts.push(`${sec}s`);
  return parts.slice(0,3).join(' ');
}

// Choose donut color by % (<=50 green, 51-80 orange, >80 red)
function getOccColor(pct){
  if (pct <= 50) return cssVar('--occ-green') || '#16a34a';
  if (pct <= 80) return cssVar('--occ-orange') || '#f59e0b';
  return cssVar('--occ-red') || '#dc2626';
}

/* ------------------ Filters & Search ------------------ */
let currentStatusFilter = localStorage.getItem('kd_status_filter') || 'all';
let currentSearch = '';

function setStatusFilter(val){
  currentStatusFilter = val;
  localStorage.setItem('kd_status_filter', val);
  $$('.segmented .seg').forEach(b=>{
    const active = b.dataset.filter === val;
    b.classList.toggle('active', active);
    b.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  applyFilters();
}

function applyFilters(){
  const cards = $$('.locker', $('#lockerGrid'));
  let visible = 0;

  cards.forEach(card=>{
    const status = card.dataset.status || 'available';
    const id = parseInt(card.dataset.id, 10);
    const matchesStatus = (currentStatusFilter === 'all') || (status === currentStatusFilter);
    const matchesSearch = !currentSearch || id === currentSearch;
    const show = matchesStatus && matchesSearch;

    card.classList.toggle('hidden', !show);
    card.classList.toggle('locker--highlight', !!currentSearch && id === currentSearch);
    if (show) visible++;
  });

  $('#emptyState')?.classList.toggle('hidden', visible > 0);
}

/* ------------------ Build one locker card ------------------ */
function buildLockerCard(i) {
  const idx = i + 1;
  const card = document.createElement('article');
  card.className = 'locker skeleton';
  card.id = `locker${i}`;
  card.dataset.id = String(idx);
  card.dataset.status = 'available';
  card.setAttribute('role', 'region');
  card.setAttribute('aria-labelledby', `locker${i}-title`);

  card.innerHTML = `
    <div class="lnum" id="locker${i}-title">Locker ${idx}</div>

    <div id="status${i}" class="status available" data-status="available" aria-live="polite">
      <span class="status-dot" aria-hidden="true"></span>
      <span class="status-text">Available</span>
    </div>

    <button id="btn${i}" class="reserveBtn" type="button" onclick="startCheckout(${i})" aria-label="Reserve locker ${idx}">
      Reserve
    </button>

    <div class="meta" id="meta${i}">
      <div class="meta-row">
        <span class="meta-label">Time left</span>
        <span class="meta-value" id="time${i}">—</span>
      </div>
    </div>
  `;

  return card;
}

/* ------------------ Prices (sync with API) ------------------ */
async function syncPricesFromAPI() {
  try {
    const r = await fetch(`${API_BASE}/locker_api.php?prices=1`, {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!r.ok) return; // keep defaults if not ok
    const data = await r.json();
    if (data?.success && data?.prices && typeof data.prices === 'object') {
      prices = { ...prices, ...data.prices };
    }
  } catch (e) {
    // ignore, fallback to hardcoded prices
  }
}

/* ------------------ Wallet widget ------------------ */
let walletPollId = null;

function ensureWalletWidget() {
  // already in the HTML (walletWidget inside toolbar)
  // keep for backward-compat with older templates
}

async function refreshWalletBalance() {
  try {
    const r = await fetch(`${API_BASE}/locker_api.php?wallet=1`, {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    const data = await r.json();
    if (data?.success) {
      const v = $('#walletBalanceValue');
      if (v) v.textContent = peso(data.balance);
    }
  } catch (e) {
    // ignore display errors
  }
}

// helper that also returns a number for modal logic
async function getWalletBalance(){
  try{
    const r = await fetch(`${API_BASE}/locker_api.php?wallet=1`, {
      credentials:'same-origin',
      cache:'no-store'
    });
    const data = await r.json();
    if (data?.success){
      const v = $('#walletBalanceValue');
      if (v) v.textContent = peso(data.balance);
      return Number(data.balance || 0);
    }
  }catch(_){}
  return 0;
}

function startWalletPolling(intervalMs = 10000) {
  if (walletPollId) return;
  walletPollId = setInterval(refreshWalletBalance, intervalMs);
}
function stopWalletPolling() {
  if (!walletPollId) return;
  clearInterval(walletPollId);
  walletPollId = null;
}

/* ------------------ Polling ------------------ */
let pollId = null;
function startPolling(){ if (!pollId) pollId = setInterval(fetchActiveLockers, 3000); }
function stopPolling(){ if (pollId) { clearInterval(pollId); pollId = null; } }

/* ------------------ Countdown management ------------------ */
const countdownMap = new Map();
let countdownTicker = null;
function ensureCountdownTicker(){
  if (countdownTicker) return;
  countdownTicker = setInterval(()=>{
    const now = Date.now();
    for (const [i, expiresAt] of countdownMap.entries()){
      const el = document.getElementById(`time${i}`);
      if (!el) continue;
      const diff = expiresAt - now;
      el.textContent = formatDuration(diff);
      el.classList.toggle('danger', diff <= 0);
    }
  }, 1000);
}
function clearCountdown(i){
  countdownMap.delete(i);
  const el = document.getElementById(`time${i}`);
  if (el) el.textContent = '—';
  if (countdownMap.size === 0 && countdownTicker){ clearInterval(countdownTicker); countdownTicker = null; }
}

/* ------------------ KPIs + Donut ------------------ */
function updateKpis(count){
  const total = getTotalLockers();
  const inUse = (count.occupied || 0) + (count.hold || 0);
  const available = count.available || 0;
  const maintenance = count.maintenance || 0;
  const occPct = total ? Math.round((inUse / total) * 100) : 0;

  const totalEl = $('#kpiTotal');
  if (totalEl) totalEl.textContent = String(total);
  $('#kpiAvailable').textContent   = String(available);
  $('#kpiOccupied').textContent    = String(count.occupied || 0);
  $('#kpiHold').textContent        = String(count.hold || 0);
  $('#kpiMaintenance').textContent = String(maintenance);

  const donut = $('#occDonut');
  const occLabel = $('#kpiOcc');
  const color = getOccColor(occPct);

  if (donut) {
    donut.style.setProperty('--p', occPct);
    donut.style.setProperty('--c', color);
    donut.style.background = `conic-gradient(${color} ${occPct}%, #e5e7eb 0)`;
    donut.setAttribute('aria-label', `Occupancy ${occPct}%`);
  }
  if (occLabel) occLabel.textContent = `${occPct}%`;

  $('#kpiOccSub').textContent = `${inUse} in use • ${available} available • ${maintenance} maintenance`;
}

/* ------------------ Fetch status ------------------ */
async function fetchActiveLockers(showToast = false) {
  setBusy(true);
  try {
    const res = await fetch(`${API_BASE}/locker_api.php`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const active = await res.json();

    let count = { available: 0, occupied: 0, hold: 0, maintenance: 0 };

    for (let i = 0; i < getTotalLockers(); i++) {
      const idx = i + 1;
      const statusDiv  = document.getElementById(`status${i}`);
      const btn        = document.getElementById(`btn${i}`);
      const card       = document.getElementById(`locker${i}`);
      const lockerData = active?.[idx];
      if (!statusDiv || !btn || !card) continue;

      card.classList.remove('skeleton');

      const textEl = statusDiv.querySelector('.status-text');
      let status = 'available';
      let expiresAt = null;

      if (lockerData && typeof lockerData.status === 'string') {
        status = lockerData.status;
        if (lockerData.expires_at) {
          const t = new Date(lockerData.expires_at);
          if (!isNaN(t.valueOf())) expiresAt = t;
        }
      }

      const isMaintenance = Number(lockerData?.maintenance) === 1;
      const hasItem = Number(lockerData?.item) === 1;

      // Maintenance overrides everything
      if (isMaintenance) {
        textEl.textContent = 'Maintenance';
        statusClasses(statusDiv, 'maintenance');
        btn.disabled = true;
        btn.textContent = 'Maintenance';
        btn.setAttribute('aria-disabled', 'true');
        card.dataset.status = 'maintenance';
        clearCountdown(i);
        count.maintenance++;
        continue;
      }

      if (status === 'available') {
        textEl.textContent = 'Available';
        statusClasses(statusDiv, 'available');
        btn.disabled = false;
        btn.textContent = 'Reserve';
        btn.removeAttribute('aria-disabled');
        count.available++;
      }

      if (status === 'hold') {
        textEl.textContent = 'On Hold';
        statusClasses(statusDiv, 'hold');
        btn.disabled = true;
        btn.textContent = 'On Hold';
        btn.setAttribute('aria-disabled', 'true');
        count.hold++;
      }

      if (status === 'occupied') {
        const label = hasItem ? 'Occupied' : 'Occupied (empty)';
        textEl.textContent = label;

        statusClasses(statusDiv, 'occupied');
        statusDiv.classList.toggle('no-item', !hasItem);
        statusDiv.classList.toggle('item', hasItem);

        btn.disabled = true;
        btn.textContent = 'Occupied';
        btn.setAttribute('aria-disabled', 'true');

        count.occupied++;
      }

      card.dataset.status = (status === 'occupied' || status === 'hold' || status === 'available') ? status : 'available';

      if ((status === 'occupied' || status === 'hold') && expiresAt) {
        countdownMap.set(i, expiresAt.getTime());
        ensureCountdownTicker();
      } else {
        clearCountdown(i);
      }
    }

    updateKpis(count);
    applyFilters();
    updateLastUpdated();
    setOffline(false);

    if (showToast && window.Swal) {
      Swal.fire({
        toast: true, position: 'top-end', timer: 1400, showConfirmButton: false,
        icon: 'success', title: 'Refreshed'
      });
    }

    // also refresh wallet (but not every 3s via separate timer)
    refreshWalletBalance();

  } catch (err) {
    console.error('Error fetching lockers:', err);
    setOffline(true);
    if (window.Swal) {
      Swal.fire({
        toast: true, position: 'top-end', timer: 2400, showConfirmButton: false,
        icon: 'error', title: 'Network error while refreshing'
      });
    }
  } finally {
    setBusy(false);
  }
}

/* ------------------ Reserve flow: modal with duration + wallet ------------------ */
function labelForDuration(value){
  return durationOptions.find(o=>o.value===value)?.text || value;
}

// Renders a single option card (value comes from your durationOptions list)
function optionCardHTML(value, text){
  const price = prices[value] ?? 0;
  const id = `dur-${value.replace(/[^a-zA-Z0-9_-]/g, '')}`;
  return `
    <label class="option-card" data-value="${value}">
      <input id="${id}" type="radio" name="reserve-duration" value="${value}">
      <div class="opt-main">
        <span class="opt-title">${text}</span>
        <span class="opt-price">${peso(price)}</span>
      </div>
    </label>
  `;
}


async function openReserveModal(locker){
  const balance = await getWalletBalance();
  const initial = durationOptions.some(o => o.value === DEFAULT_DURATION)
    ? DEFAULT_DURATION
    : '30min';

  const optionsHtml = durationOptions.map(o => optionCardHTML(o.value, o.text)).join('');

  return Swal.fire({
    title: `Reserve Locker ${locker + 1}`,
    html: `
      <div class="reserve-modal">
        <div class="wallet-inline" aria-live="polite">
          <span>Wallet</span>
          <span id="rm-balance" class="amount">${peso(balance)}</span>
        </div>

        <div class="section-title">Choose time duration</div>

        <div class="duration-grid" id="rm-grid" role="radiogroup" aria-label="Select rental duration">
          ${optionsHtml}
        </div>

        <div class="wallet-warning" id="rm-warning"></div>

        <div class="summary-row" aria-live="polite">
          <div id="rm-selected">Selected: <b>${labelForDuration(initial)}</b></div>
          <div class="total">Total: <span id="rm-total">${peso(prices[initial] ?? 0)}</span></div>
        </div>

        <div class="reference" style="margin-top:6px">
          <span class="ref-label">Need more funds?</span>
          <a class="ref-value" href="${TOPUP_URL}" target="_blank" rel="noopener">Top Up</a>
        </div>
      </div>
    `,
    customClass: { popup: 'reserve-popup' },
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Pay Now',
    confirmButtonColor: '#2563eb',
    preConfirm: () => {
      const checked = document.querySelector('input[name="reserve-duration"]:checked');
      if (!checked) {
        Swal.showValidationMessage('Please choose a time duration');
        return false;
      }
      return checked.value;
    },
    didOpen: () => {
      // mark initial selection
      const initEl = document.querySelector(`#dur-${CSS.escape(initial)}`);
      if (initEl) initEl.checked = true;

      const grid       = document.getElementById('rm-grid');
      const totalEl    = document.getElementById('rm-total');
      const warnEl     = document.getElementById('rm-warning');
      const selectedEl = document.getElementById('rm-selected');
      const confirmBtn = Swal.getConfirmButton();

      // visual affordability markers
      function markAffordability(){
        grid.querySelectorAll('.option-card').forEach(card => {
          const value = card.getAttribute('data-value');
          const price = Number(prices[value] || 0);
          card.classList.toggle('insufficient', price > balance);
        });
      }

      function update(){
        const checked = grid.querySelector('input[name="reserve-duration"]:checked');
        if (!checked) { confirmBtn.disabled = true; return; }

        const val   = checked.value;
        const price = Number(prices[val] || 0);
        const short = Math.max(0, price - balance);

        totalEl.textContent = peso(price);
        selectedEl.innerHTML = `Selected: <b>${labelForDuration(val)}</b>`;

        if (short > 0){
          warnEl.innerHTML = `Insufficient balance. Short by <b>${peso(short)}</b>. <a class="ref-value" href="${TOPUP_URL}" target="_blank" rel="noopener">Top Up</a>`;
          warnEl.classList.add('show');
          confirmBtn.disabled = true;
        }else{
          warnEl.classList.remove('show');
          warnEl.textContent = '';
          confirmBtn.disabled = false;
        }
      }

      // Click-to-select anywhere on the card
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

/* Entry point from the card */
async function startCheckout(locker) {
  const btn = document.getElementById(`btn${locker}`);
  if (!btn) return;

  btn.disabled = true;
  btn.textContent = 'Checking…';
  btn.setAttribute('aria-disabled', 'true');

  try {
    const res = await fetch(`${API_BASE}/locker_api.php?checkUserLocker=1`, {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    const data = await res.json();

    if (data?.hasLocker || data?.error === 'already_has_locker') {
      if (window.Swal) {
        Swal.fire({
          icon: 'warning',
          title: 'You already have a locker',
          html: `Locker <b>${data.lockerNumber || data.locker_number}</b> is active.<br>
                 Expires at: <b>${new Date(data.expires_at).toLocaleString()}</b>`,
          confirmButtonColor: '#2563eb'
        });
      }
      return;
    }

    // Show duration + wallet modal
    const result = await openReserveModal(locker);
    if (result.isConfirmed && result.value) {
      const chosen = result.value;
      localStorage.setItem('kd_last_duration', chosen);
      await reserveWithWallet(locker, chosen);
    }
  } catch (err) {
    console.error(err);
    if (window.Swal) Swal.fire({ icon:'error', title:'Network Error', text:'Unable to check locker status.' });
  } finally {
    btn.disabled = false;
    btn.textContent = 'Reserve';
    btn.removeAttribute('aria-disabled');
  }
}
window.startCheckout = startCheckout; // needed for onclick in generated markup

/* ------------------ Wallet reservation ------------------ */
async function reserveWithWallet(locker, duration) {
  try {
    if (window.Swal) {
      Swal.fire({
        title: 'Reserving…',
        html: `Debiting your wallet (${labelForDuration(duration)})`,
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
      });
    }

    const url = `${API_BASE}/locker_api.php?generate=${encodeURIComponent(locker + 1)}&duration=${encodeURIComponent(duration)}`;
    const r = await fetch(url, { credentials:'same-origin', cache:'no-store' });
    const data = await r.json();

    if (data?.error) {
      if (data.error === 'insufficient_balance') {
        const needed = Number(data.needed || 0);
        const bal = Number(data.balance || 0);
        const short = Math.max(0, needed - bal);
        if (window.Swal) {
          Swal.fire({
            icon: 'warning',
            title: 'Insufficient Wallet Balance',
            html: `
              <div style="text-align:left">
                <div>Price: <b>${peso(needed)}</b></div>
                <div>Your balance: <b>${peso(bal)}</b></div>
                <div>Short by: <b>${peso(short)}</b></div>
                <hr>
                <p>Please top up using GCash or Maya.</p>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Top Up',
            cancelButtonText: 'Close',
            confirmButtonColor: '#2563eb'
          }).then((res) => {
            if (res.isConfirmed) window.location = TOPUP_URL;
          });
        }
      } else {
        if (window.Swal) Swal.fire({ icon:'error', title:'Reservation failed', text: data.message || data.error });
      }
      return;
    }

    const exp = new Date(data.expires_at);
    const expStr = exp.toLocaleString('en-US', {year:'numeric', month:'long', day:'numeric', hour:'numeric', minute:'numeric', hour12:true});

    if (window.Swal) {
      Swal.fire({
        icon:'success',
        title:'Reservation Confirmed',
        html: `
          <div style="text-align:center">
            <p>Locker ${locker + 1} reserved until <b>${expStr}</b></p>
            <div style="display:flex; justify-content:center; margin-top:12px;">
              <img src="${data.qr_url}" width="150" height="150" alt="Locker QR"
                  style="display:block;" />
            </div>
            <div style="margin-top:10px;">New wallet balance: <b>${peso(data.balance ?? 0)}</b></div>
          </div>
        `,
        confirmButtonText: 'Open My Locker',
        confirmButtonColor: '#2563eb',
      }).then(()=> window.location='/kandado/public/user/mylocker.php');
    }

  } catch (err) {
    if (window.Swal) Swal.fire({ icon:'error', title:'Network Error', text: err.message });
  } finally {
    // Refresh UI & wallet after any attempt
    fetchActiveLockers();
    refreshWalletBalance();
  }
}

/* ------------------ Layout bootstrap ------------------ */
function getTotalLockers(){
  const n = Number(window.DASHBOARD?.totalLockers);
  if (Number.isFinite(n) && n > 0) return n;
  const kpi = parseInt($('#kpiTotal')?.textContent || '0', 10);
  return Number.isFinite(kpi) && kpi > 0 ? kpi : 0;
}

document.addEventListener('DOMContentLoaded', async () => {
  const lockerGrid = $('#lockerGrid');
  const total = getTotalLockers();

  for (let i = 0; i < total; i++) {
    lockerGrid.appendChild(buildLockerCard(i));
  }

  // Filters
  $$('.segmented .seg').forEach(btn=>{
    btn.addEventListener('click', ()=> setStatusFilter(btn.dataset.filter));
  });
  setStatusFilter(currentStatusFilter);

  // Search
  const searchInput = $('#searchInput');
  const clearBtn = $('#clearSearch');
  searchInput?.addEventListener('input', debounce(()=>{
    const val = searchInput.value.trim();
    if (!val){ currentSearch = ''; applyFilters(); return; }
    const num = Number(val);
    currentSearch = (Number.isInteger(num) && num >= 1 && num <= getTotalLockers()) ? num : '';
    applyFilters();
  }, 120));
  clearBtn?.addEventListener('click', ()=>{
    searchInput.value = '';
    currentSearch = '';
    applyFilters();
    searchInput.focus();
  });

  // Online/offline banners
  window.addEventListener('offline', ()=> setOffline(true));
  window.addEventListener('online',  ()=> setOffline(false));

  // Wallet widget + balance polling
  ensureWalletWidget();
  await refreshWalletBalance();
  startWalletPolling(10000);

  // Sync prices from API (keeps UI aligned with server)
  await syncPricesFromAPI();

  // Initial data & polling
  fetchActiveLockers();
  startPolling();

  // Manual refresh button
  $('#refreshBtn')?.addEventListener('click', () => fetchActiveLockers(true));

  // Pause polling when tab hidden
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopPolling();
      stopWalletPolling();
    } else {
      fetchActiveLockers();
      refreshWalletBalance();
      startPolling();
      startWalletPolling(10000);
    }
  });
});
