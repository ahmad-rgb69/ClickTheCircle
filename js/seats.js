/* Seat panel: ambil state dari seats.php, render grid 4 slot, handle take/leave/fill_bots/clear_bots.
 * Broadcast event "seats_changed" via WebSocket (window.__ROOM_WS__) supaya client lain refresh.
 */
(() => {
  const cfg = window.__ROOM_CONFIG__;
  if (!cfg) return;
  const grid   = document.getElementById('seat-grid');
  const status = document.getElementById('seat-status');
  if (!grid) return;

  const COLORS = ['#d83a3a', '#2ecc40', '#ffd23f', '#3aa0ff'];
  let lastSeats = [];

  window.__SEAT_STATE__ = { seats: [], mySeat: null };

  function broadcastChange() {
    const ws = window.__ROOM_WS__;
    if (ws && ws.readyState === 1) {
      ws.send(JSON.stringify({
        type: 'seats_changed', room_id: cfg.roomId, user_id: cfg.myId
      }));
    }
  }

  function isRoomLocked() {
    return !!window.__ROOM_CONTROLS_LOCKED__;
  }

  function render(seats) {
    lastSeats = seats;
    grid.innerHTML = '';
    const mySeat = seats.find(s => s.user_id === cfg.myId);
    window.__SEAT_STATE__.seats = seats;
    window.__SEAT_STATE__.mySeat = mySeat ? mySeat.seat_no : null;

    seats.forEach((s, i) => {
      const card = document.createElement('div');
      card.className = 'border border-ink bg-control p-2 rounded-sm flex flex-col gap-1';
      const taken = !!(s.user_id || s.is_bot);
      const isMine = s.user_id === cfg.myId;

      const dot = `<span class="inline-block w-3 h-3 rounded-full border border-ink align-middle" style="background:${COLORS[i]}"></span>`;
      let label;
      if (s.user_id) label = `<strong>${escapeHtml(s.user_name)}</strong>`;
      else if (s.is_bot) label = `<em>${escapeHtml(s.bot_label || 'BOT')}</em>`;
      else label = '<span class="text-inkMuted">kosong</span>';

      card.innerHTML = `
        <div class="flex items-center gap-2">${dot}<span class="font-bold">Seat ${s.seat_no}</span></div>
        <div>${label}</div>
        <div class="mt-1"></div>
      `;
      const actions = card.lastElementChild;

      if (isMine) {
        const btn = document.createElement('button');
        btn.className = 'btn-danger text-xs px-2 py-1';
        btn.textContent = 'Leave';
        btn.disabled = isRoomLocked();
        btn.onclick = () => { if (!isRoomLocked()) act('leave'); };
        actions.appendChild(btn);
      } else if (!s.user_id) {
        // Kosong atau bot -> user lain boleh take (bot tergantikan).
        const btn = document.createElement('button');
        btn.className = 'btn text-xs px-2 py-1';
        btn.textContent = s.is_bot ? 'Take (replace bot)' : 'Take seat';
        btn.disabled = isRoomLocked();
        btn.onclick = () => { if (!isRoomLocked()) act('take', { seat: s.seat_no }); };
        actions.appendChild(btn);
      } else if (cfg.isOwner && s.user_id && !isMine) {
        // Owner bisa kick non-owner yang sedang duduk di seat.
        const btn = document.createElement('button');
        btn.className = 'btn-danger text-xs px-2 py-1';
        btn.textContent = 'Kick';
        btn.disabled = isRoomLocked();
        btn.onclick = () => {
          if (isRoomLocked()) return;
          if (!confirm('Kick ' + (s.user_name || 'user') + ' dari room?')) return;
          kickUser(s.user_id, s.user_name || '');
        };
        actions.appendChild(btn);
      }
      grid.appendChild(card);
    });

    const filled = seats.filter(s => s.user_id || s.is_bot).length;
    status.textContent = `${filled} / 4 seat terisi. ${mySeat ? 'Kamu di Seat ' + mySeat.seat_no : 'Kamu belum duduk.'}`;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  function fetchState() {
    return fetch(cfg.seatUrl + '?room_id=' + encodeURIComponent(cfg.roomId), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => { if (d && d.ok) render(d.seats); });
  }

  function act(action, extra = {}) {
    const fd = new FormData();
    fd.append('room_id', cfg.roomId);
    fd.append('action', action);
    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
    return fetch(cfg.seatUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (d && d.seats) render(d.seats);
        broadcastChange();
      });
  }

  function kickUser(userId, userName) {
    const fd = new FormData();
    fd.append('room_id', cfg.roomId);
    fd.append('action', 'kick');
    fd.append('user_id', userId);
    return fetch(cfg.seatUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (!d || !d.ok) {
          alert('Gagal kick: ' + ((d && d.err) || 'unknown error'));
          return;
        }
        if (d.seats) render(d.seats);
        // Broadcast WS supaya target kena redirect & seat panel di klien lain refresh.
        const ws = window.__ROOM_WS__;
        if (ws && ws.readyState === 1) {
          ws.send(JSON.stringify({
            type: 'user_kicked',
            room_id: cfg.roomId,
            target_user_id: Number(userId),
            target_name: String(userName || ''),
          }));
        }
        broadcastChange();
      });
  }

  function syncOwnerButtons() {
    const locked = isRoomLocked();
    const fillBtn = document.getElementById('seat-fill-bots');
    const clearBtn = document.getElementById('seat-clear-bots');
    if (fillBtn) fillBtn.disabled = locked;
    if (clearBtn) clearBtn.disabled = locked;
  }

  document.getElementById('seat-fill-bots') ?.addEventListener('click', () => { if (!isRoomLocked()) act('fill_bots'); });
  document.getElementById('seat-clear-bots')?.addEventListener('click', () => { if (!isRoomLocked()) act('clear_bots'); });
  window.addEventListener('room-controls-lock', () => { syncOwnerButtons(); render(lastSeats); });

  // Initial + polling fallback
  syncOwnerButtons();
  fetchState();
  window.__SEAT_REFRESH__ = fetchState;
  setInterval(fetchState, 5000);
})();
