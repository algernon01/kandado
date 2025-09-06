// user_dashboard.js — with Maintenance support

/* ------------------ Config ------------------ */
const API_BASE = `${window.location.origin}/kandado/api`;

const prices = {
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

const DEFAULT_DURATION = '30s';
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

function updateLastUpdated() { $('#lastUpdated').textContent = `Updated: ${nowStr()}`; }
function statusClasses(el, status){
  el.classList.remove('available','occupied','hold','item','no-item','maintenance'); /* clear helpers */
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

// CSS var reader
const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

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

  const optionsHtml = durationOptions.map(o =>
    `<option value="${o.value}" ${o.value === DEFAULT_DURATION ? 'selected' : ''}>${o.text}</option>`
  ).join('');

  card.innerHTML = `
    <div class="lnum" id="locker${i}-title">Locker ${idx}</div>

    <div id="status${i}" class="status available" data-status="available" aria-live="polite">
      <span class="status-dot" aria-hidden="true"></span>
      <span class="status-text">Available</span>
    </div>

    <div class="price" id="price${i}" aria-label="Price for selected duration">${peso(prices[DEFAULT_DURATION])}</div>

    <div class="duration-container">
      <div class="duration">
        <label class="sr-only" for="duration${i}">Select duration for locker ${idx}</label>
        <select id="duration${i}" aria-label="Duration for locker ${idx}">
          ${optionsHtml}
        </select>
      </div>
      <button id="btn${i}" class="reserveBtn" type="button" onclick="startCheckout(${i})" aria-label="Reserve locker ${idx}">
        Reserve
      </button>
    </div>

    <div class="meta" id="meta${i}">
      <div class="meta-row">
        <span class="meta-label">Time left</span>
        <span class="meta-value" id="time${i}">—</span>
      </div>
    </div>
  `;

  const selectEl = card.querySelector(`#duration${i}`);
  const priceEl  = card.querySelector(`#price${i}`);
  selectEl.value = DEFAULT_DURATION;
  priceEl.textContent = peso(prices[selectEl.value] ?? prices[DEFAULT_DURATION]);
  selectEl.addEventListener('change', (e) => {
    priceEl.textContent = peso(prices[e.target.value] ?? prices[DEFAULT_DURATION]);
  });

  return card;
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
  const maintenance = count.maintenance || 0; // NEW
  const occPct = total ? Math.round((inUse / total) * 100) : 0;

  const totalEl = $('#kpiTotal');
  if (totalEl) totalEl.textContent = String(total);
  $('#kpiAvailable').textContent   = String(available);
  $('#kpiOccupied').textContent    = String(count.occupied || 0);
  $('#kpiHold').textContent        = String(count.hold || 0);
  $('#kpiMaintenance').textContent = String(maintenance); // NEW

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

  $('#kpiOccSub').textContent = `${inUse} in use • ${available} available • ${maintenance} maintenance`; // NEW
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

    let count = { available: 0, occupied: 0, hold: 0, maintenance: 0 }; // NEW

    for (let i = 0; i < getTotalLockers(); i++) {
      const idx = i + 1;
      const statusDiv  = document.getElementById(`status${i}`);
      const btn        = document.getElementById(`btn${i}`);
      const card       = document.getElementById(`locker${i}`);
      const timeEl     = document.getElementById(`time${i}`);
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

      const isMaintenance = Number(lockerData?.maintenance) === 1; // NEW
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
        const hasItem2 = Number(lockerData?.item) === 1;
        const label = hasItem2 ? 'Occupied' : 'Occupied (but no item inside)';
        textEl.textContent = label;

        statusClasses(statusDiv, 'occupied');
        statusDiv.classList.add(hasItem2 ? 'item' : 'no-item');

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

/* ------------------ Checkout flow (unchanged) ------------------ */
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
      btn.disabled = false;
      btn.textContent = 'Reserve';
      btn.removeAttribute('aria-disabled');
      return;
    }

    proceedCheckout(locker);
  } catch (err) {
    console.error(err);
    if (window.Swal) Swal.fire({ icon: 'error', title: 'Network Error', text: 'Unable to check locker status.' });
    btn.disabled = false;
    btn.textContent = 'Reserve';
    btn.removeAttribute('aria-disabled');
  }
}
window.startCheckout = startCheckout; // needed for onclick in generated markup

function proceedCheckout(locker) {
  /* unchanged – payment UI + API call */
  const btn = document.getElementById(`btn${locker}`);
  const durationSelect = document.getElementById(`duration${locker}`);
  const duration = durationSelect ? (durationSelect.value || DEFAULT_DURATION) : DEFAULT_DURATION;
  const amount = prices[duration] ?? prices[DEFAULT_DURATION];

  const lockSvg = `
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
      <circle cx="12" cy="15" r="1.5" fill="currentColor"/>
    </svg>`;

  const stripHtml = `
    <div class="checkout-strip" role="dialog" aria-label="Locker payment">
      <div class="strip-top">
        <div class="store">
          <div class="brand" aria-hidden="true">${lockSvg}</div>
          <div class="store-info">
            <div class="store-subtitle">Kandado</div>
            <div class="store-title">Locker Reservation</div>
          </div>
        </div>
        <div class="amount">${peso(amount)}</div>
      </div>

      <div class="sep"></div>

      <div class="section-title" id="pm-label">Choose Payment</div>
      <div class="pay-methods" role="group" aria-labelledby="pm-label">
        <button class="method active" data-method="GCash" id="pm-gcash" type="button" aria-pressed="true">
          <img src="/kandado/assets/icon/gcash.png" alt="GCash Logo" loading="lazy" decoding="async" width="72" height="48" />
          <span>GCash</span>
        </button>
        <button class="method" data-method="Maya" id="pm-maya" type="button" aria-pressed="false">
          <img src="/kandado/assets/icon/maya.png" alt="Maya Logo" loading="lazy" decoding="async" width="72" height="48" />
          <span>Maya</span>
        </button>
      </div>

      <div class="sep"></div>

      <div class="reference">
        <span class="ref-label">Reference</span>
        <span id="fakeRef" class="ref-value">—</span>
      </div>

      <div class="checkout-actions">
        <button class="btn cancel" id="cancelCheckout" type="button">Cancel</button>
        <button class="btn pay" id="payNow" type="button">Pay</button>
      </div>
    </div>
  `;

  if (!window.Swal) return;
  Swal.fire({
    title: 'Checkout',
    html: stripHtml,
    showConfirmButton: false,
    width: 560,
    didOpen: () => {
      let selectedMethod = 'GCash';
      const pmG = document.getElementById('pm-gcash');
      const pmM = document.getElementById('pm-maya');
      const payNowBtn = document.getElementById('payNow');
      const cancelBtn = document.getElementById('cancelCheckout');
      const refEl = document.getElementById('fakeRef');

      function setActive(el){
        pmG.classList.remove('active'); pmG.setAttribute('aria-pressed', 'false');
        pmM.classList.remove('active'); pmM.setAttribute('aria-pressed', 'false');
        el.classList.add('active');     el.setAttribute('aria-pressed', 'true');
        selectedMethod = el.getAttribute('data-method');
        refEl.textContent = `${selectedMethod.slice(0,2).toUpperCase()}-${Math.floor(100000000 + Math.random()*900000000)}`;
      }

      setActive(pmG);
      pmG.addEventListener('click', ()=> setActive(pmG));
      pmM.addEventListener('click', ()=> setActive(pmM));

      cancelBtn.addEventListener('click', () => {
        Swal.close();
        if (btn) { btn.disabled = false; btn.textContent = 'Reserve'; btn.removeAttribute('aria-disabled'); }
      });

      payNowBtn.addEventListener('click', () => {
        const finalRef = refEl.textContent;
        Swal.fire({
          title:'Processing Payment',
          html:`Confirming <b>${selectedMethod}</b> ${peso(amount)}...`,
          didOpen:()=>Swal.showLoading(),
          allowOutsideClick:false
        });

        setTimeout(async () => {
          try {
            const url = `${API_BASE}/locker_api.php?generate=${encodeURIComponent(locker + 1)}&duration=${encodeURIComponent(duration)}&method=${encodeURIComponent(selectedMethod)}&amount=${encodeURIComponent(amount)}&ref=${encodeURIComponent(finalRef)}`;
            const r = await fetch(url, { credentials:'same-origin', cache:'no-store' });
            const data = await r.json();

            if (data?.error) {
              Swal.fire({ icon:'error', title:'Payment failed', text: data.message || data.error });
              if (btn) { btn.disabled = false; btn.textContent = 'Reserve'; btn.removeAttribute('aria-disabled'); }
              return;
            }

            const exp = new Date(data.expires_at);
            const expStr = exp.toLocaleString('en-US', {year:'numeric', month:'long', day:'numeric', hour:'numeric', minute:'numeric', hour12:true});

            Swal.fire({
              icon:'success',
              title:'Payment Successful',
              html: `
                <div style="text-align:center">
                  <p>Method: <b>${selectedMethod}</b></p>
                  <p>Reference: <code>${finalRef}</code></p>
                  <p>Amount: <b>${peso(amount)}</b></p>
                  <hr>
                  <p>Locker ${locker + 1} reserved until <b>${expStr}</b></p>

                  <div style="display:flex; justify-content:center; margin-top:12px;">
                    <img src="${data.qr_url}" width="150" height="150" alt="Locker QR"
                        style="display:block;" />
                  </div>
                </div>
              `,
            confirmButtonText: 'Open My Locker',
            confirmButtonColor: '#2563eb',
            }).then(()=> window.location='/kandado/public/user/mylocker.php');

          } catch (err) {
            Swal.fire({ icon:'error', title:'Network Error', text: err.message });
            if (btn) { btn.disabled = false; btn.textContent = 'Reserve'; btn.removeAttribute('aria-disabled'); }
          }
        }, 900);
      });
    }
  });
}

/* ------------------ Layout bootstrap ------------------ */
function getTotalLockers(){
  // Prefer the PHP-provided bootstrap; fallback to KPI text if needed
  const n = Number(window.DASHBOARD?.totalLockers);
  if (Number.isFinite(n) && n > 0) return n;
  const kpi = parseInt($('#kpiTotal')?.textContent || '0', 10);
  return Number.isFinite(kpi) && kpi > 0 ? kpi : 0;
}

document.addEventListener('DOMContentLoaded', () => {
  const lockerGrid = $('#lockerGrid');
  const total = getTotalLockers();

  for (let i = 0; i < total; i++) {
    lockerGrid.appendChild(buildLockerCard(i));
  }

  $$('.segmented .seg').forEach(btn=>{
    btn.addEventListener('click', ()=> setStatusFilter(btn.dataset.filter));
  });
  setStatusFilter(currentStatusFilter);

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

  window.addEventListener('offline', ()=> setOffline(true));
  window.addEventListener('online',  ()=> setOffline(false));

  fetchActiveLockers();
  startPolling();

  $('#refreshBtn')?.addEventListener('click', () => fetchActiveLockers(true));

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopPolling();
    else { fetchActiveLockers(); startPolling(); }
  });
});
