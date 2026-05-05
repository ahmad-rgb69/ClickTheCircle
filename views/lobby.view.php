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

<!-- Tombol Profile Toggle -->
<button type="button" id="lobby-profile-open"
        class="chip text-left cursor-pointer hover:bg-panel2 transition-colors"
        aria-controls="user-sidebar" aria-expanded="false">
    <span class="flex-1">Hi, <strong><?= e($_SESSION['nama']) ?></strong>!</span>
    <span class="font-bold underline underline-offset-4 text-xs">Profile</span>
</button>

<!-- Akun Management -->
<details class="panel-box mt-2 mb-2">
    <summary class="cursor-pointer select-none px-4 py-2 font-bold bg-panel2 border-b border-ink rounded-t-2xl">
        Manage My Account
    </summary>
    <div class="p-4">
        <h4 class="font-extrabold mb-3 text-sm tracking-tight">Edit Profile</h4>
        <form method="post" action="profile_update.php" enctype="multipart/form-data" class="flex flex-col gap-4" id="profile-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="text-xs font-bold uppercase tracking-wider text-ink-muted">New Name
                    <input type="text" name="new_nama" value="<?= e($_SESSION['nama']) ?>" required class="field">
                </label>
                <label class="text-xs font-bold uppercase tracking-wider text-ink-muted">New NPM
                    <input type="password" name="new_npm" value="<?= e($_SESSION['npm'] ?? '') ?>" required class="field">
                </label>
            </div>

            <fieldset class="border border-ink/20 rounded-xl p-3 bg-control/30">
                <legend class="font-extrabold px-2 text-xs uppercase tracking-widest text-ink">Avatar Selection</legend>

                <div class="flex flex-col gap-4">
                    <label class="font-semibold inline-flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="avatar_mode" value="keep" checked class="accent-ink">
                        <span class="text-sm">Keep current</span>
                        <img src="<?= e($myAvatarUrl) ?>" width="32" height="32" class="rounded-full border border-ink object-cover shadow-sm">
                    </label>

                    <div class="space-y-2">
                        <label class="font-semibold inline-flex items-center gap-3 cursor-pointer">
                            <input type="radio" name="avatar_mode" value="preset" class="accent-ink">
                            <span class="text-sm">Choose Preset</span>
                        </label>
                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 pl-7">
                            <?php foreach ($presetItems as $p): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="preset_avatar" value="<?= e($p['file']) ?>"
                                           <?= ($p['file'] === $myPresetFile) ? 'checked' : '' ?>
                                           class="peer sr-only">
                                    <img src="assets/avatars/<?= e($p['file']) ?>"
                                         alt="<?= e($p['name']) ?>"
                                         class="w-10 h-10 rounded-full border-2 border-ink/10 object-cover peer-checked:border-accent-yel peer-checked:ring-2 peer-checked:ring-accent-yel transition-all hover:scale-110">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label class="font-semibold inline-flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="avatar_mode" value="upload" class="accent-ink">
                        <span class="text-sm">Upload Photo (Max 2MB)</span>
                        <input type="file" name="new_gambar" accept="image/*" class="field text-xs py-1.5 flex-1">
                    </label>
                </div>
            </fieldset>

            <button type="submit" class="btn self-start px-8">Save Changes</button>
        </form>

        <div class="mt-6 pt-4 border-t border-ink/10 flex flex-wrap gap-4 items-end">
            <form method="post" action="profile_delete.php" 
                  onsubmit="return confirm('Delete account forever?');" 
                  class="flex-1 min-w-[200px]">
                <h4 class="font-extrabold mb-1 text-xs text-accent-red uppercase">Danger Zone</h4>
                <div class="flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="text" name="delete_confirm" placeholder="Type DELETE" required class="field text-xs flex-1">
                    <button type="submit" class="btn-danger text-xs px-3">Delete</button>
                </div>
            </form>
            <a href="logout.php" class="btn-danger text-xs px-4 h-10 flex items-center">↪ Logout</a>
        </div>
    </div>
</details>

<!-- Chat Lobby -->
<div id="chat-box" class="chat-box h-[480px] md:h-[560px] scroll-smooth">
    <?php while ($p = mysqli_fetch_assoc($messages)): ?>
        <p class="text-sm leading-relaxed mb-1.5">
            <img src="<?= e(avatar_url($p['gambar'])) ?>" width="20" height="20" class="rounded-full object-cover inline align-middle mr-1 border border-ink/20">
            <strong class="text-ink"><?= e($p['nama']) ?></strong>: <span class="text-ink-muted"><?= e($p['isi_pesan']) ?></span>
        </p>
    <?php endwhile; ?>
</div>

<div class="w-full max-w-[960px] mt-3 flex gap-2">
    <input type="text" id="message" placeholder="Type a message..." class="field flex-1 mt-0 shadow-inset1">
    <button onclick="sendLobby()" class="btn px-6">Send</button>
</div>

<hr class="w-full max-w-[960px] my-8 border-t border-ink/10">

<!-- Room List -->
<h3 class="h-section">Private Rooms</h3>

<div id="rooms-container" class="w-full max-w-[960px] grid gap-4 mt-2"
     style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));">
<?php foreach ($rooms as $room): ?>
    <?php $roomName = $room['name'] ?? ('Room #' . $room['id']); ?>
    <div id="room-card-<?= $room['id'] ?>" class="room-card group hover:scale-[1.02] transition-transform">
        <h4 class="bg-panel p-2 rounded-lg border border-ink/10 text-center font-bold">😴 <?= e($roomName) ?></h4>
        <p id="owner-info-<?= $room['id'] ?>" class="text-[10px] text-ink-muted text-center italic uppercase tracking-wider my-1">
            <?php if ($room['is_occupied'] === 1 && $room['owner_name'] !== ''): ?>
                Occupied by: <?= e($room['owner_name']) ?>
            <?php else: ?>
                No owner yet.
            <?php endif; ?>
        </p>
        <form method="post" action="room_enter.php" id="form-room-<?= $room['id'] ?>" class="flex flex-col gap-2 m-0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
            <input type="password" name="room_pass" placeholder="Password" required class="field text-center text-xs">
            <span id="room-actions-<?= $room['id'] ?>">
                <?php if ($room['is_occupied'] === 0): ?>
                    <button type="submit" class="btn w-full text-xs">Enter &amp; Claim</button>
                <?php else: ?>
                    <button type="button" onclick="ketukPintu(<?= $room['id'] ?>)" class="btn w-full text-xs">Knock Door</button>
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

(function(){
    // Auto-select radio mode
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

    // Profile toggle
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