(() => {
  'use strict';

  const root = document.body;
  if (!root || !root.classList.contains('violations-page')) return;

  const SwalRef = window.Swal;
  if (!SwalRef) {
    console.warn('SweetAlert is required for violations page interactions.');
    return;
  }

  // --- Config (PH time) ------------------------------------------------------
  const PH_TZ = 'Asia/Manila';
  const PH_OFFSET_MIN = 8 * 60; // +08:00

  // --- Utils -----------------------------------------------------------------
  const csrfToken  = root.dataset.csrf || '';
  const endpoint   = root.dataset.endpoint || window.location.href;
  const accentColor = '#2d4c8f';

  const escapeHtml = (v) =>
    String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const toastOK = (title) =>
    SwalRef.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title,
      timer: 2200,
      showConfirmButton: false,
      customClass: { container: 'swal-zfix' },
    });

  const toastERR = (text) =>
    SwalRef.fire({
      icon: 'error',
      title: 'Oops',
      text,
      customClass: { container: 'swal-zfix' },
    });

  const postAction = async (action, userId, extra = {}) => {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('user_id', String(userId));
    if (csrfToken) fd.append('csrf', csrfToken);
    if (extra.note) fd.append('note', extra.note);

    const response = await fetch(endpoint, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });

    const text = await response.text();
    if (!response.ok) throw new Error(text || `Request failed (${response.status})`);

    try {
      return JSON.parse(text);
    } catch {
      throw new Error(text.slice(0, 200) || 'Server returned non-JSON response');
    }
  };

  // ---- Time handling (force Asia/Manila for naive strings) ------------------
  const hasZone = (s) => /(Z|[+\-]\d{2}:?\d{2})$/i.test(s);

  // Ensure "YYYY-MM-DDTHH:mm[:ss][+08:00]" for naive inputs.
  const ensureIsoPH = (value) => {
    if (!value) return '';
    let s = String(value).trim();

    // Normalize space to "T"
    if (!s.includes('T')) s = s.replace(' ', 'T');

    // Add seconds if missing (Safari-safe)
    // Matches ...THH:mm end (no :ss)
    if (/T\d{2}:\d{2}$/.test(s)) s += ':00';

    // Append +08:00 if no timezone present
    if (!hasZone(s)) s += '+08:00';

    return s;
  };

  // Parse to epoch ms; accepts ISO with or without zone. Naive -> +08:00.
  const parsePH = (value) => {
    if (!value) return Number.NaN;
    const iso = ensureIsoPH(value);
    const t = Date.parse(iso);
    return Number.isFinite(t) ? t : Number.NaN;
  };

  // Remaining countdown text
  const formatRemaining = (ms) => {
    if (!Number.isFinite(ms) || ms <= 0) return 'expired';
    const totalSeconds = Math.floor(ms / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);

    const parts = [];
    if (days) parts.push(`${days}d`);
    if (hours) parts.push(`${hours}h`);
    if (minutes || (!days && !hours)) parts.push(`${minutes}m`);
    return parts.join(' ');
  };

  // Optional: get "now" expressed in Manila local time (epoch ms unaffected).
  // Epoch math is timezone-agnostic, but we keep for clarity if needed later.
  const nowEpoch = () => Date.now();

  const tickRemaining = () => {
    const now = nowEpoch();
    document.querySelectorAll('.js-remaining').forEach((el) => {
      const target = parsePH(el.dataset.until);
      if (Number.isNaN(target)) {
        el.textContent = '—';
        return;
      }
      el.textContent = formatRemaining(target - now);
    });
  };

  tickRemaining();
  setInterval(tickRemaining, 60_000);

  // Build timeline modal content using already-PHP-formatted dates (PH time)
  const buildTimelineHTML = (userId) => {
    const historyTable = document.querySelector('[data-history-table]');
    if (!historyTable) {
      return '<div class="timeline-modal"><div class="timeline-empty">History unavailable.</div></div>';
    }

    const rows = Array.from(historyTable.querySelectorAll('tbody tr'))
      .filter((tr) => {
        if (tr.querySelector('.table-empty')) return false;
        const meta = tr.querySelector('td:nth-child(2) .cell-meta');
        return meta && meta.textContent.includes(`ID: ${userId}`);
      })
      .slice(0, 40);

    if (!rows.length) {
      return '<div class="timeline-modal"><div class="timeline-empty">No recent events for this user.</div></div>';
    }

    const items = rows
      .map((row) => {
        const timestamp = row.querySelector('td:nth-child(1) .cell-primary')?.textContent.trim() || '';
        const eventCell = row.querySelector('td:nth-child(4)');
        const eventText = eventCell ? eventCell.textContent.trim() : '';
        const detailCell = row.querySelector('td:nth-child(5)');
        const detail = detailCell ? detailCell.textContent.trim() : '';
        const detailHtml = detail
          ? escapeHtml(detail)
          : '<span class="timeline-detail-empty">No details provided</span>';

        return [
          '<div class="timeline-item">',
          '<div class="timeline-header">',
          `<span class="timeline-meta">${escapeHtml(timestamp)}</span>`,
          `<span class="timeline-event">${escapeHtml(eventText)}</span>`,
          '</div>',
          `<div class="timeline-detail">${detailHtml}</div>`,
          '</div>',
        ].join('');
      })
      .join('');

    return `<div class="timeline-modal"><div class="timeline">${items}</div></div>`;
  };

  const reloadSoon = () => window.setTimeout(() => window.location.reload(), 650);

  // --- Click handlers --------------------------------------------------------
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('button');
    if (!button) return;

    const userId = button.dataset.id;
    if (!userId) return;

    try {
      if (button.classList.contains('js-unban')) {
        const confirm = await SwalRef.fire({
          title: 'Unban this user?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes, unban',
          confirmButtonColor: accentColor,
        });
        if (!confirm.isConfirmed) return;

        const result = await postAction('unban', userId);
        if (result.ok) {
          toastOK('User unbanned');
          reloadSoon();
        } else {
          toastERR(result.error || 'Unable to unban user');
        }
        return;
      }

      if (button.classList.contains('js-ban1')
          || button.classList.contains('js-ban3')
          || button.classList.contains('js-banperm')) {
        let action = 'ban_1d';
        let title = 'Apply 1-day ban?';
        let confirmText = 'Ban 1 day';
        let confirmColor = '#a35515';

        if (button.classList.contains('js-ban3')) {
          action = 'ban_3d';
          title = 'Apply 3-day ban?';
          confirmText = 'Ban 3 days';
        } else if (button.classList.contains('js-banperm')) {
          action = 'ban_perm';
          title = 'Permanent ban?';
          confirmText = 'Yes, permanent';
          confirmColor = '#b42318';
        }

        const confirm = await SwalRef.fire({
          title,
          text: action === 'ban_perm' ? 'Marks the user permanently banned.' : undefined,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: confirmText,
          confirmButtonColor: confirmColor,
        });
        if (!confirm.isConfirmed) return;

        const result = await postAction(action, userId);
        if (result.ok) {
          toastOK('Ban updated');
          reloadSoon();
        } else {
          toastERR(result.error || 'Unable to update ban');
        }
        return;
      }

      if (button.classList.contains('js-note')) {
        const { value: note } = await SwalRef.fire({
          title: 'Add admin note',
          input: 'text',
          inputPlaceholder: 'Describe the note...',
          showCancelButton: true,
          confirmButtonColor: accentColor,
          inputValidator: (value) => (!value ? 'Please enter a note' : undefined),
        });
        if (!note) return;

        const result = await postAction('manual_note', userId, { note });
        if (result.ok) {
          toastOK('Note added');
          reloadSoon();
        } else {
          toastERR(result.error || 'Unable to add note');
        }
        return;
      }

      if (button.classList.contains('js-view')) {
        const html = buildTimelineHTML(userId);
        SwalRef.fire({
          title: `Timeline for User #${escapeHtml(userId)}`,
          html,
          width: 760,
          confirmButtonColor: accentColor,
          focusConfirm: false,
        });
      }
    } catch (error) {
      console.error(error);
      toastERR(error.message || 'Unexpected error');
    }
  });
})();
