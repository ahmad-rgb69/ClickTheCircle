<?php
/**
 * Helper CSRF token.
 *
 * Pakai:
 *   <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 *   csrf_check($_POST['csrf_token'] ?? '');  // di file controller
 */

require_once __DIR__ . '/session.php';

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_check(string $token): void {
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        die('CSRF token tidak valid. Refresh halaman lalu coba lagi.');
    }
}
