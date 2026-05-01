<?php
/**
 * Model: User.
 *
 * Semua query yang berhubungan dengan tabel `users` ada di sini.
 * Cara debug: var_dump hasil dari User::find($db, ...) untuk lihat data mentah.
 */

class User
{
    /** Cari user by nama+npm (login). Return array atau null. */
    public static function findByCredentials(mysqli $db, string $nama, string $npm): ?array
    {
        $stmt = mysqli_prepare($db, "SELECT * FROM users WHERE nama = ? AND npm = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $nama, $npm);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        return $row ?: null;
    }

    /** Buat user baru. Return ID baru atau 0 kalau gagal. */
    public static function create(mysqli $db, string $nama, string $npm, string $gambar): int
    {
        $stmt = mysqli_prepare($db, "INSERT INTO users (nama, npm, gambar) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $nama, $npm, $gambar);
        if (!mysqli_stmt_execute($stmt)) return 0;
        return (int)mysqli_insert_id($db);
    }

    /** Update profil. */
    public static function update(mysqli $db, int $id, string $nama, string $npm, string $gambar): bool
    {
        $stmt = mysqli_prepare($db, "UPDATE users SET nama = ?, npm = ?, gambar = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $nama, $npm, $gambar, $id);
        return mysqli_stmt_execute($stmt);
    }

    /** Hapus user. */
    public static function delete(mysqli $db, int $id): bool
    {
        $stmt = mysqli_prepare($db, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        return mysqli_stmt_execute($stmt);
    }

    /** Update presence (lobby/room/offline). */
    public static function updatePresence(mysqli $db, int $id, string $status, ?int $roomId): void
    {
        if ($id <= 0) return;
        $stmt = mysqli_prepare(
            $db,
            "UPDATE users SET presence_status = ?, presence_room_id = ?, presence_last_seen = NOW() WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sii', $status, $roomId, $id);
        mysqli_stmt_execute($stmt);
    }
}
