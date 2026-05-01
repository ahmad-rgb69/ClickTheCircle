<?php
/**
 * Model: Pesan (lobby + private room).
 *
 * Tabel: pesan (lobby), pesan_room (private)
 */

class Message
{
    /** Ambil semua pesan lobby + foto pengirim. */
    public static function lobbyAll(mysqli $db): mysqli_result
    {
        $sql = "SELECT p.isi_pesan, u.nama, u.gambar
                FROM pesan p
                JOIN users u ON u.id = p.user_id
                ORDER BY p.id ASC";
        return mysqli_query($db, $sql);
    }

    /** Ambil semua pesan private untuk satu room. */
    public static function roomAll(mysqli $db, int $roomId): mysqli_result
    {
        $stmt = mysqli_prepare(
            $db,
            "SELECT pr.isi_pesan, u.nama, u.gambar
             FROM pesan_room pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.room_id = ?
             ORDER BY pr.id ASC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }
}
