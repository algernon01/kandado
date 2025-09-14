// ../../assets/js/admin_dashboard.js
// Runs after DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // ===================
  // Status Filter Chips
  // ===================
  const statusChips = document.querySelectorAll('.filter-chip');
  const cards = document.querySelectorAll('.locker-card');
  let currentFilter = 'all';

  statusChips.forEach(chip => {
    chip.addEventListener('click', () => {
      statusChips.forEach(c => c.setAttribute('aria-pressed','false'));
      chip.setAttribute('aria-pressed','true');
      currentFilter = chip.dataset.filter;
      cards.forEach(card => {
        const status = card.getAttribute('data-status'); // available | occupied | hold
        const maint  = card.getAttribute('data-maintenance') === '1';
        let show = true;
        if (currentFilter === 'available') show = !maint && status === 'available';
        else if (currentFilter === 'occupied') show = !maint && status === 'occupied';
        else if (currentFilter === 'hold') show = !maint && status === 'hold';
        else if (currentFilter === 'maintenance') show = maint;
        else show = true; // all
        card.style.display = show ? '' : 'none';
      });
    });
  });

  // ==================
  // Reset locker
  // ==================
  document.querySelectorAll('.btn-reset').forEach(btn => {
    btn.addEventListener('click', () => {
      const lockerNumber = btn.dataset.locker;
      Swal.fire({
        title:`Reset Locker #${lockerNumber}?`,
        text:"This will mark the locker as available.",
        icon:"warning",
        showCancelButton:true,
        confirmButtonText:"Yes, reset",
        cancelButtonText:"Cancel",
        confirmButtonColor:"#2c5cff"
      }).then(result => {
        if(result.isConfirmed){
          fetch(`/kandado/api/reset_locker.php?locker=${encodeURIComponent(lockerNumber)}`)
            .then(res => res.json())
            .then(data => {
              if(data.success){
                Swal.fire('Reset!', `Locker #${lockerNumber} is now available.`,'success')
                  .then(()=>location.reload());
              } else {
                Swal.fire('Error', data.message || 'Failed to reset locker.','error');
              }
            }).catch(err => Swal.fire('Error', err.message,'error'));
        }
      });
    });
  });

  // ==================
  // Release locker (hold -> available)
  // ==================
  document.querySelectorAll('.btn-release').forEach(btn => {
    btn.addEventListener('click', () => {
      const lockerNumber = btn.dataset.locker;
      Swal.fire({
        title:`Release Locker #${lockerNumber}?`,
        text:"This will mark the locker as available.",
        icon:"warning",
        showCancelButton:true,
        confirmButtonText:"Yes, release",
        cancelButtonText:"Cancel",
        confirmButtonColor:"#10b981"
      }).then(result => {
        if(result.isConfirmed){
          fetch(`/kandado/api/release_locker.php?locker=${encodeURIComponent(lockerNumber)}`)
            .then(res => res.json())
            .then(data => {
              if(data.success){
                Swal.fire('Released!', `Locker #${lockerNumber} is now available.`,'success')
                  .then(()=>location.reload());
              } else {
                Swal.fire('Error', data.message || 'Failed to release locker.','error');
              }
            }).catch(err => Swal.fire('Error', err.message,'error'));
        }
      });
    });
  });

  // ==================
  // Maintenance toggle
  // ==================
  document.querySelectorAll('.btn-maintenance').forEach(btn => {
    btn.addEventListener('click', () => {
      const lockerNumber = btn.dataset.locker;
      const isOn = btn.dataset.maint === '1';
      const mode = isOn ? 'off' : 'on';

      Swal.fire({
        title: `${isOn ? 'End' : 'Start'} Maintenance for Locker #${lockerNumber}?`,
        text: isOn ? "This locker will follow its current status (available/hold/occupied)."
                   : "Locker will be unavailable and excluded from assignments.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: isOn ? "End Maintenance" : "Start Maintenance",
        cancelButtonText: "Cancel",
        confirmButtonColor: isOn ? "#334155" : "#4f46e5"
      }).then(result => {
        if(result.isConfirmed){
          fetch(`/kandado/api/toggle_maintenance.php?locker=${encodeURIComponent(lockerNumber)}&mode=${mode}`)
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Updated!', data.message || 'Maintenance status updated.', 'success')
                  .then(()=>location.reload());
              } else {
                Swal.fire('Error', data.message || 'Failed to update maintenance status.', 'error');
              }
            }).catch(err => Swal.fire('Error', err.message, 'error'));
        }
      });
    });
  });

  // ==================
  // Force unlock (single)
  // ==================
  document.querySelectorAll('.btn-force-unlock').forEach(btn => {
    btn.addEventListener('click', () => {
      const lockerNumber = btn.dataset.locker;
      Swal.fire({
        title:`Force Unlock Locker #${lockerNumber}?`,
        text:"Opens the door without changing status.",
        icon:"warning",
        showCancelButton:true,
        confirmButtonText:"Yes, unlock",
        cancelButtonText:"Cancel",
        confirmButtonColor:"#f59e0b"
      }).then(result => {
        if(result.isConfirmed){
          fetch(`/kandado/api/forced_unlock_api.php?locker=${encodeURIComponent(lockerNumber)}`)
            .then(res => res.json())
            .then(data => {
              if(data.success){
                Swal.fire('Unlocked!', `Locker #${lockerNumber} is now unlocked.`, 'success');
                const card = document.querySelector(`.locker-card[data-locker-number="${lockerNumber}"]`);
                if(card){ card.classList.add('highlight'); setTimeout(()=>card.classList.remove('highlight'), 1500); }
              } else {
                Swal.fire('Error', data.message || 'Failed to unlock locker.', 'error');
              }
            }).catch(err => Swal.fire('Error', err.message,'error'));
        }
      });
    });
  });

  // Force unlock (all)
  const unlockAll = document.getElementById('unlockAll');
  if (unlockAll) {
    unlockAll.addEventListener('click', () => {
      Swal.fire({
        title:`Force Unlock All Lockers?`,
        text:"Opens every locker door without changing status.",
        icon:"warning",
        showCancelButton:true,
        confirmButtonText:"Yes, unlock all",
        cancelButtonText:"Cancel",
        confirmButtonColor:"#ef4444"
      }).then(result => {
        if(result.isConfirmed){
          fetch(`/kandado/api/forced_unlock_api.php?all=1`)
            .then(res => res.json())
            .then(data => {
              if(data.success){
                Swal.fire('Unlocked!', 'All lockers are now unlocked.', 'success');
                document.querySelectorAll('.locker-card').forEach(card => {
                  card.classList.add('highlight');
                  setTimeout(()=>card.classList.remove('highlight'), 1500);
                });
              } else {
                Swal.fire('Error', data.message || 'Failed to unlock all lockers.', 'error');
              }
            }).catch(err => Swal.fire('Error', err.message,'error'));
        }
      });
    });
  }

  // ==================
  // Charts (Usage)
  // ==================
  (function initUsageCharts(){
    const boot = window.DASHBOARD_DATA || {};
    const dailyCtx = document.getElementById('dailyUsageChart')?.getContext('2d');
    const statusCtx = document.getElementById('statusChart')?.getContext('2d');
    if (!dailyCtx || !statusCtx) return;

    new Chart(dailyCtx, {
      type:'line',
      data:{
        labels: Array.isArray(boot.dates) ? boot.dates : [],
        datasets:[{
          label:'Locker Usage',
          data: Array.isArray(boot.usage_counts) ? boot.usage_counts : [],
          fill:true,
          backgroundColor:'rgba(76, 102, 255, 0.15)',
          borderColor:'rgba(44, 92, 255, 1)',
          borderWidth:2,
          pointRadius:4,
          pointHoverRadius:5,
          tension:0.35
        }]
      },
      options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{ legend:{ display:true, position:'top' }, tooltip:{ mode:'index', intersect:false } },
        scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } }
      }
    });

    const st = boot.status || { occupied:0, available:0, hold:0, maintenance:0 };
    new Chart(statusCtx, {
      type:'doughnut',
      data:{
        labels:['Occupied','Available','Hold','Maintenance'],
        datasets:[{
          data:[+st.occupied||0, +st.available||0, +st.hold||0, +st.maintenance||0],
          backgroundColor:['#ef4444','#10b981','#f59e0b','#4f46e5'],
          hoverOffset:10
        }]
      },
      options:{ responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ position:'bottom' } } }
    });
  })();

  // ============================
  // Duration options
  // ============================
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

  const durationSelect = document.getElementById('durationSelect');
  if (durationSelect) {
    durationOptions.forEach(opt => {
      const o = document.createElement('option');
      o.value = opt.value; o.textContent = opt.text;
      durationSelect.appendChild(o);
    });
  }

  // ============================
  // Assign form - VALIDATION + CONFIRM
  // ============================
  (function () {
    const form = document.getElementById('assignLockerForm');
    if (!form) return;

    const selectedUserIdInput = document.getElementById('selectedUserId');
    const lockerSelect = document.getElementById('lockerSelect');
    const durationSelectEl = document.getElementById('durationSelect');

    form.addEventListener('submit', function(e){
      e.preventDefault();
      const missing = [];
      if (!selectedUserIdInput.value) missing.push('Please select a user.');
      if (!lockerSelect.value) missing.push('Please choose an available locker.');
      if (!durationSelectEl.value) missing.push('Please select a duration.');

      if (missing.length) {
        Swal.fire({
          icon: 'error',
          title: 'Missing information',
          html: `<ul style="list-style:none;padding:0;margin:0;">${missing.map(m=>`<li>${m}</li>`).join('')}</ul>`
        });
        return;
      }

      Swal.fire({
        title:"Assign Locker?",
        text:"The selected locker will be assigned to the selected user.",
        icon:"warning",
        showCancelButton:true,
        confirmButtonText:"Yes, assign",
        cancelButtonText:"Cancel",
        confirmButtonColor:"#2c5cff"
      }).then(result => {
        if(result.isConfirmed){
          const formData = new FormData(form);
          fetch('/kandado/api/assign_locker.php', { method:'POST', body:formData })
            .then(res => res.json())
            .then(data => {
              if(data.success){
                Swal.fire('Assigned!', data.message || 'Locker assigned successfully.', 'success')
                  .then(()=>location.reload());
              } else {
                Swal.fire('Error', data.message || 'Failed to assign locker.', 'error');
              }
            })
            .catch(err => Swal.fire('Error', err.message, 'error'));
        }
      });
    });
  })();

  // ============================
  // User table search + 3-wide pagination (stable height)
  // ============================
  (function () {
    const searchInput = document.getElementById('userSearchInput');
    const table = document.getElementById('userTable');
    if (!searchInput || !table) return;

    const tbody = table.querySelector('tbody');
    const dataRows = Array.from(tbody.querySelectorAll('tr[data-user-id]'));
    const noRows = tbody.querySelector('.no-rows');
    const selectedUserIdInput = document.getElementById('selectedUserId');
    const paginationContainer = document.getElementById('pagination');

    const rowsPerPage = 5;
    const groupSize = 3;
    let currentPage = 1;
    let filteredRows = dataRows;

    function renderTable() {
      dataRows.forEach(row => row.style.display = 'none');
      const start = (currentPage - 1) * rowsPerPage;
      const slice = filteredRows.slice(start, start + rowsPerPage);
      slice.forEach(row => row.style.display = '');
      const hasRows = filteredRows.length > 0;
      if (noRows) noRows.style.display = hasRows ? 'none' : '';
      renderPagination();
    }

    function renderPagination() {
      paginationContainer.innerHTML = '';
      const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
      if (totalPages <= 1) return;

      const groupIndex = Math.floor((currentPage - 1) / groupSize);
      const groupStart = groupIndex * groupSize + 1;
      const groupEnd = Math.min(groupStart + groupSize - 1, totalPages);

      if (groupStart > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button'; prevBtn.textContent = '«';
        prevBtn.addEventListener('click', () => { currentPage = Math.max(1, groupStart - groupSize); renderTable(); });
        paginationContainer.appendChild(prevBtn);
      }
      for (let i = groupStart; i <= groupEnd; i++) {
        const btn = document.createElement('button');
        btn.type = 'button'; btn.textContent = i;
        if (i === currentPage) btn.classList.add('active');
        btn.addEventListener('click', () => { currentPage = i; renderTable(); });
        paginationContainer.appendChild(btn);
      }
      if (groupEnd < totalPages) {
        const nextBtn = document.createElement('button');
        nextBtn.type = 'button'; nextBtn.textContent = '»';
        nextBtn.addEventListener('click', () => { currentPage = groupEnd + 1; renderTable(); });
        paginationContainer.appendChild(nextBtn);
      }
    }

    searchInput.addEventListener('keyup', function() {
      const filter = this.value.toLowerCase().trim();
      filteredRows = dataRows.filter(row => {
        const cells = row.getElementsByTagName('td');
        return cells[0].textContent.toLowerCase().includes(filter) ||
               cells[1].textContent.toLowerCase().includes(filter) ||
               cells[2].textContent.toLowerCase().includes(filter);
      });
      currentPage = 1;
      renderTable();
    });

    tbody.addEventListener('click', function(e) {
      const targetRow = e.target.closest('tr[data-user-id]');
      if (!targetRow) return;
      dataRows.forEach(r => r.classList.remove('selected'));
      targetRow.classList.add('selected');
      selectedUserIdInput.value = targetRow.dataset.userId;
    });

    filteredRows = dataRows;
    renderTable();
  })();

  // ============================
  // Recent Activity pagination (5 rows/page, 3-wide pager)
  // ============================
  (function () {
    const list = document.getElementById('activityList');
    if (!list) return;
    const itemsAll = Array.from(list.querySelectorAll('[data-activity="1"]'));
    const pager = document.getElementById('activityPagination');

    const rowsPerPage = 5;
    const groupSize = 3;
    let currentPage = 1;
    let filtered = itemsAll; // reserved if you later add search/filter

    function renderList(){
      itemsAll.forEach(it => it.style.display = 'none');
      const start = (currentPage - 1) * rowsPerPage;
      const slice = filtered.slice(start, start + rowsPerPage);
      slice.forEach(it => it.style.display = '');
      renderPager();
    }

    function renderPager(){
      pager.innerHTML = '';
      const totalPages = Math.ceil(filtered.length / rowsPerPage);
      if (totalPages <= 1) return;

      const gi = Math.floor((currentPage - 1) / groupSize);
      const gs = gi * groupSize + 1;
      const ge = Math.min(gs + groupSize - 1, totalPages);

      if (gs > 1){
        const prev = document.createElement('button');
        prev.type='button'; prev.textContent='«';
        prev.onclick = () => { currentPage = Math.max(1, gs - groupSize); renderList(); };
        pager.appendChild(prev);
      }
      for (let i=gs; i<=ge; i++){
        const b = document.createElement('button');
        b.type='button'; b.textContent = i;
        if (i === currentPage) b.classList.add('active');
        b.onclick = () => { currentPage = i; renderList(); };
        pager.appendChild(b);
      }
      if (ge < totalPages){
        const next = document.createElement('button');
        next.type='button'; next.textContent = '»';
        next.onclick = () => { currentPage = ge + 1; renderList(); };
        pager.appendChild(next);
      }
    }

    renderList();
  })();

  /* ====================================================
     SALES REPORT: Frontend logic (fetch, charts, tables)
     ==================================================== */
  (function(){
    const peso = new Intl.NumberFormat('en-PH', { style:'currency', currency:'PHP', maximumFractionDigits:2 });
    const numberFmt = new Intl.NumberFormat('en-US');

    const salesStart = document.getElementById('salesStart');
    const salesEnd   = document.getElementById('salesEnd');
    const salesMethod= document.getElementById('salesMethod');
    const salesChips = document.querySelectorAll('.sales-chip');
    const applyBtn = document.getElementById('applySales');

    const kpiRevenue   = document.getElementById('kpiRevenue');
    const kpiOrders    = document.getElementById('kpiOrders');
    const kpiAOV       = document.getElementById('kpiAOV');
    const kpiCustomers = document.getElementById('kpiCustomers');

    const tcBody = document.querySelector('#topCustomersTable tbody');
    const tlBody = document.querySelector('#topLockersTable tbody');
    const recentList = document.getElementById('salesRecentList');
    const recentPager = document.getElementById('salesActivityPagination');

    if (!salesStart || !salesEnd) return;

    // Default range: 30D
    const today = new Date();
    const defEnd = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const defStart = new Date(defEnd); defStart.setDate(defEnd.getDate() - 29);
    salesStart.value = toYMD(defStart);
    salesEnd.value   = toYMD(defEnd);

    salesChips.forEach(c => c.setAttribute('aria-pressed', c.dataset.range === '30d' ? 'true' : 'false'));

    // Charts
    let salesTimeChart, salesMethodChart, salesDurationChart;
    const timeCtx = document.getElementById('salesTimeChart')?.getContext('2d');
    const methodCtx = document.getElementById('salesMethodChart')?.getContext('2d');
    const durationCtx = document.getElementById('salesDurationChart')?.getContext('2d');

    function initCharts(){
      if (!timeCtx || !methodCtx || !durationCtx) return;
      if (salesTimeChart) salesTimeChart.destroy();
      if (salesMethodChart) salesMethodChart.destroy();
      if (salesDurationChart) salesDurationChart.destroy();

      salesTimeChart = new Chart(timeCtx, {
        type: 'line',
        data: { labels: [], datasets:[{
          label: 'Revenue',
          data: [],
          fill: true,
          backgroundColor:'rgba(16, 185, 129, 0.15)',
          borderColor:'rgba(16, 185, 129, 1)',
          borderWidth:2,
          pointRadius:3,
          pointHoverRadius:5,
          tension:0.35
        }]},
        options:{
          responsive:true,
          maintainAspectRatio:false,
          plugins:{ legend:{ display:true, position:'top' } },
          scales:{ y:{ beginAtZero:true, ticks:{ callback:(v)=>'₱'+numberFmt.format(v) } } }
        }
      });

      salesMethodChart = new Chart(methodCtx, {
        type: 'doughnut',
        data: {
          labels: ['GCash','Maya','Wallet'],
          datasets: [{
            data: [0,0,0],
            backgroundColor:['#0ea5e9','#8b5cf6','#f59e0b'],
            hoverOffset:10
          }]
        },
        options:{ responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ position:'bottom' } } }
      });

      salesDurationChart = new Chart(durationCtx, {
        type: 'bar',
        data: { labels: [], datasets:[{
          label:'Revenue',
          data: [],
          backgroundColor:'rgba(79, 70, 229, 0.6)'
        }]},
        options:{
          responsive:true,
          maintainAspectRatio:false,
          plugins:{ legend:{ display:false } },
          scales:{
            y:{ beginAtZero:true, ticks:{ callback:(v)=>'₱'+numberFmt.format(v) } },
            x:{ ticks:{ autoSkip:false, maxRotation: 25, minRotation: 0 } }
          }
        }
      });
    }
    initCharts();

    // Quick ranges
    salesChips.forEach(ch => ch.addEventListener('click', () => {
      salesChips.forEach(c => c.setAttribute('aria-pressed','false'));
      ch.setAttribute('aria-pressed','true');
      const now = new Date();
      const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      let start = new Date(end);

      switch (ch.dataset.range) {
        case '7d':
          start.setDate(end.getDate() - 6);
          break;
        case '30d':
          start.setDate(end.getDate() - 29);
          break;
        case 'mtd':
          start = new Date(end.getFullYear(), end.getMonth(), 1);
          break;
        case 'qtd': {
          const q = Math.floor(end.getMonth()/3);
          const sm = q*3;
          start = new Date(end.getFullYear(), sm, 1);
          break;
        }
        case 'ytd':
          start = new Date(end.getFullYear(), 0, 1);
          break;
        case 'all':
          start = new Date(2000,0,1); // safe lower bound for TIMESTAMP
          break;
      }
      salesStart.value = toYMD(start);
      salesEnd.value   = toYMD(end);
      loadSales();
    }));

    if (applyBtn) applyBtn.addEventListener('click', () => loadSales());

    function toYMD(d){
      const y=d.getFullYear();
      const m=('0'+(d.getMonth()+1)).slice(-2);
      const D=('0'+d.getDate()).slice(-2);
      return `${y}-${m}-${D}`;
    }

    function loadSales(){
      const start = salesStart.value;
      const end   = salesEnd.value;
      const method= salesMethod.value || 'all';

      const url = new URL(window.location.origin + window.location.pathname);
      url.searchParams.set('sales_json','1');
      url.searchParams.set('start', start);
      url.searchParams.set('end', end);
      url.searchParams.set('method', method);

      fetch(url.toString())
        .then(r => {
          if (!r.ok) throw new Error('HTTP '+r.status);
          return r.json();
        })
        .then(updateSalesUI)
        .catch(err => {
          console.error(err);
          Swal.fire('Error', 'Failed to load sales data.', 'error');
        });
    }

    function updateSalesUI(data){
      if (!data || !data.success) return;

      // KPIs
      kpiRevenue.textContent = peso.format(data.kpis.revenue || 0);
      kpiOrders.textContent = numberFmt.format(data.kpis.orders || 0);
      kpiAOV.textContent = peso.format(data.kpis.aov || 0);
      kpiCustomers.textContent = numberFmt.format(data.kpis.unique_customers || 0);

      // Time chart
      const labels = data.daily.map(d=>d.day);
      const revs   = data.daily.map(d=>+(d.revenue||0));
      salesTimeChart.data.labels = labels;
      salesTimeChart.data.datasets[0].data = revs;
      salesTimeChart.update();

      // Method chart
      const map = {GCash:0, Maya:0, Wallet:0};
      (data.by_method||[]).forEach(m => { map[m.method] = +m.revenue || 0; });
      const arr = [map.GCash||0, map.Maya||0, map.Wallet||0];
      const total = arr.reduce((a,b)=>a+(+b||0),0);

      // FIX #2: show a neutral ring when all values are zero
      if (total <= 0){
        salesMethodChart.data.labels = ['No data'];
        salesMethodChart.data.datasets[0].data = [1];
        salesMethodChart.data.datasets[0].backgroundColor = ['#e5e7eb'];
      } else {
        salesMethodChart.data.labels = ['GCash','Maya','Wallet'];
        salesMethodChart.data.datasets[0].data = arr;
        salesMethodChart.data.datasets[0].backgroundColor = ['#0ea5e9','#8b5cf6','#f59e0b'];
      }
      salesMethodChart.update();

      // Duration chart (top 8 + Others)
      const durations = (data.by_duration||[]).slice();
      const top = durations.slice(0,8);
      const othersSum = durations.slice(8).reduce((s,x)=>s+(+x.revenue||0),0);
      let dLabels = top.map(x=>x.duration||'Unknown');
      let dData   = top.map(x=>+(x.revenue||0));
      if (othersSum>0){ dLabels.push('Others'); dData.push(othersSum); }
      salesDurationChart.data.labels = dLabels;
      salesDurationChart.data.datasets[0].data = dData;
      salesDurationChart.update();

      // Top customers
      tcBody.innerHTML = '';
      if ((data.top_customers||[]).length === 0){
        tcBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#64748b;">No data.</td></tr>`;
      } else {
        data.top_customers.forEach(c=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHTML(c.name||'Unknown')}</td>
            <td><span class="mono">${escapeHTML(c.email||'')}</span></td>
            <td>${numberFmt.format(c.orders||0)}</td>
            <td><strong>${peso.format(c.revenue||0)}</strong></td>
          `;
          tcBody.appendChild(tr);
        });
      }

      // Top lockers
      tlBody.innerHTML = '';
      if ((data.top_lockers||[]).length === 0){
        tlBody.innerHTML = `<tr><td colspan="3" style="text-align:center;color:#64748b;">No data.</td></tr>`;
      } else {
        data.top_lockers.forEach(l=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>Locker ${escapeHTML(l.locker_number)}</td>
            <td>${numberFmt.format(l.orders||0)}</td>
            <td><strong>${peso.format(l.revenue||0)}</strong></td>
          `;
          tlBody.appendChild(tr);
        });
      }

      // Recent payments (with pagination)
      renderRecentPayments(data.recent||[]);
    }

    function escapeHTML(s){
      return (''+s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function renderRecentPayments(items){
      recentList.innerHTML = '';
      if (!items.length){
        recentList.innerHTML = `<div class="activity-item" style="justify-content:center;"><div class="activity-meta"><div class="sub">No payments found for this range.</div></div></div>`;
        recentPager.innerHTML = '';
        return;
      }
      // Build items
      items.forEach(p=>{
        const dt = new Date(p.created_at);
        const when = dt.toLocaleString();
        const name = (p.first_name||p.last_name) ? `${p.first_name||''} ${p.last_name||''}`.trim() : 'Unknown User';
        const li = document.createElement('div');
        li.className = 'activity-item';
        li.setAttribute('data-sp', '1');
        li.innerHTML = `
          <div class="activity-ico"><i class="fa-solid fa-peso-sign"></i></div>
          <div class="activity-meta">
            <div class="title">${escapeHTML(name)} paid <strong>${peso.format(p.amount||0)}</strong> via ${escapeHTML(p.method||'')}</div>
            <div class="sub">Ref: <span class="mono">${escapeHTML(p.reference_no||'')}</span> • Locker ${escapeHTML(p.locker_number||'')}</div>
          </div>
          <div class="activity-time">${escapeHTML(when)}</div>
        `;
        recentList.appendChild(li);
      });

      // Pagination (8/page)
      const rowsPerPage = 8;
      const all = Array.from(recentList.querySelectorAll('[data-sp="1"]'));
      let currentPage = 1;

      function drawPage(){
        all.forEach(el => el.style.display = 'none');
        const start = (currentPage-1)*rowsPerPage;
        const slice = all.slice(start, start+rowsPerPage);
        slice.forEach(el => el.style.display = '');
        drawPager();
      }
      function drawPager(){
        recentPager.innerHTML = '';
        const totalPages = Math.ceil(all.length / rowsPerPage);
        if (totalPages <= 1) return;
        const groupSize = 3;
        const gi = Math.floor((currentPage - 1) / groupSize);
        const gs = gi * groupSize + 1;
        const ge = Math.min(gs + groupSize - 1, totalPages);

        if (gs > 1){
          const prev = document.createElement('button'); prev.textContent = '«';
          prev.onclick = ()=>{ currentPage = Math.max(1, gs - groupSize); drawPage(); };
          recentPager.appendChild(prev);
        }
        for (let i=gs; i<=ge; i++){
          const b = document.createElement('button'); b.textContent = i;
          if (i===currentPage) b.classList.add('active');
          b.onclick = ()=>{ currentPage=i; drawPage(); };
          recentPager.appendChild(b);
        }
        if (ge < totalPages){
          const next = document.createElement('button'); next.textContent = '»';
          next.onclick = ()=>{ currentPage = ge + 1; drawPage(); };
          recentPager.appendChild(next);
        }
      }
      drawPage();
    }

    /* ============ Global Power Alert (email all current occupants) ============ */
    const globalBtn = document.getElementById('globalPowerAlert');
    if (globalBtn) {
      globalBtn.addEventListener('click', () => {
        Swal.fire({
          icon: 'warning',
          title: 'Send power notice to all active users?',
          html: `This will email <b>everyone currently using a locker</b> asking them to
                 retrieve their items within <b>1 hour</b> because the site is on backup power.`,
          showCancelButton: true,
          confirmButtonText: 'Send notices',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#f59e0b'
        }).then(result => {
          if (!result.isConfirmed) return;

          fetch('/kandado/api/power_alert_all.php')
            .then(r => r.json())
            .then(data => {
              if (data && data.success) {
                const sent = data.sent || 0;
                const skipped = data.skipped || 0;
                const list = (data.recipients || [])
                  .map(r => `<li><span class="mono">${r.name} &lt;${r.email}&gt;</span> — Locker #${r.locker}</li>`)
                  .join('');
                Swal.fire({
                  icon: 'success',
                  title: `Notices sent: ${sent}`,
                  html: `${skipped ? `${skipped} skipped.<br>` : ''}${sent ? `<ul style="text-align:left;margin:8px 0 0 18px;">${list}</ul>` : 'No occupied lockers found.'}`
                });
              } else {
                const msg = (data && (data.message || data.error)) || 'Failed to send notices.';
                Swal.fire('Error', msg, 'error');
              }
            })
            .catch(err => Swal.fire('Error', err.message || 'Request failed', 'error'));
        });
      });
    }

    // Initial load
    loadSales();
  })();
});
