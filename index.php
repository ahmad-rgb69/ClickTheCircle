<?php
/**
 * Halaman utama. Belum login → ke login. Sudah login → ke lobby.
 */
require __DIR__ . '/helpers/session.php';
header('Location: ' . (empty($_SESSION['login']) ? 'login.php' : 'lobby.php'));
exit;
