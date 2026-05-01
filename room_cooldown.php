<?php
/** Controller: mulai cooldown lapor owner kosong (owner masih aktif). */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_login();
csrf_check($_POST['csrf_token'] ?? '');

require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/Room.php';

$roomId = (int)($_POST['room_id'] ?? 0);
if (in_array($roomId, Room::VALID_IDS, true)) {
    Room::startCooldown($db, $roomId);
}
header('Location: lobby.php');
exit;
