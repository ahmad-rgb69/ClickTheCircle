<?php
/**
 * @var array          $rooms
 * @var mysqli_result  $messages
 * @var array          $initialCooldowns
 */
require_once __DIR__ . '/../helpers/avatar.php';
include __DIR__ . '/header.php';

$myGambar       = (string)$_SESSION['gambar'];
$myAvatarUrl   = avatar_url($myGambar);
$myPresetFile  = avatar_preset_file($myGambar);
$presetItems   = avatar_presets_list();
?>

<style>
    @keyframes peep {
        0%, 100% {
            transform: translateY(100%); /* Tersembunyi di bawah */
        }
        10%, 90% {
            transform: translateY(30%);    /* Muncul penuh */
        }
    }

    .animate-peep {
        animation: peep 5s ease-in-out infinite;
    }
</style>

<audio id="lobby-bgm" loop>
    <source src="img/softandwet.mp3" type="audio/mpeg">
</audio>

<main class="flex flex-col lg:flex-row items-stretch justify-between max-w-7xl mx-auto w-full gap-8 px-4 py-8 mb-10 relative">

    <div class="w-full lg:w-4/12 flex flex-col gap-6 z-10">
        
        <button type="button" id="lobby-profile-open"
                class="flex justify-between items-center bg-gray-300 text-gray-800 py-4 px-6 text-xl font-bold border-2 border-gray-600 shadow-md hover:bg-gray-200 transition-colors cursor-pointer rounded"
                aria-controls="user-sidebar" aria-expanded="false">
            <span class="flex-1 text-left">Hi, <strong><?= e($_SESSION['nama']) ?></strong>!</span>
            <span class="font-bold underline underline-offset-4 text-sm text-gray-600">Profile</span>
        </button>

        <button id="host-btn" class="w-full bg-gray-300 text-gray-800 py-4 px-6 text-xl font-bold border-2 border-gray-600 shadow-md hover:bg-gray-200 transition-colors text-center cursor-pointer rounded">
            Host
        </button>

        <button id="join-btn" class="w-full bg-gray-300 text-gray-800 py-4 px-6 text-xl font-bold border-2 border-gray-600 shadow-md hover:bg-gray-200 transition-colors text-center cursor-pointer rounded">
            Join Room
        </button>

        <button id="setting-btn" class="w-full bg-gray-300 text-gray-800 py-4 px-6 text-xl font-bold border-2 border-gray-600 shadow-md hover:bg-gray-200 transition-colors text-center cursor-pointer rounded">
            Setting
        </button>

        <button id="about-btn" class="w-full bg-gray-300 text-gray-800 py-4 px-6 text-xl font-bold border-2 border-gray-600 shadow-md hover:bg-gray-200 transition-colors text-center cursor-pointer rounded">
            About
        </button>

        <div class="w-full bg-gray-400 border-2 border-gray-600 p-2 flex flex-col h-[280px] rounded shadow-inner">
            <div class="bg-gray-300 text-gray-700 text-xs px-2 py-1 font-semibold border-b border-gray-500 rounded-t">
                Chat box
            </div>
            <div id="chat-box" class="flex-1 bg-gray-300 p-2 text-gray-500 text-xs overflow-y-auto">
                <?php while ($p = mysqli_fetch_assoc($messages)): ?>
                    <p class="mb-1 flex items-center gap-2">
                        <img src="<?= e(avatar_url($p['gambar'])) ?>" width="20" height="20" class="rounded-full object-cover">
                        <strong><?= e($p['nama']) ?></strong>: <?= e($p['isi_pesan']) ?>
                    </p>
                <?php endwhile; ?>
            </div>
            <div class="mt-2 bg-gray-300 p-1 border-t border-gray-400 flex items-center gap-2 rounded-b">
                <input type="text" id="message" placeholder="Write a lobby message..." class="field flex-1 text-xs py-1 border border-gray-400 bg-gray-50 text-gray-700 outline-none px-2 rounded">
                <button onclick="sendLobby()" class="bg-gray-600 hover:bg-gray-700 text-white text-xs font-semibold px-3 py-1 rounded border border-gray-800 transition-colors">Send</button>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-8/12 flex flex-col gap-6 items-center justify-start relative z-10">
        
        <div class="w-full max-w-4xl flex justify-center">
            <img src="img/logo.png" alt="Click The Circle Logo" class="w-full h-auto border-4 border-gray-600 rounded-md shadow-xl object-cover">
        </div>

        <div id="pilih-menu-placeholder" class="w-full text-center py-20 bg-gray-300 border-2 border-dashed border-gray-500 rounded-md shadow-md text-gray-500 max-w-4xl">
            Pilih menu di sebelah kiri untuk melihat detail.
        </div>

        <div id="private-rooms-section" class="w-full max-w-4xl mt-2 hidden transition-all duration-300 ease-in-out transform scale-95 opacity-0 bg-gray-100 border-2 border-gray-600 rounded-md shadow-xl p-6 text-gray-800">
            <h3 class="text-2xl font-bold text-gray-700 border-b border-gray-400 pb-2 mb-6">Private Rooms</h3>
            
            <div id="rooms-container" class="grid grid-cols-1 md:grid-cols-2 gap-6"
                 style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
            <?php foreach ($rooms as $room): ?>
                <?php $roomName = $room['name'] ?? ('Room #' . $room['id']); ?>
                <div id="room-card-<?= $room['id'] ?>" class="bg-gray-300 border border-gray-500 p-5 rounded-md shadow-md text-gray-800 flex flex-col justify-between h-40">
                    <div>
                        <h4 class="font-bold text-lg mb-1">😴 <?= e($roomName) ?></h4>
                        <p id="owner-info-<?= $room['id'] ?>" class="text-xs text-gray-600 m-0 mb-3">
                            <?php if ($room['is_occupied'] === 1 && $room['owner_name'] !== ''): ?>
                                Current owner: <?= e($room['owner_name']) ?>
                            <?php else: ?>
                                No owner yet.
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <form method="post" action="room_enter.php" id="form-room-<?= $room['id'] ?>" class="flex flex-col gap-2 m-0 mt-auto">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                        <input type="password" name="room_pass" placeholder="Password for Room <?= e($roomName) ?>" required class="w-full px-3 py-1.5 bg-gray-50 border border-gray-400 rounded text-xs focus:outline-none">
                        <span id="room-actions-<?= $room['id'] ?>" class="w-full mt-1">
                            <?php if ($room['is_occupied'] === 0): ?>
                                <button type="submit" class="w-full bg-gray-700 text-white font-semibold py-1.5 rounded text-xs hover:bg-gray-800 transition-colors">Enter &amp; Become Owner</button>
                            <?php else: ?>
                                <button type="button" onclick="ketukPintu(<?= $room['id'] ?>)" class="w-full bg-orange-500 text-white font-semibold py-1.5 rounded text-xs hover:bg-orange-600 transition-colors">Request to Join</button>
                            <?php endif; ?>
                        </span>
                    </form>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div id="join-room-section" class="w-full max-w-4xl mt-2 hidden transition-all duration-300 ease-in-out transform scale-95 opacity-0 bg-gray-100 border-2 border-gray-600 rounded-md shadow-xl p-6 text-gray-800">
            <h3 class="text-2xl font-bold border-b pb-2 mb-6 border-gray-400 text-gray-700">Join Room Selection</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="border-2 border-gray-400 p-4 rounded bg-gray-200 flex flex-col justify-between h-28 shadow-sm">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">Room Selection <?= $i ?></p>
                            <span class="text-xs text-gray-500">Kapasitas: 0/4</span>
                        </div>
                        <button class="w-full bg-gray-700 hover:bg-gray-800 text-white font-semibold py-1 mt-3 rounded text-xs transition-colors">Join Room <?= $i ?></button>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div id="setting-section" class="w-full max-w-4xl mt-2 hidden transition-all duration-300 ease-in-out transform scale-95 opacity-0 bg-gray-100 border-2 border-gray-600 rounded-md shadow-xl p-6 text-gray-800">
            <h3 class="text-2xl font-bold border-b pb-2 mb-4 border-gray-400 text-gray-700">Settings</h3>
            
            <div class="flex flex-col gap-4">
                <h4 class="font-extrabold text-sm border-b pb-1 border-gray-400 text-gray-700">Edit Profile</h4>
                <form method="post" action="profile_update.php" enctype="multipart/form-data" class="flex flex-col gap-3" id="profile-form">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <label class="font-semibold text-sm flex flex-col gap-1">New name:
                        <input type="text" name="new_nama" value="<?= e($_SESSION['nama']) ?>" required class="w-full px-3 py-2 bg-gray-100 border border-gray-500 rounded text-sm focus:outline-none">
                    </label>
                    <label class="font-semibold text-sm flex flex-col gap-1">New NPM:
                        <input type="password" name="new_npm" value="<?= e($_SESSION['npm'] ?? '') ?>" required class="w-full px-3 py-2 bg-gray-100 border border-gray-500 rounded text-sm focus:outline-none">
                    </label>

                    <fieldset class="border-2 border-gray-400 rounded p-3 bg-gray-200">
                        <legend class="font-extrabold px-1 text-xs">Profile Avatar</legend>

                        <div class="flex flex-col gap-3 text-xs mt-2">
                            <label class="font-semibold flex items-center gap-2 cursor-pointer bg-gray-100 p-2 rounded">
                                <input type="radio" name="avatar_mode" value="keep" checked>
                                <span>Use current avatar</span>
                                <img src="<?= e($myAvatarUrl) ?>" width="32" height="32" class="rounded-full border border-gray-500 object-cover ml-auto">
                            </label>

                            <label class="font-semibold flex items-start gap-2 cursor-pointer border-t pt-2 border-gray-300">
                                <input type="radio" name="avatar_mode" value="preset" class="mt-1">
                                <span class="flex-1">
                                    <div class="mb-1 font-bold text-gray-700">Choose from presets</div>
                                    <div class="grid grid-cols-4 gap-2">
                                        <?php foreach ($presetItems as $p): ?>
                                            <label class="cursor-pointer relative flex justify-center">
                                                <input type="radio" name="preset_avatar" value="<?= e($p['file']) ?>"
                                                       <?= ($p['file'] === $myPresetFile) ? 'checked' : '' ?>
                                                       class="peer sr-only">
                                                <img src="assets/avatars/<?= e($p['file']) ?>"
                                                     alt="<?= e($p['name']) ?>"
                                                     title="<?= e($p['name']) ?>"
                                                     class="w-10 h-10 rounded-full border-2 border-gray-400 object-cover peer-checked:border-red-400 peer-checked:ring-2 peer-checked:ring-red-400 transition-all">
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </span>
                            </label>

                         <label class="font-bold inline-flex flex-wrap items-center gap-3 p-3 border-t-2 border-ink bg-white/10 w-full cursor-pointer hover:bg-white/20 transition-colors">
                            <input type="radio" name="avatar_mode" value="upload" class="w-5 h-5 cursor-pointer accent-ink">
                            
                            <span class="text-sm uppercase tracking-tighter">Upload your own photo (max 2MB)</span>
                            
                            <input type="file" name="new_gambar" accept="image/*" 
                                class="field flex-1 min-w-[200px] text-xs cursor-pointer file:mr-4 file:py-1 file:px-3 file:border-2 file:border-ink  file:font-black file:uppercase file:text-[10px] hover:file:bg-accent-yel/80">
                        </label>
                        </div>
                    </fieldset>

                    <button type="submit" class="w-full bg-gray-600 text-white py-2 rounded font-semibold text-sm hover:bg-gray-700 transition-colors">Save Changes</button>
                </form>

                <hr class="my-1 border-t border-gray-400">
                <h4 class="font-extrabold text-sm text-gray-700">Delete Account</h4>
                <form method="post" action="profile_delete.php"
                      onsubmit="return confirm('Delete your account? This action cannot be undone.');"
                      class="flex flex-col gap-2 text-sm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <label class="font-semibold flex flex-col gap-1">Type DELETE to confirm: 
                        <input type="text" name="delete_confirm" required class="px-3 py-1 bg-gray-100 border border-gray-500 rounded text-sm">
                    </label>
                    <button type="submit" class="w-full bg-red-600 text-white py-1 rounded font-semibold hover:bg-red-700 transition-colors">Delete My Account</button>
                </form>

                <hr class="my-1 border-t border-gray-400">
                <h4 class="font-extrabold text-sm text-gray-700">Logout</h4>
                <a href="logout.php" class="w-full text-center bg-red-500 text-white py-1 rounded font-semibold text-sm hover:bg-red-600 transition-colors">↪ Logout</a>
            </div>
        </div>

        <div id="about-section" class="w-full max-w-4xl mt-2 hidden transition-all duration-300 ease-in-out transform scale-95 opacity-0 bg-gray-300 border-2 border-gray-600 rounded-md shadow-xl p-6 text-gray-800">
            <h3 class="text-2xl font-bold border-b pb-2 mb-4 border-gray-500 text-gray-700">About</h3>
            <p class="text-xs text-gray-600 leading-relaxed">
                ClickTheCircle adalah game adu ketangkasan berbasis web yang dirancang untuk menguji kecepatan reaksi dan akurasi.
                Di game ini, pemain bisa duel secara real-time bareng pemain lain dalam satu ruangan (private room). Intinya simpel: Siapa yang paling cepat dapat target dan poin paling tinggi, dia yang menang.
                Dikembangkan dengan gaya visual Neubrutalism, game ini fokus pada gameplay yang kompetitif, kustomisasi penuh, dan performa yang responsif.

            </p>
        </div>
        
    </div>

</main>

<div class="fixed bottom-0 right-0 w-80 h-80 z-0 pointer-events-none hidden md:block animate-peep overflow-hidden">
    <img src="img/ssrb.png" alt="SSRB Mascot" class="w-full h-full object-contain origin-bottom">
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

document.addEventListener("DOMContentLoaded", function() {
    // === LOGIKA BGM ===
    const bgm = document.getElementById("lobby-bgm");
    bgm.volume = 0.3; // Atur volume (0.0 ke 1.0)

    const startAudio = () => {
        bgm.play().catch(err => console.log("Autoplay dicegah browser"));
        document.removeEventListener("click", startAudio);
    };
    // Menunggu klik pertama user untuk memulai audio
    document.addEventListener("click", startAudio);

    // === LOGIKA POP-UP MENU ===
    const hostBtn = document.getElementById("host-btn");
    const roomsSection = document.getElementById("private-rooms-section");

    const joinBtn = document.getElementById("join-btn");
    const joinSection = document.getElementById("join-room-section");
    
    const settingBtn = document.getElementById("setting-btn");
    const settingSection = document.getElementById("setting-section");

    const aboutBtn = document.getElementById("about-btn");
    const aboutSection = document.getElementById("about-section");
    
    const placeholder = document.getElementById("pilih-menu-placeholder");

    const allSections = [roomsSection, joinSection, settingSection, aboutSection];

    function hideAllSectionsExcept(activeSection) {
        allSections.forEach(section => {
            if (section !== activeSection && !section.classList.contains("hidden")) {
                section.classList.add("opacity-0", "scale-95");
                setTimeout(() => {
                    section.classList.add("hidden");
                }, 300);
            }
        });
    }

    function toggleSection(section) {
        hideAllSectionsExcept(section);
        
        if (section.classList.contains("hidden")) {
            placeholder.classList.add("hidden");
            section.classList.remove("hidden");
            setTimeout(() => {
                section.classList.add("opacity-100", "scale-100");
                section.classList.remove("opacity-0", "scale-95");
            }, 10);
        } else {
            section.classList.add("opacity-0", "scale-95");
            section.classList.remove("opacity-100", "scale-100");
            setTimeout(() => {
                section.classList.add("hidden");
                placeholder.classList.remove("hidden");
            }, 300);
        }
    }

    if (hostBtn) hostBtn.addEventListener("click", () => toggleSection(roomsSection));
    if (joinBtn) joinBtn.addEventListener("click", () => toggleSection(joinSection));
    if (settingBtn) settingBtn.addEventListener("click", () => toggleSection(settingSection));
    if (aboutBtn) aboutBtn.addEventListener("click", () => toggleSection(aboutSection));
});

// UX Avatar radio handling
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