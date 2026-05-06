<?php
/**
 * Controller: Login.
 *
 * GET  → tampilkan form
 * POST → cek credential, set session, redirect ke lobby
 */
require __DIR__ . '/helpers/session.php';
require __DIR__ . '/helpers/csrf.php';
require_guest();

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? '');

    require __DIR__ . '/helpers/db.php';   // $db
    require __DIR__ . '/models/User.php';

    $nama = trim($_POST['nama'] ?? '');
    $npm  = trim($_POST['npm']  ?? '');
    $user = User::findByCredentials($db, $nama, $npm);

    if ($user) {
        session_regenerate_id(true);
        $_SESSION['login']  = true;
        $_SESSION['id']     = (int)$user['id'];
        $_SESSION['nama']   = $user['nama'];
        $_SESSION['npm']    = $user['npm'];
        $_SESSION['gambar'] = $user['gambar'];

        User::updatePresence($db, (int)$user['id'], 'lobby', null);
        header('Location: lobby.php');
        exit;
    }
    $error = true;
}

include __DIR__ . '/views/login.view.php';
