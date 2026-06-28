<?php
/**
 * @var int           $roomId
 * @var string        $role
 * @var mysqli_result $messages
 */
require_once __DIR__ . '/../helpers/avatar.php';
require_once __DIR__ . '/../models/Room.php';
require_once __DIR__ . '/../helpers/room_playing.php';
include __DIR__ . '/header.php';
$isOwner   = (($role ?? '') === 'owner');
// Nama room (Dark, Lux, Neon, Frost, Ember) — bukan angka.
$roomName  = Room::nameFor((int)$roomId);
$roleLabel = $isOwner ? 'owner' : 'guest';
$wasPlaying = room_is_playing((int)$roomId);
?>

<div class="flex flex-col xl:flex-row gap-6 w-full items-start max-w-[1400px] mx-auto mt-4 text-[#FFFFF6]">

    <div class="flex-1 w-full min-w-0 max-w-[960px]">
        <h1 class="h-title text-2xl font-black mb-4 text-[#B57DDA]">Private Room <?= e($roomName) ?> (Status: <?= e($roleLabel) ?>)</h1>
        
        <button type="button" id="room-profile-open"
                class="chip rounded-none text-left cursor-pointer bg-[#41478B] hover:bg-[#B57DDA] hover:text-[#1A1A3A] w-full mt-4 flex items-center p-3 gap-3 border-2 border-[#1A1A3A] shadow-[4px_4px_0_0_rgba(26,26,58,1)] text-[#FFFFF6] transition-colors"
                aria-controls="user-sidebar" aria-expanded="false">
            <img src="<?= e(avatar_url($_SESSION['gambar'])) ?>" width="30" height="30" class="rounded-none border border-[#1A1A3A] object-cover">
            <span class="flex-1">Logged in as: <strong><?= e($_SESSION['nama']) ?></strong></span>
            <?php if ($isOwner): ?>
                <span class="px-2 py-0.5 bg-[#B57DDA] text-[#1A1A3A] rounded-none text-xs font-bold border border-[#1A1A3A]">OWNER</span>
            <?php endif; ?>
        </button>

        <div class="panel-box rounded-none mt-4 p-4 bg-[#242752] border-2 border-[#1A1A3A] shadow-[5px_5px_0_0_rgba(26,26,58,1)]">
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <h2 class="text-xl font-extrabold tracking-wide mr-auto text-[#B57DDA]">Seats</h2>
                <?php if ($isOwner): ?>
                    <button id="seat-fill-bots" class="btn bg-[#B57DDA] text-[#1A1A3A] font-bold px-3 py-1 text-sm">Fill with bots</button>
                    <button id="seat-clear-bots" class="btn-danger bg-[#AAA0BB] text-[#1A1A3A] font-bold px-3 py-1 text-sm">Clear bots</button>
                <?php endif; ?>
            </div>
            <div id="seat-grid" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm"></div>
            <p id="seat-status" class="mt-2 text-xs text-[#AAA0BB]">Loading seats...</p>
        </div>

        <div class="panel-box rounded-none mt-4 p-4 bg-[#242752] border-2 border-[#1A1A3A] shadow-[5px_5px_0_0_rgba(26,26,58,1)]">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <h2 class="text-xl font-extrabold tracking-wide mr-auto text-[#B57DDA]">Game Arena</h2>

                <label class="text-sm font-bold text-[#E8E2D4]">Difficulty :
                    <select id="game-difficulty" class="field mt-0 inline-block w-auto ml-1 bg-[#1A1A3A] text-[#FFFFF6] border border-[#41478B] p-1" <?= $isOwner ? '' : 'disabled' ?>>
                        <option value="easy">Easy</option>
                        <option value="normal" selected>Normal</option>
                        <option value="hard">Hard</option>
                        <option value="indonesian">Indonesian (Insane)</option>
                    </select>
                    <?php if (!$isOwner): ?>
                        <span class="text-xs text-[#AAA0BB] ml-1">(set by owner)</span>
                    <?php endif; ?>
                </label>

                <label class="text-sm font-bold text-[#E8E2D4]">Duration :
                    <select id="game-duration" class="field mt-0 inline-block w-auto ml-1 bg-[#1A1A3A] text-[#FFFFF6] border border-[#41478B] p-1" <?= $isOwner ? '' : 'disabled' ?>>
                        <option value="30">30 seconds</option>
                        <option value="60">60 seconds</option>
                        <option value="90" selected>90 seconds</option>
                    </select>
                </label>

                <label class="text-sm font-bold text-[#E8E2D4]">BGM :
                    <select id="game-bgm" class="field mt-0 inline-block w-auto ml-1 bg-[#1A1A3A] text-[#FFFFF6] border border-[#41478B] p-1">
                        <option value="">— No music —</option>
                    </select>
                </label>

                <label class="text-sm font-bold text-[#E8E2D4]">Arena :
                    <select id="game-arena" class="field mt-0 inline-block w-auto ml-1 bg-[#1A1A3A] text-[#FFFFF6] border border-[#41478B] p-1">
                        <option value="">— Default (dark) —</option>
                    </select>
                </label>

                <label class="text-sm font-bold text-[#E8E2D4]">Skin :
                    <select id="game-boxskin" class="field mt-0 inline-block w-auto ml-1 bg-[#1A1A3A] text-[#FFFFF6] border border-[#41478B] p-1">
                        <option value="default">— Default (color) —</option>
                    </select>
                </label>

                <label class="text-sm font-bold inline-flex items-center gap-1 text-[#E8E2D4]">
                    <input type="checkbox" id="game-sfx-on" checked class="align-middle accent-[#B57DDA]"> SFX
                </label>

                <button id="game-start" class="btn bg-[#B57DDA] text-[#1A1A3A] font-black px-4 py-1.5" <?= $isOwner ? '' : 'disabled' ?>>Start</button>
                <button id="game-reset" class="btn-danger bg-[#AAA0BB] text-[#1A1A3A] font-black px-4 py-1.5" <?= $isOwner ? '' : 'disabled' ?>>Reset</button>

                <div class="ml-auto text-sm font-bold text-[#E8E2D4]">
                    Time: <span id="game-timer" class="px-2 py-1 bg-[#1A1A3A] text-[#B57DDA] border border-[#41478B] inline-block font-mono">90s</span>
                </div>
            </div>

            <div id="game-scores" class="flex flex-wrap gap-2 mb-3 text-sm"></div>

            <div class="bg-[#1A1A3A] border-2 border-[#41478B] p-2 inline-block canvas-wrapper shadow-[4px_4px_0_0_rgba(0,0,0,0.5)]">
                <canvas id="game-canvas" width="900" height="520" class="block max-w-full bg-[#1A1A3A]"></canvas>
            </div>

            <div id="touch-controls" class="hidden"></div>

            <p id="game-status" class="mt-2 text-sm text-[#AAA0BB]">
                <?= $isOwner ? 'Pick a seat then press Start. Other players on different devices will join automatically once they take a seat.' : 'You have been auto-seated in an empty seat. Wait for the owner to press Start. Use WASD + Space (or the joystick on mobile) to play.' ?>
            </p>

            <details class="mt-2 text-sm text-[#AAA0BB]">
                <summary class="cursor-pointer font-bold text-[#B57DDA]">Controls</summary>
                <ul class="list-disc pl-5 mt-1 space-y-0.5">
                    <li><strong>Desktop:</strong> WASD to move, Space for turbo.</li>
                    <li><strong>Mobile:</strong> analog joystick at bottom-left, Turbo button (hold) at bottom-right.</li>
                </ul>
            </details>
        </div>

        <div class="w-full mt-4">
            <button id="room-leave-btn" onclick="keluarSesuaiProsedur()" class="btn-danger bg-[#AAA0BB] text-[#1A1A3A] font-extrabold px-5 py-2.5">Leave to Lobby</button>
        </div>
    </div> 
    
    <aside id="chat-drawer"
           class="w-full xl:w-[420px] shrink-0 bg-[#242752] border-2 border-[#1A1A3A] shadow-[5px_5px_0_0_rgba(26,26,58,1)] flex flex-col h-[500px] xl:h-[calc(100vh-2rem)] xl:sticky top-4 z-20 overflow-hidden">
        <div class="flex items-center justify-between px-3 py-2 border-b-2 border-[#1A1A3A] bg-[#41478B] text-[#FFFFF6]">
            <strong class="text-sm">Chat — Room <?= e($roomName) ?></strong>
            <button type="button" id="chat-close" class="hidden btn-danger bg-[#AAA0BB] text-[#1A1A3A] px-2 py-1 text-xs font-bold">Close</button>
        </div>

        <div id="privat-box" class="chat-box flex-1 m-0 rounded-none border-0 overflow-y-auto bg-[#1A1A3A] p-2 text-[#E8E2D4]">
            <?php while ($pr = mysqli_fetch_assoc($messages)): ?>
                <p class="mb-1">
                    <img src="<?= e(avatar_url($pr['gambar'])) ?>" width="20" height="20" class="rounded-none border border-[#41478B] object-cover inline-block align-middle">
                    <strong class="text-[#B57DDA]"><?= e($pr['nama']) ?></strong>: <span class="text-[#FFFFF6]"><?= e($pr['isi_pesan']) ?></span>
                </p>
            <?php endwhile; ?>
        </div>

        <form class="p-2 pb-3 border-t-2 border-[#1A1A3A] flex gap-2 bg-[#242752]"
              onsubmit="event.preventDefault(); sendRoom();">
            <input type="text" id="msgRoom" placeholder="Type a private message..."
                   class="field flex-1 mt-0 bg-[#1A1A3A] text-[#FFFFF6] border border-[#41478B] px-2 py-1 outline-none placeholder-[#AAA0BB]" autocomplete="off">
            <button type="submit" id="room-chat-send" class="btn shrink-0 bg-[#B57DDA] text-[#1A1A3A] font-black px-4">Send</button>
        </form>
    </aside>

</div> 

<div id="mobile-joystick" class="hidden" aria-hidden="true">
    <div id="joy-base"><div id="joy-knob"></div></div>
</div>

<div id="floating-controls" class="hidden" aria-hidden="true">
    <button id="float-turbo" type="button" class="float-btn float-btn--turbo" title="Turbo (hold)" aria-label="Turbo">⚡</button>
    <button id="float-chat"  type="button" class="float-btn"                                  title="Scroll to Chat" aria-label="Chat">💬</button>
</div>

<div id="end-modal" class="end-modal hidden" role="dialog" aria-modal="true" aria-labelledby="end-modal-title" aria-hidden="true">
    <div class="end-modal__backdrop"></div>
    <div class="end-modal__panel">
        <h2 id="end-modal-title">Time's Up!</h2>
        <p id="end-modal-headline" class="end-modal__headline"></p>
        <ol id="end-modal-scores" class="end-modal__scores"></ol>
        <div id="end-modal-stats-box" class="end-modal__statsbox"></div>
        <div class="end-modal__actions">
            <button id="end-modal-stats" type="button" class="btn" style="display:none">📊 Lihat Statistik</button>
            <button id="end-modal-ok" type="button" class="btn-danger">OK</button>
        </div>
        <p id="end-modal-hint" class="end-modal__hint"></p>
    </div>
</div>

<style>
  /* ===== GLOBAL NEO-BRUTALISM OVERRIDE WITH PALETTE THEME ===== */
  .btn, 
  .btn-danger, 
  .field, 
  .panel-box, 
  .chip, 
  #chat-drawer, 
  .end-modal__panel,
  #game-timer,
  .canvas-wrapper {
    border-radius: 0 !important;
    border: 2px solid #1A1A3A !important;
    box-shadow: 5px 5px 0px 0px #1A1A3A !important; 
  }

  .btn, .btn-danger, .chip, .float-btn, .field {
    transition: transform 0.1s ease, box-shadow 0.1s ease !important;
  }

  /* Warna untuk isi bar turbo (Bright Lavender) */
  .bg-accentGreen {
    background-color: #B57DDA !important; 
  }

  /* Container bar turbo dasar (French Blue Tua) */
  .bg-ink\/20 {
    background-color: #1A1A3A !important; 
  }

  #game-scores span.inline-block.w-20.h-2 {
    height: 12px !important; 
    border: 1.5px solid #1A1A3A !important;
    box-shadow: none !important; 
  }
  
  .btn:active, 
  .btn-danger:active, 
  .chip:active,
  .float-btn:active {
    transform: translate(5px, 5px) !important;
    box-shadow: 0px 0px 0px 0px #1A1A3A !important;
  }

  .field:focus {
    outline: none;
    background: #1A1A3A;
    box-shadow: 5px 5px 0px 0px #1A1A3A !important;
  }

  /* ===== Joystick Custom Vibe ===== */
  #mobile-joystick {
    position:fixed; left:24px; bottom:calc(24px + env(safe-area-inset-bottom));
    z-index:45; width:140px; height:140px; touch-action:none; user-select:none;
  }
  #mobile-joystick.hidden{ display:none; }
  #joy-base {
    position:absolute; inset:0; 
    background:rgba(65, 71, 139, 0.45); border:2px solid #1A1A3A;
    backdrop-filter:blur(4px);
  }
  #joy-knob {
    position:absolute; left:50%; top:50%; width:60px; height:60px; margin:-30px 0 0 -30px;
    background:#B57DDA; border:2px solid #1A1A3A;
    box-shadow: 4px 4px 0px 0px #1A1A3A; transition:transform .05s linear;
  }

  /* ===== Floating buttons ===== */
  #floating-controls {
    position:fixed; right:16px; bottom:calc(16px + env(safe-area-inset-bottom));
    z-index:46; display:flex; flex-direction:column-reverse; gap:12px;
  }
  #floating-controls.hidden{ display:none; }
  .float-btn {
    width:60px; height:60px; border-radius: 0 !important; 
    background:#B57DDA; color:#1A1A3A; border:2px solid #1A1A3A !important;
    box-shadow: 5px 5px 0px 0px #1A1A3A !important;
    font-size:22px; font-weight:800; line-height:1;
    display:flex; align-items:center; justify-content:center;
    touch-action:none; user-select:none; cursor:pointer;
  }
  .float-btn--turbo{ background:#B57DDA; }
  .float-btn--turbo.is-active{ background:#41478B; color:#FFFFF6; }
  .float-btn--owner{ background:#AAA0BB; color:#1A1A3A; }
  body:not(.is-owner) .float-btn--owner{ display:none; }
  
  /* ===== End-Game Modal Palette ===== */
  .end-modal{ position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; }
  .end-modal.hidden{ display:none; }
  .end-modal__backdrop{ position:absolute; inset:0; background:rgba(26,26,58,.6); backdrop-filter:blur(4px); }
  .end-modal__panel{
    position:relative; background:#242752; color:#FFFFF6; 
    padding:20px 22px; min-width:300px; max-width:92vw;
  }
  .end-modal__panel h2{ margin:0 0 6px; font-size:22px; text-align:center; font-weight:800; color:#B57DDA; }
  .end-modal__headline{ margin:0 0 12px; text-align:center; color:#E8E2D4; font-weight:700; font-size:14px; }
  .end-modal__scores{ list-style:none; padding:0; margin:0 0 16px; max-height:50vh; overflow-y:auto; }
  .end-modal__scores li{
    display:flex; justify-content:space-between; align-items:center;
    padding:8px 12px; margin-bottom:6px; border: 2px solid #1A1A3A;
    box-shadow: 3px 3px 0 0 #1A1A3A; 
    background:#1A1A3A; font-size:14px; color:#E8E2D4;
  }
  .end-modal__scores li.is-winner{ background:linear-gradient(90deg,#B57DDA,#41478B); color:#FFFFF6; font-weight:800; }
  .end-modal__actions{ display:flex; gap:8px; justify-content:flex-end; }
  .end-modal__hint{ margin:10px 0 0; text-align:center; font-size:11px; color:#AAA0BB; }
  .end-modal__statsbox{ margin:0 0 14px; }
  .end-modal__statsbox:empty{ display:none; }
  .end-modal__stats-title{ margin:6px 0 6px; font-size:13px; font-weight:800; color:#B57DDA; text-align:center; letter-spacing:.5px; text-transform:uppercase; }
  .end-modal__stats{ width:100%; border-collapse:collapse; font-size:12px; color:#E8E2D4; }
  .end-modal__stats th, .end-modal__stats td{ padding:6px 8px; border-bottom:1px solid #41478B; text-align:left; }
  .end-modal__stats th{ font-size:10px; text-transform:uppercase; letter-spacing:.4px; color:#AAA0BB; font-weight:700; background:rgba(26,26,58,0.5); }
  
  html { scroll-behavior: smooth; }
  html.game-screen-locked, body.game-screen-locked { scroll-behavior: auto !important; }
  #game-canvas { scroll-margin-top: 80px; scroll-margin-bottom: 80px; }
  body.game-screen-locked { overscroll-behavior: none; -webkit-overflow-scrolling: auto; touch-action: none; }
</style>

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
    seatUrl:  "seats.php",
    wasPlaying: <?= $wasPlaying ? 'true' : 'false' ?>
};

/* ---- Auto-Focus Chat Script ---- */
(function () {
    const btn   = document.getElementById('float-chat');
    const input = document.getElementById('msgRoom');
    if (!btn || !input) return;

    btn.addEventListener('click', () => {
        input.focus();
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
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
        document.getElementById('floating-controls')?.removeAttribute('aria-hidden');
        document.getElementById('floating-controls')?.classList.remove('hidden');
    }
})();

/* ---- Tombol Profil di room ---- */
(function () {
    const btn = document.getElementById('room-profile-open');
    if (!btn) return;
    btn.addEventListener('click', () => {
        if (typeof window.openUserSidebar === 'function') window.openUserSidebar();
    });
})();
</script>

<script src="js/room.js?v=<?= filemtime(__DIR__ . '/../js/room.js') ?>"></script>
<script src="js/seats.js"></script>
<script src="js/game.js?v=<?= filemtime(__DIR__ . '/../js/game.js') ?>"></script>
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

/* ===== Avatar dari profil (DB) ===== */
(function(){
  const ROOM_ID = (window.__ROOM_CONFIG__ && Number(window.__ROOM_CONFIG__.roomId)) || 0;

  binf = function resolveAvatarUrl(g){
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

  window.__AVATAR_IMGS_BY_ROOM__ = window.__AVATAR_IMGS_BY_ROOM__ || {};
  window.__AVATAR_URLS_BY_ROOM__ = window.__AVATAR_URLS_BY_ROOM__ || {};
  if (!window.__AVATAR_IMGS_BY_ROOM__[ROOM_ID]) window.__AVATAR_IMGS_BY_ROOM__[ROOM_ID] = {};
  if (!window.__AVATAR_URLS_BY_ROOM__[ROOM_ID]) window.__AVATAR_URLS_BY_ROOM__[ROOM_ID] = {};

  window.__AVATAR_IMGS__ = window.__AVATAR_IMGS_BY_ROOM__[ROOM_ID];
  window.__AVATAR_URLS__ = window.__AVATAR_URLS_BY_ROOM__[ROOM_ID];

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

  function syncFromSeatState(){
    const seats = (window.__SEAT_STATE__ && window.__SEAT_STATE__.seats) || [];
    for (let n = 1; n <= 4; n++) {
      const s = seats.find(x => Number(x.seat_no) === n);
      const uid = s && Number(s.user_id) || 0;
      if (!s || !uid) {
        applyAvatarToSeat(n, '');
        continue;
      }
      const url = binf(s.user_foto || '');
      applyAvatarToSeat(n, url);
    }
  }

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
        setTimeout(syncFromSeatState, 100);
        clearInterval(t);
      } else if (++tries > 50) {
        clearInterval(t);
      }
    }, 100);
  })();
})();
</script>