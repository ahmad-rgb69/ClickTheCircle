<?php
/**
 * Endpoint: aksi seat dalam room.
 * GET ?room_id=N           => list seat saat ini
 * POST action=take seat=N  => duduki seat
 * POST action=leave        => keluar dari seat
 * POST action=fill_bots    => (owner) isi sisa seat dengan bot
 * POST action=clear_bots   => (owner) hapus semua bot
 */
require __DIR__ . '/helpers/session.php';
require_login();
require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/Room.php';
require __DIR__ . '/models/Seat.php';

header('Content-Type: application/json; charset=utf-8');

$roomId = (int)($_REQUEST['room_id'] ?? 0);
if (!in_array($roomId, Room::VALID_IDS, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'invalid room']); exit;
}

$myId  = (int)$_SESSION['id'];
$room  = Room::find($db, $roomId);
if (!$room) { http_response_code(404); echo json_encode(['ok'=>false,'err'=>'no room']); exit; }
$isOwner = ((int)($room['owner_id'] ?? 0) === $myId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    switch ($action) {
        case 'take':
            $seat = (int)($_POST['seat'] ?? 0);
            $ok = Seat::take($db, $roomId, $seat, $myId);
            echo json_encode(['ok' => $ok, 'seats' => Seat::listForRoom($db, $roomId)]); exit;

        case 'leave':
            Seat::leaveAll($db, $roomId, $myId);
            echo json_encode(['ok' => true, 'seats' => Seat::listForRoom($db, $roomId)]); exit;

        case 'fill_bots':
            if (!$isOwner) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'only owner']); exit; }
            Seat::fillBots($db, $roomId);
            echo json_encode(['ok' => true, 'seats' => Seat::listForRoom($db, $roomId)]); exit;

        case 'clear_bots':
            if (!$isOwner) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'only owner']); exit; }
            Seat::clearBots($db, $roomId);
            echo json_encode(['ok' => true, 'seats' => Seat::listForRoom($db, $roomId)]); exit;

        case 'kick':
            if (!$isOwner) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'only owner']); exit; }
            $target = (int)($_POST['user_id'] ?? 0);
            if ($target <= 0 || $target === $myId) {
                http_response_code(400);
                echo json_encode(['ok'=>false,'err'=>'invalid target']); exit;
            }
            Seat::leaveAll($db, $roomId, $target);
            echo json_encode(['ok' => true, 'kicked_user_id' => $target, 'seats' => Seat::listForRoom($db, $roomId)]); exit;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'unknown action']); exit;
    }
}

// GET
echo json_encode([
    'ok'       => true,
    'is_owner' => $isOwner,
    'my_id'    => $myId,
    'seats'    => Seat::listForRoom($db, $roomId),
]);
