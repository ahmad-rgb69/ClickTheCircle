<?php
/**
 * Konfigurasi terpusat. Ubah di sini saja.
 *
 * Dipakai oleh: helpers/db.php, ws/chat-server.php, views/header.php
 */

return [
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'rrr',
    ],

    // Port WebSocket. Server WS dijalankan terpisah:  php ws/chat-server.php
    'ws_port' => 8080,

    // Folder upload avatar (relatif terhadap project root).
    'upload_dir' => __DIR__ . '/img',

    'timezone' => 'Asia/Jakarta',
];
