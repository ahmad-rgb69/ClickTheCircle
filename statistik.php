<?php
/**
 * statistik.php
 *
 * Dua mode:
 * - statistik.php            -> daftar riwayat sesi yang dimiliki user (owner) yang sedang login.
 * - statistik.php?session=ID -> detail statistik 1 sesi (chart + tabel + per-hit).
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

<div class="w-full max-w-[960px] mx-auto px-4 py-6 flex flex-col min-h-[calc(100vh-80px)]">
    
    <div class="w-full bg-panel2 border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] text-xl md:text-2xl font-extrabold tracking-wide flex flex-row items-center justify-between gap-4 p-4 mb-4">
        <h2 class="m-0 font-extrabold text-xl sm:text-2xl tracking-wide flex items-center gap-2">📊 Statistik Permainan</h2>
        <div class="flex items-center gap-2 shrink-0">
            <?php if ($sessionId > 0): ?>
                <a href="statistik.php" class="btn whitespace-nowrap !bg-control hover:!bg-control-hi shadow-[2px_2px_0px_#1a1a1a] active:translate-y-[2px] active:shadow-none transition-all !rounded-none">← Kembali</a>
            <?php endif; ?>
            <a href="lobby.php" class="btn whitespace-nowrap shadow-[2px_2px_0px_#1a1a1a] active:translate-y-[2px] active:shadow-none transition-all !rounded-none">Lobby</a>
        </div>
    </div>

    <?php if ($sessionId > 0):
        // ---- DETAIL SATU SESI ----
        $stmt = mysqli_prepare($db, "SELECT gs.*, u.nama AS owner_name FROM game_sessions gs LEFT JOIN users u ON u.id = gs.owner_id WHERE gs.id = ?");
        $stmt = mysqli_prepare($db, "SELECT gs.*, u.nama AS owner_name FROM game_sessions gs LEFT JOIN users u ON u.id = gs.owner_id WHERE gs.id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $sessionId);
        mysqli_stmt_execute($stmt);
        $session = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$session):
            ?>
            <div class="w-full bg-panel border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] p-6 text-center">
                <p class="font-bold mb-2">Sesi tidak ditemukan.</p>
                <a href="statistik.php" class="underline font-semibold text-ink-muted">Lihat semua riwayat</a>
            </div>
        <?php else:
            // Ambil data skor
            $stmt = mysqli_prepare($db, "SELECT slot, player_label, score, is_winner, avg_reaction_ms, consistency_ms, change_after5_ms, hits_count FROM game_scores WHERE session_id = ? ORDER BY score DESC, slot ASC");
            mysqli_stmt_bind_param($stmt, 'i', $sessionId);
            mysqli_stmt_execute($stmt);
            $scores = [];
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res)) {
                $scores[] = $r;
            }

            // Ambil data reaksi
            $stmt = mysqli_prepare($db, "SELECT slot, player_label, hit_index, reaction_ms FROM game_reactions WHERE session_id = ? ORDER BY slot ASC, hit_index ASC");
            mysqli_stmt_bind_param($stmt, 'i', $sessionId);
            mysqli_stmt_execute($stmt);
            $reactions = [];
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res)) {
                $reactions[] = $r;
            }

            $bySlot = [];
            foreach ($reactions as $r) {
                $s = (int) $r['slot'];
                $bySlot[$s][] = (int) $r['reaction_ms'];
            }
            ?>
            
            <div class="w-full bg-panel2 border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] p-4 mb-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 text-center text-xs font-bold uppercase tracking-wider text-ink-muted">
                    <div class="bg-control border-2 border-ink p-2 shadow-[2px_2px_0px_#1a1a1a]">
                        <span class="block text-[10px] text-ink-muted/70">Sesi</span>
                        <span class="text-ink text-sm">#<?= (int) $session['id'] ?></span>
                    </div>
                    <div class="bg-control border-2 border-ink p-2 shadow-[2px_2px_0px_#1a1a1a]">
                        <span class="block text-[10px] text-ink-muted/70">Room ID</span>
                        <span class="text-ink text-sm"><?= (int) $session['room_id'] ?></span>
                    </div>
                    <div class="bg-control border-2 border-ink p-2 shadow-[2px_2px_0px_#1a1a1a]">
                        <span class="block text-[10px] text-ink-muted/70">Difficulty</span>
                        <span class="text-ink text-sm"><?= e($session['difficulty']) ?></span>
                    </div>
                    <div class="bg-control border-2 border-ink p-2 shadow-[2px_2px_0px_#1a1a1a]">
                        <span class="block text-[10px] text-ink-muted/70">Pemain</span>
                        <span class="text-ink text-sm"><?= (int) $session['num_players'] ?></span>
                    </div>
                    <div class="bg-control border-2 border-ink p-2 shadow-[2px_2px_0px_#1a1a1a]">
                        <span class="block text-[10px] text-ink-muted/70">Durasi</span>
                        <span class="text-ink text-sm"><?= (int) $session['duration_sec'] ?>s</span>
                    </div>
                    <div class="bg-control border-2 border-ink p-2 shadow-[2px_2px_0px_#1a1a1a] col-span-2 sm:col-span-1">
                        <span class="block text-[10px] text-ink-muted/70">Owner</span>
                        <span class="text-ink text-sm truncate block"><?= e($session['owner_name'] ?? '—') ?></span>
                    </div>
                    <div class="bg-control border-2 border-ink p-2 shadow-[2px_2px_0px_#1a1a1a] col-span-2 lg:col-span-1">
                        <span class="block text-[10px] text-ink-muted/70">Waktu Selesai</span>
                        <span class="text-ink text-xs block mt-1"><?= date('H:i:s', strtotime($session['ended_at'] ?? $session['started_at'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="w-full bg-panel border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] p-4 mb-4">
                <h3 class="font-extrabold mb-3 text-base text-ink tracking-wide">🏆 Ringkasan Skor & Waktu Reaksi</h3>
                <div class="overflow-x-auto border-2 border-ink bg-control">
                    <table class="w-full text-sm border-collapse text-left">
                        <thead class="bg-panel2 border-b-2 border-ink text-ink font-bold">
                            <tr>
                                <th class="p-3 border-r-2 border-ink w-12 text-center">#</th>
                                <th class="p-3 border-r-2 border-ink">Pemain</th>
                                <th class="p-3 border-r-2 border-ink text-center">Skor</th>
                                <th class="p-3 border-r-2 border-ink text-center">Hit</th>
                                <th class="p-3 border-r-2 border-ink text-center">Rata-rata RT</th>
                                <th class="p-3 border-r-2 border-ink text-center">Konsistensi</th>
                                <th class="p-3 text-center">Perubahan (5 Hit)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y-2 divide-ink font-medium">
                            <?php foreach ($scores as $i => $row):
                                $change = (int) $row['change_after5_ms'];
                                $changeColor = $change === 0 ? '' : ($change > 0 ? 'color:#d83a3a' : 'color:#2ecc40');
                                $changeTxt = (int) $row['hits_count'] >= 6
                                    ? fmt_change($change) . ($change > 0 ? ' 🐢' : ($change < 0 ? ' ⚡' : ''))
                                    : '—';
                                ?>
                                <tr class="<?= ((int) $row['is_winner'] === 1) ? 'bg-accent-yel/30 font-bold' : 'hover:bg-white/20' ?>">
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums"><?= $i + 1 ?></td>
                                    <td class="p-3 border-r-2 border-ink flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <?= e($row['player_label']) ?>
                                            <?php if ((int) $row['is_winner'] === 1): ?>
                                                <!-- IMPLEMENTASI CHIP WINNER -->
                                                <span class="inline-flex items-center gap-1 bg-accent-yel border border-ink text-[10px] font-black uppercase px-2 py-0.5 tracking-wide shadow-[1px_1px_0px_#1a1a1a]">
                                                    🏆 WINNER
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- IMPLEMENTASI CHIP SLOT -->
                                        <span class="inline-block bg-panel2 border border-ink text-[10px] font-mono px-1.5 py-0.5 tracking-tight shrink-0 select-none text-ink text-xs font-bold shadow-[1px_1px_0px_#1a1a1a]">
                                            SLOT <?= (int) $row['slot'] ?>
                                        </span>
                                    </td>
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums font-bold"><?= (int) $row['score'] ?> pts</td>
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums"><?= (int) $row['hits_count'] ?></td>
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums">
                                        <?= (int) $row['hits_count'] > 0 ? fmt_ms($row['avg_reaction_ms']) : '—' ?>
                                    </td>
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums">
                                        <?= (int) $row['hits_count'] > 0 ? fmt_ms($row['consistency_ms']) : '—' ?>
                                    </td>
                                    <td class="p-3 text-center tabular-nums font-bold" style="<?= $changeColor ?>">
                                        <?= e($changeTxt) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex flex-col gap-1 bg-panel2/50 border-2 border-ink p-2.5 text-xs text-ink-muted">
                    <div>💡 <strong>Konsistensi</strong>: Selisih reaksi tercepat & terlambat. Makin kecil angkanya, kendali motorik makin stabil.</div>
                    <div>💡 <strong>Perubahan (5 Hit)</strong>: Selisih performa awal vs akhir game. Angka <span class="text-accent-green font-bold">negatif (hijau)</span> berarti Anda makin fokus/cepat.</div>
                </div>
            </div>

            <?php if (!empty($reactions)): ?>
                <div class="w-full bg-panel border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] p-4 mb-4">
                    <h3 class="font-extrabold mb-3 text-base text-ink tracking-wide">📈 Grafik Tren Waktu Reaksi</h3>
                    <div class="border-2 border-ink p-2 bg-[#111111]">
                        <canvas id="reactChart" height="240" style="width:100%; max-width:100%; display:block;"></canvas>
                    </div>
                </div>

                <div class="w-full bg-panel border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] p-3 mb-4">
                    <details class="group">
                        <summary class="cursor-pointer font-bold text-sm flex items-center justify-between p-1 select-none text-ink">
                            <span>📋 Lihat Semua Log Hit Sesi (Raw Data)</span>
                            <span class="transition-transform duration-200 group-open:rotate-180">▼</span>
                        </summary>
                        <div class="overflow-x-auto mt-3 border-2 border-ink bg-control max-h-60 overflow-y-auto shadow-inset1">
                            <table class="w-full text-xs border-collapse text-left">
                                <thead class="bg-panel2 border-b-2 border-ink sticky top-0 font-bold">
                                    <tr>
                                        <th class="p-2 border-r-2 border-ink">Pemain</th>
                                        <th class="p-2 border-r-2 border-ink text-center">Hit Ke-</th>
                                        <th class="p-2 text-center">Waktu Reaksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y-2 divide-ink tabular-nums">
                                    <?php foreach ($reactions as $r): ?>
                                        <tr class="hover:bg-white/30">
                                            <td class="p-2 border-r-2 border-ink flex items-center justify-between gap-2">
                                                <span><?= e($r['player_label']) ?></span>
                                                <!-- IMPLEMENTASI CHIP SLOT RAW LOG -->
                                                <span class="inline-block bg-panel2 border border-ink text-[9px] font-mono px-1 py-0.5 text-ink font-bold shadow-[1px_1px_0px_#1a1a1a]">
                                                    SLOT <?= (int) $r['slot'] ?>
                                                </span>
                                            </td>
                                            <td class="p-2 border-r-2 border-ink text-center"><?= (int) $r['hit_index'] ?></td>
                                            <td class="p-2 text-center font-semibold"><?= (int) $r['reaction_ms'] ?> ms</td>
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
                            const w = cv.parentElement.clientWidth - 16, h = 240;
                            cv.width = w * dpr; cv.height = h * dpr;
                            cv.style.width = w + 'px'; cv.style.height = h + 'px';
                            draw();
                        }
                        function draw() {
                            const ctx = cv.getContext('2d');
                            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                            const W = cv.width / dpr, H = cv.height / dpr;
                            ctx.clearRect(0,0,W,H);
                            
                            let maxMs = 100, maxN = 5;
                            for (const s of data.series) {
                                if (s.points.length > maxN) maxN = s.points.length;
                                for (const v of s.points) if (v > maxMs) maxMs = v;
                            }
                            maxMs = Math.ceil(maxMs / 200) * 200;
                            const pad = { l: 55, r: 20, t: 25, b: 30 };
                            const cw = W - pad.l - pad.r, ch = H - pad.t - pad.b;
                            
                            ctx.strokeStyle = 'rgba(255,255,255,.08)'; ctx.fillStyle = '#888'; ctx.font = '10px monospace';
                            for (let i = 0; i <= 4; i++) {
                                const y = pad.t + (ch * i / 4);
                                ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(W - pad.r, y); ctx.stroke();
                                const v = Math.round(maxMs - (maxMs * i / 4));
                                ctx.fillText(v + ' ms', 6, y + 3);
                            }
                            
                            const stepX = maxN > 1 ? cw / (maxN - 1) : cw;
                            ctx.fillStyle = '#888';
                            ctx.fillText('Hit #1', pad.l, H - 8);
                            ctx.fillText('Hit #' + maxN, W - pad.r - 35, H - 8);
                            
                            data.series.forEach((s, idx) => {
                                const color = COLORS[(s.slot - 1) % COLORS.length];
                                ctx.strokeStyle = color; ctx.fillStyle = color; ctx.lineWidth = 2.5;
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
                                    ctx.beginPath(); ctx.arc(x, y, 3.5, 0, Math.PI * 2); ctx.fill();
                                    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(x, y, 1.5, 0, Math.PI * 2); ctx.fill();
                                    ctx.fillStyle = color;
                                });
                            });
                            
                            let lx = pad.l;
                            ctx.font = 'bold 11px sans-serif';
                            data.series.forEach((s, idx) => {
                                const color = COLORS[(s.slot - 1) % COLORS.length];
                                ctx.fillStyle = color; ctx.fillRect(lx, 4, 11, 11);
                                ctx.strokeStyle = '#000'; ctx.strokeRect(lx, 4, 11, 11);
                                ctx.fillStyle = '#ddd'; ctx.fillText(s.label, lx + 15, 14);
                                lx += 15 + ctx.measureText(s.label).width + 18;
                            });
                        }
                        fit();
                        window.addEventListener('resize', fit);
                    })();
                </script>
            <?php endif; ?>
        <?php endif;
    else:
        // ---- MODE UTAMA: DAFTAR RIWAYAT SEMUA SESI ----
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
        
        <div class="w-full bg-panel2 border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] p-3 mb-4 flex flex-wrap items-center gap-2">
            <p class="text-sm text-ink font-semibold m-0">Menampilkan riwayat permainan Anda:</p>
            <!-- INDIKATOR ROLE MENGGUNAKAN CHIP BRUTALISM -->
            <span class="inline-flex bg-accent-blue text-ink font-bold text-xs uppercase px-2.5 py-1 border-2 border-ink shadow-[2px_2px_0px_#1a1a1a]">
                👑 Owner Room
            </span>
            <span class="inline-flex bg-accent-green text-ink font-bold text-xs uppercase px-2.5 py-1 border-2 border-ink shadow-[2px_2px_0px_#1a1a1a]">
                🏃 Pemain
            </span>
        </div>

        <div class="w-full bg-panel border-2 border-ink shadow-[4px_4px_0px_#1a1a1a] p-4">
            <?php if (empty($rows)): ?>
                <div class="text-center p-8 bg-control border-2 border-ink shadow-[2px_2px_0px_#1a1a1a]">
                    <p class="text-ink-muted font-bold m-0">Belum ada rekam data pertempuran.</p>
                    <p class="text-xs text-ink-muted/80 mt-1 m-0">Selesaikan minimal satu permainan di room untuk memunculkan statistik.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto border-2 border-ink bg-control">
                    <table class="w-full text-sm border-collapse text-left">
                        <thead class="bg-panel2 border-b-2 border-ink font-bold text-ink">
                            <tr>
                                <th class="p-3 border-r-2 border-ink">ID Sesi</th>
                                <th class="p-3 border-r-2 border-ink text-center">Room</th>
                                <th class="p-3 border-r-2 border-ink text-center">Difficulty</th>
                                <th class="p-3 border-r-2 border-ink text-center">Pemain</th>
                                <th class="p-3 border-r-2 border-ink text-center">Durasi</th>
                                <th class="p-3 border-r-2 border-ink">Pemenang</th>
                                <th class="p-3 border-r-2 border-ink text-center">Waktu Main</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y-2 divide-ink font-medium">
                            <?php foreach ($rows as $r): 
                                // Ganti warna background chip kesulitan sesuai tingkatan
                                $diff = strtolower($r['difficulty']);
                                $diffBg = 'bg-control';
                                if (str_contains($diff, 'easy')) $diffBg = 'bg-accent-green/60';
                                if (str_contains($diff, 'medium')) $diffBg = 'bg-accent-yel/80';
                                if (str_contains($diff, 'hard')) $diffBg = 'bg-accent-red/60';
                                ?>
                                <tr class="hover:bg-white/20 transition-colors">
                                    <td class="p-3 border-r-2 border-ink font-bold tabular-nums">#<?= (int) $r['id'] ?></td>
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums"><?= (int) $r['room_id'] ?></td>
                                    <td class="p-3 border-r-2 border-ink text-center">
                                        <!-- MODIFIKASI CHIP DIFFICULTY PADA TABEL -->
                                        <span class="px-2 py-0.5 <?= $diffBg ?> border-2 border-ink text-xs uppercase font-black tracking-wide inline-block shadow-[1px_1px_0px_#1a1a1a]">
                                            <?= e($r['difficulty']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums"><?= (int) $r['num_players'] ?> / 4</td>
                                    <td class="p-3 border-r-2 border-ink text-center tabular-nums"><?= (int) $r['duration_sec'] ?>s</td>
                                    <td class="p-3 border-r-2 border-ink font-semibold">
                                        <?= $r['winner_label'] ? e($r['winner_label']) . ' <span class="text-xs text-ink-muted font-normal">('.(int)$r['top_score'].' pts)</span>' : '—' ?>
                                    </td>
                                    <td class="p-3 border-r-2 border-ink text-center text-xs text-ink-muted tabular-nums"><?= date('d M Y H:i', strtotime($r['ended_at'] ?? $r['started_at'])) ?></td>
                                    <td class="p-2 text-center">
                                        <a class="btn !px-3 !py-1 !min-h-[30px] text-xs shadow-[2px_2px_0px_#1a1a1a] active:translate-y-[2px] active:shadow-none transition-all !rounded-none" href="statistik.php?session=<?= (int) $r['id'] ?>">Detail</a>
                                    </td>
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