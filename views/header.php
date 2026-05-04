<?php
/**
 * Header HTML + setup WS URL global untuk JavaScript.
 * Versi Full Inline Utility - Tidak tergantung pada custom utility di app.css
 */
$config = $config ?? require __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'CL!CK THE CIRCLE — RRR') ?></title>
    
    <!-- Vite Assets: Langsung panggil app.css lo -->
    <script type="module" src="http://localhost:5173/@vite/client"></script>
    <script type="module" src="http://localhost:5173/resources/css/app.css"></script>
    
    <link rel="icon" type="image/png" href="img/logo.png">

    <script>
    (function () {
        var port = <?= (int)$config['ws_port'] ?>;
        var proto = location.protocol === "https:" ? "wss:" : "ws:";
        window.__WS_URL__ = proto + "//" + location.hostname + ":" + port;
    })();
    </script>
</head>
<body class="font-body">

  <?php
  if (!empty($_SESSION['login'])):
      require_once __DIR__ . '/../helpers/avatar.php';
      $__sb_avatar = avatar_url($_SESSION['gambar'] ?? '');
      $__sb_nama   = (string)($_SESSION['nama'] ?? 'Player');
      $__sb_uid    = (int)($_SESSION['id'] ?? 0);
  ?>
  <!-- Backdrop -->
  <div id="user-sidebar-backdrop" class="sidebar-backdrop" aria-hidden="true"></div>
  
  <!-- Sidebar -->
  <aside id="user-sidebar" class="sidebar-container" aria-label="Player info sidebar">
      
      <button type="button" id="user-sidebar-close" class="absolute right-2 top-2 lg:hidden text-xl font-bold leading-none px-2 py-1" aria-label="Close profile">×</button>

      <div class="font-pixel text-xs text-white text-center bg-ink/80 border border-ink rounded-xl py-2"
          style="text-shadow: 1px 1px 0 #000;">
          PLAYER INFO
      </div>

      <!-- Logged In Box -->
      <div class="flex flex-col items-center gap-2 bg-control border border-ink rounded-xl p-3 shadow-inset1">
          <div class="text-center">
              <div class="text-xs text-ink-muted">Logged in as</div>
              <div class="font-extrabold text-base break-all"><?= e($__sb_nama) ?></div>
          </div>
      </div>

      <!-- Online Users Box -->
      <div class="flex-1 min-h-0 flex flex-col bg-control border border-ink rounded-xl p-2 shadow-inset1">
          <div class="font-pixel text-[10px] text-ink text-center bg-panel2 border border-ink rounded-xl py-1 mb-2">
              ONLINE (<span id="online-users-count">0</span>)
          </div>
          <div id="online-users-list" class="flex flex-col gap-1 overflow-y-auto pr-1 text-sm text-ink">
              <div class="text-[10px] text-ink-muted text-center py-2">Connecting...</div>
          </div>
      </div>

      <div class="text-[10px] text-ink-muted text-center leading-relaxed">
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

  <?php
  $ok  = flash_pop('ok');
  $err = flash_pop('err');
  
  if ($ok)  echo '<div class="flash-msg bg-green-200">' . e($ok) . '</div>';
  if ($err) echo '<div class="flash-msg bg-red-200">' . e($err) . '</div>';
  ?>