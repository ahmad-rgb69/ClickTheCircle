<?php
/**
 * statistik.php
 *
 * Dua mode:
 *  - statistik.php             -> daftar riwayat sesi yang dimiliki user (owner) yang sedang login.
 *  - statistik.php?session=ID  -> detail statistik 1 sesi (chart + tabel + per-hit).
 *
 * Statistik wajib (per pemain / slot):
 *   1. Rata-rata reaction time (ms)
 *   2. Konsistensi  = max(reaction) - min(reaction)
 *   3. Perubahan setelah 5 ronde = avg(5 hit terakhir) - avg(5 hit pertama).
 *      Negatif = makin cepat, positif = melambat.
 *
 * Sumber data:
 *   - game_sessions, game_scores  (dibuat oleh sql/migration_game.sql)
 *   - game_reactions              (dibuat oleh sql/migration_stats.sql)
 *   - Ringkasan juga disimpan di game_scores.{avg,consistency,change,hits_count} untuk list cepat.
 */
require __DIR__ . '/helpers/session.php';
require_login();
require __DIR__ . '/helpers/db.php';
require __DIR__ . '/models/Room.php';

$myId = (int) $_SESSION['id'];
$sessionId = isset($_GET['session']) ? (int) $_GET['session'] : 0;

$title = 'Statistik — CL!CK THE CIRCLE';
include __DIR__ . '/views/header.php';

/**
 * Format ms human friendly.
 */
function fmt_ms($v): string
{
    return number_format((float) $v, 0, ',', '.') . ' ms';
}
function fmt_change($v): string
{
    $v = (int) $v;
    if ($v === 0)
        return '0 ms';
    return ($v > 0 ? '+' : '') . $v . ' ms';
}
?>

<div class="w-full max-w-[1100px] mx-auto">
    <div class="h-section !max-w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 !p-4">
        <h2 class="m-0 flex-1 font-extrabold text-xl sm:text-2xl tracking-wide">📊 Statistik Permainan</h2>
        <div class="flex items-center gap-3 shrink-0">
            <?php if ($sessionId > 0): ?>
                <a href="statistik.php" class="btn whitespace-nowrap !bg-white !text-black border-2 border-black hover:!bg-gray-100">← Kembali</a>
            <?php endif; ?>
            <a href="lobby.php" class="btn whitespace-nowrap">Lobby</a>
        </div>
    </div>

    <?php if ($sessionId > 0):
        // ---- Detail satu sesi ----
        $stmt = mysqli_prepare($db, "SELECT gs.*, u.nama AS owner_name FROM game_sessions gs LEFT JOIN users u ON u.id = gs.owner_id WHERE gs.id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $sessionId);
        mysqli_stmt_execute($stmt);
        $session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$session):
            ?>
            <div class="panel-box p-6 text-center">
                <p class="font-bold mb-2">Sesi tidak ditemukan.</p>
                <a href="statistik.php" class="underline">Lihat semua riwayat</a>
            </div>
        <?php else:
            // Skor + ringkasan
            $stmt = mysqli_prepare($db, "SELECT slot, player_label, score, is_winner, avg_reaction_ms, consistency_ms, change_after5_ms, hits_count FROM game_scores WHERE session_id = ? ORDER BY score DESC, slot ASC");
            mysqli_stmt_bind_param($stmt, 'i', $sessionId);
            mysqli_stmt_execute($stmt);
            $scores = [];
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res))
                $scores[] = $r;

            // Reactions detail
            $stmt = mysqli_prepare($db, "SELECT slot, player_label, hit_index, reaction_ms FROM game_reactions WHERE session_id = ? ORDER BY slot ASC, hit_index ASC");
            mysqli_stmt_bind_param($stmt, 'i', $sessionId);
            mysqli_stmt_execute($stmt);
            $reactions = [];
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res))
                $reactions[] = $r;

            // Group reactions by slot for chart
            $bySlot = [];
            foreach ($reactions as $r) {
                $s = (int) $r['slot'];
                $bySlot[$s][] = (int) $r['reaction_ms'];
            }
            ?>
            <div class="panel-box p-4 mb-4">
                <div class="flex flex-wrap gap-4 items-center text-sm">
                    <div><strong>Sesi #<?= (int) $session['id'] ?></strong></div>
                    <div>Room: <strong><?= (int) $session['room_id'] ?></strong></div>
                    <div>Difficulty: <strong class="uppercase"><?= e($session['difficulty']) ?></strong></div>
                    <div>Pemain: <strong><?= (int) $session['num_players'] ?></strong></div>
                    <div>Durasi: <strong><?= (int) $session['duration_sec'] ?>s</strong></div>
                    <div>Owner: <strong><?= e($session['owner_name'] ?? '—') ?></strong></div>
                    <div>Selesai: <strong><?= e($session['ended_at'] ?? $session['started_at']) ?></strong></div>
                </div>
            </div>

            <div class="panel-box p-4 mb-4">
                <h3 class="font-extrabold mb-3 text-base">Ringkasan Statistik Reaksi</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-panel2 text-left">
                            <tr>
                                <th class="p-2 border border-ink/30">#</th>
                                <th class="p-2 border border-ink/30">Pemain</th>
                                <th class="p-2 border border-ink/30">Skor</th>
                                <th class="p-2 border border-ink/30">Hit</th>
                                <th class="p-2 border border-ink/30">Rata-rata RT</th>
                                <th class="p-2 border border-ink/30">Konsistensi (max−min)</th>
                                <th class="p-2 border border-ink/30">Perubahan setelah 5 hit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scores as $i => $row):
                                $change = (int) $row['change_after5_ms'];
                                $changeColor = $change === 0 ? '' : ($change > 0 ? 'color:#b91c1c' : 'color:#15803d');
                                $changeTxt = (int) $row['hits_count'] >= 6
                                    ? fmt_change($change) . ($change > 0 ? ' (melambat)' : ($change < 0 ? ' (lebih cepat)' : ''))
                                    : '— belum cukup data —';
                                ?>
                                <tr class="<?= ((int) $row['is_winner'] === 1) ? 'bg-accentYel/40 font-bold' : '' ?>">
                                    <td class="p-2 border border-ink/20"><?= $i + 1 ?></td>
                                    <td class="p-2 border border-ink/20"><?= e($row['player_label']) ?>
                                        <?= ((int) $row['is_winner'] === 1) ? ' 🏆' : '' ?>
                                    </td>
                                    <td class="p-2 border border-ink/20"><?= (int) $row['score'] ?> poin</td>
                                    <td class="p-2 border border-ink/20"><?= (int) $row['hits_count'] ?></td>
                                    <td class="p-2 border border-ink/20 tabular-nums">
                                        <?= (int) $row['hits_count'] > 0 ? fmt_ms($row['avg_reaction_ms']) : '—' ?>
                                    </td>
                                    <td class="p-2 border border-ink/20 tabular-nums">
                                        <?= (int) $row['hits_count'] > 0 ? fmt_ms($row['consistency_ms']) : '—' ?>
                                    </td>
                                    <td class="p-2 border border-ink/20 tabular-nums" style="<?= $changeColor ?>">
                                        <?= e($changeTxt) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-inkMuted mt-2">
                    <strong>Konsistensi</strong>: selisih reaksi tercepat dan terlambat — makin kecil makin stabil.
                    <strong>Perubahan setelah 5 hit</strong>: rata-rata 5 hit terakhir dikurangi rata-rata 5 hit pertama
                    — angka <span style="color:#15803d">negatif</span> berarti makin cepat, <span
                        style="color:#b91c1c">positif</span> berarti melambat.
                </p>
            </div>

            <?php if (!empty($reactions)): ?>
                <div class="panel-box p-4 mb-4">
                    <h3 class="font-extrabold mb-3 text-base">Grafik Waktu Reaksi per Hit</h3>
                    <canvas id="reactChart" height="220"
                        style="width:100%; max-width:100%; background:#111; border-radius:6px;"></canvas>
                    <p class="text-xs text-inkMuted mt-2">Sumbu X: urutan hit (hit), sumbu Y: waktu reaksi (ms). Setiap warna =
                        pemain.</p>
                </div>

                <div class="panel-box p-4 mb-6">
                    <details>
                        <summary class="cursor-pointer font-bold">Lihat semua data hit (raw)</summary>
                        <div class="overflow-x-auto mt-3">
                            <table class="w-full text-xs border-collapse">
                                <thead class="bg-panel2">
                                    <tr>
                                        <th class="p-2 border border-ink/20 text-left">Pemain</th>
                                        <th class="p-2 border border-ink/20 text-left">Hit #</th>
                                        <th class="p-2 border border-ink/20 text-left">Reaction (ms)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reactions as $r): ?>
                                        <tr>
                                            <td class="p-2 border border-ink/10"><?= e($r['player_label']) ?> (slot
                                                <?= (int) $r['slot'] ?>)
                                            </td>
                                            <td class="p-2 border border-ink/10"><?= (int) $r['hit_index'] ?></td>
                                            <td class="p-2 border border-ink/10 tabular-nums"><?= (int) $r['reaction_ms'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>

                <script>
                    (function () {
                        const data = <?= json_encode([
                            'series' => array_map(function ($s) use ($bySlot) {
                            return [
                                'label' => $s['player_label'],
                                'slot' => (int) $s['slot'],
                                'points' => $bySlot[(int) $s['slot']] ?? []
                            ];
                        }, $scores),
                        ], JSON_UNESCAPED_UNICODE) ?>;
                        const COLORS = ['#d83a3a', '#2ecc40', '#ffd23f', '#3aa0ff'];
                        const cv = document.getElementById('reactChart');
                        if (!cv) return;
                        const dpr = window.devicePixelRatio || 1;
                        function fit() {
                            const w = cv.clientWidth || 800, h = 220;
                            cv.width = w * dpr; cv.height = h * dpr;
                            draw();
                        }
                        function draw() {
                            const ctx = cv.getContext('2d');
                            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                            const W = cv.width / dpr, H = cv.height / dpr;
                            ctx.fillStyle = '#111'; ctx.fillRect(0, 0, W, H);
                            // Compute scale
                            let maxMs = 100, maxN = 5;
                            for (const s of data.series) {
                                if (s.points.length > maxN) maxN = s.points.length;
                                for (const v of s.points) if (v > maxMs) maxMs = v;
                            }
                            maxMs = Math.ceil(maxMs / 200) * 200;
                            const pad = { l: 50, r: 12, t: 14, b: 28 };
                            const cw = W - pad.l - pad.r, ch = H - pad.t - pad.b;
                            // Grid Y
                            ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.fillStyle = '#aaa'; ctx.font = '10px Inter,sans-serif';
                            for (let i = 0; i <= 5; i++) {
                                const y = pad.t + (ch * i / 5);
                                ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(W - pad.r, y); ctx.stroke();
                                const v = Math.round(maxMs - (maxMs * i / 5));
                                ctx.fillText(v + ' ms', 6, y + 3);
                            }
                            // X labels
                            const stepX = maxN > 1 ? cw / (maxN - 1) : cw;
                            ctx.fillText('Hit #1', pad.l - 6, H - 8);
                            ctx.fillText('Hit #' + maxN, W - pad.r - 40, H - 8);
                            // Series
                            data.series.forEach((s, idx) => {
                                const color = COLORS[(s.slot - 1) % COLORS.length] || COLORS[idx % COLORS.length];
                                ctx.strokeStyle = color; ctx.fillStyle = color; ctx.lineWidth = 2;
                                ctx.beginPath();
                                s.points.forEach((v, i) => {
                                    const x = pad.l + (maxN > 1 ? stepX * i : cw / 2);
                                    const y = pad.t + ch - (v / maxMs) * ch;
                                    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                                });
                                ctx.stroke();
                                s.points.forEach((v, i) => {
                                    const x = pad.l + (maxN > 1 ? stepX * i : cw / 2);
                                    const y = pad.t + ch - (v / maxMs) * ch;
                                    ctx.beginPath(); ctx.arc(x, y, 3, 0, Math.PI * 2); ctx.fill();
                                });
                            });
                            // Legend
                            let lx = pad.l;
                            ctx.font = 'bold 11px Inter,sans-serif';
                            data.series.forEach((s, idx) => {
                                const color = COLORS[(s.slot - 1) % COLORS.length] || COLORS[idx % COLORS.length];
                                ctx.fillStyle = color; ctx.fillRect(lx, 2, 10, 10);
                                ctx.fillStyle = '#ddd'; ctx.fillText(s.label, lx + 14, 11);
                                lx += 14 + ctx.measureText(s.label).width + 16;
                            });
                        }
                        fit();
                        window.addEventListener('resize', fit);
                    })();
                </script>
            <?php endif; ?>
            <?php
        endif; // session found
    else:
        // ---- Daftar riwayat ----
        // FIX: dulu hanya menampilkan sesi di mana user adalah OWNER.
        // Sekarang juga sesi di mana nama user (users.nama) muncul sebagai
        // player_label di game_scores — supaya pemain biasa juga bisa lihat
        // statistik permainannya.
        $myName = (string) ($_SESSION['nama'] ?? '');
        $stmt = mysqli_prepare($db, "SELECT DISTINCT gs.id, gs.room_id, gs.difficulty, gs.num_players, gs.duration_sec, gs.started_at, gs.ended_at,
                                        gs.owner_id,
                                        (SELECT player_label FROM game_scores WHERE session_id = gs.id AND is_winner=1 ORDER BY score DESC, slot ASC LIMIT 1) AS winner_label,
                                        (SELECT MAX(score) FROM game_scores WHERE session_id = gs.id) AS top_score
                                 FROM game_sessions gs
                                 LEFT JOIN game_scores sc ON sc.session_id = gs.id
                                 WHERE gs.owner_id = ? OR sc.player_label = ?
                                 ORDER BY gs.id DESC
                                 LIMIT 100");
        mysqli_stmt_bind_param($stmt, 'is', $myId, $myName);
        mysqli_stmt_execute($stmt);
        $rows = [];
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res))
            $rows[] = $r;
        ?>
        <div class="panel-box p-4">
            <p class="text-sm mb-3">Menampilkan semua sesi yang kamu ikuti — sebagai <strong>owner</strong> atau sebagai <strong>pemain</strong>.</p>
            <?php if (empty($rows)): ?>
                <p class="text-inkMuted">Belum ada sesi tersimpan. Mainkan satu game di room dulu untuk melihat statistik di
                    sini.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-panel2 text-left">
                            <tr>
                                <th class="p-2 border border-ink/30">Sesi</th>
                                <th class="p-2 border border-ink/30">Room</th>
                                <th class="p-2 border border-ink/30">Difficulty</th>
                                <th class="p-2 border border-ink/30">Pemain</th>
                                <th class="p-2 border border-ink/30">Durasi</th>
                                <th class="p-2 border border-ink/30">Pemenang</th>
                                <th class="p-2 border border-ink/30">Tanggal</th>
                                <th class="p-2 border border-ink/30">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="p-2 border border-ink/20">#<?= (int) $r['id'] ?></td>
                                    <td class="p-2 border border-ink/20"><?= (int) $r['room_id'] ?></td>
                                    <td class="p-2 border border-ink/20 uppercase"><?= e($r['difficulty']) ?></td>
                                    <td class="p-2 border border-ink/20"><?= (int) $r['num_players'] ?></td>
                                    <td class="p-2 border border-ink/20"><?= (int) $r['duration_sec'] ?>s</td>
                                    <td class="p-2 border border-ink/20"><?= e($r['winner_label'] ?? '—') ?> <span
                                            class="text-inkMuted">(<?= (int) $r['top_score'] ?> poin)</span></td>
                                    <td class="p-2 border border-ink/20"><?= e($r['ended_at'] ?? $r['started_at']) ?></td>
                                    <td class="p-2 border border-ink/20"><a class="btn"
                                            href="statistik.php?session=<?= (int) $r['id'] ?>">Lihat</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/views/footer.php'; ?>
