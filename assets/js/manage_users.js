(function () {
  'use strict';

  // ---------- helpers ----------
  const onReady = (fn) => {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  };
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const debounce = (fn, ms = 300) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  };

  // Expose functions used by inline HTML attributes
  window.copyText = function (txt) {
    if (!navigator.clipboard) return false;
    navigator.clipboard.writeText(txt).then(() => {
      if (window.Swal) {
        Swal.fire({
          icon: 'success',
          title: 'Copied',
          text: 'Copied to clipboard.',
          timer: 1100,
          showConfirmButton: false
        });
      }
    });
    return false;
  };

  window.confirmRow = function (form, message) {
    if (!window.Swal) return true; // fallback: allow submit if SweetAlert isn't loaded
    Swal.fire({
      title: 'Confirm',
      text: message || 'Are you sure?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      confirmButtonColor: '#2563eb'
    }).then((r) => {
      if (r.isConfirmed) form.submit();
    });
    return false; // prevent default submit; we'll submit in the .then
  };

  window.confirmBulk = function (action) {
    const checked = qsa("input[name='user_ids[]']:checked").map((i) => i.value);
    if (!checked.length) {
      if (window.Swal) {
        Swal.fire({
          icon: 'info',
          title: 'No users selected',
          text: 'Please select at least one user.',
          confirmButtonColor: '#2563eb'
        });
      }
      return;
    }
    const text =
      action === 'delete'
        ? 'Selected users will be permanently deleted!'
        : action === 'archive'
        ? 'Selected users will be archived!'
        : 'Selected users will be restored!';
    const color = action === 'delete' ? '#dc2626' : '#2563eb';
    if (!window.Swal) return; // require SweetAlert for this flow
    Swal.fire({
      title: 'Are you sure?',
      text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: color,
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, proceed!'
    }).then((r) => {
      if (r.isConfirmed) {
        const actionInput = qs('#bulkAction');
        const bulkForm = qs('#bulkForm');
        if (actionInput && bulkForm) {
          actionInput.value = action;
          bulkForm.submit();
        }
      }
    });
  };

  onReady(() => {
    // ---------- Columns dropdown toggle ----------
    (function () {
      const toggle = qs('#colsToggle');
      const menu = qs('#colsMenu');
      if (!toggle || !menu) return;
      const close = () => {
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      };
      toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = menu.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.addEventListener('click', (e) => {
        if (!menu.contains(e.target) && e.target !== toggle) close();
      });
    })();

    // ---------- Selection / bulk bar ----------
    const selAll = qs('#selectAll');

    function selCount() {
      return qsa("input[name='user_ids[]']:checked").length;
    }

    function updateSelState() {
      const n = selCount();
      const bar = qs('#bulkBar');
      const counter = qs('#selCount');
      if (counter) counter.textContent = n ? n + ' selected' : 'No selection';
      if (window.matchMedia('(max-width: 860px)').matches) {
        if (bar) bar.style.display = n ? 'flex' : 'none';
      } else if (bar) {
        // Ensure desktop layout doesn't leave it stuck visible
        bar.style.display = '';
      }
    }

    if (selAll) {
      selAll.addEventListener('click', () => {
        qsa("input[name='user_ids[]']").forEach((cb) => {
          if (!cb.disabled) cb.checked = selAll.checked;
        });
        updateSelState();
      });
    }
    qsa('.row-check').forEach((cb) => cb.addEventListener('change', updateSelState));
    window.addEventListener('resize', updateSelState);
    updateSelState();

    // ---------- Persist column visibility ----------
    (function () {
      const key = 'col_visibility_users';
      let state = {};
      try {
        state = JSON.parse(localStorage.getItem(key) || '{}') || {};
      } catch (_) {
        state = {};
      }
      const apply = () => {
        ['email', 'role', 'created', 'status', 'locker'].forEach((col) => {
          const vis = state[col] !== false;
          qsa('.col-' + col).forEach((el) => el.classList.toggle('hidden-col', !vis));
          const input = qs('.col-toggle[data-col="' + col + '"]');
          if (input) input.checked = vis;
        });
      };
      qsa('.col-toggle').forEach((inp) =>
        inp.addEventListener('change', () => {
          state[inp.dataset.col] = inp.checked;
          localStorage.setItem(key, JSON.stringify(state));
          apply();
        })
      );
      apply();
    })();

    // ---------- Autosubmit search with debounce ----------
    (function () {
      const input = qs('#searchInput');
      const form = qs('#filtersForm');
      if (!input || !form) return;
      const run = debounce(() => form.submit(), 500);
      input.addEventListener('input', run);
    })();

    // ---------- View modal (fixed: removed stray 'l' and ensured bindings) ----------
    (function () {
      const modal = qs('#viewModal');
      if (!modal) return;

      const archChip = qs('#vmArchivedChip');
      const open = () => modal.classList.add('open');
      const close = () => modal.classList.remove('open');

      function setText(id, val) {
        const el = qs('#' + id);
        if (el) el.textContent = val || '—';
      }

      function fillModal(data) {
        const ava = qs('#vmAvatar');
        if (ava) {
          ava.src = data.img || '/kandado/assets/uploads/default.jpg';
          ava.onerror = () => {
            ava.onerror = null;
            ava.src = '/kandado/assets/uploads/default.jpg';
          };
        }
        setText('vmName', data.name);
        setText('vmId', data.id);
        const email = data.email || '';
        const a = qs('#vmEmail');
        if (a) {
          a.textContent = email || '—';
          a.href = email ? 'mailto:' + email : '#';
        }
        setText('vmRole', data.role === 'admin' ? 'Admin' : 'User');
        setText('vmCreated', data.created);
        setText('vmStatus', data.archived ? 'Archived' : 'Active');
        setText('vmLocker', data.locker ? 'Locker ' + data.locker : 'None');

        const copyBtn = qs('#vmCopyEmail');
        if (copyBtn) {
          copyBtn.onclick = () => email && window.copyText(email);
        }
        if (archChip) archChip.style.display = data.archived ? 'inline-flex' : 'none';
      }

      function parseRowPayload(tr) {
        try {
          return JSON.parse(tr.dataset.user || '{}');
        } catch (_) {
          return {};
        }
      }

      qsa('.viewBtn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          const tr = e.target.closest('tr');
          if (!tr) return;
          const data = parseRowPayload(tr);
          fillModal(data);
          open();
        });
      });

      qsa('.user-row').forEach((tr) => {
        tr.addEventListener('click', (e) => {
          const target = e.target;
          if (
            (target.closest && (target.closest('button') || target.closest('a') || target.closest('input'))) ||
            target.tagName === 'INPUT' ||
            target.tagName === 'BUTTON' ||
            target.tagName === 'A'
          ) {
            return;
          }
          const data = parseRowPayload(tr);
          fillModal(data);
          open();
        });
      });

      modal.addEventListener('click', (e) => {
        if (e.target === modal || (e.target.hasAttribute && e.target.hasAttribute('data-close'))) close();
      });
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
      });
    })();

    // ---------- Action toast (success/error) ----------
    (function () {
      if (!window.Swal) return;
      const body = document.body;
      if (!body) return;
      const done = body.getAttribute('data-action-done') || '';
      const err = body.getAttribute('data-action-error') || '';
      if (done) {
        Swal.fire({
          icon: 'success',
          title: 'Success',
          text: 'Action completed: ' + done,
          confirmButtonColor: '#2563eb'
        });
      }
      if (err) {
        Swal.fire({
          icon: 'error',
          title: 'Oops',
          text: err,
          confirmButtonColor: '#2563eb'
        });
      }
    })();
  });
})();
