(function(){
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const checkAll = $('#checkAll');
  const rowChecks = $$('.rowcheck');
  const bulkForm = $('#bulkForm');
  const bulkAction = $('#bulkAction');
  const bulkCount = $('#bulkCount');
  const formAllRead = $('#formAllRead');
  const btnAllRead = $('#btnMarkAllRead');

  function refreshBulk(){
    const n = rowChecks.filter(c => c.checked).length;
    if (checkAll){
      checkAll.checked = n === rowChecks.length && n > 0;
      checkAll.indeterminate = n > 0 && n < rowChecks.length;
    }
    if (bulkCount) bulkCount.textContent = n + ' selected';
  }

  if (checkAll){
    checkAll.addEventListener('change', () => {
      rowChecks.forEach(c => { c.checked = checkAll.checked; });
      refreshBulk();
    });
  }
  rowChecks.forEach(c => c.addEventListener('change', refreshBulk));
  refreshBulk();

  window.submitBulk = function(action){
    const picked = rowChecks.some(c => c.checked);
    if (!picked){
      Swal.fire({ icon:'info', title:'Select at least one alert', timer:1600, showConfirmButton:false });
      return;
    }
    if (action === 'delete'){
      Swal.fire({
        icon:'warning',
        title:'Delete selected alert(s)?',
        text:'This action cannot be undone.',
        showCancelButton:true,
        confirmButtonText:'Delete',
        cancelButtonText:'Cancel'
      }).then(r => {
        if (r.isConfirmed){
          if (bulkAction) bulkAction.value = action;
          if (bulkForm) bulkForm.submit();
        }
      });
      return;
    }
    if (bulkAction) bulkAction.value = action;
    if (bulkForm) bulkForm.submit();
  };

  if (btnAllRead && formAllRead){
    btnAllRead.addEventListener('click', () => {
      Swal.fire({
        icon:'question',
        title:'Mark all alerts (7 days) as read?',
        showCancelButton:true,
        confirmButtonText:'Yes, mark all',
        cancelButtonText:'Cancel'
      }).then(r => { if (r.isConfirmed) formAllRead.submit(); });
    });
  }

  $$('form[data-confirm]').forEach(f => {
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      const msg = f.getAttribute('data-confirm') || 'Are you sure?';
      Swal.fire({
        icon:'warning',
        title:msg,
        showCancelButton:true,
        confirmButtonText:'Yes',
        cancelButtonText:'Cancel'
      }).then(r => { if (r.isConfirmed) f.submit(); });
    });
  });

  const urlParams = new URLSearchParams(location.search);
  if (urlParams.get('autorefresh') === '1'){
    setInterval(() => location.reload(), 30000);
  }

  const btnStop = $('#btnStopAlerting');
  if (btnStop){
    btnStop.addEventListener('click', () => {
      Swal.fire({
        icon:'warning',
        title:'Stop alerting now?',
        text:'This will attempt to silence active alarms.',
        showCancelButton:true,
        confirmButtonText:'Stop',
        cancelButtonText:'Cancel'
      }).then(r => {
        if (!r.isConfirmed) return;
        fetch('/kandado/api/locker_api.php?stop_alert=1&secret=MYSECRET123', { method:'GET' })
          .then(res => {
            if (res.ok){
              Swal.fire({
                toast:true,
                position:'top-end',
                icon:'success',
                title:'Alerts stopped',
                showConfirmButton:false,
                timer:1600
              });
            } else {
              Swal.fire({
                icon:'error',
                title:'Failed to stop alerts',
                text:'Server returned ' + res.status
              });
            }
          })
          .catch(() => {
            Swal.fire({
              icon:'error',
              title:'Network error',
              text:'Please try again.'
            });
          });
      });
    });
  }

  const flashHost = document.querySelector('[data-flash-icon][data-flash-title]');
  if (flashHost){
    const icon = flashHost.getAttribute('data-flash-icon') || 'info';
    const title = flashHost.getAttribute('data-flash-title') || '';
    Swal.fire({
      toast:true,
      position:'top-end',
      icon,
      title,
      showConfirmButton:false,
      timer:2200,
      timerProgressBar:true
    });
    flashHost.removeAttribute('data-flash-icon');
    flashHost.removeAttribute('data-flash-title');
  }
})();
