CREATE DATABASE IF NOT EXISTS rrr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rrr;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    npm VARCHAR(50) NOT NULL,
    gambar VARCHAR(255) NOT NULL,
    presence_status ENUM('offline','lobby','room') NOT NULL DEFAULT 'offline',
    presence_room_id INT NULL,
    presence_last_seen DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pesan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    isi_pesan TEXT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pesan_user_id (user_id),
    CONSTRAINT fk_pesan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pesan_room (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL DEFAULT 1,
    user_id INT NOT NULL,
    isi_pesan TEXT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pesan_room_room_id (room_id),
    INDEX idx_pesan_room_user_id (user_id),
    CONSTRAINT fk_pesan_room_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS room_status (
    id INT PRIMARY KEY,
    password_room VARCHAR(255) NOT NULL,
    is_occupied TINYINT(1) NOT NULL DEFAULT 0,
    owner_id INT NULL,
    last_reset_attempt DATETIME NULL,
    cooldown_until DATETIME NULL,
    INDEX idx_room_owner (owner_id),
    CONSTRAINT fk_room_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

SET @room_id_col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pesan_room'
      AND COLUMN_NAME = 'room_id'
);
SET @sql_room_id = IF(
    @room_id_col_exists = 0,
    'ALTER TABLE pesan_room ADD COLUMN room_id INT NOT NULL DEFAULT 1 AFTER id',
    'SELECT 1'
);
PREPARE stmt_room_id FROM @sql_room_id;
EXECUTE stmt_room_id;
DEALLOCATE PREPARE stmt_room_id;

INSERT INTO room_status (id, password_room, is_occupied, owner_id, last_reset_attempt) VALUES
    (1, 'room1', 0, NULL, NULL),
    (2, 'room2', 0, NULL, NULL),
    (3, 'room3', 0, NULL, NULL),
    (4, 'room4', 0, NULL, NULL),
    (5, 'room5', 0, NULL, NULL)
ON DUPLICATE KEY UPDATE
    password_room = VALUES(password_room);

SET @updated_at_col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'updated_at'
);
SET @sql_updated_at = IF(
    @updated_at_col_exists = 0,
    'ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt_updated_at FROM @sql_updated_at;
EXECUTE stmt_updated_at;
DEALLOCATE PREPARE stmt_updated_at;

-- Cooldown per-room (safe migrations)
SET @cooldown_until_col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'room_status'
      AND COLUMN_NAME = 'cooldown_until'
);
SET @sql_cooldown_until = IF(
    @cooldown_until_col_exists = 0,
    'ALTER TABLE room_status ADD COLUMN cooldown_until DATETIME NULL AFTER last_reset_attempt',
    'SELECT 1'
);
PREPARE stmt_cooldown_until FROM @sql_cooldown_until;
EXECUTE stmt_cooldown_until;
DEALLOCATE PREPARE stmt_cooldown_until;

-- Presence columns (safe migrations)
SET @presence_status_col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'presence_status'
);
SET @sql_presence_status = IF(
    @presence_status_col_exists = 0,
    "ALTER TABLE users ADD COLUMN presence_status ENUM('offline','lobby','room') NOT NULL DEFAULT 'offline' AFTER gambar",
    'SELECT 1'
);
PREPARE stmt_presence_status FROM @sql_presence_status;
EXECUTE stmt_presence_status;
DEALLOCATE PREPARE stmt_presence_status;

SET @presence_room_id_col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'presence_room_id'
);
SET @sql_presence_room_id = IF(
    @presence_room_id_col_exists = 0,
    "ALTER TABLE users ADD COLUMN presence_room_id INT NULL AFTER presence_status",
    'SELECT 1'
);
PREPARE stmt_presence_room_id FROM @sql_presence_room_id;
EXECUTE stmt_presence_room_id;
DEALLOCATE PREPARE stmt_presence_room_id;

SET @presence_last_seen_col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'presence_last_seen'
);
SET @sql_presence_last_seen = IF(
    @presence_last_seen_col_exists = 0,
    "ALTER TABLE users ADD COLUMN presence_last_seen DATETIME NULL AFTER presence_room_id",
    'SELECT 1'
);
PREPARE stmt_presence_last_seen FROM @sql_presence_last_seen;
EXECUTE stmt_presence_last_seen;
DEALLOCATE PREPARE stmt_presence_last_seen;
-- Migration: tambahan untuk gameplay CL!CK THE CIRCLE
USE rrr;

-- Tambah kolom difficulty + game_active di room_status (idempotent)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_status' AND COLUMN_NAME='difficulty');
SET @s := IF(@c=0,
  "ALTER TABLE room_status ADD COLUMN difficulty ENUM('normal','hard') NOT NULL DEFAULT 'normal' AFTER cooldown_until",
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_status' AND COLUMN_NAME='difficulty_updated_at');
SET @s := IF(@c=0,
  "ALTER TABLE room_status ADD COLUMN difficulty_updated_at DATETIME NULL AFTER difficulty",
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Sesi game
CREATE TABLE IF NOT EXISTS game_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    owner_id INT NULL,
    difficulty ENUM('normal','hard') NOT NULL DEFAULT 'normal',
    num_players TINYINT NOT NULL DEFAULT 1,
    duration_sec INT NOT NULL DEFAULT 90,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    INDEX idx_gs_room (room_id),
    INDEX idx_gs_owner (owner_id),
    CONSTRAINT fk_gs_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Skor per slot pemain (hot-seat: nama bisa P1..P4 / nama user owner)
CREATE TABLE IF NOT EXISTS game_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    slot TINYINT NOT NULL,
    player_label VARCHAR(32) NOT NULL,
    score INT NOT NULL DEFAULT 0,
    is_winner TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_score_session (session_id),
    CONSTRAINT fk_score_session FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
