<?php
/**
 * @var array          $rooms
 * @var mysqli_result  $messages
 * @var array          $initialCooldowns
 */
require_once __DIR__ . '/../helpers/avatar.php';
include __DIR__ . '/header.php';

$myGambar      = (string)$_SESSION['gambar'];
$myAvatarUrl   = avatar_url($myGambar);
$myPresetFile  = avatar_preset_file($myGambar);
$presetItems   = avatar_presets_list();
?>
<button type="button" id="lobby-profile-open"
        class="chip text-left cursor-pointer hover:bg-panel2 transition-colors"
        aria-controls="user-sidebar" aria-expanded="false">
    <span class="flex-1">Hi, <strong><?= e($_SESSION['nama']) ?></strong>!</span>
    <span class="font-bold underline underline-offset-4">Profile</span>
</button>

<details class="panel-box mt-1 mb-1 rounded">
    <summary class="cursor-pointer select-none px-4 py-2 font-bold bg-panel2 border border-ink rounded">
        Manage My Account
    </summary>
    <div class="p-4">
        <h4 class="font-extrabold mb-2">Edit Profile</h4>
        <form method="post" action="profile_update.php" enctype="multipart/form-data" class="flex flex-col gap-3" id="profile-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <label class="font-semibold">New name:
                <input type="text" name="new_nama" value="<?= e($_SESSION['nama']) ?>" required class="field">
            </label>
            <label class="font-semibold">New NPM:
                <input type="password" name="new_npm" value="<?= e($_SESSION['npm'] ?? '') ?>" required class="field">
            </label>

            <fieldset class="border border-ink/40 rounded-sm p-3">
                <legend class="font-extrabold px-1">Profile Avatar</legend>

                <div class="flex flex-col gap-3">
                    <label class="font-semibold inline-flex items-center gap-2">
                        <input type="radio" name="avatar_mode" value="keep" checked>
                        <span>Use current avatar</span>
                        <img src="<?= e($myAvatarUrl) ?>" width="32" height="32" class="rounded-full border border-ink object-cover">
                    </label>

                    <label class="font-semibold inline-flex items-start gap-2">
                        <input type="radio" name="avatar_mode" value="preset" class="mt-1">
                        <span class="flex-1">
                            <div class="mb-1">Choose from presets</div>
                            <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
                                <?php foreach ($presetItems as $p): ?>
                                    <label class="cursor-pointer relative">
                                        <input type="radio" name="preset_avatar" value="<?= e($p['file']) ?>"
                                               <?= ($p['file'] === $myPresetFile) ? 'checked' : '' ?>
                                               class="peer sr-only">
                                        <img src="assets/avatars/<?= e($p['file']) ?>"
                                             alt="<?= e($p['name']) ?>"
                                             title="<?= e($p['name']) ?>"
                                             class="w-12 h-12 rounded-full border-2 border-ink/30 object-cover peer-checked:border-accentRed peer-checked:ring-2 peer-checked:ring-accentRed">
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </span>
                    </label>

                    <label class="font-semibold inline-flex items-center gap-2">
                        <input type="radio" name="avatar_mode" value="upload">
                        <span>Upload your own photo (max 2MB)</span>
                        <input type="file" name="new_gambar" accept="image/*" class="field cursor-pointer">
                    </label>
                </div>
            </fieldset>

            <button type="submit" class="btn self-start">Save Changes</button>
        </form>

        <hr class="my-4 border-t border-ink/30">
        <h4 class="font-extrabold mb-2">Delete Account</h4>
        <form method="post" action="profile_delete.php"
              onsubmit="return confirm('Delete your account? This action cannot be undone.');"
              class="flex flex-col gap-2">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <label class="font-semibold">Type DELETE to confirm: <input type="text" name="delete_confirm" required class="field"></label>
            <button type="submit" class="btn-danger self-start">Delete My Account</button>
        </form>

        <hr class="my-4 border-t border-ink/30">
        <h4 class="font-extrabold mb-2">Logout</h4>
        <a href="logout.php" class="btn-danger self-start inline-block">↪ Logout</a>
    </div>
</details>

<!-- Chat box lobby (DIPERBESAR) -->
<div id="chat-box" class="chat-box h-[480px] md:h-[560px]">
    <?php while ($p = mysqli_fetch_assoc($messages)): ?>
        <p>
            <img src="<?= e(avatar_url($p['gambar'])) ?>" width="20" height="20" class="rounded-full object-cover">
            <strong><?= e($p['nama']) ?></strong>: <?= e($p['isi_pesan']) ?>
        </p>
    <?php endwhile; ?>
</div>

<div class="w-full max-w-[960px] mt-3 flex gap-2">
    <input type="text" id="message" placeholder="Write a lobby message..." class="field flex-1 mt-0">
    <button onclick="sendLobby()" class="btn">Send</button>
</div>

<hr class="w-full max-w-[960px] my-6 border-t border-ink/30">
<h3 class="h-section">Private Rooms</h3>

<div id="rooms-container" class="w-full max-w-[960px] grid gap-4 mt-2"
     style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));">
<?php foreach ($rooms as $room): ?>
    <?php $roomName = $room['name'] ?? ('Room #' . $room['id']); ?>
    <div id="room-card-<?= $room['id'] ?>" class="room-card">
        <h4>😴 <?= e($roomName) ?></h4>
        <p id="owner-info-<?= $room['id'] ?>" class="text-sm text-inkMuted m-0">
            <?php if ($room['is_occupied'] === 1 && $room['owner_name'] !== ''): ?>
                Current owner: <?= e($room['owner_name']) ?>
            <?php else: ?>
                No owner yet.
            <?php endif; ?>
        </p>
        <form method="post" action="room_enter.php" id="form-room-<?= $room['id'] ?>" class="flex flex-col gap-2 m-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
            <input type="password" name="room_pass" placeholder="Password for Room <?= e($roomName) ?>" required class="field">
            <span id="room-actions-<?= $room['id'] ?>">
                <?php if ($room['is_occupied'] === 0): ?>
                    <button type="submit" class="btn w-full">Enter &amp; Become Owner</button>
                <?php else: ?>
                    <button type="button" onclick="ketukPintu(<?= $room['id'] ?>)" class="btn w-full">Request to Join</button>
                <?php endif; ?>
            </span>
        </form>
    </div>
<?php endforeach; ?>
</div>

<script>
window.__LOBBY_CONFIG__ = {
    wsUrl: window.__WS_URL__,
    myId:  <?= (int)$_SESSION['id'] ?>,
    myName: <?= json_encode((string)$_SESSION['nama']) ?>,
    myFoto: <?= json_encode((string)$myAvatarUrl) ?>,
    csrfToken: <?= json_encode(csrf_token()) ?>,
    initialCooldowns: <?= json_encode($initialCooldowns) ?>
};

// UX: ketika user klik radio preset/upload, otomatis pilih mode-nya.
(function(){
  document.querySelectorAll('#profile-form input[name="preset_avatar"]').forEach(r => {
    r.addEventListener('change', () => {
      const m = document.querySelector('#profile-form input[name="avatar_mode"][value="preset"]');
      if (m) m.checked = true;
    });
  });
  const fileIn = document.querySelector('#profile-form input[name="new_gambar"]');
  if (fileIn) fileIn.addEventListener('change', () => {
    const m = document.querySelector('#profile-form input[name="avatar_mode"][value="upload"]');
    if (m) m.checked = true;
  });

  const lobbyProfileOpen = document.getElementById('lobby-profile-open');
  if (lobbyProfileOpen) {
    lobbyProfileOpen.addEventListener('click', () => {
      if (typeof window.openUserSidebar === 'function') window.openUserSidebar();
    });
  }
})();
</script>
<script src="js/lobby.js"></script>
<?php include __DIR__ . '/footer.php'; ?>
