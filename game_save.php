<?php
/**
 * Endpoint AJAX: simpan hasil 1 sesi gameplay.
 * Body JSON: {
 *   room_id, difficulty, num_players, duration_sec,
 *   scores: [{slot,label,score}],
 *   reactions: [{slot,label,ms,idx}]   // optional, urut waktu hit per pemain
 * }
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
    echo json_encode(['ok' => false, 'err' => 'invalid json']);
    exit;
}

$roomId = (int) ($body['room_id'] ?? 0);
$diff = (string) ($body['difficulty'] ?? 'normal');
$np = max(1, min(4, (int) ($body['num_players'] ?? 1)));
$dur = max(1, min(600, (int) ($body['duration_sec'] ?? 90)));
$scores = $body['scores'] ?? [];
$reactions = $body['reactions'] ?? [];

if (!in_array($roomId, Room::VALID_IDS, true) || !in_array($diff, ['easy', 'normal', 'hard', 'indonesian'], true) || !is_array($scores) || !is_array($reactions)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'invalid params']);
    exit;
}

$myId = (int) $_SESSION['id'];
$room = Room::find($db, $roomId);
// FIX: izinkan simpan kalau (a) user adalah owner room, ATAU
// (b) room belum punya owner (NULL) — sesi hot-seat tetap bisa disimpan
//     dengan owner_id = user yang sedang login.
$roomOwner = $room ? (int) ($room['owner_id'] ?? 0) : -1;
if (!$room || ($roomOwner !== 0 && $roomOwner !== $myId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'only owner can save']);
    exit;
}

// ----- Helper: hitung statistik per slot dari list reactions -----
function compute_stats_per_slot(array $reactions): array
{
    // group by slot, urutkan by idx
    $bySlot = [];
    foreach ($reactions as $r) {
        $slot = (int) ($r['slot'] ?? 0);
        $ms = (int) ($r['ms'] ?? 0);
        $idx = (int) ($r['idx'] ?? 0);
        if ($slot <= 0 || $ms <= 0)
            continue;
        $bySlot[$slot][] = ['ms' => $ms, 'idx' => $idx];
    }
    $out = [];
    foreach ($bySlot as $slot => $arr) {
        usort($arr, fn($a, $b) => $a['idx'] <=> $b['idx']);
        $msList = array_column($arr, 'ms');
        $n = count($msList);
        if ($n === 0) {
            $out[$slot] = ['avg' => 0, 'cons' => 0, 'change' => 0, 'count' => 0];
            continue;
        }
        $avg = (int) round(array_sum($msList) / $n);
        $cons = max($msList) - min($msList);

        // Perubahan setelah 5 ronde: avg 5 pertama vs avg 5 terakhir.
        // change > 0 = melambat, change < 0 = makin cepat.
        $change = 0;
        if ($n >= 6) {
            $first5 = array_slice($msList, 0, 5);
            $last5 = array_slice($msList, -5);
            $change = (int) round((array_sum($last5) / count($last5)) - (array_sum($first5) / count($first5)));
        }
        $out[$slot] = ['avg' => $avg, 'cons' => $cons, 'change' => $change, 'count' => $n];
    }
    return $out;
}

$stats = compute_stats_per_slot($reactions);

mysqli_begin_transaction($db);
try {
    $stmt = mysqli_prepare($db, "INSERT INTO game_sessions (room_id, owner_id, difficulty, num_players, duration_sec, ended_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt)
        throw new RuntimeException('game_sessions table missing — run sql/migration_game.sql');
    mysqli_stmt_bind_param($stmt, 'iisii', $roomId, $myId, $diff, $np, $dur);
    mysqli_stmt_execute($stmt);
    $sessionId = mysqli_insert_id($db);

    // tentukan winner
    // FIX: kalau ada tie (skor max sama), hanya pemain dengan slot terkecil
    // yang ditandai winner — supaya tidak ada "juara dobel" di UI.
    $maxScore = 0;
    foreach ($scores as $s) {
        if ((int) ($s['score'] ?? 0) > $maxScore)
            $maxScore = (int) $s['score'];
    }
    $winnerSlot = -1;
    if ($maxScore > 0) {
        foreach ($scores as $s) {
            if ((int) ($s['score'] ?? 0) === $maxScore) {
                $sl = (int) ($s['slot'] ?? 0);
                if ($winnerSlot < 0 || $sl < $winnerSlot)
                    $winnerSlot = $sl;
            }
        }
    }

    $ins = mysqli_prepare($db, "INSERT INTO game_scores (session_id, slot, player_label, score, is_winner, avg_reaction_ms, consistency_ms, change_after5_ms, hits_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$ins)
        throw new RuntimeException('game_scores table missing — run sql/migration_game.sql + migration_stats.sql');
    foreach ($scores as $s) {
        $slot = (int) ($s['slot'] ?? 0);
        $label = substr((string) ($s['label'] ?? ''), 0, 32);
        $sc = (int) ($s['score'] ?? 0);
        $win = ($winnerSlot > 0 && $slot === $winnerSlot) ? 1 : 0;
        $st = $stats[$slot] ?? ['avg' => 0, 'cons' => 0, 'change' => 0, 'count' => 0];
        mysqli_stmt_bind_param(
            $ins,
            'iisiiiiii',
            $sessionId,
            $slot,
            $label,
            $sc,
            $win,
            $st['avg'],
            $st['cons'],
            $st['change'],
            $st['count']
        );
        mysqli_stmt_execute($ins);
    }

    // Simpan detail reaksi (kalau ada). Nama tabel game_reactions dari migration_stats.sql.
    if (!empty($reactions)) {
        $rIns = mysqli_prepare($db, "INSERT INTO game_reactions (session_id, slot, player_label, hit_index, reaction_ms) VALUES (?, ?, ?, ?, ?)");
        if ($rIns) {
            foreach ($reactions as $r) {
                $slot = (int) ($r['slot'] ?? 0);
                $label = substr((string) ($r['label'] ?? ''), 0, 32);
                $idx = (int) ($r['idx'] ?? 0);
                $ms = (int) ($r['ms'] ?? 0);
                if ($slot <= 0 || $ms <= 0)
                    continue;
                mysqli_stmt_bind_param($rIns, 'iisii', $sessionId, $slot, $label, $idx, $ms);
                mysqli_stmt_execute($rIns);
            }
        }
        // Kalau tabel belum ada, summary tetap tersimpan di game_scores; jangan gagalkan transaksi.
    }

    mysqli_commit($db);
    echo json_encode(['ok' => true, 'session_id' => $sessionId, 'stats' => $stats]);
} catch (Throwable $e) {
    mysqli_rollback($db);
    http_response_code(500);
    echo json_encode(['ok' => false, 'err' => $e->getMessage() ?: 'save failed']);
}
