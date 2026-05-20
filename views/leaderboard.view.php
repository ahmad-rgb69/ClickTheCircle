<div class="w-full max-w-[1100px] mx-auto">
    <div class="h-section !max-w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 !p-4">
        <h2 class="m-0 flex-1 font-extrabold text-xl sm:text-2xl tracking-wide">🏆 Global Leaderboard</h2>
        <a href="lobby.php" class="btn whitespace-nowrap shrink-0 transition-all duration-100 hover:tombol_animasi">← Kembali ke Lobby</a>
    </div>

    <div class="panel-box p-4 mb-8 flex flex-col gap-4 bg-panel2">
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-bold text-sm min-w-[120px]">Pilih Difficulty:</span>
            <div class="flex flex-wrap gap-2">
                <?php 
                $diffLabels = ['easy' => 'EASY', 'normal' => 'NORMAL', 'hard' => 'HARD', 'indonesian' => 'INDONESIAN'];
                foreach($diffLabels as $k => $label): 
                    $isActive = $currentDiff === $k;
                    $activeClass = $isActive ? 'bg-black text-white' : 'bg-white text-black hover:bg-gray-100';
                ?>
                    <a href="?diff=<?= $k ?>&dur=<?= $currentDuration ?>" class="px-4 py-2 border-2 border-black font-bold text-sm rounded shadow-[2px_2px_0_0_rgba(0,0,0,1)] transition-colors <?= $activeClass ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-bold text-sm min-w-[120px]">Pilih Durasi:</span>
            <div class="flex flex-wrap gap-2">
                <?php 
                $durations = [30 => '30 DETIK', 60 => '60 DETIK', 90 => '90 DETIK'];
                foreach($durations as $dVal => $dLabel): 
                    $isActive = $currentDuration === $dVal;
                    $activeClass = $isActive ? 'bg-black text-white' : 'bg-white text-black hover:bg-gray-100';
                ?>
                    <a href="?diff=<?= $currentDiff ?>&dur=<?= $dVal ?>" class="px-4 py-2 border-2 border-black font-bold text-sm rounded shadow-[2px_2px_0_0_rgba(0,0,0,1)] transition-colors <?= $activeClass ?>">
                        <?= $dLabel ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="panel-box p-4 mb-8">
        <h3 class="font-extrabold mb-3 text-base">Top 20 Skor Tertinggi</h3>
        <?php if(empty($topScores)): ?>
            <p class="text-inkMuted text-sm">Belum ada skor yang tersimpan.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead class="bg-panel2 text-left">
                        <tr>
                            <th class="p-2 border border-ink/30 w-16">#</th>
                            <th class="p-2 border border-ink/30">Pemain</th>
                            <th class="p-2 border border-ink/30">Skor Tertinggi</th>
                            <th class="p-2 border border-ink/30">Waktu Dicapai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($topScores as $i => $row): ?>
                            <tr class="<?= $i === 0 ? 'bg-accentYel/40 font-bold' : '' ?>">
                                <td class="p-2 border border-ink/20"><?= $i+1 ?></td>
                                <td class="p-2 border border-ink/20"><?= e($row['player_label']) ?><?= $i === 0 ? ' 👑' : '' ?></td>
                                <td class="p-2 border border-ink/20"><?= (int)$row['best_score'] ?> poin</td>
                                <td class="p-2 border border-ink/20 tabular-nums"><?= e($row['achieved_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel-box p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-extrabold text-base m-0">Top 20 Waktu Reaksi Tercepat</h3>
            <span class="text-xs bg-panel2 border border-ink/30 px-2 py-1 rounded">Minimal 10 hit untuk kualifikasi</span>
        </div>
        
        <?php if(empty($topFastest)): ?>
            <p class="text-inkMuted text-sm">Belum ada reaksi yang memenuhi syarat.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead class="bg-panel2 text-left">
                        <tr>
                            <th class="p-2 border border-ink/30 w-16">#</th>
                            <th class="p-2 border border-ink/30">Pemain</th>
                            <th class="p-2 border border-ink/30">Rata-rata RT</th>
                            <th class="p-2 border border-ink/30">Konsistensi (max−min)</th>
                            <th class="p-2 border border-ink/30">Perubahan setelah 5 hit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($topFastest as $i => $row): 
                            $change = (int)$row['change_after5_ms'];
                            $changeColor = $change === 0 ? '' : ($change > 0 ? 'color:#b91c1c' : 'color:#15803d');
                            $changeTxt = $change === 0 ? '0 ms' : (($change > 0 ? '+' : '') . $change . ' ms' . ($change > 0 ? ' (melambat)' : ' (lebih cepat)'));
                        ?>
                            <tr class="<?= $i === 0 ? 'bg-accentYel/40 font-bold' : '' ?>">
                                <td class="p-2 border border-ink/20"><?= $i+1 ?></td>
                                <td class="p-2 border border-ink/20"><?= e($row['player_label']) ?><?= $i === 0 ? ' ⚡' : '' ?></td>
                                <td class="p-2 border border-ink/20 tabular-nums"><?= number_format((float)$row['best_reaction'], 0, ',', '.') ?> ms</td>
                                <td class="p-2 border border-ink/20 tabular-nums"><?= number_format((float)$row['consistency_ms'], 0, ',', '.') ?> ms</td>
                                <td class="p-2 border border-ink/20 tabular-nums" style="<?= $changeColor ?>"><?= e($changeTxt) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-inkMuted mt-3">
                <strong>Konsistensi</strong>: selisih reaksi tercepat dan terlambat — makin kecil makin stabil.
                <strong>Perubahan setelah 5 hit</strong>: rata-rata 5 hit terakhir dikurangi rata-rata 5 hit pertama.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
