<?php
/**
 * Header HTML + setup WS URL global untuk JavaScript.
 * Versi Tailwind (CDN) — file css/style.css tidak lagi dimuat.
 */
$config = $config ?? require __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'CL!CK THE CIRCLE — RRR') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        bgpage:     '#6b6f78',
        panel:      '#9aa0a6',
        panel2:     '#b3b8bd',
        control:    '#d9dcdf',
        controlHi:  '#e6e9ec',
        ink:        '#1a1a1a',
        inkMuted:   '#4b4f55',
        accentRed:  '#d83a3a',
        accentGreen:'#2ecc40',
        accentYel:  '#ffd23f',
      },
      boxShadow: {
        panel: '0 2px 0 rgba(0,0,0,.25), 0 6px 18px rgba(0,0,0,.18)',
        btn:   '0 2px 0 rgba(0,0,0,.35)',
        inset1:'inset 0 2px 6px rgba(0,0,0,.1)',
      },
      fontFamily: {
        pixel: ['"Press Start 2P"', '"Courier New"', 'monospace'],
        body:  ['Inter', '"Segoe UI"', 'system-ui', 'sans-serif'],
      }
    }
  }
};
</script>
<style type="text/tailwindcss">
  @layer base {
    body {
      @apply font-body text-ink bg-bgpage min-h-screen flex flex-col items-center px-4 py-8;
      background-image:
        radial-gradient(rgba(0,0,0,.08) 1px, transparent 1px),
        radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
      background-size: 4px 4px, 4px 4px;
      background-position: 0 0, 2px 2px;
    }
  }
  @layer components {
    .panel-box   { @apply w-full max-w-[960px] bg-panel border border-ink shadow-panel; }
    .panel2-box  { @apply w-full max-w-[960px] bg-panel2 border border-ink shadow-panel; }
    .h-title     { @apply panel2-box text-center text-2xl md:text-3xl font-extrabold tracking-wide px-5 py-3 mb-3; }
    .h-section   { @apply panel2-box text-xl md:text-2xl font-extrabold tracking-wide px-4 py-2 mb-3; }
    .field       { @apply block w-full mt-1 px-3 py-2 bg-control border border-ink rounded-sm outline-none focus:bg-white focus:ring-2 focus:ring-black/30; }
    .btn         { @apply inline-flex items-center justify-center font-bold uppercase tracking-wide bg-control hover:bg-controlHi border border-ink rounded-sm px-5 py-2 shadow-btn active:translate-y-[2px] active:shadow-none disabled:opacity-50 disabled:cursor-not-allowed min-h-[40px]; }
    .btn-danger  { @apply btn bg-accentRed text-white hover:brightness-110; }
    .flash       { @apply panel-box bg-green-200 px-4 py-3 my-3 font-semibold; }
    .flash-err   { @apply panel-box bg-red-200 px-4 py-3 my-3 font-semibold; }
    .chip        { @apply panel-box flex items-center gap-3 px-4 py-2; }
    .chat-box    { @apply w-full max-w-[960px] bg-control border border-ink rounded-sm p-4 overflow-y-auto shadow-inset1; }
    .chat-box p  { @apply m-1 flex items-center gap-2; }
    .chat-box img{ @apply border border-ink bg-white; }
    .room-card   { @apply panel2-box rounded-sm p-4 flex flex-col gap-2 max-w-none; }
    .room-card h4{ @apply m-0 text-lg font-extrabold text-center px-2 py-1 bg-control border border-ink; }
  }
</style>
<script>
// WS URL otomatis pakai hostname yang sama dengan halaman.
(function () {
    var port = <?= (int)$config['ws_port'] ?>;
    var proto = location.protocol === "https:" ? "wss:" : "ws:";
    window.__WS_URL__ = proto + "//" + location.hostname + ":" + port;
})();
</script>
</head>
<body>

<?php
/* ===== Sidebar info user yang login (muncul di semua halaman setelah login) ===== */
if (!empty($_SESSION['login'])):
    require_once __DIR__ . '/../helpers/avatar.php';
    $__sb_avatar = avatar_url($_SESSION['gambar'] ?? '');
    $__sb_nama   = (string)($_SESSION['nama'] ?? 'Player');
    $__sb_uid    = (int)($_SESSION['id'] ?? 0);
?>
<div id="user-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-ink/45 lg:hidden" aria-hidden="true"></div>
<aside id="user-sidebar"
       class="fixed top-0 left-0 z-40 h-full w-[220px] bg-panel border-r-2 border-ink shadow-panel
              p-4 hidden flex-col gap-3 lg:flex"
       aria-label="Player info sidebar">
    <button type="button" id="user-sidebar-close" class="absolute right-2 top-2 lg:hidden text-xl font-bold leading-none px-2 py-1" aria-label="Close profile">×</button>

    <div class="font-pixel text-xs text-white text-center bg-ink/80 border border-ink rounded-sm py-2"
         style="text-shadow: 1px 1px 0 #000;">
        PLAYER INFO
    </div>

    <div class="flex flex-col items-center gap-2 bg-control border border-ink rounded-sm p-3 shadow-inset1">
        <div class="text-center">
            <div class="text-xs text-inkMuted">Logged in as</div>
            <div class="font-extrabold text-base break-all"><?= e($__sb_nama) ?></div>
        </div>
    </div>

    <!-- ===== Online users (live via WebSocket) ===== -->
    <div class="flex-1 min-h-0 flex flex-col bg-control border border-ink rounded-sm p-2 shadow-inset1">
        <div class="font-pixel text-[10px] text-ink text-center bg-panel2 border border-ink rounded-sm py-1 mb-2">
            ONLINE (<span id="online-users-count">0</span>)
        </div>
        <div id="online-users-list" class="flex flex-col gap-1 overflow-y-auto pr-1 text-sm">
            <div class="text-[10px] text-inkMuted text-center py-2">Connecting...</div>
        </div>
    </div>

    <div class="text-[10px] text-inkMuted text-center leading-relaxed">
        RRR Lite v4 — player info sidebar
    </div>
</aside>

<script>
window.__PRESENCE_CFG__ = {
    wsUrl:   window.__WS_URL__,
    myId:    <?= (int)$__sb_uid ?>,
    myName:  <?= json_encode($__sb_nama, JSON_UNESCAPED_UNICODE) ?>,
    myFoto:  <?= json_encode($__sb_avatar, JSON_UNESCAPED_UNICODE) ?>,
    context: "sidebar"
};
</script>
<script src="js/presence.js" defer></script>

<style>
    /* Geser konten utama ke kanan saat sidebar tampil (≥ lg) */
    @media (min-width: 1024px) {
        body { padding-left: 240px; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var closeBtn = document.getElementById('user-sidebar-close');
    var sidebar = document.getElementById('user-sidebar');
    var backdrop = document.getElementById('user-sidebar-backdrop');
    window.openUserSidebar = function () {
        if (!sidebar || !backdrop) return;
        sidebar.classList.remove('hidden');
        sidebar.classList.add('flex');
        backdrop.classList.remove('hidden');
    };
    window.closeUserSidebar = function () {
        if (!sidebar || !backdrop) return;
        if (window.matchMedia('(min-width: 1024px)').matches) return;
        sidebar.classList.add('hidden');
        sidebar.classList.remove('flex');
        backdrop.classList.add('hidden');
    };
    if (closeBtn) closeBtn.addEventListener('click', window.closeUserSidebar);
    if (backdrop) backdrop.addEventListener('click', window.closeUserSidebar);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') window.closeUserSidebar(); });
});
</script>
<?php endif; ?>


<div class="w-full max-w-[960px] text-center">
    <div class="inline-block bg-panel border border-ink shadow-panel px-7 py-4 my-2 mb-6 font-pixel text-2xl md:text-3xl tracking-[2px] text-white"
         style="text-shadow: 2px 2px 0 #000;">
        CL<span class="text-accentRed">!</span>CK THE CIRCLE
    </div>
</div>

<?php
$ok  = flash_pop('ok');
$err = flash_pop('err');
if ($ok)  echo '<div class="flash">' . e($ok) . '</div>';
if ($err) echo '<div class="flash-err">' . e($err) . '</div>';
?>
