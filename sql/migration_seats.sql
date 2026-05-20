-- Migration: seats per room untuk multi-device gameplay
USE rrr;

CREATE TABLE IF NOT EXISTS room_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    seat_no TINYINT NOT NULL,
    user_id INT NULL,
    is_bot TINYINT(1) NOT NULL DEFAULT 0,
    bot_label VARCHAR(16) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_room_seat (room_id, seat_no),
    UNIQUE KEY uniq_room_user (room_id, user_id),
    INDEX idx_seats_room (room_id),
    CONSTRAINT fk_seat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Pre-create 4 seat slot per room (id 1..5)
INSERT IGNORE INTO room_seats (room_id, seat_no) VALUES
 (1,1),(1,2),(1,3),(1,4),
 (2,1),(2,2),(2,3),(2,4),
 (3,1),(3,2),(3,3),(3,4),
 (4,1),(4,2),(4,3),(4,4),
 (5,1),(5,2),(5,3),(5,4);
