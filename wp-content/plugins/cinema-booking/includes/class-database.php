<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Database {
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate     = $wpdb->get_charset_collate();
		$seats_table         = $wpdb->prefix . 'cinema_seats';
		$seat_bookings_table = $wpdb->prefix . 'cinema_seat_bookings';
		$payments_table      = $wpdb->prefix . 'cinema_payments';

		$sql = "
		CREATE TABLE {$seats_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_id BIGINT UNSIGNED NOT NULL,
			row_label VARCHAR(5) NOT NULL,
			seat_number INT UNSIGNED NOT NULL,
			seat_type ENUM('normal','vip','couple') NOT NULL DEFAULT 'normal',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY room_seat_unique (room_id, row_label, seat_number),
			KEY room_id (room_id),
			KEY seat_type (seat_type)
		) {$charset_collate};

		CREATE TABLE {$seat_bookings_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			showtime_id BIGINT UNSIGNED NOT NULL,
			seat_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			booking_code VARCHAR(20) DEFAULT '',
			status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
			locked_at DATETIME NULL,
			confirmed_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY showtime_seat_unique (showtime_id, seat_id),
			KEY user_id (user_id),
			KEY booking_code (booking_code),
			KEY status (status)
		) {$charset_collate};

		CREATE TABLE {$payments_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_ids LONGTEXT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
			payment_status ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
			transaction_id VARCHAR(100) DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY payment_method (payment_method),
			KEY payment_status (payment_status),
			KEY transaction_id (transaction_id)
		) {$charset_collate};
		";

		dbDelta($sql);
	}
}
