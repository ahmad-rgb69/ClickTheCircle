<?php
/** Controller: laporan owner kosong → reset paksa room. */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_login();
csrf_check($_POST['csrf_token'] ?? '');

require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/Room.php';

$roomId = (int)($_POST['room_id'] ?? 0);
if (in_array($roomId, Room::VALID_IDS, true)) {
    Room::forceVacant($db, $roomId);
    // FIX Bug #5: pakai nama room (Dark, Lux, ...) bukan angka, biar konsisten.
    $roomName = Room::nameFor($roomId);
    flash_set('ok', "Room {$roomName} direset karena owner tidak aktif.");
    unset($_SESSION['role_room'], $_SESSION['room_id']);
}
header('Location: lobby.php');
exit;
