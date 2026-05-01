# RRR — Versi MVC ringan (file per halaman)

Struktur ini sengaja **TIDAK** pakai router/front controller/middleware/autoload.
Tujuannya cuma satu: **mudah di-debug**.

- Setiap URL = 1 file PHP. Buka langsung di browser. Tidak butuh `mod_rewrite`.
- `models/` cuma class biasa berisi query DB (bisa langsung `var_dump` hasilnya).
- `views/` cuma include PHP yang menampilkan HTML.
- `helpers/` berisi koneksi DB, session, CSRF.

## 🚀 Jalankan di Laragon (paling gampang)

1. Copy folder `rrr_lite/` ke `C:\Users\<kamu>\scoop\persist\laragon\www\rrr_lite`.
2. Import database: buka HeidiSQL → File → Run SQL file → pilih `sql/w.sql`.
3. Buka browser: **`http://localhost/rrr_lite/`** ✅
4. Untuk WebSocket, buka terminal terpisah:
   ```cmd
   cd C:\Users\<kamu>\scoop\persist\laragon\www\rrr_lite
   php ws\chat-server.php
   ```

Tidak perlu virtual host, tidak perlu setting `.htaccess`, tidak perlu URL pretty.

## 📱 Akses dari HP Android di WiFi yang sama

1. Cek IP laptop di Laragon (klik Laragon → menu kanan bawah ada IP, atau cmd `ipconfig`).
2. Allow port **80** (HTTP Apache) dan **8080** (WebSocket) di Windows Firewall:
   ```powershell
   New-NetFirewallRule -DisplayName "RRR HTTP" -Direction Inbound -LocalPort 80   -Protocol TCP -Action Allow
   New-NetFirewallRule -DisplayName "RRR WS"   -Direction Inbound -LocalPort 8080 -Protocol TCP -Action Allow
   ```
3. Di HP buka: `http://192.168.x.x/rrr_lite/` (ganti IP sesuai laptop).
4. WS otomatis konek ke `ws://192.168.x.x:8080` (di-handle `views/header.php`).

## 📁 Struktur

```
rrr_lite/
├── config.php              ← DB host/user/pass, port WS
├── index.php               ← redirect ke login/lobby
│
├── login.php               ← controller: form + proses login
├── register.php            ← controller: form + proses register
├── logout.php
├── lobby.php               ← controller: lobby (chat umum + daftar room)
├── room.php                ← controller: halaman private room (?id=1)
├── room_enter.php          ← cek password & masuk room
├── room_leave.php          ← keluar room
├── room_report.php         ← lapor owner kosong
├── room_cooldown.php       ← mulai cooldown lapor
├── profile_update.php      ← edit profil
├── profile_delete.php      ← hapus akun
│
├── models/
│   ├── User.php            ← class User { findByCredentials, create, update, ... }
│   ├── Room.php            ← class Room { listAll, occupy, releaseIfOwner, ... }
│   └── Message.php         ← class Message { lobbyAll, roomAll }
│
├── views/
│   ├── header.php          ← <html>, <head>, init WS URL global
│   ├── footer.php
│   ├── login.view.php
│   ├── register.view.php
│   ├── lobby.view.php
│   └── room.view.php
│
├── helpers/
│   ├── db.php              ← bikin $db (mysqli)
│   ├── session.php         ← session_start + e() + flash + require_login()
│   └── csrf.php            ← csrf_token() / csrf_check()
│
├── ws/
│   └── chat-server.php     ← Ratchet WebSocket server (jalankan terpisah)
│
├── img/                    ← avatar
├── js/
│   ├── lobby.js
│   └── room.js
├── sql/w.sql               ← import ini sekali di awal
└── vendor/                 ← Composer (Ratchet)
```

## 🐞 Cara debug

Karena 1 URL = 1 file, debugging straight-forward:

| Gejala                              | Buka file                              |
| ----------------------------------- | -------------------------------------- |
| Login gagal terus                   | `login.php` + `models/User.php`        |
| Pesan lobby tidak tampil            | `lobby.php` + `models/Message.php`     |
| Tampilan rusak                      | `views/lobby.view.php`                 |
| Password room dianggap salah        | `room_enter.php` + `models/Room.php`   |
| Realtime tidak jalan                | `ws/chat-server.php` + console browser |
| Koneksi DB error                    | `config.php` + `helpers/db.php`        |

Tinggal taruh `var_dump(...); exit;` di tempat yang dicurigai — output langsung
muncul di browser tanpa perlu mikir lifecycle / dispatcher.

## 🗄️ SQL

`sql/w.sql` sudah otomatis `CREATE DATABASE rrr` + bikin semua tabel +
seed Room 1-5 dengan password `room1`..`room5`.

## ⚠️ Catatan keamanan (sengaja tidak diperketat)

- Login pakai Nama+NPM tanpa password hash (sesuai versi original).
- WS server percaya `user_id` dari klien (bisa di-spoof). OK untuk LAN/dev.
- Untuk produksi: tambahkan password hash + token signed sebelum dipublish.
# ClickTheCircle
"# testing" 
