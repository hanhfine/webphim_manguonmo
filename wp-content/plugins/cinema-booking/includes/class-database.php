<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Database {
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// ---------------------------------------------------------------
		// Core domain tables (100% custom — no WordPress post types)
		// ---------------------------------------------------------------

		$movies_table = $wpdb->prefix . 'cinema_movies';
		$sql_movies   = "CREATE TABLE {$movies_table} (
			id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
			title            VARCHAR(255) NOT NULL DEFAULT '',
			slug             VARCHAR(255) NOT NULL DEFAULT '',
			description      LONGTEXT,
			genre            VARCHAR(100) NOT NULL DEFAULT '',
			duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			poster_url       VARCHAR(500) NOT NULL DEFAULT '',
			trailer_url      VARCHAR(500) NOT NULL DEFAULT '',
			director         VARCHAR(255) NOT NULL DEFAULT '',
			cast_list        TEXT,
			rating           VARCHAR(20) NOT NULL DEFAULT '',
			review_score     DECIMAL(3,1) NOT NULL DEFAULT 0.0,
			release_date     DATE NULL DEFAULT NULL,
			end_date         DATE NULL DEFAULT NULL,
			status           ENUM('now_showing','coming_soon','ended') NOT NULL DEFAULT 'now_showing',
			created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY release_date (release_date)
		) {$charset_collate};";

		$rooms_table = $wpdb->prefix . 'cinema_rooms';
		$sql_rooms   = "CREATE TABLE {$rooms_table} (
			id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name            VARCHAR(100) NOT NULL DEFAULT '',
			type            ENUM('2d','3d','imax') NOT NULL DEFAULT '2d',
			total_rows      TINYINT UNSIGNED NOT NULL DEFAULT 0,
			total_columns   TINYINT UNSIGNED NOT NULL DEFAULT 0,
			vip_rows        VARCHAR(100) NOT NULL DEFAULT '',
			couple_rows     VARCHAR(100) NOT NULL DEFAULT '',
			vip_seats       TEXT,
			couple_seats    TEXT,
			inactive_seats  TEXT,
			created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$showtimes_table = $wpdb->prefix . 'cinema_showtimes';
		$sql_showtimes   = "CREATE TABLE {$showtimes_table} (
			id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
			movie_id       INT UNSIGNED NOT NULL,
			room_id        INT UNSIGNED NOT NULL,
			start_datetime DATETIME NOT NULL,
			end_datetime   DATETIME NULL DEFAULT NULL,
			status         ENUM('open','locked','completed','cancelled') NOT NULL DEFAULT 'open',
			price_normal   DECIMAL(12,0) NOT NULL DEFAULT 0,
			price_vip      DECIMAL(12,0) NOT NULL DEFAULT 0,
			price_couple   DECIMAL(12,0) NOT NULL DEFAULT 0,
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY movie_id (movie_id),
			KEY room_id (room_id),
			KEY start_datetime (start_datetime),
			KEY status (status),
			KEY movie_status_start (movie_id, status, start_datetime)
		) {$charset_collate};";

		$bookings_table = $wpdb->prefix . 'cinema_bookings';
		$sql_bookings   = "CREATE TABLE {$bookings_table} (
			id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_code   VARCHAR(20) NOT NULL DEFAULT '',
			showtime_id    INT UNSIGNED NOT NULL,
			user_id        BIGINT UNSIGNED NOT NULL DEFAULT 0,
			customer_name  VARCHAR(255) NOT NULL DEFAULT '',
			customer_email VARCHAR(255) NOT NULL DEFAULT '',
			customer_phone VARCHAR(30) NOT NULL DEFAULT '',
			total_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
			booking_status ENUM('confirmed','cancelled','refunded') NOT NULL DEFAULT 'confirmed',
			payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
			booked_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_code (booking_code),
			KEY showtime_id (showtime_id),
			KEY user_id (user_id),
			KEY customer_email (customer_email),
			KEY booking_status (booking_status)
		) {$charset_collate};";

		// ---------------------------------------------------------------
		// Seat tables (already existed, keep as-is — compatible)
		// ---------------------------------------------------------------

		$seats_table = $wpdb->prefix . 'cinema_seats';
		$sql_seats   = "CREATE TABLE {$seats_table} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_id     BIGINT UNSIGNED NOT NULL,
			row_label   VARCHAR(5) NOT NULL,
			seat_number INT UNSIGNED NOT NULL,
			seat_type   ENUM('normal','vip','couple') NOT NULL DEFAULT 'normal',
			is_active   TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY room_seat_unique (room_id, row_label, seat_number),
			KEY room_id (room_id),
			KEY seat_type (seat_type)
		) {$charset_collate};";

		$seat_bookings_table = $wpdb->prefix . 'cinema_seat_bookings';
		$sql_seat_bookings   = "CREATE TABLE {$seat_bookings_table} (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			showtime_id  BIGINT UNSIGNED NOT NULL,
			seat_id      BIGINT UNSIGNED NOT NULL,
			user_id      BIGINT UNSIGNED NOT NULL,
			booking_code VARCHAR(20) NOT NULL DEFAULT '',
			status       ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
			locked_at    DATETIME NULL DEFAULT NULL,
			confirmed_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY showtime_seat_unique (showtime_id, seat_id),
			KEY user_id (user_id),
			KEY booking_code (booking_code),
			KEY status (status)
		) {$charset_collate};";

		$payments_table = $wpdb->prefix . 'cinema_payments';
		$sql_payments   = "CREATE TABLE {$payments_table} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_ids    LONGTEXT NULL,
			user_id        BIGINT UNSIGNED NOT NULL,
			total_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
			payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
			payment_status ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
			transaction_id VARCHAR(100) NOT NULL DEFAULT '',
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY payment_method (payment_method),
			KEY payment_status (payment_status),
			KEY transaction_id (transaction_id)
		) {$charset_collate};";

		dbDelta($sql_movies);
		dbDelta($sql_rooms);
		dbDelta($sql_showtimes);
		dbDelta($sql_bookings);
		dbDelta($sql_seats);
		dbDelta($sql_seat_bookings);
		dbDelta($sql_payments);

		update_option('cinema_booking_db_version', '2.0.0');
	}

	/**
	 * Run after install() to migrate existing wp_posts data.
	 */
	public static function migrate_from_post_types() {
		global $wpdb;

		$movies_table    = $wpdb->prefix . 'cinema_movies';
		$rooms_table     = $wpdb->prefix . 'cinema_rooms';
		$showtimes_table = $wpdb->prefix . 'cinema_showtimes';
		$bookings_table  = $wpdb->prefix . 'cinema_bookings';

		// ----- Migrate Movies -----
		$existing_movies = $wpdb->get_col("SELECT id FROM {$movies_table}");
		$wp_movies       = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_name, p.post_content
			FROM {$wpdb->posts} p
			WHERE p.post_type = 'movie' AND p.post_status = 'publish'",
			ARRAY_A
		);

		$movie_id_map = array(); // old wp post ID → new cinema_movies ID

		foreach ($wp_movies as $m) {
			$post_id = (int) $m['ID'];
			$meta    = array(
				'duration_minutes' => (int) get_post_meta($post_id, '_cinema_duration_minutes', true),
				'poster_url'       => (string) get_post_meta($post_id, '_cinema_poster_url', true),
				'trailer_url'      => (string) get_post_meta($post_id, '_cinema_trailer_url', true),
				'director'         => (string) get_post_meta($post_id, '_cinema_director', true),
				'cast_list'        => (string) get_post_meta($post_id, '_cinema_cast', true),
				'rating'           => (string) get_post_meta($post_id, '_cinema_rating', true),
				'review_score'     => (float) get_post_meta($post_id, '_cinema_review_score', true),
				'release_date'     => (string) get_post_meta($post_id, '_cinema_release_date', true),
				'end_date'         => (string) get_post_meta($post_id, '_cinema_end_date', true),
				'status'           => (string) get_post_meta($post_id, '_cinema_movie_status', true) ?: 'now_showing',
			);

			// Get genre
			$terms = get_the_terms($post_id, 'movie_genre');
			$genre = ($terms && ! is_wp_error($terms)) ? $terms[0]->name : '';

			// Poster fallback
			if (empty($meta['poster_url'])) {
				$meta['poster_url'] = (string) get_the_post_thumbnail_url($post_id, 'large');
			}

			$slug = $m['post_name'];
			// ensure unique slug
			$existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$movies_table} WHERE slug = %s", $slug));
			if ($existing) {
				$slug = $slug . '-' . $post_id;
			}

			$wpdb->insert(
				$movies_table,
				array(
					'title'            => $m['post_title'],
					'slug'             => $slug,
					'description'      => $m['post_content'],
					'genre'            => $genre,
					'duration_minutes' => $meta['duration_minutes'],
					'poster_url'       => $meta['poster_url'],
					'trailer_url'      => $meta['trailer_url'],
					'director'         => $meta['director'],
					'cast_list'        => $meta['cast_list'],
					'rating'           => $meta['rating'],
					'review_score'     => $meta['review_score'],
					'release_date'     => $meta['release_date'] ?: null,
					'end_date'         => $meta['end_date'] ?: null,
					'status'           => $meta['status'],
				),
				array('%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%f','%s','%s','%s')
			);

			$movie_id_map[$post_id] = $wpdb->insert_id;
		}

		// ----- Migrate Rooms -----
		$wp_rooms     = $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'room' AND post_status = 'publish'",
			ARRAY_A
		);
		$room_id_map  = array();

		foreach ($wp_rooms as $r) {
			$post_id = (int) $r['ID'];
			$wpdb->insert(
				$rooms_table,
				array(
					'name'           => $r['post_title'],
					'type'           => (string) get_post_meta($post_id, '_cinema_room_type', true) ?: '2d',
					'total_rows'     => (int) get_post_meta($post_id, '_cinema_room_total_rows', true),
					'total_columns'  => (int) get_post_meta($post_id, '_cinema_room_total_columns', true),
					'vip_rows'       => (string) get_post_meta($post_id, '_cinema_room_vip_rows', true),
					'couple_rows'    => (string) get_post_meta($post_id, '_cinema_room_couple_rows', true),
					'vip_seats'      => (string) get_post_meta($post_id, '_cinema_room_vip_seats', true),
					'couple_seats'   => (string) get_post_meta($post_id, '_cinema_room_couple_seats', true),
					'inactive_seats' => (string) get_post_meta($post_id, '_cinema_room_inactive_seats', true),
				),
				array('%s','%s','%d','%d','%s','%s','%s','%s','%s')
			);
			$room_id_map[$post_id] = $wpdb->insert_id;
		}

		// ----- Migrate Showtimes -----
		$wp_showtimes = $wpdb->get_results(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'showtime' AND post_status = 'publish'",
			ARRAY_A
		);
		$showtime_id_map = array();

		foreach ($wp_showtimes as $s) {
			$post_id     = (int) $s['ID'];
			$old_movie   = (int) get_post_meta($post_id, '_cinema_showtime_movie_id', true);
			$old_room    = (int) get_post_meta($post_id, '_cinema_showtime_room_id', true);
			$new_movie   = $movie_id_map[$old_movie] ?? 0;
			$new_room    = $room_id_map[$old_room] ?? 0;
			$start       = str_replace('T', ' ', (string) get_post_meta($post_id, '_cinema_showtime_start_datetime', true));
			$end         = str_replace('T', ' ', (string) get_post_meta($post_id, '_cinema_showtime_end_datetime', true));

			if (! $new_movie || ! $new_room || ! $start) {
				continue;
			}

			$wpdb->insert(
				$showtimes_table,
				array(
					'movie_id'       => $new_movie,
					'room_id'        => $new_room,
					'start_datetime' => $start,
					'end_datetime'   => $end ?: null,
					'status'         => (string) get_post_meta($post_id, '_cinema_showtime_status', true) ?: 'open',
					'price_normal'   => (float) get_post_meta($post_id, '_cinema_showtime_price_normal', true),
					'price_vip'      => (float) get_post_meta($post_id, '_cinema_showtime_price_vip', true),
					'price_couple'   => (float) get_post_meta($post_id, '_cinema_showtime_price_couple', true),
				),
				array('%d','%d','%s','%s','%s','%f','%f','%f')
			);

			$new_showtime_id = $wpdb->insert_id;
			$showtime_id_map[$post_id] = $new_showtime_id;

			// Update seat_bookings FK
			if ($new_showtime_id) {
				$wpdb->update(
					$wpdb->prefix . 'cinema_seat_bookings',
					array('showtime_id' => $new_showtime_id),
					array('showtime_id' => $post_id),
					array('%d'),
					array('%d')
				);
			}
		}

		// ----- Migrate Bookings -----
		$wp_bookings = $wpdb->get_results(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'booking' AND post_status = 'publish'",
			ARRAY_A
		);

		foreach ($wp_bookings as $b) {
			$post_id       = (int) $b['ID'];
			$old_showtime  = (int) get_post_meta($post_id, '_cinema_booking_showtime_id', true);
			$new_showtime  = $showtime_id_map[$old_showtime] ?? 0;
			$booking_code  = (string) get_post_meta($post_id, '_cinema_booking_code', true);

			if (! $booking_code) {
				continue;
			}

			// Skip if already migrated
			$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$bookings_table} WHERE booking_code = %s", $booking_code));
			if ($exists) {
				continue;
			}

			$wpdb->insert(
				$bookings_table,
				array(
					'booking_code'   => $booking_code,
					'showtime_id'    => $new_showtime,
					'user_id'        => (int) get_post_field('post_author', $post_id),
					'customer_name'  => (string) get_post_meta($post_id, '_cinema_customer_name', true),
					'customer_email' => (string) get_post_meta($post_id, '_cinema_customer_email', true),
					'customer_phone' => (string) get_post_meta($post_id, '_cinema_customer_phone', true),
					'total_amount'   => (float) get_post_meta($post_id, '_cinema_booking_total_amount', true),
					'payment_method' => (string) get_post_meta($post_id, '_cinema_booking_payment_method', true) ?: 'cash',
					'booking_status' => 'confirmed',
					'payment_status' => (string) get_post_meta($post_id, '_cinema_booking_payment_status', true) ?: 'pending',
				),
				array('%s','%d','%d','%s','%s','%s','%f','%s','%s','%s')
			);
		}

		// Store mapping for reference
		update_option('cinema_booking_movie_id_map', $movie_id_map, false);
		update_option('cinema_booking_room_id_map', $room_id_map, false);
		update_option('cinema_booking_showtime_id_map', $showtime_id_map, false);
		update_option('cinema_booking_migration_done', '2.0.0', false);
	}
}
