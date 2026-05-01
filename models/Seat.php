<?php
/**
 * Model: Seat (slot pemain dalam room, max 4).
 *
 * Tabel: room_seats (room_id, seat_no, user_id?, is_bot, bot_label?)
 */

class Seat
{
    public const MAX_SEATS = 4;

    /** Pastikan baris seat 1..4 untuk $roomId ada. */
    public static function ensureRows(mysqli $db, int $roomId): void
    {
        for ($i = 1; $i <= self::MAX_SEATS; $i++) {
            $stmt = mysqli_prepare($db,
                "INSERT IGNORE INTO room_seats (room_id, seat_no) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ii', $roomId, $i);
            mysqli_stmt_execute($stmt);
        }
    }

    /** Ambil semua seat + nama user-nya. */
    public static function listForRoom(mysqli $db, int $roomId): array
    {
        self::ensureRows($db, $roomId);
        $stmt = mysqli_prepare($db,
            "SELECT s.seat_no, s.user_id, s.is_bot, s.bot_label,
                    COALESCE(u.nama, '') AS user_name,
                    COALESCE(u.gambar, '') AS user_foto
             FROM room_seats s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.room_id = ?
             ORDER BY s.seat_no ASC");
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['seat_no'] = (int)$r['seat_no'];
            $r['user_id'] = $r['user_id'] !== null ? (int)$r['user_id'] : null;
            $r['is_bot']  = (int)$r['is_bot'];
            $out[] = $r;
        }
        return $out;
    }

    /** Cari seat user di room (jika ada). */
    public static function findUserSeat(mysqli $db, int $roomId, int $userId): ?int
    {
        $stmt = mysqli_prepare($db,
            "SELECT seat_no FROM room_seats WHERE room_id = ? AND user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $roomId, $userId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        return $row ? (int)$row['seat_no'] : null;
    }

    /**
     * Coba duduki seat. Return true kalau berhasil.
     * Gagal jika seat sudah dipakai user/bot lain.
     */
    public static function take(mysqli $db, int $roomId, int $seatNo, int $userId): bool
    {
        if ($seatNo < 1 || $seatNo > self::MAX_SEATS) return false;
        self::ensureRows($db, $roomId);
        // Lepas seat lama user di room ini (kalau pindah)
        self::leaveAll($db, $roomId, $userId);
        // Boleh take seat yang kosong ATAU yang sedang diisi bot (bot tergantikan).
        // Tidak boleh menggeser user lain.
        $stmt = mysqli_prepare($db,
            "UPDATE room_seats
             SET user_id = ?, is_bot = 0, bot_label = NULL
             WHERE room_id = ? AND seat_no = ? AND user_id IS NULL");
        mysqli_stmt_bind_param($stmt, 'iii', $userId, $roomId, $seatNo);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_affected_rows($stmt) > 0;
    }

    /** User keluar dari semua seat di room. */
    public static function leaveAll(mysqli $db, int $roomId, int $userId): void
    {
        $stmt = mysqli_prepare($db,
            "UPDATE room_seats SET user_id = NULL WHERE room_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $roomId, $userId);
        mysqli_stmt_execute($stmt);
    }

    /** Owner mengisi semua seat kosong dengan bot. */
    public static function fillBots(mysqli $db, int $roomId): void
    {
        self::ensureRows($db, $roomId);
        for ($i = 1; $i <= self::MAX_SEATS; $i++) {
            $label = 'BOT' . $i;
            $stmt = mysqli_prepare($db,
                "UPDATE room_seats SET is_bot = 1, bot_label = ?
                 WHERE room_id = ? AND seat_no = ? AND user_id IS NULL AND is_bot = 0");
            mysqli_stmt_bind_param($stmt, 'sii', $label, $roomId, $i);
            mysqli_stmt_execute($stmt);
        }
    }

    /** Owner reset semua bot di room (clear is_bot). */
    public static function clearBots(mysqli $db, int $roomId): void
    {
        $stmt = mysqli_prepare($db,
            "UPDATE room_seats SET is_bot = 0, bot_label = NULL WHERE room_id = ? AND is_bot = 1");
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
    }


    /**
     * Cari seat kosong (tanpa user & tanpa bot) terkecil, lalu duduki utk $userId.
     * Jika semua kursi sudah dipakai user, return null. Bot boleh tergantikan.
     * Return seat_no yang berhasil ditempati, atau null kalau penuh oleh user.
     */
    public static function takeFirstAvailable(mysqli $db, int $roomId, int $userId): ?int
    {
        self::ensureRows($db, $roomId);
        // Sudah duduk? langsung return.
        $existing = self::findUserSeat($db, $roomId, $userId);
        if ($existing !== null) return $existing;

        // Prioritas 1: seat kosong total (no user, no bot)
        $stmt = mysqli_prepare($db,
            "SELECT seat_no FROM room_seats
             WHERE room_id = ? AND user_id IS NULL AND is_bot = 0
             ORDER BY seat_no ASC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($row) {
            $no = (int)$row['seat_no'];
            if (self::take($db, $roomId, $no, $userId)) return $no;
        }

        // Prioritas 2: gantikan bot
        $stmt = mysqli_prepare($db,
            "SELECT seat_no FROM room_seats
             WHERE room_id = ? AND user_id IS NULL AND is_bot = 1
             ORDER BY seat_no ASC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($row) {
            $no = (int)$row['seat_no'];
            // take() menerima seat yang user_id NULL (termasuk yang sedang dipakai bot).
            if (self::take($db, $roomId, $no, $userId)) return $no;
        }
        return null;
    }
    /** Kosongkan semua seat di room (dipanggil saat owner lepas room). */
    public static function clearAll(mysqli $db, int $roomId): void
    {
        $stmt = mysqli_prepare($db,
            "UPDATE room_seats SET user_id = NULL, is_bot = 0, bot_label = NULL WHERE room_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
    }
}
