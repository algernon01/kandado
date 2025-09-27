// ---------- Filter form: clean 'locker' on submit ----------
document.addEventListener('DOMContentLoaded', function () {
  const filterForm = document.getElementById('filterForm');
  filterForm?.addEventListener('submit', function(){
    const lockerEl = document.getElementById('locker');
    if (!lockerEl) return;
    const m = (lockerEl.value || '').match(/\d+/);
    lockerEl.value = m ? m[0] : ''; 
  });

  // ---------- Selection handling ----------
  const rowChecks = Array.from(document.querySelectorAll('.row-check'));
  const groupSelectAll = Array.from(document.querySelectorAll('.select-all'));
  const bulkBtn = document.getElementById('bulkBtn');
  const bulkForm = document.getElementById('bulkForm');
  const selInfo = document.getElementById('selInfo');

  function updateSelectionUI(){
    const ids = rowChecks.filter(cb=>cb.checked).map(cb=>cb.value);
    if (bulkBtn) bulkBtn.disabled = ids.length===0;
    if (selInfo) {
      if (ids.length>0) { selInfo.style.display='block'; selInfo.textContent = ids.length + ' selected'; }
      else { selInfo.style.display='none'; selInfo.textContent=''; }
    }
  }
  rowChecks.forEach(cb=>cb.addEventListener('change', updateSelectionUI));

  groupSelectAll.forEach(sa=>{
    sa.addEventListener('change', function(){
      const card = this.closest('.card');
      if (!card) return;
      card.querySelectorAll('.select-all').forEach(x=>{ if (x!==this) x.checked=this.checked; });
      card.querySelectorAll('.row-check').forEach(cb=>cb.checked=this.checked);
      updateSelectionUI();
    });
  });


  if (bulkForm) {
    bulkForm.addEventListener('submit', (e)=>{
      const ids = rowChecks.filter(cb=>cb.checked).map(cb=>cb.value);
      if (ids.length===0) { e.preventDefault(); return; }
      e.preventDefault();
      const isArchive = (bulkForm.querySelector('input[name="action"]').value === 'bulk_archive');
      Swal.fire({
        title: isArchive? 'Archive selected records?' : 'Unarchive selected records?',
        text: isArchive? 'They will be hidden from Active. You can undo immediately.' : 'They will return to Active.',
        icon: 'warning', showCancelButton:true, confirmButtonColor: isArchive?'#dc2626':'#1e3a8a', cancelButtonColor:'#6b7280',
        confirmButtonText: isArchive? 'Yes, archive' : 'Yes, unarchive'
      }).then(res=>{
        if (res.isConfirmed) {
          bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el=>el.remove());
          ids.forEach(id=>{
            const i=document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=id; bulkForm.appendChild(i);
          });
          bulkForm.submit();
        }
      });
    });
  }

  // ---------- Toggle code visibility ----------
  const toggleBtn = document.getElementById('toggleCodes');
  const CODE_MASK = '••••••';
  const LS_KEY = 'codesHidden';

  function setCodesHidden(hide){
    document.querySelectorAll('.code.secret').forEach(el=>{
      if (hide) {
        if (!el.dataset.real) el.dataset.real = el.textContent;
        el.textContent = CODE_MASK;
      } else {
        el.textContent = el.dataset.real ?? '';
      }
    });
    if (toggleBtn) {
      toggleBtn.innerHTML = hide
        ? '<i class="fa-solid fa-eye"></i> Show Codes'
        : '<i class="fa-solid fa-eye-slash"></i> Hide Codes';
    }
    try { localStorage.setItem(LS_KEY, hide ? '1':'0'); } catch(_e){}
  }

  let initialHidden = true;
  try {
    const v = localStorage.getItem(LS_KEY);
    if (v === '0') initialHidden = false;
  } catch(_e){}
  setCodesHidden(initialHidden);

  if (toggleBtn) {
    toggleBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      const hide = (localStorage.getItem(LS_KEY) ?? '1') === '1';
      setCodesHidden(!hide);
    });
  }

  // ---------- Toasts for last action (success only, same behavior) ----------
  const flashEl = document.getElementById('flash-data');
  if (flashEl && flashEl.dataset.type === 'success' && flashEl.dataset.msg) {
    Swal.fire({icon:'success', title:'Done', text:flashEl.dataset.msg, confirmButtonColor:'#1e3a8a'});
  }
});
