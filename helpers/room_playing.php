<?php
/**
 * Helper status "room sedang bermain".
 *
 * Disimpan sebagai flag file di sys_get_temp_dir() supaya proses HTTP
 * (room_enter.php) dan proses WS (ws/chat-server.php) bisa share state
 * tanpa perlu kolom DB baru.
 *
 * File: {tmp}/rrr_room_playing_<roomId>.flag
 *  - ada  -> game sedang berlangsung
 *  - tidak ada -> idle / belum mulai / sudah selesai
 */

if (!function_exists('room_playing_flag_path')) {
    function room_playing_flag_path(int $roomId): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'rrr_room_playing_' . $roomId . '.flag';
    }

    function room_playing_set(int $roomId): void
    {
        @file_put_contents(room_playing_flag_path($roomId), (string)time());
    }

    function room_playing_clear(int $roomId): void
    {
        $p = room_playing_flag_path($roomId);
        if (is_file($p)) @unlink($p);
    }

    function room_is_playing(int $roomId): bool
    {
        $p = room_playing_flag_path($roomId);
        if (!is_file($p)) return false;
        // Anggap stale & auto-clear kalau lebih lama dari 30 menit (game pasti sudah selesai).
        $mtime = (int)@filemtime($p);
        if ($mtime > 0 && (time() - $mtime) > 1800) {
            @unlink($p);
            return false;
        }
        return true;
    }
}
