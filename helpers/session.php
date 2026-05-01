<?php
/**
 * Helper session + flash message + escape.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $key, string $msg): void {
    $_SESSION['_flash'][$key] = $msg;
}

function flash_pop(string $key): ?string {
    if (!isset($_SESSION['_flash'][$key])) return null;
    $v = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $v;
}

/** Cek user sudah login. Kalau belum → redirect ke login.php. */
function require_login(): void {
    if (empty($_SESSION['login'])) {
        header('Location: login.php');
        exit;
    }
}

/** Cek user belum login (untuk halaman login/register). */
function require_guest(): void {
    if (!empty($_SESSION['login'])) {
        header('Location: lobby.php');
        exit;
    }
}
