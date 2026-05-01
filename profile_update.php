<?php
/** Controller: update profil (nama, npm, avatar via upload ATAU preset). */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_login();
csrf_check($_POST['csrf_token'] ?? '');

$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers/db.php';
require __DIR__ . '/helpers/avatar.php';
require __DIR__ . '/models/User.php';

$ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

$myId    = (int)$_SESSION['id'];
$newNama = trim($_POST['new_nama'] ?? '');
$newNpm  = trim($_POST['new_npm']  ?? '');

if ($newNama === '' || $newNpm === '') {
    flash_set('err', 'Nama dan NPM wajib diisi.');
    header('Location: lobby.php'); exit;
}

// ===== Tentukan sumber avatar =====
// Prioritas:
//   1. Upload file baru (kalau ada)
//   2. Pilihan preset (radio)
//   3. Pertahankan gambar lama
$newGambar  = (string)$_SESSION['gambar'];
$avatarMode = (string)($_POST['avatar_mode'] ?? '');
$upload     = $_FILES['new_gambar'] ?? null;
$err        = $upload['error'] ?? UPLOAD_ERR_NO_FILE;

if ($avatarMode === 'upload' && $err !== UPLOAD_ERR_NO_FILE) {
    if ($err !== UPLOAD_ERR_OK) {
        flash_set('err', 'Upload gambar profil gagal.');
        header('Location: lobby.php'); exit;
    }
    $tmp  = $upload['tmp_name'];
    $size = (int)($upload['size'] ?? 0);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);

    if (!isset($ALLOWED_MIME[$mime]) || $size <= 0 || $size > 2 * 1024 * 1024) {
        flash_set('err', 'Format gambar tidak valid atau > 2MB.');
        header('Location: lobby.php'); exit;
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ALLOWED_MIME[$mime];
    if (!move_uploaded_file($tmp, $config['upload_dir'] . '/' . $name)) {
        flash_set('err', 'Gagal menyimpan gambar profil.');
        header('Location: lobby.php'); exit;
    }
    $newGambar = $name; // disimpan sebagai filename murni (folder img/)

} elseif ($avatarMode === 'preset') {
    $presetFile = trim((string)($_POST['preset_avatar'] ?? ''));
    // Validasi: harus ada di manifest
    $allowed = array_column(avatar_presets_list(), 'file');
    if ($presetFile === '' || !in_array($presetFile, $allowed, true)) {
        flash_set('err', 'Avatar preset tidak valid.');
        header('Location: lobby.php'); exit;
    }
    $newGambar = 'preset:' . $presetFile;
}
// kalau avatar_mode kosong / 'keep' -> $newGambar tetap nilai lama

User::update($db, $myId, $newNama, $newNpm, $newGambar);
$_SESSION['nama']   = $newNama;
$_SESSION['npm']    = $newNpm;
$_SESSION['gambar'] = $newGambar;
flash_set('ok', 'Profil berhasil diperbarui.');
header('Location: lobby.php');
exit;
