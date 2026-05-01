<?php
/**
 * Model: Room (private room 1-5).
 *
 * Tabel: room_status (id, password_room, is_occupied, owner_id, cooldown_until, ...)
 */

class Room
{
    /** Room yang valid. */
    public const VALID_IDS = [1, 2, 3, 4, 5];

    /** Cooldown lapor "owner kosong" dalam detik. */
    public const COOLDOWN_SECONDS = 60;

    /**
     * Mapping ID -> nama room (bukan angka).
     * Tampilan UI memakai nama-nama ini, bukan "Room 1", "Room 2", dst.
     */
    public const NAMES = [
        1 => 'mo koi nante shinai',
        2 => 'saya akan lawan',
        3 => 'hidup jo-',
        4 => 'saya akan kembali ke oslo sebagai rakyat biasa',
        5 => 'akan terbuka 19 juta lapangan bola',
    ];

    /** Ambil nama room berdasarkan ID. Fallback "Room #<id>" kalau tidak ada. */
    public static function nameFor(int $id): string
    {
        return self::NAMES[$id] ?? ('Room #' . $id);
    }

    /** Ambil semua room + nama owner + status cooldown. */
    public static function listAll(mysqli $db): array
    {
        $sql = "SELECT r.id, r.password_room, r.is_occupied, r.owner_id, r.cooldown_until,
                       COALESCE(u.nama, '') AS owner_name
                FROM room_status r
                LEFT JOIN users u ON u.id = r.owner_id
                ORDER BY r.id ASC";
        $res = mysqli_query($db, $sql);
        $rooms = [];
        $now = time();
        while ($row = mysqli_fetch_assoc($res)) {
            $cooldownUntil = $row['cooldown_until'] ? strtotime($row['cooldown_until']) : 0;
            $row['id'] = (int)$row['id'];
            $row['is_occupied'] = (int)$row['is_occupied'];
            $row['is_cooldown'] = $cooldownUntil > $now;
            $row['sisa_detik']  = $row['is_cooldown'] ? ($cooldownUntil - $now) : 0;
            // Sisipkan nama room agar view bisa langsung pakai $room['name'].
            $row['name'] = self::nameFor($row['id']);
            $rooms[] = $row;
        }
        return $rooms;
    }

    public static function find(mysqli $db, int $id): ?array
    {
        $stmt = mysqli_prepare($db, "SELECT * FROM room_status WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        return $row ?: null;
    }

    /** Tandai room ditempati oleh $userId (jadi owner). */
    public static function occupy(mysqli $db, int $roomId, int $userId): void
    {
        $stmt = mysqli_prepare(
            $db,
            "UPDATE room_status SET is_occupied = 1, owner_id = ? WHERE id = ? AND is_occupied = 0"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $roomId);
        mysqli_stmt_execute($stmt);
    }

    /** Lepaskan owner kalau memang dia yang punya. */
    public static function releaseIfOwner(mysqli $db, int $roomId, int $userId): void
    {
        $stmt = mysqli_prepare(
            $db,
            "UPDATE room_status SET is_occupied = 0, owner_id = NULL WHERE id = ? AND owner_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $roomId, $userId);
        mysqli_stmt_execute($stmt);
    }

    /** Reset paksa karena dilaporkan kosong. */
    public static function forceVacant(mysqli $db, int $roomId): bool
    {
        $stmt = mysqli_prepare(
            $db,
            "UPDATE room_status SET is_occupied = 0, owner_id = NULL WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        return mysqli_stmt_execute($stmt);
    }

    /** Mulai cooldown lapor. */
    public static function startCooldown(mysqli $db, int $roomId): void
    {
        $stmt = mysqli_prepare(
            $db,
            "UPDATE room_status SET cooldown_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?"
        );
        $sec = self::COOLDOWN_SECONDS;
        mysqli_stmt_bind_param($stmt, 'ii', $sec, $roomId);
        mysqli_stmt_execute($stmt);
    }
}
