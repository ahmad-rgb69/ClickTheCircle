-- Migration: tabel reaksi per-collection untuk statistik gameplay
-- Jalankan setelah migration_game.sql
USE rrr;

CREATE TABLE IF NOT EXISTS game_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    slot TINYINT NOT NULL,
    player_label VARCHAR(32) NOT NULL,
    hit_index INT NOT NULL,            -- urutan koleksi pemain ini (1,2,3,...)
    reaction_ms INT NOT NULL,          -- waktu antara box spawn -> diambil (ms)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_react_session (session_id),
    INDEX idx_react_session_slot (session_id, slot),
    CONSTRAINT fk_react_session FOREIGN KEY (session_id)
        REFERENCES game_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cache ringkasan langsung di game_scores (idempotent) supaya halaman history cepat
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='game_scores' AND COLUMN_NAME='avg_reaction_ms');
SET @s := IF(@c=0,
  "ALTER TABLE game_scores ADD COLUMN avg_reaction_ms INT NOT NULL DEFAULT 0 AFTER is_winner",
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='game_scores' AND COLUMN_NAME='consistency_ms');
SET @s := IF(@c=0,
  "ALTER TABLE game_scores ADD COLUMN consistency_ms INT NOT NULL DEFAULT 0 AFTER avg_reaction_ms",
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='game_scores' AND COLUMN_NAME='change_after5_ms');
SET @s := IF(@c=0,
  "ALTER TABLE game_scores ADD COLUMN change_after5_ms INT NOT NULL DEFAULT 0 AFTER consistency_ms",
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='game_scores' AND COLUMN_NAME='hits_count');
SET @s := IF(@c=0,
  "ALTER TABLE game_scores ADD COLUMN hits_count INT NOT NULL DEFAULT 0 AFTER change_after5_ms",
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
