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
$roomName  = Room::nameFor((int)$roomId);
$roleLabel = $isOwner ? 'owner' : 'guest';
$wasPlaying = room_is_playing((int)$roomId);
?>

<!-- Room Header -->
<h1 class="h-title">Private Room <?= e($roomName) ?> (Status: <?= e($roleLabel) ?>)</h1>

<button type="button" id="room-profile-open"
        class="chip text-left cursor-pointer hover:bg-panel2 transition-colors w-full"
        aria-controls="user-sidebar" aria-expanded="false">
    <img src="<?= e(avatar_url($_SESSION['gambar'])) ?>" width="30" height="30" class="rounded-full border border-ink object-cover">
    <span class="flex-1 text-sm md:text-base">Logged in as: <strong><?= e($_SESSION['nama']) ?></strong></span>
    <?php if ($isOwner): ?>
        <span class="px-2 py-0.5 bg-accent-green text-ink rounded-sm text-xs font-bold">OWNER</span>
    <?php endif; ?>
    <span class="font-bold underline underline-offset-4 text-xs md:text-sm">👤 Profile</span>
</button>

<!-- ============ SEAT PANEL ============ -->
<div class="panel-box mt-4 p-4">
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <h2 class="text-xl font-extrabold tracking-wide mr-auto">Seats</h2>
        <?php if ($isOwner): ?>
            <button id="seat-fill-bots"  class="btn text-xs">Fill Bots</button>
            <button id="seat-clear-bots" class="btn-danger text-xs">Clear Bots</button>
        <?php endif; ?>
    </div>
    <div id="seat-grid" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
        <!-- Managed by JS -->
    </div>
    <p id="seat-status" class="mt-2 text-xs text-ink-muted">Loading seats...</p>
</div>

<!-- ============ GAME ARENA ============ -->
<div class="panel-box mt-4 p-4">
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <h2 class="text-xl font-extrabold tracking-wide mr-auto">Arena</h2>

        <div class="flex flex-wrap gap-2 items-center">
            <select id="game-difficulty" class="field mt-0 w-auto text-xs" <?= $isOwner ? '' : 'disabled' ?>>
                <option value="easy">Easy</option>
                <option value="normal" selected>Normal</option>
                <option value="hard">Hard</option>
                <option value="indonesian">Indonesian</option>
            </select>

            <select id="game-duration" class="field mt-0 w-auto text-xs" <?= $isOwner ? '' : 'disabled' ?>>
                <option value="30">30s</option>
                <option value="60">60s</option>
                <option value="90" selected>90s</option>
            </select>

            <select id="game-bgm" class="field mt-0 w-auto text-xs">
                <option value="">— No BGM —</option>
            </select>

            <label class="text-xs font-bold inline-flex items-center gap-1 cursor-pointer">
                <input type="checkbox" id="game-sfx-on" checked class="align-middle"> SFX
            </label>
        </div>

        <div class="flex gap-2 ml-auto items-center">
            <div class="text-xs font-bold mr-2">
                Time: <span id="game-timer" class="px-2 py-1 bg-control border border-ink rounded-sm">90s</span>
            </div>
            <button id="game-start" class="btn h-9" <?= $isOwner ? '' : 'disabled' ?>>Start</button>
            <button id="game-reset" class="btn-danger h-9" <?= $isOwner ? '' : 'disabled' ?>>Reset</button>
        </div>
    </div>

    <div id="game-scores" class="flex flex-wrap gap-2 mb-3 text-sm"></div>

    <div class="bg-ink border border-ink shadow-inset1 p-1 inline-block w-full overflow-hidden">
        <canvas id="game-canvas" width="900" height="520" class="block max-w-full mx-auto"></canvas>
    </div>

    <div class="mt-3 flex flex-wrap gap-4 items-start">
        <div class="flex-1">
            <p id="game-status" class="text-xs text-ink-muted leading-relaxed">
                <?= $isOwner ? 'Pick a seat then press Start.' : 'Wait for the owner to press Start.' ?>
            </p>
        </div>
        <div class="flex gap-4">
             <select id="game-arena" class="field mt-0 w-auto text-xs">
                <option value="">— Arena: Dark —</option>
            </select>
            <select id="game-boxskin" class="field mt-0 w-auto text-xs">
                <option value="default">— Skin: Default —</option>
            </select>
        </div>
    </div>
</div>

<!-- Logic Mobile & Modal tetap sama -->
<div id="mobile-joystick" class="hidden">
    <div id="joy-base"><div id="joy-knob"></div></div>
</div>

<div id="floating-controls" class="hidden">
    <button id="float-turbo" type="button" class="float-btn float-btn--turbo">⚡</button>
    <button id="float-chat" type="button" class="float-btn">💬</button>
</div>

<div id="end-modal" class="end-modal hidden" role="dialog" aria-hidden="true">
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

<!-- FIX CHAT DRAWER: Gue balikin ke class lama biar JS nemu elemennya -->
<aside id="chat-drawer" 
       class="fixed top-0 right-0 z-30 h-full w-full max-w-[420px] bg-panel border-l border-ink shadow-2xl translate-x-full transition-transform duration-300 ease-out flex flex-col" 
       aria-hidden="true">
    <div class="flex items-center justify-between px-3 py-2 border-b border-ink bg-control">
        <strong class="text-sm">Chat — Room <?= e($roomName) ?></strong>
        <button type="button" id="chat-close" class="btn-danger px-2 py-1 text-xs">Close</button>
    </div>
    <div id="privat-box" class="chat-box flex-1 m-0 rounded-none border-0 overflow-y-auto">
        <?php while ($pr = mysqli_fetch_assoc($messages)): ?>
            <p class="p-1 text-sm">
                <img src="<?= e(avatar_url($pr['gambar'])) ?>" width="20" height="20" class="rounded-full object-cover inline align-middle">
                <strong><?= e($pr['nama']) ?></strong>: <?= e($pr['isi_pesan']) ?>
            </p>
        <?php endwhile; ?>
    </div>
    <form class="p-2 pb-3 border-t border-ink flex gap-2" onsubmit="event.preventDefault(); sendRoom();">
        <input type="text" id="msgRoom" placeholder="Type a message..." class="field flex-1 mt-0" autocomplete="off">
        <button type="submit" id="room-chat-send" class="btn shrink-0">Send</button>
    </form>
</aside>

<div class="w-full max-w-[960px] mt-4 mb-10">
    <button id="room-leave-btn" onclick="keluarSesuaiProsedur()" class="btn-danger">Leave to Lobby</button>
</div>

<!-- Logic Scripts -->
<script>
window.__ROOM_CONFIG__ = {
    wsUrl: window.__WS_URL__,
    role: <?= json_encode($role) ?>,
    isOwner: <?= $isOwner ? 'true' : 'false' ?>,
    myName: <?= json_encode((string)$_SESSION['nama']) ?>,
    myFoto: <?= json_encode((string)avatar_url($_SESSION['gambar'])) ?>,
    myId: <?= (int)$_SESSION['id'] ?>,
    roomId: <?= (int)$roomId ?>,
    roomName: <?= json_encode($roomName) ?>,
    leaveUrl: <?= json_encode('room_leave.php?room_id=' . (int)$roomId) ?>,
    lobbyUrl: "lobby.php",
    diffUrl: "room_difficulty.php",
    saveUrl: "game_save.php",
    seatUrl: "seats.php",
    wasPlaying: <?= $wasPlaying ? 'true' : 'false' ?>
};

(function () {
    const drawer = document.getElementById('chat-drawer');
    const btn = document.getElementById('float-chat');
    const close = document.getElementById('chat-close');
    if (!drawer || !btn) return;
    let open = false;
    function setOpen(v) {
        open = v;
        drawer.classList.toggle('translate-x-full', !v);
        document.body.classList.toggle('chat-open', v);
    }
    btn.addEventListener('click', () => setOpen(!open));
    if (close) close.addEventListener('click', () => setOpen(false));

    if (window.__ROOM_CONFIG__.isOwner) document.body.classList.add('is-owner');
    if (('ontouchstart' in window) || navigator.maxTouchPoints > 0) {
        document.body.classList.add('is-touch');
    } else {
        document.getElementById('floating-controls')?.classList.remove('hidden');
    }
})();
</script>

<script src="js/room.js"></script>
<script src="js/seats.js"></script>
<script src="js/game.js"></script>

<!-- Asset Loader Logic (Arena & Boxskin) -->
<script>
/* Paste kembali logic script Arena & Boxskin lu di sini */
</script>

<?php include __DIR__ . '/footer.php'; ?>