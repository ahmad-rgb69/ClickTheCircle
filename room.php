<?php
/**
 * Controller: tampilkan halaman private room.
 *  - URL: room.php?id=1
 */
require __DIR__ . '/helpers/session.php';
require_login();

require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/Room.php';
require __DIR__ . '/models/Message.php';

$roomId = (int)($_GET['id'] ?? 0);

if (!in_array($roomId, Room::VALID_IDS, true)) {
    flash_set('err', 'Room tidak valid.');
    header('Location: lobby.php'); exit;
}

if ((int)($_SESSION['room_id'] ?? 0) !== $roomId || empty($_SESSION['role_room'])) {
    flash_set('err', 'Akses room tidak sah. Masuk lewat password dulu.');
    header('Location: lobby.php'); exit;
}

$myId = (int)$_SESSION['id'];
$role = (string)$_SESSION['role_room'];

// Kalau owner tapi data DB ternyata sudah dilepas → balik ke lobby.
if ($role === 'owner') {
    $room = Room::find($db, $roomId);
    $stillOwner = $room && (int)$room['is_occupied'] === 1 && (int)($room['owner_id'] ?? 0) === $myId;
    if (!$stillOwner) {
        unset($_SESSION['role_room'], $_SESSION['room_id']);
        header('Location: lobby.php'); exit;
    }
}

User::updatePresence($db, $myId, 'room', $roomId);
$messages = Message::roomAll($db, $roomId);

include __DIR__ . '/views/room.view.php';
