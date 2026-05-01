<?php
/**
 * Endpoint AJAX: simpan hasil 1 sesi gameplay.
 * Body JSON: { room_id, difficulty, num_players, duration_sec, scores: [{slot,label,score}] }
 * Hanya owner yang boleh menyimpan (sesi hot-seat berlangsung di device owner).
 */
require __DIR__ . '/helpers/session.php';
require_login();
require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/Room.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'invalid json']); exit;
}

$roomId = (int)($body['room_id'] ?? 0);
$diff   = (string)($body['difficulty'] ?? 'normal');
$np     = max(1, min(4, (int)($body['num_players'] ?? 1)));
$dur    = max(1, min(600, (int)($body['duration_sec'] ?? 90)));
$scores = $body['scores'] ?? [];

if (!in_array($roomId, Room::VALID_IDS, true) || !in_array($diff, ['normal','hard'], true) || !is_array($scores)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'invalid params']); exit;
}

$myId = (int)$_SESSION['id'];
$room = Room::find($db, $roomId);
if (!$room || (int)($room['owner_id'] ?? 0) !== $myId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'only owner can save']); exit;
}

mysqli_begin_transaction($db);
try {
    // FIX Bug #2: cek hasil prepare biar tidak crash kalau tabel game_sessions
    // belum dibuat (migrasi sql/migration_game.sql belum dijalankan).
    $stmt = mysqli_prepare($db, "INSERT INTO game_sessions (room_id, owner_id, difficulty, num_players, duration_sec, ended_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) throw new RuntimeException('game_sessions table missing — run sql/migration_game.sql');
    mysqli_stmt_bind_param($stmt, 'iisii', $roomId, $myId, $diff, $np, $dur);
    mysqli_stmt_execute($stmt);
    $sessionId = mysqli_insert_id($db);

    // tentukan winner
    $maxScore = 0;
    foreach ($scores as $s) { if ((int)($s['score'] ?? 0) > $maxScore) $maxScore = (int)$s['score']; }

    $ins = mysqli_prepare($db, "INSERT INTO game_scores (session_id, slot, player_label, score, is_winner) VALUES (?, ?, ?, ?, ?)");
    if (!$ins) throw new RuntimeException('game_scores table missing — run sql/migration_game.sql');
    foreach ($scores as $s) {
        $slot  = (int)($s['slot'] ?? 0);
        $label = substr((string)($s['label'] ?? ''), 0, 32);
        $sc    = (int)($s['score'] ?? 0);
        $win   = ($maxScore > 0 && $sc === $maxScore) ? 1 : 0;
        mysqli_stmt_bind_param($ins, 'iisii', $sessionId, $slot, $label, $sc, $win);
        mysqli_stmt_execute($ins);
    }

    mysqli_commit($db);
    echo json_encode(['ok' => true, 'session_id' => $sessionId]);
} catch (Throwable $e) {
    mysqli_rollback($db);
    http_response_code(500);
    echo json_encode(['ok' => false, 'err' => $e->getMessage() ?: 'save failed']);
}
