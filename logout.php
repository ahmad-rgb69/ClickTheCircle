<?php
/**
 * Controller: Logout. Lepas owner kalau perlu, hapus session, redirect login.
 */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/Room.php';

$myId = (int)($_SESSION['id'] ?? 0);

if (($_SESSION['role_room'] ?? '') === 'owner') {
    $rid = (int)($_SESSION['room_id'] ?? 0);
    if ($rid > 0) Room::releaseIfOwner($db, $rid, $myId);
}

User::updatePresence($db, $myId, 'offline', null);
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
