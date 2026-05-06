/* global WebSocket */
(() => {
  const cfg = window.__LOBBY_CONFIG__;
  if (!cfg) return;

  const conn = new WebSocket(cfg.wsUrl);
  const chatBox = document.getElementById("chat-box");

  if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

  function ketukPintu(roomId) {
    conn.send(JSON.stringify({ type: "minta_izin_masuk", nama: cfg.myName, room_id: roomId }));
    alert("Menunggu izin owner...");
  }

  function sendLobby() {
    const input = document.getElementById("message");
    if (!input || input.value.trim() === "") return;
    conn.send(JSON.stringify({
      user_id: cfg.myId, nama: cfg.myName, foto: cfg.myFoto,
      msg: input.value, target_room: "lobby"
    }));
    input.value = "";
  }

  // Kirim pesan dengan tombol Enter
  document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("message");
    if (input) {
      input.addEventListener("keydown", (e) => {
        if (e.key === "Enter") { e.preventDefault(); sendLobby(); }
      });
    }
  });

  window.sendLobby  = sendLobby;
  window.ketukPintu = ketukPintu;

  conn.onopen = () => {
    conn.send(JSON.stringify({
      type: "identify_connection", user_id: cfg.myId,
      context: "lobby", role: "lobby_user"
    }));
  };

  conn.onmessage = (e) => {
    const data = JSON.parse(e.data);

    if (data.type === "room_occupied") {
      const roomId = Number(data.room_id || 0);
      const actions = document.getElementById(`room-actions-${roomId}`);
      const owner   = document.getElementById(`owner-info-${roomId}`);
      if (actions) actions.innerHTML = `<button type="button" class="btn w-full" onclick="ketukPintu(${roomId})">Minta Izin Masuk</button>`;
      if (owner && data.owner_name) owner.textContent = `Owner saat ini: ${data.owner_name}`;
    }

    if (data.type === "room_vacant") {
      const roomId = Number(data.room_id || 0);
      const actions = document.getElementById(`room-actions-${roomId}`);
      const owner   = document.getElementById(`owner-info-${roomId}`);
      if (actions) actions.innerHTML = `<button type="submit" class="btn w-full">Masuk &amp; Jadi Owner</button>`;
      if (owner)   owner.textContent = "Belum ada owner.";
    }

    if (data.type === "room_busy_playing"
        && Number(data.room_id) > 0
        && (!data.nama || data.nama === cfg.myName)) {
      alert(`Owner Room ${data.room_id} sedang bermain. Coba lagi setelah game selesai.`);
    }

    if (data.type === "izin_disetujui" && data.target === cfg.myName) {
      const roomId = Number(data.room_id || 0);
      alert(`Izin masuk Room ${roomId} disetujui. Anda akan langsung masuk jika password sudah terisi.`);
      const card = document.getElementById(`room-card-${roomId}`);
      const form = document.getElementById(`form-room-${roomId}`);
      const input = card && card.querySelector('input[name="room_pass"]');
      if (input && input.value.trim() !== "" && form) form.submit();
      else if (input) input.focus();
    }

    if (data.msg && (data.target_room === "lobby" || !data.target_room)) {
      if (!chatBox) return;
      const p = document.createElement("p");
      const img = document.createElement("img");
      // FIX: data.foto sudah berupa URL lengkap dari avatar_url() (mis. "assets/avatars/avatar-3.png" atau "img/foo.png").
      // Sebelumnya di-prefix lagi dengan "img/" sehingga avatar tidak muncul di chat.
      img.src = (data.foto && String(data.foto).trim() !== "") ? String(data.foto) : "img/default.png";
      img.width = 20; img.height = 20;
      const strong = document.createElement("strong");
      strong.textContent = data.nama || "";
      p.append(img, " ", strong, document.createTextNode(`: ${data.msg || ""}`));
      chatBox.appendChild(p);
      chatBox.scrollTop = chatBox.scrollHeight;
    }
  };
})();
