
(() => {
  const toggle = document.getElementById('colsToggle');
  const menu = document.getElementById('colsMenu');
  if(!toggle || !menu) return;
  const close = () => { menu.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); };
  toggle.addEventListener('click', (e)=>{ e.stopPropagation(); const open=menu.classList.toggle('open'); toggle.setAttribute('aria-expanded', open?'true':'false'); });
  document.addEventListener('click', (e)=>{ if(!menu.contains(e.target) && e.target!==toggle) close(); });
})();


const selAll = document.getElementById('selectAll');
function selCount(){ return document.querySelectorAll("input[name='user_ids[]']:checked").length; }
function updateSelState(){
  const n=selCount();
  const bar=document.getElementById('bulkBar');
  const counter=document.getElementById('selCount');
  if(counter) counter.textContent = n? (n+' selected'):'No selection';
  if (window.matchMedia('(max-width: 860px)').matches) { if(bar) bar.style.display = n? 'flex':'none'; }
}
selAll && selAll.addEventListener('click', ()=>{
  document.querySelectorAll("input[name='user_ids[]']").forEach(cb=>{ if(!cb.disabled) cb.checked=selAll.checked;});
  updateSelState();
});
Array.from(document.querySelectorAll('.row-check')).forEach(cb=> cb.addEventListener('change', updateSelState));
window.addEventListener('resize', updateSelState);
document.addEventListener('DOMContentLoaded', updateSelState);


function confirmBulk(action){
  const checked=Array.from(document.querySelectorAll("input[name='user_ids[]']:checked")).map(i=>i.value);
  if(!checked.length){
    Swal.fire({icon:'info',title:'No users selected',text:'Please select at least one user.',confirmButtonColor:'#2563eb'});
    return;
  }
  const text = action==='delete'
      ? 'Selected users will be permanently deleted!'
      : (action==='archive' ? 'Selected users will be archived!' : 'Selected users will be restored!');
  const color = action==='delete'?'#dc2626':'#2563eb';
  Swal.fire({ title:'Are you sure?', text, icon:'warning', showCancelButton:true, confirmButtonColor:color, cancelButtonColor:'#6b7280', confirmButtonText:'Yes, proceed!' })
   .then(r=>{ if(r.isConfirmed){ document.getElementById('bulkAction').value=action; document.getElementById('bulkForm').submit(); }});
}
function confirmRow(form, message){
  return Swal.fire({title:'Confirm', text:message||'Are you sure?', icon:'question', showCancelButton:true, confirmButtonText:'Yes', confirmButtonColor:'#2563eb'})
    .then(r=>{ if(r.isConfirmed){ form.submit(); } return false; }), false;
}


function copyText(txt){
  navigator.clipboard.writeText(txt).then(()=>{
    Swal.fire({ icon:'success', title:'Copied', text:'Copied to clipboard.', timer:1100, showConfirmButton:false });
  });
}


(function(){
  const key='col_visibility_users';
  const state=JSON.parse(localStorage.getItem(key)||'{}');
  const apply=()=>{
    ['email','role','created','status','locker'].forEach(col=>{
      const vis=state[col]!==false;
      document.querySelectorAll('.col-'+col).forEach(el=>el.classList.toggle('hidden-col',!vis));
      const input=document.querySelector('.col-toggle[data-col="'+col+'"]');
      if(input) input.checked=vis;
    });
  };
  document.querySelectorAll('.col-toggle').forEach(inp=> inp.addEventListener('change',()=>{
    state[inp.dataset.col]=inp.checked; localStorage.setItem(key, JSON.stringify(state)); apply();
  }));
  apply();
})();


(function(){
  const input=document.getElementById('searchInput'); const form=document.getElementById('filtersForm'); let t; if(!input) return;
  input.addEventListener('input',()=>{ clearTimeout(t); t=setTimeout(()=>{ form.submit(); },500); });
})();

l
(function(){
  const modal=document.getElementById('viewModal');
  if(!modal) return;
  const archChip=document.getElementById('vmArchivedChip');
  const open = ()=> modal.classList.add('open');
  const close = ()=> modal.classList.remove('open');

  function fillModal(data){
    const ava=document.getElementById('vmAvatar'); if(ava){ ava.src=data.img; ava.onerror=()=>{ ava.src='/kandado/assets/uploads/default.jpg'; }; }
    const setText=(id,val)=>{ const el=document.getElementById(id); if(el) el.textContent = val||'—'; };
    setText('vmName', data.name);
    setText('vmId', data.id);
    const email = data.email||''; const a=document.getElementById('vmEmail'); if(a){ a.textContent=email||'—'; a.href=email?('mailto:'+email):'#'; }
    setText('vmRole', data.role==='admin'?'Admin':'User');
    setText('vmCreated', data.created);
    setText('vmStatus', data.archived? 'Archived':'Active');
    setText('vmLocker', data.locker? ('Locker '+data.locker) : 'None');
    const copyBtn=document.getElementById('vmCopyEmail'); if(copyBtn){ copyBtn.onclick = ()=> email && copyText(email); }
    if(archChip){ archChip.style.display = data.archived ? 'inline-flex':'none'; }
  }

  Array.from(document.querySelectorAll('.viewBtn')).forEach(btn=>{
    btn.addEventListener('click', (e)=>{
      const tr=e.target.closest('tr'); if(!tr) return;
      const data=JSON.parse(tr.dataset.user||'{}'); fillModal(data); open();
    });
  });
  Array.from(document.querySelectorAll('.user-row')).forEach(tr=>{
    tr.addEventListener('click', (e)=>{
      const target=e.target;
      if(target.closest('button')||target.closest('a')||target.closest('input')||target.tagName==='INPUT' || target.tagName==='BUTTON') return;
      const data=JSON.parse(tr.dataset.user||'{}'); fillModal(data); open();
    });
  });

  modal.addEventListener('click', (e)=>{ if(e.target===modal || e.target.hasAttribute('data-close')) close(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') close(); });
})();


(function(){
  const body = document.body;
  if (!body) return;
  const done = body.getAttribute('data-action-done') || '';
  const err  = body.getAttribute('data-action-error') || '';
  if (done) {
    Swal.fire({ icon:'success', title:'Success', text:'Action completed: ' + done, confirmButtonColor:'#2563eb' });
  }
  if (err) {
    Swal.fire({ icon:'error', title:'Oops', text: err, confirmButtonColor:'#2563eb' });
  }
})();
