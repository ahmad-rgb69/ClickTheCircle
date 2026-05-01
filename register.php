<?php
/**
 * Controller: Register.
 *
 * GET  → tampilkan form
 * POST → upload foto + simpan user, redirect ke login
 */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_guest();

$ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? '');

    $config = require __DIR__ . '/config.php';
    require __DIR__ . '/helpers/db.php';   // $db
    require __DIR__ . '/models/User.php';

    $nama   = trim($_POST['nama'] ?? '');
    $npm    = trim($_POST['npm']  ?? '');
    $upload = $_FILES['gambar'] ?? null;
    $err    = $upload['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($nama === '' || $npm === '') {
        $error = 'Nama dan NPM wajib diisi.';
    } elseif ($err !== UPLOAD_ERR_OK) {
        $error = 'Upload gambar gagal.';
    } else {
        $tmp  = $upload['tmp_name'];
        $size = (int)($upload['size'] ?? 0);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
        if ($finfo) finfo_close($finfo);

        if (!isset($ALLOWED_MIME[$mime]) || $size <= 0 || $size > 2 * 1024 * 1024) {
            $error = 'Format gambar tidak valid atau ukuran > 2MB.';
        } else {
            $name = bin2hex(random_bytes(16)) . '.' . $ALLOWED_MIME[$mime];
            $dest = $config['upload_dir'] . '/' . $name;

            if (!move_uploaded_file($tmp, $dest)) {
                $error = 'Gagal menyimpan gambar.';
            } elseif (User::create($db, $nama, $npm, $name) === 0) {
                $error = 'Gagal mendaftar.';
            } else {
                flash_set('ok', 'Akun berhasil dibuat. Silakan login.');
                header('Location: login.php');
                exit;
            }
        }
    }
}

include __DIR__ . '/views/register.view.php';
