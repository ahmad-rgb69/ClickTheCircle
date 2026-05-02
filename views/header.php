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
<script type="module" src="http://localhost:5173/@vite/client"></script>
    <link rel="stylesheet" href="http://localhost:5173/assets/app.css">
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
