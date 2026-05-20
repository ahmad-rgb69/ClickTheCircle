<?php
/**
 * Helper: koneksi database mysqli.
 *
 * Cara debug:
 *   - Kalau koneksi gagal, halaman langsung mati dengan pesan jelas.
 *   - Tambahkan var_dump($db) di file pemanggil kalau perlu cek state.
 */

$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

$db = mysqli_connect(
    $config['db']['host'],
    $config['db']['user'],
    $config['db']['pass'],
    $config['db']['name']
);

if (!$db) {
    die(
        '<h2>Koneksi database gagal</h2>'
        . '<p>' . htmlspecialchars(mysqli_connect_error()) . '</p>'
        . '<p>Pastikan database <code>' . htmlspecialchars($config['db']['name']) . '</code> '
        . 'sudah dibuat. Import <code>sql/w.sql</code> dulu via HeidiSQL/phpMyAdmin.</p>'
    );
}

mysqli_set_charset($db, 'utf8mb4');

// $db tersedia untuk file yang require helper ini.
