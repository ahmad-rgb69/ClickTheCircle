<?php
/**
 * Helper: resolve URL avatar dari nilai kolom `users.gambar`.
 *
 * Konvensi nilai yang disimpan di DB:
 *   - "preset:avatar-3.png"  -> assets/avatars/avatar-3.png  (dipilih dari preset)
 *   - "namarand.png"         -> img/namarand.png             (hasil upload user)
 *   - ""                     -> img/default.png (fallback)
 *
 * Dipakai oleh semua view yang menampilkan foto user.
 */

if (!function_exists('avatar_url')) {
    function avatar_url(?string $gambar): string {
        $g = trim((string)$gambar);
        if ($g === '') return 'img/default.png';
        if (strncmp($g, 'preset:', 7) === 0) {
            $f = substr($g, 7);
            // sanitasi: hanya nama file polos (avatar-N.png / png/jpg/webp)
            if (preg_match('/^[A-Za-z0-9._-]+$/', $f)) {
                return 'assets/avatars/' . $f;
            }
            return 'img/default.png';
        }
        // upload lama / nilai bebas -> folder img/
        if (preg_match('/^[A-Za-z0-9._-]+$/', $g)) {
            return 'img/' . $g;
        }
        return 'img/default.png';
    }
}

if (!function_exists('avatar_is_preset')) {
    function avatar_is_preset(?string $gambar): bool {
        return is_string($gambar) && strncmp($gambar, 'preset:', 7) === 0;
    }
}

if (!function_exists('avatar_preset_file')) {
    /** Return nama file preset (avatar-3.png) atau '' kalau bukan preset. */
    function avatar_preset_file(?string $gambar): string {
        return avatar_is_preset($gambar) ? substr((string)$gambar, 7) : '';
    }
}

if (!function_exists('avatar_presets_list')) {
    /** Baca manifest preset, return array of ['file'=>..., 'name'=>...]. */
    function avatar_presets_list(): array {
        $path = __DIR__ . '/../assets/avatars/manifest.json';
        if (!is_file($path)) return [];
        $j = json_decode((string)file_get_contents($path), true);
        $items = (is_array($j) && isset($j['items']) && is_array($j['items'])) ? $j['items'] : [];
        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $file = (string)($it['file'] ?? '');
            if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) continue;
            $out[] = [
                'file' => $file,
                'name' => (string)($it['name'] ?? $file),
            ];
        }
        return $out;
    }
}
