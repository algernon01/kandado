(function () {
  const $  = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  const APP = window.APP || {};
  const USERS = Array.isArray(APP.USERS) ? APP.USERS : [];
  const FLASH = APP.FLASH || null;

  // Flash
  if (FLASH) {
    Swal.fire({
      icon: FLASH.type,
      title: FLASH.title,
      text: FLASH.msg,
      confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb'
    });
  }

  // Open/Close Modals
  const open = (id) => $(id)?.classList.add('open');
  const close = (id) => $(id)?.classList.remove('open');
  $$('#open-create, #open-create-2').forEach(b => b?.addEventListener('click', () => {
    // Auto-fill reference if empty
    const ref = $('#c_reference_no');
    if (ref && !ref.value) genRef(ref);
    open('#create-modal');
  }));
  $$('[data-close]').forEach(b => b.addEventListener('click', () => close(b.getAttribute('data-close'))));
  $$('.modal').forEach(m => {
    m.addEventListener('click', (e) => { if (e.target === m) close('#'+m.id); });
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') close('#'+m.id); }, { once: true });
  });

  // Reference generator
  function genRef(inputEl) {
    const d = new Date();
    const pad = n => n.toString().padStart(2,'0');
    const val = `PAY-${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
    inputEl.value = val;
  }
  $('#gen-ref')?.addEventListener('click', () => genRef($('#c_reference_no')));

  // Copy to clipboard (inline button)
  $$('[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      const sel = btn.getAttribute('data-copy');
      const el = $(sel);
      if (!el) return;
      const text = el.textContent.trim();
      navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ icon:'success', title:'Copied', text:'Reference copied to clipboard', timer:1200, showConfirmButton:false });
      });
    });
  });

  // Delete confirm
  $$('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      Swal.fire({
        title: 'Delete payment?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--brand-1').trim() || '#3353bb',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, delete',
      }).then((res) => { if (res.isConfirmed) btn.closest('form').submit(); });
    });
  });

  // View modal fill
  $$('.btn-view').forEach(btn => {
    btn.addEventListener('click', () => {
      const body = $('#view-body'); body.innerHTML = '';
      const row = {
        Date: btn.dataset.date,
        User: btn.dataset.user,
        Email: btn.dataset.email,
        'Locker #': '#'+btn.dataset.locker,
        Method: btn.dataset.method,
        Amount: '₱ ' + (parseFloat(btn.dataset.amount || '0').toFixed(2)),
        'Reference #': btn.dataset.ref,
        Duration: btn.dataset.duration
      };
      const frag = document.createDocumentFragment();
      Object.entries(row).forEach(([k,v]) => {
        const wrap = document.createElement('div'); wrap.className = 'field';
        const lab = document.createElement('label'); lab.textContent = k;
        const val = document.createElement('div'); val.textContent = v; val.style.fontWeight = '800';
        wrap.append(lab,val); frag.append(wrap);
      });
      body.append(frag);
      open('#view-modal');
    });
  });

  // ---------------- Searchable user picker ----------------
  function initUserPicker(comboId, inputId, listId, hiddenId) {
    const combo = $(comboId); if (!combo) return;
    const input = $(inputId), list = $(listId), hidden = $(hiddenId);
    const clearBtn = combo.querySelector('.clear');

    function render(items) {
      list.innerHTML = '';
      if (!items.length) {
        const d = document.createElement('div');
        d.className = 'item'; d.textContent = 'No matches'; d.style.color='var(--muted)'; d.style.cursor='default';
        list.appendChild(d); return;
      }
      items.slice(0, 12).forEach((u, idx) => {
        const div = document.createElement('div');
        div.className = 'item' + (idx===0 ? ' active' : '');
        div.setAttribute('role','option');
        div.dataset.id = u.id; div.textContent = u.label;
        list.appendChild(div);
      });
    }

    function openList() { list.hidden = false; }
    function closeList() { list.hidden = true; }

    function filter(q) {
      q = (q||'').toLowerCase().trim();
      const items = q ? USERS.filter(u => u.label.toLowerCase().includes(q)) : USERS;
      render(items); openList();
    }

    function selectByEl(el) {
      const id = parseInt(el?.dataset.id || '0', 10);
      if (!id) return;
      hidden.value = String(id);
      input.value  = USERS.find(u => u.id === id)?.label || '';
      closeList();
    }

    input.addEventListener('input', () => { hidden.value=''; filter(input.value); });
    input.addEventListener('focus', () => { filter(input.value); });
    input.addEventListener('keydown', (e) => {
      const options = Array.from(list.querySelectorAll('.item'));
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const cur = list.querySelector('.item.active');
        let idx = options.indexOf(cur);
        if (e.key === 'ArrowDown') idx = Math.min(options.length-1, idx+1);
        if (e.key === 'ArrowUp')   idx = Math.max(0, idx-1);
        options.forEach(o => o.classList.remove('active'));
        if (options[idx]) options[idx].classList.add('active');
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const cur = list.querySelector('.item.active') || options[0];
        if (cur) selectByEl(cur);
      } else if (e.key === 'Escape') {
        closeList();
      }
    });
    list.addEventListener('mousedown', (e) => {
      const el = e.target.closest('.item'); if (el) selectByEl(el);
    });
    document.addEventListener('click', (e) => { if (!combo.contains(e.target)) closeList(); });
    clearBtn?.addEventListener('click', () => { input.value=''; hidden.value=''; input.focus(); filter(''); });

    // If there is an initial hidden value, show label
    if (hidden.value) {
      const id = parseInt(hidden.value, 10);
      const label = USERS.find(u => u.id === id)?.label || '';
      if (label) input.value = label;
    }
  }

  // Init pickers
  initUserPicker('#c_combo', '#c_user_input', '#c_user_list', '#c_user_id');
  initUserPicker('#e_combo', '#e_user_input', '#e_user_list', '#e_user_id');

  // Edit modal prefill
  function findUserLabel(id) {
    id = parseInt(id,10);
    const u = USERS.find(u => u.id===id);
    return u ? u.label : '';
  }
  $$('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      $('#e_id').value = btn.dataset.id;
      $('#e_user_id').value = btn.dataset.user_id;
      $('#e_user_input').value = findUserLabel(btn.dataset.user_id);
      $('#e_locker').value = btn.dataset.locker;
      $('#e_method').value = btn.dataset.method;
      $('#e_amount').value = btn.dataset.amount;
      $('#e_reference_no').value = btn.dataset.ref;
      $('#e_duration').value = btn.dataset.duration;
      $('#e_created_at').value = btn.dataset.created_at;
      open('#edit-modal');
    });
  });

})();
