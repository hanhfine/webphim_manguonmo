SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS booking_seats;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS showtimes;
DROP TABLE IF EXISTS seats;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS cinemas;
DROP TABLE IF EXISTS movies;

CREATE TABLE movies (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR(150) NOT NULL,
	slug VARCHAR(180) NOT NULL UNIQUE,
	description TEXT NOT NULL,
	genre VARCHAR(80) NOT NULL,
	duration_minutes INT UNSIGNED NOT NULL,
	poster_url VARCHAR(255) NOT NULL DEFAULT '',
	trailer_url VARCHAR(255) NOT NULL DEFAULT '',
	rating VARCHAR(20) NOT NULL DEFAULT 'T13',
	release_date DATE NOT NULL,
	status ENUM('now_showing', 'coming_soon', 'ended') NOT NULL DEFAULT 'now_showing',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cinemas (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(150) NOT NULL,
	address VARCHAR(255) NOT NULL,
	city VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rooms (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	cinema_id INT UNSIGNED NOT NULL,
	name VARCHAR(120) NOT NULL,
	screen_type ENUM('2d', '3d', 'imax') NOT NULL DEFAULT '2d',
	total_rows INT UNSIGNED NOT NULL,
	total_columns INT UNSIGNED NOT NULL,
	CONSTRAINT fk_rooms_cinema FOREIGN KEY (cinema_id) REFERENCES cinemas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seats (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	room_id INT UNSIGNED NOT NULL,
	row_label VARCHAR(5) NOT NULL,
	seat_number INT UNSIGNED NOT NULL,
	seat_type ENUM('normal', 'vip', 'couple') NOT NULL DEFAULT 'normal',
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	UNIQUE KEY uniq_room_seat (room_id, row_label, seat_number),
	CONSTRAINT fk_seats_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE showtimes (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	movie_id INT UNSIGNED NOT NULL,
	room_id INT UNSIGNED NOT NULL,
	start_time DATETIME NOT NULL,
	end_time DATETIME NOT NULL,
	status ENUM('open', 'locked', 'completed', 'cancelled') NOT NULL DEFAULT 'open',
	price_normal DECIMAL(10, 2) NOT NULL DEFAULT 0,
	price_vip DECIMAL(10, 2) NOT NULL DEFAULT 0,
	price_couple DECIMAL(10, 2) NOT NULL DEFAULT 0,
	CONSTRAINT fk_showtimes_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
	CONSTRAINT fk_showtimes_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	booking_code VARCHAR(20) NOT NULL UNIQUE,
	showtime_id INT UNSIGNED NOT NULL,
	customer_name VARCHAR(150) NOT NULL,
	customer_email VARCHAR(150) NOT NULL,
	customer_phone VARCHAR(30) NOT NULL,
	total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
	payment_method ENUM('counter', 'bank_transfer') NOT NULL DEFAULT 'counter',
	payment_status ENUM('pending', 'paid', 'unpaid') NOT NULL DEFAULT 'unpaid',
	booking_status ENUM('confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_booking_email (customer_email),
	CONSTRAINT fk_bookings_showtime FOREIGN KEY (showtime_id) REFERENCES showtimes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_seats (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	booking_id INT UNSIGNED NOT NULL,
	seat_id INT UNSIGNED NOT NULL,
	seat_label VARCHAR(12) NOT NULL,
	seat_type ENUM('normal', 'vip', 'couple') NOT NULL DEFAULT 'normal',
	unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
	UNIQUE KEY uniq_booking_seat (booking_id, seat_id),
	CONSTRAINT fk_booking_seats_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
	CONSTRAINT fk_booking_seats_seat FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO movies (
	title,
	slug,
	description,
	genre,
	duration_minutes,
	poster_url,
	trailer_url,
	rating,
	release_date,
	status
) VALUES
('Midnight Saigon', 'midnight-saigon', 'Mot dem truy duoi xuyen trung tam thanh pho, noi moi quyet dinh deu den trong vai giay.', 'Thriller', 118, 'assets/images/poster-midnight.svg', 'https://www.youtube.com/watch?v=midnightsaigon', 'T18', DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'now_showing'),
('Mua He Tren Tang Thuong', 'mua-he-tren-tang-thuong', 'Bo phim tinh cam do thi ve mot mua he thay doi cach nhin cua hai nguoi tre giua Sai Gon.', 'Drama', 102, 'assets/images/poster-summer.svg', 'https://www.youtube.com/watch?v=muahesummer', 'T13', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'now_showing'),
('Cuoc Dua Sao Bang', 'cuoc-dua-sao-bang', 'Su kien hanh dong IMAX noi mot phi hanh doan lao vao duong dua ngoai khong gian.', 'Action', 126, 'assets/images/poster-orbit.svg', 'https://www.youtube.com/watch?v=cuocduasaobang', 'T16', DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'coming_soon');

INSERT INTO cinemas (name, address, city) VALUES
('Lumiere Nguyen Hue', '12 Nguyen Hue', 'TP HCM'),
('Starlight Riverside', '220 Vo Van Kiet', 'TP HCM');

INSERT INTO rooms (cinema_id, name, screen_type, total_rows, total_columns) VALUES
(1, 'Sala 1', '2d', 5, 8),
(2, 'Sala Premium', 'imax', 5, 8);

INSERT INTO seats (room_id, row_label, seat_number, seat_type, is_active) VALUES
(1, 'A', 1, 'normal', 1),
(1, 'A', 2, 'normal', 1),
(1, 'A', 3, 'normal', 1),
(1, 'A', 4, 'normal', 1),
(1, 'A', 5, 'normal', 1),
(1, 'A', 6, 'normal', 1),
(1, 'A', 7, 'normal', 1),
(1, 'A', 8, 'normal', 1),
(1, 'B', 1, 'normal', 1),
(1, 'B', 2, 'normal', 1),
(1, 'B', 3, 'normal', 1),
(1, 'B', 4, 'normal', 0),
(1, 'B', 5, 'normal', 1),
(1, 'B', 6, 'normal', 1),
(1, 'B', 7, 'normal', 1),
(1, 'B', 8, 'normal', 1),
(1, 'C', 1, 'vip', 1),
(1, 'C', 2, 'vip', 1),
(1, 'C', 3, 'vip', 1),
(1, 'C', 4, 'vip', 1),
(1, 'C', 5, 'vip', 1),
(1, 'C', 6, 'vip', 1),
(1, 'C', 7, 'vip', 1),
(1, 'C', 8, 'vip', 1),
(1, 'D', 1, 'normal', 1),
(1, 'D', 2, 'normal', 1),
(1, 'D', 3, 'normal', 1),
(1, 'D', 4, 'normal', 1),
(1, 'D', 5, 'normal', 1),
(1, 'D', 6, 'normal', 1),
(1, 'D', 7, 'couple', 1),
(1, 'D', 8, 'couple', 1),
(1, 'E', 1, 'normal', 1),
(1, 'E', 2, 'normal', 1),
(1, 'E', 3, 'normal', 1),
(1, 'E', 4, 'normal', 1),
(1, 'E', 5, 'normal', 1),
(1, 'E', 6, 'normal', 1),
(1, 'E', 7, 'couple', 1),
(1, 'E', 8, 'couple', 1),
(2, 'A', 1, 'normal', 1),
(2, 'A', 2, 'normal', 1),
(2, 'A', 3, 'normal', 1),
(2, 'A', 4, 'normal', 1),
(2, 'A', 5, 'normal', 1),
(2, 'A', 6, 'normal', 1),
(2, 'A', 7, 'normal', 1),
(2, 'A', 8, 'normal', 1),
(2, 'B', 1, 'normal', 1),
(2, 'B', 2, 'normal', 1),
(2, 'B', 3, 'normal', 1),
(2, 'B', 4, 'normal', 1),
(2, 'B', 5, 'normal', 1),
(2, 'B', 6, 'normal', 1),
(2, 'B', 7, 'normal', 1),
(2, 'B', 8, 'normal', 1),
(2, 'C', 1, 'vip', 1),
(2, 'C', 2, 'vip', 1),
(2, 'C', 3, 'vip', 1),
(2, 'C', 4, 'vip', 1),
(2, 'C', 5, 'vip', 1),
(2, 'C', 6, 'vip', 1),
(2, 'C', 7, 'vip', 1),
(2, 'C', 8, 'vip', 1),
(2, 'D', 1, 'normal', 1),
(2, 'D', 2, 'normal', 1),
(2, 'D', 3, 'normal', 1),
(2, 'D', 4, 'normal', 1),
(2, 'D', 5, 'normal', 1),
(2, 'D', 6, 'normal', 1),
(2, 'D', 7, 'couple', 1),
(2, 'D', 8, 'couple', 1),
(2, 'E', 1, 'normal', 1),
(2, 'E', 2, 'normal', 1),
(2, 'E', 3, 'normal', 1),
(2, 'E', 4, 'normal', 1),
(2, 'E', 5, 'normal', 1),
(2, 'E', 6, 'normal', 1),
(2, 'E', 7, 'couple', 1),
(2, 'E', 8, 'couple', 1);

SET @day1 = DATE_ADD(CURDATE(), INTERVAL 1 DAY);
SET @day2 = DATE_ADD(CURDATE(), INTERVAL 2 DAY);
SET @day3 = DATE_ADD(CURDATE(), INTERVAL 3 DAY);

INSERT INTO showtimes (
	movie_id,
	room_id,
	start_time,
	end_time,
	status,
	price_normal,
	price_vip,
	price_couple
) VALUES
(1, 1, TIMESTAMP(@day1, '19:00:00'), TIMESTAMP(@day1, '20:58:00'), 'open', 90000, 120000, 170000),
(1, 2, TIMESTAMP(@day2, '20:15:00'), TIMESTAMP(@day2, '22:13:00'), 'open', 110000, 150000, 190000),
(2, 1, TIMESTAMP(@day1, '15:30:00'), TIMESTAMP(@day1, '17:12:00'), 'open', 80000, 110000, 150000),
(2, 2, TIMESTAMP(@day3, '18:45:00'), TIMESTAMP(@day3, '20:27:00'), 'open', 95000, 130000, 170000),
(3, 2, TIMESTAMP(@day3, '21:00:00'), TIMESTAMP(@day3, '23:06:00'), 'open', 120000, 160000, 200000);

INSERT INTO bookings (
	booking_code,
	showtime_id,
	customer_name,
	customer_email,
	customer_phone,
	total_amount,
	payment_method,
	payment_status,
	booking_status,
	created_at
) VALUES
('CBDEMO1', 1, 'Tran Minh', 'demo1@example.com', '0909000001', 240000, 'bank_transfer', 'paid', 'confirmed', NOW()),
('CBDEMO2', 3, 'Le Ha', 'demo2@example.com', '0909000002', 80000, 'counter', 'unpaid', 'confirmed', NOW());

INSERT INTO booking_seats (booking_id, seat_id, seat_label, seat_type, unit_price) VALUES
(1, 19, 'C3', 'vip', 120000),
(1, 20, 'C4', 'vip', 120000),
(2, 1, 'A1', 'normal', 80000);

SET FOREIGN_KEY_CHECKS = 1;
