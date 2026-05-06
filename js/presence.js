/* global WebSocket */
/**
 * Presence client.
 * Membuka koneksi WS terpisah untuk menampilkan daftar user yang sedang online
 * di sidebar (PLAYER INFO -> ONLINE USERS).
 *
 * Konfigurasi diambil dari window.__PRESENCE_CFG__ yang di-set di header.php
 * (hanya saat user sudah login). Reconnect otomatis bila koneksi putus.
 */
(function () {
  if (typeof window === "undefined") return;
  var cfg = window.__PRESENCE_CFG__;
  if (!cfg || !cfg.wsUrl || !cfg.myId) return;

  var listEl  = document.getElementById("online-users-list");
  var countEl = document.getElementById("online-users-count");
  if (!listEl) return;

  var ws = null;
  var retry = 0;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;")
      .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  function render(users) {
    if (countEl) countEl.textContent = String(users.length);
    if (!users.length) {
      listEl.innerHTML =
        '<div class="text-[10px] text-inkMuted text-center py-2">Belum ada user lain.</div>';
      return;
    }
    listEl.innerHTML = users.map(function (u) {
      var isMe   = Number(u.id) === Number(cfg.myId);
      var ctx    = u.context === "room" ? ("Room " + (u.room_id || "?")) : "";
      var foto   = u.foto && String(u.foto).trim() !== "" ? u.foto : "img/default.png";
      return ''
        + '<div class="flex items-center gap-2 bg-control border border-ink rounded-sm px-2 py-1 ' + (isMe ? 'ring-2 ring-accentGreen' : '') + '">'
        +   '<img src="' + escapeHtml(foto) + '" width="28" height="28" '
        +        'class="rounded-full border border-ink object-cover bg-white shrink-0" alt="">'
        +   '<div class="min-w-0 flex-1">'
        +     '<div class="text-xs font-bold truncate">'
        +        escapeHtml(u.nama) + (isMe ? ' <span class="text-[9px] text-accentGreen">(you)</span>' : '')
        +     '</div>'
        +     (ctx ? '<div class="text-[10px] text-inkMuted truncate">' + escapeHtml(ctx) + '</div>' : '')
        +   '</div>'
        + '</div>';
    }).join("");
  }

  function connect() {
    try { ws = new WebSocket(cfg.wsUrl); }
    catch (e) { scheduleReconnect(); return; }

    ws.onopen = function () {
      retry = 0;
      ws.send(JSON.stringify({
        type: "identify_connection",
        user_id: cfg.myId,
        nama: cfg.myName,
        foto: cfg.myFoto,
        context: cfg.context || "sidebar",
        role: "presence_watcher"
      }));
      ws.send(JSON.stringify({ type: "request_presence", user_id: cfg.myId }));
    };

    ws.onmessage = function (ev) {
      var data;
      try { data = JSON.parse(ev.data); } catch (e) { return; }
      if (!data || data.type !== "presence") return;
      render(Array.isArray(data.users) ? data.users : []);
    };

    ws.onclose = scheduleReconnect;
    ws.onerror = function () { try { ws.close(); } catch (e) {} };
  }

  function scheduleReconnect() {
    retry = Math.min(retry + 1, 6);
    var delay = 1000 * retry;
    setTimeout(connect, delay);
  }

  connect();
})();
