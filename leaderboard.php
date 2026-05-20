<?php
/**
 * Leaderboard & Global Stats
 */
require __DIR__ . '/helpers/session.php';
require_login();
require __DIR__ . '/helpers/db.php';

$title = 'Leaderboard & Global Stats — CL!CK THE CIRCLE';
include __DIR__ . '/views/header.php';

$validDiffs = ['easy', 'normal', 'hard', 'indonesian'];
$currentDiff = isset($_GET['diff']) && in_array($_GET['diff'], $validDiffs) ? $_GET['diff'] : 'normal';

$validDurations = [30, 60, 90];
$currentDuration = isset($_GET['dur']) && in_array((int)$_GET['dur'], $validDurations) ? (int)$_GET['dur'] : 30;

// Get Top 20 by Score (Group by player_label to show highest score per player)
$stmtTopScores = mysqli_prepare($db, "
    SELECT 
        gs.player_label, 
        gs.score as best_score, 
        gs.created_at as achieved_at
    FROM game_scores gs
    JOIN (
        SELECT MAX(a.id) AS best_id
        FROM game_scores a
        JOIN game_sessions s ON a.session_id = s.id
        JOIN (
            SELECT gs2.player_label, MAX(gs2.score) as max_score
            FROM game_scores gs2
            JOIN game_sessions s2 ON gs2.session_id = s2.id
            WHERE gs2.score > 0 AND s2.difficulty = ? AND s2.duration_sec = ?
            GROUP BY gs2.player_label
        ) b ON a.player_label = b.player_label AND a.score = b.max_score
        WHERE a.score > 0 AND s.difficulty = ? AND s.duration_sec = ?
        GROUP BY a.player_label
    ) c ON gs.id = c.best_id
    ORDER BY best_score DESC 
    LIMIT 20
");
mysqli_stmt_bind_param($stmtTopScores, 'sisi', $currentDiff, $currentDuration, $currentDiff, $currentDuration);
mysqli_stmt_execute($stmtTopScores);
$topScores = [];
$res1 = mysqli_stmt_get_result($stmtTopScores);
while ($r = mysqli_fetch_assoc($res1)) {
    $topScores[] = $r;
}

// Get Top 20 Fastest Reaction Time (Minimum 10 hits to qualify)
$stmtFastest = mysqli_prepare($db, "
    SELECT 
        gs.player_label, 
        gs.avg_reaction_ms as best_reaction, 
        gs.consistency_ms, 
        gs.change_after5_ms, 
        gs.created_at as achieved_at
    FROM game_scores gs
    JOIN (
        SELECT MAX(a.id) AS best_id
        FROM game_scores a
        JOIN game_sessions s ON a.session_id = s.id
        JOIN (
            SELECT gs2.player_label, MIN(gs2.avg_reaction_ms) as min_reaction
            FROM game_scores gs2
            JOIN game_sessions s2 ON gs2.session_id = s2.id
            WHERE gs2.hits_count >= 10 AND gs2.avg_reaction_ms > 0 AND s2.difficulty = ? AND s2.duration_sec = ?
            GROUP BY gs2.player_label
        ) b ON a.player_label = b.player_label AND a.avg_reaction_ms = b.min_reaction
        WHERE a.hits_count >= 10 AND a.avg_reaction_ms > 0 AND s.difficulty = ? AND s.duration_sec = ?
        GROUP BY a.player_label
    ) c ON gs.id = c.best_id
    ORDER BY best_reaction ASC 
    LIMIT 20
");
mysqli_stmt_bind_param($stmtFastest, 'sisi', $currentDiff, $currentDuration, $currentDiff, $currentDuration);
mysqli_stmt_execute($stmtFastest);
$topFastest = [];
$res2 = mysqli_stmt_get_result($stmtFastest);
while ($r = mysqli_fetch_assoc($res2)) {
    $topFastest[] = $r;
}

include __DIR__ . '/views/leaderboard.view.php';
