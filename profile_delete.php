<?php
/** Controller: hapus akun. */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_login();
csrf_check($_POST['csrf_token'] ?? '');

require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/Room.php';

$confirm = strtoupper(trim($_POST['delete_confirm'] ?? ''));
if ($confirm !== 'HAPUS') {
    flash_set('err', 'Ketik HAPUS untuk konfirmasi hapus akun.');
    header('Location: lobby.php'); exit;
}

$myId   = (int)$_SESSION['id'];
$roomId = (int)($_SESSION['room_id'] ?? 0);

if (($_SESSION['role_room'] ?? '') === 'owner' && $roomId > 0) {
    Room::releaseIfOwner($db, $roomId, $myId);
}

User::delete($db, $myId);
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
