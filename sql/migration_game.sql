-- Migration: tambahan untuk gameplay CL!CK THE CIRCLE
USE rrr;

-- Tambah kolom difficulty + game_active di room_status (idempotent)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_status' AND COLUMN_NAME='difficulty');
SET @s := IF(@c=0,
  "ALTER TABLE room_status ADD COLUMN difficulty ENUM('easy','normal','hard','indonesian') NOT NULL DEFAULT 'normal' AFTER cooldown_until",
  "ALTER TABLE room_status MODIFY COLUMN difficulty ENUM('easy','normal','hard','indonesian') NOT NULL DEFAULT 'normal'");
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
    difficulty ENUM('easy','normal','hard','indonesian') NOT NULL DEFAULT 'normal',
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
