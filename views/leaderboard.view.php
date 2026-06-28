<div class="w-full max-w-[1100px] mx-auto text-[#FFFFF6]">
    <!-- Judul & Tombol Kembali -->
    <div class="h-section !max-w-full flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 !p-4 bg-[#242752] border-2 border-[#1A1A3A] rounded shadow-[4px_4px_0_0_rgba(26,26,58,1)]">
        <h2 class="m-0 flex-1 font-extrabold text-xl sm:text-2xl tracking-wide text-[#B57DDA]">🏆 Global Leaderboard</h2>
        <a href="lobby.php" class="whitespace-nowrap shrink-0 transition-all duration-100 bg-[#B57DDA] text-[#1A1A3A] font-black py-2 px-4 border-2 border-[#1A1A3A] shadow-[2px_2px_0_0_rgba(26,26,58,1)] hover:shadow-none hover:translate-x-0.5 hover:translate-y-0.5 rounded text-sm">
            ← Kembali ke Lobby
        </a>
    </div>

    <!-- Filter Batas Difficulty & Durasi -->
    <div class="panel-box p-4 mb-8 flex flex-col gap-4 bg-[#242752] border-2 border-[#1A1A3A] rounded shadow-[4px_4px_0_0_rgba(26,26,58,1)]">
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-bold text-sm min-w-[120px] text-[#E8E2D4]">Pilih Difficulty:</span>
            <div class="flex flex-wrap gap-2">
                <?php 
                $diffLabels = ['easy' => 'EASY', 'normal' => 'NORMAL', 'hard' => 'HARD', 'indonesian' => 'INDONESIAN'];
                foreach($diffLabels as $k => $label): 
                    $isActive = $currentDiff === $k;
                    // Aktif: Bright Lavender, Tidak Aktif: French Blue Tua
                    $activeClass = $isActive ? 'bg-[#B57DDA] text-[#1A1A3A]' : 'bg-[#1A1A3A] text-[#E8E2D4] hover:bg-[#41478B] hover:text-[#FFFFF6]';
                ?>
                    <a href="?diff=<?= $k ?>&dur=<?= $currentDuration ?>" class="px-4 py-2 border-2 border-[#1A1A3A] font-extrabold text-sm rounded shadow-[2px_2px_0_0_rgba(26,26,58,1)] transition-colors <?= $activeClass ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-bold text-sm min-w-[120px] text-[#E8E2D4]">Pilih Durasi:</span>
            <div class="flex flex-wrap gap-2">
                <?php 
                $durations = [30 => '30 DETIK', 60 => '60 DETIK', 90 => '90 DETIK'];
                foreach($durations as $dVal => $dLabel): 
                    $isActive = $currentDuration === $dVal;
                    // Aktif: Bright Lavender, Tidak Aktif: French Blue Tua
                    $activeClass = $isActive ? 'bg-[#B57DDA] text-[#1A1A3A]' : 'bg-[#1A1A3A] text-[#E8E2D4] hover:bg-[#41478B] hover:text-[#FFFFF6]';
                ?>
                    <a href="?diff=<?= $currentDiff ?>&dur=<?= $dVal ?>" class="px-4 py-2 border-2 border-[#1A1A3A] font-extrabold text-sm rounded shadow-[2px_2px_0_0_rgba(26,26,58,1)] transition-colors <?= $activeClass ?>">
                        <?= $dLabel ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabel Top 20 Skor Tertinggi -->
    <div class="panel-box p-4 mb-8 bg-[#242752] border-2 border-[#1A1A3A] rounded shadow-[4px_4px_0_0_rgba(26,26,58,1)]">
        <h3 class="font-extrabold mb-3 text-base text-[#B57DDA]">Top 20 Skor Tertinggi</h3>
        <?php if(empty($topScores)): ?>
            <p class="text-[#AAA0BB] text-sm">Belum ada skor yang tersimpan.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse border border-[#1A1A3A]">
                    <thead class="bg-[#41478B] text-[#FFFFF6] text-left">
                        <tr>
                            <th class="p-2 border border-[#1A1A3A] w-16">#</th>
                            <th class="p-2 border border-[#1A1A3A]">Pemain</th>
                            <th class="p-2 border border-[#1A1A3A]">Skor Tertinggi</th>
                            <th class="p-2 border border-[#1A1A3A]">Waktu Dicapai</th>
                        </tr>
                    </thead>
                    <tbody class="bg-[#1A1A3A]">
                        <?php foreach($topScores as $i => $row): ?>
                            <?php // Juara 1 mendapatkan highlight baris Bright Lavender tipis ?>
                            <tr class="<?= $i === 0 ? 'bg-[#B57DDA]/20 text-[#B57DDA] font-bold' : 'text-[#E8E2D4] border-b border-[#41478B]/30' ?>">
                                <td class="p-2 border border-[#41478B]/50"><?= $i+1 ?></td>
                                <td class="p-2 border border-[#41478B]/50"><?= e($row['player_label']) ?><?= $i === 0 ? ' 👑' : '' ?></td>
                                <td class="p-2 border border-[#41478B]/50"><?= (int)$row['best_score'] ?> poin</td>
                                <td class="p-2 border border-[#41478B]/50 tabular-nums"><?= e($row['achieved_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabel Top 20 Waktu Reaksi Tercepat -->
    <div class="panel-box p-4 mb-4 bg-[#242752] border-2 border-[#1A1A3A] rounded shadow-[4px_4px_0_0_rgba(26,26,58,1)]">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-3 gap-2">
            <h3 class="font-extrabold text-base m-0 text-[#B57DDA]">Top 20 Waktu Reaksi Tercepat</h3>
            <span class="text-xs bg-[#1A1A3A] border border-[#41478B] text-[#AAA0BB] px-2 py-1 rounded">Minimal 10 hit untuk kualifikasi</span>
        </div>
        
        <?php if(empty($topFastest)): ?>
            <p class="text-[#AAA0BB] text-sm">Belum ada reaksi yang memenuhi syarat.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse border border-[#1A1A3A]">
                    <thead class="bg-[#41478B] text-[#FFFFF6] text-left">
                        <tr>
                            <th class="p-2 border border-[#1A1A3A] w-16">#</th>
                            <th class="p-2 border border-[#1A1A3A]">Pemain</th>
                            <th class="p-2 border border-[#1A1A3A]">Rata-rata RT</th>
                            <th class="p-2 border border-[#1A1A3A]">Konsistensi (max−min)</th>
                            <th class="p-2 border border-[#1A1A3A]">Perubahan setelah 5 hit</th>
                        </tr>
                    </thead>
                    <tbody class="bg-[#1A1A3A]">
                        <?php foreach($topFastest as $i => $row): 
                            $change = (int)$row['change_after5_ms'];
                            // Hijau palet kontras untuk lebih cepat, merah lembut untuk melambat
                            $changeColor = $change === 0 ? '' : ($change > 0 ? 'color:#FF9F80' : 'color:#4ADE80');
                            $changeTxt = $change === 0 ? '0 ms' : (($change > 0 ? '+' : '') . $change . ' ms' . ($change > 0 ? ' (melambat)' : ' (lebih cepat)'));
                        ?>
                            <tr class="<?= $i === 0 ? 'bg-[#B57DDA]/20 text-[#B57DDA] font-bold' : 'text-[#E8E2D4] border-b border-[#41478B]/30' ?>">
                                <td class="p-2 border border-[#41478B]/50"><?= $i+1 ?></td>
                                <td class="p-2 border border-[#41478B]/50"><?= e($row['player_label']) ?><?= $i === 0 ? ' ⚡' : '' ?></td>
                                <td class="p-2 border border-[#41478B]/50 tabular-nums"><?= number_format((float)$row['best_reaction'], 0, ',', '.') ?> ms</td>
                                <td class="p-2 border border-[#41478B]/50 tabular-nums"><?= number_format((float)$row['consistency_ms'], 0, ',', '.') ?> ms</td>
                                <td class="p-2 border border-[#41478B]/50 tabular-nums" style="<?= $changeColor ?>"><?= e($changeTxt) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-[#AAA0BB] mt-3 leading-relaxed">
                <strong>Konsistensi</strong>: selisih reaksi tercepat dan terlambat — makin kecil makin stabil.<br>
                <strong>Perubahan setelah 5 hit</strong>: rata-rata 5 hit terakhir dikurangi rata-rata 5 hit pertama.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
