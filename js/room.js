/* global WebSocket */
(() => {
  const cfg = window.__ROOM_CONFIG__;
  if (!cfg) return;

  const conn = new WebSocket(cfg.wsUrl);
  window.__ROOM_WS__ = conn;
  const privatBox = document.getElementById("privat-box");
  let allowSystemNavigate = false;
  let pendingCheckTimer = null;

  if (privatBox) privatBox.scrollTop = privatBox.scrollHeight;

  conn.onopen = () => {
    conn.send(JSON.stringify({
      type: "identify_connection", user_id: cfg.myId,
      context: "room", role: cfg.role, room_id: cfg.roomId
    }));
    if (cfg.role === "owner") {
      conn.send(JSON.stringify({
        type: "room_occupied", room_id: cfg.roomId, owner_name: cfg.myName
      }));
    }
  };

  window.addEventListener("beforeunload", (e) => {
    if (cfg.role === "owner" && !allowSystemNavigate) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  conn.onmessage = (e) => {
    const data = JSON.parse(e.data);

    if (data.type === "cek_owner_aktif"
        && cfg.role === "owner"
        && Number(data.room_id) === cfg.roomId) {
      let answered = false;
      pendingCheckTimer = setTimeout(() => {
        if (!answered) {
          alert("Anda terdeteksi tidak aktif. Dialihkan ke lobby.");
          allowSystemNavigate = true;
          window.location.href = cfg.leaveUrl;
        }
      }, 10000);
      const ok = confirm("Apakah Anda masih aktif? OK = ya, Cancel = keluar.");
      answered = true;
      clearTimeout(pendingCheckTimer);
      if (ok) {
        conn.send(JSON.stringify({
          type: "owner_merespon", target: data.nama_pelapor, room_id: cfg.roomId
        }));
      } else {
        allowSystemNavigate = true;
        window.location.href = cfg.leaveUrl;
      }
    }

    if (data.type === "minta_izin_masuk"
        && cfg.role === "owner"
        && Number(data.room_id) === cfg.roomId) {
      if (confirm(`User ${data.nama} ingin masuk Private Room ${cfg.roomId}. Izinkan?`)) {
        conn.send(JSON.stringify({
          type: "izin_disetujui", target: data.nama, room_id: cfg.roomId
        }));
      }
    }

    if (data.type === "owner_keluar"
        && cfg.role === "guest"
        && Number(data.room_id) === cfg.roomId) {
      alert("Owner telah meninggalkan room. Dialihkan ke lobby.");
      allowSystemNavigate = true;
      window.location.href = cfg.lobbyUrl;
    }

    if (data.type === "room_vacant" && Number(data.room_id) === cfg.roomId) {
      alert(`Room ${cfg.roomId} sudah kosong. Dialihkan ke lobby.`);
      allowSystemNavigate = true;
      window.location.href = cfg.lobbyUrl;
    }

    // Seat berubah → minta refresh
    if (data.type === "seats_changed" && Number(data.room_id) === cfg.roomId) {
      if (typeof window.__SEAT_REFRESH__ === "function") window.__SEAT_REFRESH__();
    }

    // Pesan game (relay) — diteruskan via CustomEvent ke game.js.
    // Whitelist prefix: game_*, sfx_*, bgm_*  (semua harus lewat filter room).
    if (typeof data.type === "string"
        && (data.type.startsWith("game_") || data.type.startsWith("sfx_") || data.type.startsWith("bgm_"))
        && Number(data.room_id) === cfg.roomId) {
      window.dispatchEvent(new CustomEvent("ws-game-msg", { detail: data }));
    }

    if (data.target_room === "room" && Number(data.room_id) === cfg.roomId) {
      if (!privatBox) return;
      const p = document.createElement("p");
      const img = document.createElement("img");
      // FIX: data.foto sudah berupa URL lengkap dari avatar_url() (mis. "assets/avatars/avatar-3.png" atau "img/foo.png").
      // Sebelumnya di-prefix lagi dengan "img/" sehingga avatar tidak muncul di chat.
      img.src = (data.foto && String(data.foto).trim() !== "") ? String(data.foto) : "img/default.png";
      img.width = 20; img.height = 20;
      const strong = document.createElement("strong");
      strong.textContent = data.nama || "";
      p.append(img, " ", strong, document.createTextNode(`: ${data.msg || ""}`));
      privatBox.appendChild(p);
      privatBox.scrollTop = privatBox.scrollHeight;
    }
  };

  function keluarSesuaiProsedur() {
    if (confirm("Yakin kembali ke Lobby?")) {
      allowSystemNavigate = true;
      window.location.href = cfg.leaveUrl;
    }
  }

  function sendRoom() {
    const input = document.getElementById("msgRoom");
    if (!input || input.value.trim() === "") return;
    conn.send(JSON.stringify({
      user_id: cfg.myId, nama: cfg.myName, foto: cfg.myFoto,
      msg: input.value, target_room: "room", room_id: cfg.roomId
    }));
    input.value = "";
  }

  window.sendRoom = sendRoom;
  window.keluarSesuaiProsedur = keluarSesuaiProsedur;

  const msgInput = document.getElementById("msgRoom");
  if (msgInput) msgInput.addEventListener("keypress", (e) => { if (e.key === "Enter") sendRoom(); });
})();
