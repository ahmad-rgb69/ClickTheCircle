<?php
/**
 * Controller: Lobby (halaman utama setelah login).
 */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_login();

require __DIR__ . '/helpers/db.php';        // $db
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/Room.php';
require __DIR__ . '/models/Message.php';

$myId = (int)$_SESSION['id'];

// Kalau dia owner di room dan masuk lobby → lepas dulu.
if (($_SESSION['role_room'] ?? '') === 'owner' && !empty($_SESSION['room_id'])) {
    Room::releaseIfOwner($db, (int)$_SESSION['room_id'], $myId);
    unset($_SESSION['role_room'], $_SESSION['room_id']);
}

User::updatePresence($db, $myId, 'lobby', null);

$rooms    = Room::listAll($db);
$messages = Message::lobbyAll($db);

$initialCooldowns = [];
foreach ($rooms as $r) {
    if ($r['is_occupied'] === 1 && $r['is_cooldown']) {
        $initialCooldowns[] = ['roomId' => $r['id'], 'sisaDetik' => $r['sisa_detik']];
    }
}

include __DIR__ . '/views/lobby.view.php';
