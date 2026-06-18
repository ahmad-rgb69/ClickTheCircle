-- migration_stats.sql
-- Digunakan untuk memperbaiki tabel game_scores yang kurang kolom
-- dan membuat tabel game_reactions untuk menyimpan data leaderboard & statistik

USE rrr;

-- Tambah kolom statistik di game_scores jika belum ada (idempotent)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_scores' AND COLUMN_NAME = 'avg_reaction_ms');
SET @s = IF(@col_exists = 0, 'ALTER TABLE game_scores ADD COLUMN avg_reaction_ms INT NOT NULL DEFAULT 0 AFTER is_winner', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_scores' AND COLUMN_NAME = 'consistency_ms');
SET @s = IF(@col_exists = 0, 'ALTER TABLE game_scores ADD COLUMN consistency_ms INT NOT NULL DEFAULT 0 AFTER avg_reaction_ms', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_scores' AND COLUMN_NAME = 'change_after5_ms');
SET @s = IF(@col_exists = 0, 'ALTER TABLE game_scores ADD COLUMN change_after5_ms INT NOT NULL DEFAULT 0 AFTER consistency_ms', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_scores' AND COLUMN_NAME = 'hits_count');
SET @s = IF(@col_exists = 0, 'ALTER TABLE game_scores ADD COLUMN hits_count INT NOT NULL DEFAULT 0 AFTER change_after5_ms', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Buat tabel game_reactions untuk riwayat reaksi per hit per pemain
CREATE TABLE IF NOT EXISTS game_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    slot TINYINT NOT NULL,
    player_label VARCHAR(32) NOT NULL,
    hit_index INT NOT NULL,
    reaction_ms INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reaction_session (session_id),
    CONSTRAINT fk_reaction_session FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
