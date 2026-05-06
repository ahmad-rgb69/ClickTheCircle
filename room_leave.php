<?php
/** Controller: keluar dari room ke lobby. */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_login();

require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/Room.php';
require __DIR__ . '/models/Seat.php';

// FIX Bug #3: hanya terima POST + CSRF, supaya tidak bisa di-trigger lewat
// <img src="room_leave.php?room_id=1"> dari halaman lain (CSRF).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lobby.php'); exit;
}
csrf_check($_POST['csrf_token'] ?? '');

$roomId = (int)($_POST['room_id'] ?? 0);
$myId   = (int)($_SESSION['id'] ?? 0);
$role   = (string)($_SESSION['role_room'] ?? '');

if ($role === 'owner' && $roomId > 0) {
    Room::releaseIfOwner($db, $roomId, $myId);
    Seat::clearAll($db, $roomId);
} elseif ($roomId > 0 && $myId > 0) {
    Seat::leaveAll($db, $roomId, $myId);
}
User::updatePresence($db, $myId, 'lobby', null);
unset($_SESSION['role_room'], $_SESSION['room_id']);

header('Location: lobby.php');
exit;
