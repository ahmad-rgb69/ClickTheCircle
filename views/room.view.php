<?php
/**
 * @var int           $roomId
 * @var string        $role
 * @var mysqli_result $messages
 */
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../models/Room.php';
include __DIR__ . '/header.php';
$isOwner   = (($role ?? '') === 'owner');
// Nama room (Dark, Lux, Neon, Frost, Ember) — bukan angka.
$roomName  = Room::nameFor((int)$roomId);
$roleLabel = $isOwner ? 'owner' : 'guest';
?>
<h1 class="h-title">Private Room <?= e($roomName) ?> (Status: <?= e($roleLabel) ?>)</h1>
<button type="button" id="room-profile-open"
        class="chip text-left cursor-pointer hover:bg-panel2 transition-colors w-full"
        aria-controls="user-sidebar" aria-expanded="false">
    <img src="<?= e(avatar_url($_SESSION['gambar'])) ?>" width="30" height="30" class="rounded-full border border-ink object-cover">
    <span class="flex-1">Logged in as: <strong><?= e($_SESSION['nama']) ?></strong></span>
    <?php if ($isOwner): ?>
        <span class="px-2 py-0.5 bg-accentGreen text-ink rounded-sm text-xs font-bold">OWNER</span>
    <?php endif; ?>
    <span class="font-bold underline underline-offset-4">👤 Profile</span>
</button>

<!-- ============ SEAT PANEL ============ -->
<div class="panel-box mt-4 p-4">
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <h2 class="text-xl font-extrabold tracking-wide mr-auto">Seats</h2>
        <?php if ($isOwner): ?>
            <button id="seat-fill-bots"  class="btn">Fill with bots</button>
            <button id="seat-clear-bots" class="btn-danger">Clear bots</button>
        <?php endif; ?>
    </div>
    <div id="seat-grid" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
        <!-- diisi oleh JS -->
    </div>
    <p id="seat-status" class="mt-2 text-xs text-inkMuted">Loading seats...</p>
</div>

<!-- ============ GAMEPLAY: CL!CK THE CIRCLE ============ -->
<div class="panel-box mt-4 p-4">
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <h2 class="text-xl font-extrabold tracking-wide mr-auto">Game Arena</h2>

        <label class="text-sm font-bold">Difficulty
            <select id="game-difficulty" class="field mt-0 inline-block w-auto ml-1" <?= $isOwner ? '' : 'disabled' ?>>
                <option value="normal" selected>Normal</option>
                <option value="hard">Hard</option>
            </select>
            <?php if (!$isOwner): ?>
                <span class="text-xs text-inkMuted ml-1">(set by owner)</span>
            <?php endif; ?>
        </label>

        <label class="text-sm font-bold">Duration
            <select id="game-duration" class="field mt-0 inline-block w-auto ml-1" <?= $isOwner ? '' : 'disabled' ?>>
                <option value="30">30 seconds</option>
                <option value="60">60 seconds</option>
                <option value="90" selected>90 seconds</option>
            </select>
        </label>

        <label class="text-sm font-bold">BGM
            <select id="game-bgm" class="field mt-0 inline-block w-auto ml-1">
                <option value="">— No music —</option>
                <!-- diisi otomatis oleh JS dari assets/bgm/manifest.json -->
            </select>
        </label>

        <label class="text-sm font-bold">Arena
            <select id="game-arena" class="field mt-0 inline-block w-auto ml-1">
                <option value="">— Default (dark) —</option>
                <!-- diisi otomatis oleh JS dari assets/arena/manifest.json -->
            </select>
        </label>

        <label class="text-sm font-bold">Box Skin
            <select id="game-boxskin" class="field mt-0 inline-block w-auto ml-1">
                <option value="default">— Default (color) —</option>
                <!-- diisi otomatis dari assets/boxes/manifest.json -->
            </select>
        </label>

        <label class="text-sm font-bold inline-flex items-center gap-1">
            <input type="checkbox" id="game-sfx-on" checked class="align-middle"> SFX
        </label>

        <button id="game-start" class="btn"        <?= $isOwner ? '' : 'disabled' ?>>Start</button>
        <button id="game-reset" class="btn-danger" <?= $isOwner ? '' : 'disabled' ?>>Reset</button>

        <div class="ml-auto text-sm font-bold">
            Time: <span id="game-timer" class="px-2 py-1 bg-control border border-ink rounded-sm">90s</span>
        </div>
    </div>

    <div id="game-scores" class="flex flex-wrap gap-2 mb-3 text-sm"></div>

    <div class="bg-ink border border-ink shadow-inset1 p-2 inline-block">
        <canvas id="game-canvas" width="900" height="520" class="block max-w-full"></canvas>
    </div>

    <!-- (D-pad lama dihapus; diganti joystick fixed MOBA + floating buttons di luar panel) -->
    <div id="touch-controls" class="hidden"></div>

    <p id="game-status" class="mt-2 text-sm text-inkMuted">
        <?= $isOwner ? 'Pick a seat then press Start. Other players on different devices will join automatically once they take a seat.' : 'You have been auto-seated in an empty seat. Wait for the owner to press Start. Use WASD + Space (or the joystick on mobile) to play.' ?>
    </p>

    <details class="mt-2 text-sm">
        <summary class="cursor-pointer font-bold">Controls</summary>
        <ul class="list-disc pl-5 mt-1 space-y-0.5">
            <li><strong>Desktop:</strong> WASD to move, Space for turbo.</li>
            <li><strong>Mobile:</strong> analog joystick at bottom-left, Turbo button (hold) at bottom-right.</li>
            <li>Tap the yellow boxes for points. The owner can pick the duration (30 / 60 / 90 seconds).</li>
        </ul>
    </details>
</div>

<!-- ================== MOBILE: Joystick + Floating Buttons ================== -->
<div id="mobile-joystick" class="hidden" aria-hidden="true">
    <div id="joy-base"><div id="joy-knob"></div></div>
</div>

<div id="floating-controls" class="hidden" aria-hidden="true">
    <!-- urutan visual (column-reverse): Turbo (atas) → Chat (bawah). Reset Game dihapus dari mobile -->
    <button id="float-turbo" type="button" class="float-btn float-btn--turbo" title="Turbo (hold)" aria-label="Turbo">⚡</button>
    <button id="float-chat"  type="button" class="float-btn"                  title="Chat" aria-label="Chat">💬</button>
</div>

<!-- ================== End-Game Modal (custom, tidak block WS) ================== -->
<div id="end-modal" class="end-modal hidden" role="dialog" aria-modal="true" aria-labelledby="end-modal-title" aria-hidden="true">
    <div class="end-modal__backdrop"></div>
    <div class="end-modal__panel">
        <h2 id="end-modal-title">Time's Up!</h2>
        <p id="end-modal-headline" class="end-modal__headline"></p>
        <ol id="end-modal-scores" class="end-modal__scores"></ol>
        <div class="end-modal__actions">
            <button id="end-modal-ok" type="button" class="btn-danger">OK</button>
        </div>
        <p id="end-modal-hint" class="end-modal__hint"></p>
    </div>
</div>

<style>
  /* ===== Joystick fixed (gaya MOBA klasik) ===== */
  #mobile-joystick{
    position:fixed; left:24px; bottom:calc(24px + env(safe-area-inset-bottom));
    z-index:45; width:140px; height:140px; touch-action:none; user-select:none;
  }
  #mobile-joystick.hidden{ display:none; }
  #joy-base{
    position:absolute; inset:0; border-radius:50%;
    background:rgba(20,20,20,.45); border:2px solid rgba(255,255,255,.25);
    backdrop-filter:blur(4px);
  }
  #joy-knob{
    position:absolute; left:50%; top:50%; width:60px; height:60px; margin:-30px 0 0 -30px;
    border-radius:50%; background:#f4d35e; border:2px solid #1a1a1a;
    box-shadow:2px 2px 0 #1a1a1a; transition:transform .05s linear;
  }

  /* ===== Floating buttons (kanan-bawah, urutan: Chat→Turbo→Reset) ===== */
  #floating-controls{
    position:fixed; right:16px; bottom:calc(16px + env(safe-area-inset-bottom));
    z-index:46; display:flex; flex-direction:column-reverse; gap:12px;
  }
  #floating-controls.hidden{ display:none; }
  .float-btn{
    width:60px; height:60px; border-radius:50%;
    background:#f4d35e; color:#1a1a1a; border:2px solid #1a1a1a;
    box-shadow:2px 2px 0 #1a1a1a;
    font-size:22px; font-weight:800; line-height:1;
    display:flex; align-items:center; justify-content:center;
    touch-action:none; user-select:none; cursor:pointer;
  }
  .float-btn:active{ transform:translate(2px,2px); box-shadow:none; }
  .float-btn--turbo{ background:#7ad97a; }
  .float-btn--turbo.is-active{ background:#ff8c42; color:#fff; }
  .float-btn--owner{ background:#ff6b6b; color:#fff; }
  /* sembunyikan tombol owner-only untuk non-owner */
  body:not(.is-owner) .float-btn--owner{ display:none; }
  /* sembunyikan semua floating saat chat drawer terbuka */
  body.chat-open #floating-controls,
  body.chat-open #mobile-joystick{ display:none !important; }

  /* ===== Touch detection: tampilkan joystick & floating hanya di device touch ===== */
  body.is-touch #mobile-joystick,
  body.is-touch #floating-controls{ display:flex; }
  body.is-touch #mobile-joystick{ display:block; }

  /* ===== End-Game Modal ===== */
  .end-modal{ position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; }
  .end-modal.hidden{ display:none; }
  .end-modal__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.6); backdrop-filter:blur(4px); }
  .end-modal__panel{
    position:relative; background:#1a1a1a; color:#fff; border:2px solid #f4d35e;
    border-radius:12px; padding:20px 22px; min-width:300px; max-width:92vw;
    box-shadow:6px 6px 0 #000;
  }
  .end-modal__panel h2{ margin:0 0 6px; font-size:22px; text-align:center; font-weight:800; }
  .end-modal__headline{ margin:0 0 12px; text-align:center; color:#f4d35e; font-weight:700; font-size:14px; }
  .end-modal__scores{ list-style:none; padding:0; margin:0 0 16px; max-height:50vh; overflow-y:auto; }
  .end-modal__scores li{
    display:flex; justify-content:space-between; align-items:center;
    padding:8px 12px; border-radius:6px; margin-bottom:6px;
    background:rgba(255,255,255,.06); font-size:14px;
  }
  .end-modal__scores li.is-winner{ background:linear-gradient(90deg,#f4d35e,#ffba49); color:#1a1a1a; font-weight:800; }
  .end-modal__actions{ display:flex; gap:8px; justify-content:flex-end; }
  .end-modal__hint{ margin:10px 0 0; text-align:center; font-size:11px; color:#888; }
</style>

<!-- (tombol chat-toggle lama dihapus; kini pakai #float-chat di stack floating) -->

<!-- Drawer chat dari kanan -->
<aside id="chat-drawer"
       class="fixed top-0 right-0 z-30 h-full w-full max-w-[420px] bg-panel border-l border-ink shadow-2xl
              translate-x-full transition-transform duration-300 ease-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-3 py-2 border-b border-ink bg-control">
        <strong class="text-sm">Chat — Room <?= e($roomName) ?></strong>
        <button type="button" id="chat-close" class="btn-danger px-2 py-1 text-xs">Close</button>
    </div>

    <div id="privat-box" class="chat-box flex-1 m-0 rounded-none border-0">
        <?php while ($pr = mysqli_fetch_assoc($messages)): ?>
            <p>
                <img src="<?= e(avatar_url($pr['gambar'])) ?>" width="20" height="20" class="rounded-full object-cover">
                <strong><?= e($pr['nama']) ?></strong>: <?= e($pr['isi_pesan']) ?>
            </p>
        <?php endwhile; ?>
    </div>

    <form class="p-2 pb-3 border-t border-ink flex gap-2"
          onsubmit="event.preventDefault(); sendRoom();">
        <input type="text" id="msgRoom" placeholder="Type a private message..."
               class="field flex-1 mt-0" autocomplete="off">
        <button type="submit" id="room-chat-send" class="btn shrink-0">Send</button>
    </form>
</aside>

<div class="w-full max-w-[960px] mt-4">
    <button id="room-leave-btn" onclick="keluarSesuaiProsedur()" class="btn-danger">Leave to Lobby</button>
</div>

<script>
window.__ROOM_CONFIG__ = {
    wsUrl:    window.__WS_URL__,
    role:     <?= json_encode($role) ?>,
    isOwner:  <?= $isOwner ? 'true' : 'false' ?>,
    myName:   <?= json_encode((string)$_SESSION['nama']) ?>,
    myFoto:   <?= json_encode((string)avatar_url($_SESSION['gambar'])) ?>,
    myId:     <?= (int)$_SESSION['id'] ?>,
    roomId:   <?= (int)$roomId ?>,
    roomName: <?= json_encode($roomName) ?>,
    leaveUrl: <?= json_encode('room_leave.php?room_id=' . (int)$roomId) ?>,
    lobbyUrl: "lobby.php",
    diffUrl:  "room_difficulty.php",
    saveUrl:  "game_save.php",
    seatUrl:  "seats.php"
};

/* ---- Toggle chat drawer (sumber tombol: #float-chat) ----
 * Saat drawer terbuka, semua floating controls + joystick disembunyikan via
 * class `chat-open` di <body> (lihat CSS di atas).
 */
(function () {
    const drawer = document.getElementById('chat-drawer');
    const btn    = document.getElementById('float-chat');
    const close  = document.getElementById('chat-close');
    if (!drawer || !btn) return;
    let open = false;
    function setOpen(v) {
        open = v;
        drawer.classList.toggle('translate-x-full', !v);
        drawer.setAttribute('aria-hidden', v ? 'false' : 'true');
        btn.setAttribute('aria-expanded', v ? 'true' : 'false');
        document.body.classList.toggle('chat-open', v);
    }
    btn.addEventListener('click',   () => setOpen(!open));
    if (close) close.addEventListener('click', () => setOpen(false));
})();

/* ---- Tandai role owner & device touch di <body> untuk styling ---- */
(function () {
    if (window.__ROOM_CONFIG__ && window.__ROOM_CONFIG__.isOwner) {
        document.body.classList.add('is-owner');
    }
    const isTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
    if (isTouch) {
        document.body.classList.add('is-touch');
        document.getElementById('mobile-joystick')?.removeAttribute('aria-hidden');
        document.getElementById('floating-controls')?.removeAttribute('aria-hidden');
        document.getElementById('mobile-joystick')?.classList.remove('hidden');
        document.getElementById('floating-controls')?.classList.remove('hidden');
    } else {
        // Desktop tetap perlu tombol Chat & Reset (owner) → tampilkan floating tanpa joystick.
        document.getElementById('floating-controls')?.removeAttribute('aria-hidden');
        document.getElementById('floating-controls')?.classList.remove('hidden');
    }
})();

/* ---- Tombol Profil di room → buka sidebar (sama seperti di lobby) ---- */
(function () {
    const btn = document.getElementById('room-profile-open');
    if (!btn) return;
    btn.addEventListener('click', () => {
        if (typeof window.openUserSidebar === 'function') window.openUserSidebar();
    });
})();
</script>
<script src="js/room.js"></script>
<script src="js/seats.js"></script>
<script src="js/game.js"></script>
<?php include __DIR__ . '/footer.php'; ?>

<script>
(function(){
  const sel = document.getElementById('game-arena');
  if (!sel) return;
  window.__ARENA_IMG__ = null;
  function setArena(url){
    if (!url) { window.__ARENA_IMG__ = null; return; }
    const img = new Image();
    img.onload  = () => { window.__ARENA_IMG__ = img; };
    img.onerror = () => { window.__ARENA_IMG__ = null; };
    img.src = url;
  }
  fetch('assets/arena/manifest.json', { cache: 'no-store' })
    .then(r => r.ok ? r.json() : { items: [] })
    .then(j => {
      const items = (j && j.items) || [];
      items.forEach(it => {
        const o = document.createElement('option');
        o.value = it.file ? ('assets/arena/' + it.file) : '';
        o.textContent = it.name || it.file || 'Arena';
        sel.appendChild(o);
      });
      const saved = localStorage.getItem('rrr_arena') || '';
      if (saved) {
        for (const o of sel.options) if (o.value === saved) { sel.value = saved; break; }
        setArena(saved);
      }
    })
    .catch(() => {});
  sel.addEventListener('change', () => {
    localStorage.setItem('rrr_arena', sel.value || '');
    setArena(sel.value);
  });
})();
</script>


<script>
/* ===== Box Skin loader (per-user) ===== */
(function(){
  const sel = document.getElementById('game-boxskin');
  if (!sel) return;
  const KINDS = ['normal','bonus','minus','penalty','freeze','slow','shock','boost'];
  window.__BOX_SKINS__ = {};
  window.__BOX_PACKS__ = [{ id:'default', name:'Default', dir:'' }];

  function applyPack(pack){
    window.__BOX_SKINS__ = {};
    if (!pack || !pack.dir) return;
    KINDS.forEach(k => {
      const img = new Image();
      img.onload = () => { window.__BOX_SKINS__[k] = img; };
      img.onerror = () => {};
      img.src = 'assets/boxes/' + pack.dir + k + '.png';
    });
  }

  fetch('assets/boxes/manifest.json', { cache: 'no-store' })
    .then(r => r.ok ? r.json() : { packs: [] })
    .then(j => {
      const packs = (j && j.packs) || [];
      window.__BOX_PACKS__ = packs;
      sel.innerHTML = '';
      packs.forEach(pk => {
        const o = document.createElement('option');
        o.value = pk.id; o.textContent = pk.name || pk.id;
        sel.appendChild(o);
      });
      const saved = localStorage.getItem('rrr_boxskin') || 'default';
      sel.value = saved;
      applyPack(packs.find(p => p.id === saved) || packs[0]);
    })
    .catch(() => {});

  sel.addEventListener('change', () => {
    localStorage.setItem('rrr_boxskin', sel.value);
    const pk = (window.__BOX_PACKS__ || []).find(p => p.id === sel.value);
    applyPack(pk);
  });
})();

/* ===== Avatar dari profil (DB) =====
   Avatar setiap pemain diambil dari kolom `users.gambar` yang sudah ikut
   dikirim oleh seats.php sebagai `user_foto`. Tidak ada lagi pemilihan
   avatar di room, tidak ada broadcast WebSocket per-avatar, tidak ada
   localStorage. Single source of truth = database.

   js/game.js merender avatar tiap pemain dari window.__AVATAR_IMGS__[seat_no].
   Fungsi di bawah hanya bertugas mengisi map itu setiap kali state seat
   berubah, berdasarkan `user_foto` yang sudah dikirim server.
*/
(function(){
  const ROOM_ID = (window.__ROOM_CONFIG__ && Number(window.__ROOM_CONFIG__.roomId)) || 0;

  // Helper: resolve nilai DB `gambar` -> URL absolut yang bisa di-load <img>.
  // Konvensi sama dengan helpers/avatar.php (avatar_url()).
  function resolveAvatarUrl(g){
    g = String(g || '').trim();
    if (g === '') return '';
    if (g.indexOf('preset:') === 0) {
      const f = g.slice(7);
      if (/^[A-Za-z0-9._-]+$/.test(f)) return 'assets/avatars/' + f;
      return '';
    }
    if (/^[A-Za-z0-9._-]+$/.test(g)) return 'img/' + g;
    return '';
  }

  // Scoping per-room (tetap dipertahankan supaya pindah room tidak bocor).
  window.__AVATAR_IMGS_BY_ROOM__ = window.__AVATAR_IMGS_BY_ROOM__ || {};
  window.__AVATAR_URLS_BY_ROOM__ = window.__AVATAR_URLS_BY_ROOM__ || {};
  if (!window.__AVATAR_IMGS_BY_ROOM__[ROOM_ID]) window.__AVATAR_IMGS_BY_ROOM__[ROOM_ID] = {};
  if (!window.__AVATAR_URLS_BY_ROOM__[ROOM_ID]) window.__AVATAR_URLS_BY_ROOM__[ROOM_ID] = {};

  // Alias yang dibaca js/game.js
  window.__AVATAR_IMGS__ = window.__AVATAR_IMGS_BY_ROOM__[ROOM_ID];
  window.__AVATAR_URLS__ = window.__AVATAR_URLS_BY_ROOM__[ROOM_ID];

  // Reset slot 1..4 saat halaman room ini load (avoid stale dari sesi lain).
  for (let n = 1; n <= 4; n++) {
    delete window.__AVATAR_IMGS__[n];
    delete window.__AVATAR_URLS__[n];
  }

  const DBG = (() => { try { return localStorage.getItem('rrr_avatar_debug') === '1'; } catch(e){ return false; } })();
  function dlog(){ if (DBG) try { console.log.apply(console, ['[avatar][room=' + ROOM_ID + ']', ...arguments]); } catch(e){} }

  function applyAvatarToSeat(seatNo, url){
    if (!seatNo) return;
    if (!url) {
      delete window.__AVATAR_IMGS__[seatNo];
      delete window.__AVATAR_URLS__[seatNo];
      dlog('clear seat', seatNo);
      return;
    }
    if (window.__AVATAR_URLS__[seatNo] === url && window.__AVATAR_IMGS__[seatNo]) return;
    window.__AVATAR_URLS__[seatNo] = url;
    const img = new Image();
    img.onload  = () => { window.__AVATAR_IMGS__[seatNo] = img; dlog('apply seat', seatNo, 'OK', url); };
    img.onerror = () => { delete window.__AVATAR_IMGS__[seatNo]; dlog('apply seat', seatNo, 'ERR', url); };
    img.src = url;
  }

  // Sinkronkan __AVATAR_IMGS__ dengan __SEAT_STATE__.seats setiap kali seat berubah.
  function syncFromSeatState(){
    const seats = (window.__SEAT_STATE__ && window.__SEAT_STATE__.seats) || [];
    for (let n = 1; n <= 4; n++) {
      const s = seats.find(x => Number(x.seat_no) === n);
      const uid = s && Number(s.user_id) || 0;
      if (!s || !uid) {
        // Seat kosong / bot -> tidak ada avatar foto.
        applyAvatarToSeat(n, '');
        continue;
      }
      const url = resolveAvatarUrl(s.user_foto || '');
      applyAvatarToSeat(n, url);
    }
  }

  // Hook ke __SEAT_REFRESH__ yang dipasang js/seats.js.
  (function hookSeatRefresh(){
    let tries = 0;
    const t = setInterval(() => {
      if (typeof window.__SEAT_REFRESH__ === 'function') {
        const orig = window.__SEAT_REFRESH__;
        window.__SEAT_REFRESH__ = function(){
          const p = orig();
          if (p && typeof p.then === 'function') p.then(syncFromSeatState);
          else setTimeout(syncFromSeatState, 50);
          return p;
        };
        // Jalankan sekali di awal kalau seat sudah ter-render.
        setTimeout(syncFromSeatState, 100);
        clearInterval(t);
      } else if (++tries > 50) {
        clearInterval(t);
      }
    }, 100);
  })();
})();
</script>
