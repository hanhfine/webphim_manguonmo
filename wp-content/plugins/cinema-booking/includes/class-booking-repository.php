<?php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * CRUD repository for cinema_bookings table.
 */
class Cinema_Booking_Repository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cinema_bookings';
	}

	/**
	 * Find booking by booking code.
	 *
	 * @return array|null
	 */
	public function find_by_code( string $code ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE booking_code = %s", $code ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Find bookings by customer email.
	 *
	 * @return array[]
	 */
	public function find_by_email( string $email, int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE customer_email = %s ORDER BY booked_at DESC LIMIT %d",
				sanitize_email( $email ),
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Find bookings by WordPress user ID.
	 *
	 * @return array[]
	 */
	public function find_by_user( int $user_id, int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, s.start_datetime, s.end_datetime, s.status AS showtime_status,
				        m.title AS movie_title, m.poster_url, m.rating, m.genre,
				        r.name AS room_name, r.type AS room_type
				FROM {$this->table()} b
				LEFT JOIN {$wpdb->prefix}cinema_showtimes s ON s.id = b.showtime_id
				LEFT JOIN {$wpdb->prefix}cinema_movies    m ON m.id = s.movie_id
				LEFT JOIN {$wpdb->prefix}cinema_rooms     r ON r.id = s.room_id
				WHERE b.user_id = %d
				ORDER BY b.booked_at DESC
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get all bookings (admin view).
	 *
	 * @return array[]
	 */
	public function find_all( int $limit = 100 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, s.start_datetime, m.title AS movie_title
				FROM {$this->table()} b
				LEFT JOIN {$wpdb->prefix}cinema_showtimes s ON s.id = b.showtime_id
				LEFT JOIN {$wpdb->prefix}cinema_movies    m ON m.id = s.movie_id
				ORDER BY b.booked_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Create a new booking record.
	 *
	 * @return int  New booking ID, or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert(
			$this->table(),
			array(
				'booking_code'   => sanitize_text_field( $data['booking_code'] ?? '' ),
				'showtime_id'    => absint( $data['showtime_id'] ?? 0 ),
				'user_id'        => absint( $data['user_id'] ?? 0 ),
				'customer_name'  => sanitize_text_field( $data['customer_name'] ?? '' ),
				'customer_email' => sanitize_email( $data['customer_email'] ?? '' ),
				'customer_phone' => sanitize_text_field( $data['customer_phone'] ?? '' ),
				'total_amount'   => (float) ( $data['total_amount'] ?? 0 ),
				'payment_method' => sanitize_key( $data['payment_method'] ?? 'cash' ),
				'booking_status' => 'confirmed',
				'payment_status' => sanitize_key( $data['payment_status'] ?? 'pending' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update payment status.
	 */
	public function update_payment_status( int $id, string $status ): bool {
		global $wpdb;
		$allowed = [ 'pending', 'paid', 'failed', 'refunded' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return (bool) $wpdb->update(
			$this->table(),
			[ 'payment_status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Update booking status.
	 */
	public function update_booking_status( int $id, string $status ): bool {
		global $wpdb;
		$allowed = [ 'confirmed', 'cancelled', 'refunded' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return (bool) $wpdb->update(
			$this->table(),
			[ 'booking_status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Update payment status by booking code.
	 */
	public function update_payment_status_by_code( string $code, string $status ): bool {
		global $wpdb;
		$allowed = [ 'pending', 'paid', 'failed', 'refunded' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return (bool) $wpdb->update(
			$this->table(),
			[ 'payment_status' => $status ],
			[ 'booking_code' => $code ],
			[ '%s' ],
			[ '%s' ]
		);
	}

	public function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
	}

	/**
	 * Monthly revenue chart data.
	 *
	 * @param int $months_back  Number of past months.
	 * @return array{labels:string[],values:float[]}
	 */
	public function get_monthly_revenue( int $months_back = 5 ): array {
		global $wpdb;

		$months = [];
		for ( $i = $months_back; $i >= 0; $i-- ) {
			$months[] = wp_date( 'Y-m', strtotime( "-{$i} months" ) );
		}

		$data = array_fill_keys( $months, 0.0 );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(booked_at, '%%Y-%%m') AS month, COALESCE(SUM(total_amount),0) AS revenue
				FROM {$this->table()}
				WHERE payment_status = 'paid'
				  AND booked_at >= %s
				GROUP BY month
				ORDER BY month ASC",
				wp_date( 'Y-m-01', strtotime( "-{$months_back} months" ) )
			),
			ARRAY_A
		) ?: [];

		foreach ( $rows as $row ) {
			if ( isset( $data[$row['month']] ) ) {
				$data[$row['month']] = (float) $row['revenue'];
			}
		}

		return [
			'labels' => array_keys( $data ),
			'values' => array_values( $data ),
		];
	}
}
