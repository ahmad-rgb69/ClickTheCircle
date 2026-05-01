<?php
/** Controller: masuk room (cek password, set owner kalau kosong). */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_login();
csrf_check($_POST['csrf_token'] ?? '');

require __DIR__ . '/helpers/db.php';
require __DIR__ . '/helpers/room_playing.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/Room.php';
require __DIR__ . '/models/Seat.php';

$roomId = (int)($_POST['room_id'] ?? 0);
$pass   = (string)($_POST['room_pass'] ?? '');

if (!in_array($roomId, Room::VALID_IDS, true)) {
    flash_set('err', 'Invalid Room ID.');
    header('Location: lobby.php'); exit;
}

// Pakai nama room (Dark, Lux, dst.) untuk pesan ke user, bukan angka.
$roomName = Room::nameFor($roomId);

$room = Room::find($db, $roomId);
if (!$room || $pass !== (string)$room['password_room']) {
    flash_set('err', "Wrong password for Room {$roomName}.");
    header('Location: lobby.php'); exit;
}

$myId = (int)$_SESSION['id'];

// Tolak join saat owner sedang bermain (kecuali user adalah owner room itu sendiri).
if ((int)$room['is_occupied'] === 1
    && (int)($room['owner_id'] ?? 0) !== $myId
    && room_is_playing($roomId)
) {
    flash_set('err', "Owner of Room {$roomName} is currently playing. Try again after the game ends.");
    header('Location: lobby.php'); exit;
}

if ((int)$room['is_occupied'] === 0) {
    // FIX Bug #1: occupy() bisa gagal kalau ada user lain yang barusan duluan
    // (race condition). Verifikasi dengan membaca ulang dan cek owner_id.
    Room::occupy($db, $roomId, $myId);
    $verify = Room::find($db, $roomId);
    if ($verify && (int)($verify['owner_id'] ?? 0) === $myId) {
        $_SESSION['role_room'] = 'owner';
        // Owner otomatis dapat seat #1; reset semua seat lama.
        Seat::clearAll($db, $roomId);
        Seat::take($db, $roomId, 1, $myId);
    } else {
        // Kalah race -> jadi guest biasa.
        $_SESSION['role_room'] = 'guest';
        Seat::takeFirstAvailable($db, $roomId, $myId);
    }
} else {
    $_SESSION['role_room'] = 'guest';
    // Guest otomatis didudukkan ke seat kosong pertama (atau menggantikan bot).
    // Jika semua 4 seat sudah diisi user, guest tetap masuk room sebagai spectator.
    Seat::takeFirstAvailable($db, $roomId, $myId);
}
$_SESSION['room_id'] = $roomId;

User::updatePresence($db, $myId, 'room', $roomId);
header('Location: room.php?id=' . $roomId);
exit;
