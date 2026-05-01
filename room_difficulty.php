<?php
/**
 * Endpoint AJAX: get/set difficulty room.
 * - GET  : kembalikan difficulty + apakah caller adalah owner.
 * - POST : hanya owner yang boleh ubah (normal|hard).
 */
require __DIR__ . '/helpers/session.php';
require_login();
require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/Room.php';

header('Content-Type: application/json; charset=utf-8');

$roomId = (int)($_REQUEST['room_id'] ?? 0);
if (!in_array($roomId, Room::VALID_IDS, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'invalid room']); exit;
}

$myId = (int)$_SESSION['id'];
$room = Room::find($db, $roomId);
if (!$room) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'err' => 'room not found']); exit;
}

$isOwner = ((int)($room['owner_id'] ?? 0) === $myId);
$diff = $room['difficulty'] ?? 'normal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isOwner) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'only owner']); exit;
    }
    $new = (string)($_POST['difficulty'] ?? '');
    if (!in_array($new, ['normal','hard'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'invalid difficulty']); exit;
    }
    // FIX Bug #2: cek hasil prepare. Kalau kolom belum ada (migrasi belum dijalankan),
    // jangan crash dengan fatal "bind_param() on bool".
    $stmt = mysqli_prepare($db, "UPDATE room_status SET difficulty = ?, difficulty_updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'err' => 'difficulty column missing — run sql/migration_game.sql',
        ]); exit;
    }
    mysqli_stmt_bind_param($stmt, 'si', $new, $roomId);
    mysqli_stmt_execute($stmt);
    $diff = $new;
}

echo json_encode([
    'ok' => true,
    'difficulty' => $diff,
    'is_owner' => $isOwner,
]);
