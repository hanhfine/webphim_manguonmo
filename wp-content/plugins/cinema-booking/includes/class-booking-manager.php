<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Booking_Manager {
	/**
	 * @var Cinema_Booking_Seat_Manager
	 */
	private $seat_manager;

	/**
	 * @var Cinema_Booking_Repository
	 */
	private $booking_repo;

	/**
	 * @var Cinema_Showtime_Repository
	 */
	private $showtime_repo;

	public function __construct(
		Cinema_Booking_Seat_Manager $seat_manager,
		Cinema_Booking_Repository $booking_repo,
		Cinema_Showtime_Repository $showtime_repo
	) {
		$this->seat_manager  = $seat_manager;
		$this->booking_repo  = $booking_repo;
		$this->showtime_repo = $showtime_repo;
	}

	public function create_booking( $showtime_id, $seat_ids, $user_id, $payment_method = 'cash' ) {
		return $this->create_booking_record( $showtime_id, $seat_ids, $user_id, $payment_method, [], null );
	}

	public function create_external_booking( $showtime_id, $seat_ids, $customer_data, $payment_method = 'cash' ) {
		$lock_owner_id = $this->generate_external_lock_owner();
		$lock_result   = $this->seat_manager->lock_seats( $showtime_id, $seat_ids, $lock_owner_id );

		if ( is_wp_error( $lock_result ) ) {
			return $lock_result;
		}

		return $this->create_booking_record( $showtime_id, $seat_ids, 0, $payment_method, $customer_data, $lock_owner_id );
	}

	public function find_booking_by_code( $booking_code ) {
		$row = $this->booking_repo->find_by_code( sanitize_text_field( (string) $booking_code ) );

		if ( ! $row ) {
			return [];
		}

		return $this->build_booking_payload( $row );
	}

	public function find_bookings_by_customer_email( $email, $limit = 20 ) {
		$rows = $this->booking_repo->find_by_email( sanitize_email( (string) $email ), absint( $limit ) );

		return array_map( [ $this, 'build_booking_payload' ], $rows );
	}

	public function get_user_bookings( $user_id, $limit = 20 ) {
		$rows = $this->booking_repo->find_by_user( absint( $user_id ), absint( $limit ) );

		return array_map( [ $this, 'build_booking_payload' ], $rows );
	}

	public function get_booking_payload( $booking_id ) {
		// Support both legacy int ID and already-fetched rows.
		if ( is_array( $booking_id ) ) {
			return $this->build_booking_payload( $booking_id );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cinema_bookings';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $booking_id ) ),
			ARRAY_A
		);

		if ( ! $row ) {
			// Legacy fallback: try wp_posts booking
			return $this->get_legacy_booking_payload( absint( $booking_id ) );
		}

		return $this->build_booking_payload( $row );
	}

	public function update_booking_payment_status( $booking_id, $status ) {
		$this->booking_repo->update_payment_status( absint( $booking_id ), $status );
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	private function create_booking_record( $showtime_id, $seat_ids, $user_id, $payment_method, $customer_data, $lock_owner_id ) {
		$showtime_id   = absint( $showtime_id );
		$user_id       = absint( $user_id );
		$seat_ids      = array_values( array_unique( array_filter( array_map( 'absint', (array) $seat_ids ) ) ) );
		$customer      = $this->normalize_customer_data( $user_id, (array) $customer_data );
		$lock_owner_id = null === $lock_owner_id ? $user_id : absint( $lock_owner_id );

		if ( ! $showtime_id || empty( $seat_ids ) || ! $lock_owner_id ) {
			return new WP_Error( 'invalid_booking_payload', __( 'Booking payload is incomplete.', 'cinema-booking' ), [ 'status' => 400 ] );
		}

		if ( empty( $customer['email'] ) || ! is_email( $customer['email'] ) ) {
			return new WP_Error( 'invalid_customer_email', __( 'Customer email is required.', 'cinema-booking' ), [ 'status' => 400 ] );
		}

		$booking_code = $this->generate_booking_code();
		$confirmed    = $this->seat_manager->confirm_seats( $showtime_id, $seat_ids, $lock_owner_id, $booking_code );

		if ( is_wp_error( $confirmed ) ) {
			return $confirmed;
		}

		$total_amount = $this->calculate_total( $showtime_id, $seat_ids );
		$booking_id   = $this->booking_repo->create( [
			'booking_code'   => $booking_code,
			'showtime_id'    => $showtime_id,
			'user_id'        => $user_id,
			'customer_name'  => $customer['name'],
			'customer_email' => $customer['email'],
			'customer_phone' => $customer['phone'],
			'total_amount'   => $total_amount,
			'payment_method' => sanitize_key( $payment_method ),
			'payment_status' => 'pending',
		] );

		if ( ! $booking_id ) {
			$this->seat_manager->cancel_seat_locks( $showtime_id, $seat_ids, $lock_owner_id );
			return new WP_Error( 'booking_insert_failed', __( 'Failed to save booking.', 'cinema-booking' ), [ 'status' => 500 ] );
		}

		// Hybrid Sync: Create WP Post so Admin can see the booking
		$wp_post_id = wp_insert_post([
			'post_title'   => 'Booking ' . $booking_code,
			'post_type'    => 'booking',
			'post_status'  => 'publish',
			'post_author'  => $user_id,
		]);

		if ( $wp_post_id && ! is_wp_error( $wp_post_id ) ) {
			update_post_meta( $wp_post_id, '_cinema_custom_id', $booking_id );
			update_post_meta( $wp_post_id, '_cinema_booking_code', $booking_code );
			update_post_meta( $wp_post_id, '_cinema_customer_name', $customer['name'] );
			update_post_meta( $wp_post_id, '_cinema_customer_email', $customer['email'] );
			update_post_meta( $wp_post_id, '_cinema_customer_phone', $customer['phone'] );
			update_post_meta( $wp_post_id, '_cinema_booking_showtime_id', $showtime_id );
			update_post_meta( $wp_post_id, '_cinema_booking_total_amount', $total_amount );
			update_post_meta( $wp_post_id, '_cinema_booking_payment_method', sanitize_key( $payment_method ) );
			update_post_meta( $wp_post_id, '_cinema_booking_status', 'confirmed' );
			update_post_meta( $wp_post_id, '_cinema_booking_seat_ids', $seat_ids );
		}

		return [
			'booking_id'     => $booking_id,
			'booking_code'   => $booking_code,
			'total_amount'   => $total_amount,
			'payment_method' => sanitize_key( $payment_method ),
			'booking'        => $this->get_booking_payload( $booking_id ),
		];
	}

	/**
	 * Build the full booking payload array from a cinema_bookings row.
	 */
	private function build_booking_payload( array $row ): array {
		$showtime_id = absint( $row['showtime_id'] ?? 0 );
		$seat_ids    = $this->get_seat_ids_for_booking( $row['booking_code'] ?? '' );
		$seat_labels = $this->seat_manager->get_seat_labels( $seat_ids );

		// Fetch showtime + movie + room from custom tables (with JOIN)
		$showtime = $showtime_id ? $this->showtime_repo->find( $showtime_id ) : null;
		$movie    = $showtime ? [
			'id'         => (int) $showtime['movie_id'],
			'title'      => $showtime['movie_title'] ?? __( 'Phim đang cập nhật', 'cinema-booking' ),
			'poster_url' => $showtime['poster_url'] ?? '',
			'genre'      => $showtime['genre'] ?? '',
			'rating'     => $showtime['rating'] ?? '',
		] : [ 'id' => 0, 'title' => __( 'Phim đang cập nhật', 'cinema-booking' ), 'poster_url' => '', 'genre' => '', 'rating' => '' ];

		return [
			'booking_id'     => (int) $row['id'],
			'booking_code'   => (string) $row['booking_code'],
			'status'         => (string) $row['booking_status'],
			'payment_status' => (string) $row['payment_status'],
			'total_amount'   => (float) $row['total_amount'],
			'payment_method' => (string) $row['payment_method'],
			'booked_at'      => (string) $row['booked_at'],
			'customer_name'  => (string) $row['customer_name'],
			'customer_email' => (string) $row['customer_email'],
			'customer_phone' => (string) $row['customer_phone'],
			'movie'          => $movie,
			'showtime'       => $showtime ? [
				'id'             => $showtime_id,
				'title'          => ( $movie['title'] ?? '' ) . ' | ' . ( $showtime['room_name'] ?? '' ),
				'start_datetime' => $showtime['start_datetime'] ?? '',
				'end_datetime'   => $showtime['end_datetime'] ?? '',
				'status'         => $showtime['status'] ?? '',
			] : [],
			'cinema' => $this->get_single_cinema_payload(),
			'room'   => $showtime ? [
				'id'    => (int) $showtime['room_id'],
				'title' => $showtime['room_name'] ?? __( 'Phòng đang cập nhật', 'cinema-booking' ),
				'type'  => $showtime['room_type'] ?? '2d',
			] : [],
			'seats' => $this->attach_unit_prices( $seat_labels, $showtime_id ),
		];
	}

	/**
	 * Get seat IDs from cinema_seat_bookings by booking_code.
	 *
	 * @return int[]
	 */
	private function get_seat_ids_for_booking( string $booking_code ): array {
		global $wpdb;
		if ( ! $booking_code ) {
			return [];
		}
		return array_map(
			'absint',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT seat_id FROM {$wpdb->prefix}cinema_seat_bookings WHERE booking_code = %s AND status = 'confirmed'",
					$booking_code
				)
			)
		);
	}

	/**
	 * Legacy fallback: read from wp_posts + postmeta (for old bookings).
	 */
	private function get_legacy_booking_payload( int $booking_id ): array {
		if ( ! $booking_id || 'booking' !== get_post_type( $booking_id ) ) {
			return [];
		}

		$showtime_id   = absint( get_post_meta( $booking_id, '_cinema_booking_showtime_id', true ) );
		$seat_ids      = (array) get_post_meta( $booking_id, '_cinema_booking_seat_ids', true );
		$booking_code  = (string) get_post_meta( $booking_id, '_cinema_booking_code', true );
		$total_amount  = (float) get_post_meta( $booking_id, '_cinema_booking_total_amount', true );
		$payment_method= (string) get_post_meta( $booking_id, '_cinema_booking_payment_method', true );
		$movie_id      = absint( get_post_meta( $showtime_id, '_cinema_showtime_movie_id', true ) );
		$room_id       = absint( get_post_meta( $showtime_id, '_cinema_showtime_room_id', true ) );
		$seat_labels   = $this->seat_manager->get_seat_labels( $seat_ids );

		return [
			'booking_id'     => $booking_id,
			'booking_code'   => $booking_code,
			'status'         => get_post_meta( $booking_id, '_cinema_booking_status', true ),
			'payment_status' => get_post_meta( $booking_id, '_cinema_booking_payment_status', true ),
			'total_amount'   => $total_amount,
			'payment_method' => $payment_method,
			'booked_at'      => get_post_meta( $booking_id, '_cinema_booking_booked_at', true ),
			'customer_name'  => (string) get_post_meta( $booking_id, '_cinema_customer_name', true ),
			'customer_email' => (string) get_post_meta( $booking_id, '_cinema_customer_email', true ),
			'customer_phone' => (string) get_post_meta( $booking_id, '_cinema_customer_phone', true ),
			'movie'          => [
				'id'         => $movie_id,
				'title'      => $movie_id ? get_the_title( $movie_id ) : __( 'Phim đang cập nhật', 'cinema-booking' ),
				'poster_url' => $movie_id ? ( get_post_meta( $movie_id, '_cinema_poster_url', true ) ?: '' ) : '',
				'genre'      => '',
				'rating'     => (string) get_post_meta( $movie_id, '_cinema_rating', true ),
			],
			'showtime' => [
				'id'             => $showtime_id,
				'title'          => get_the_title( $showtime_id ),
				'start_datetime' => get_post_meta( $showtime_id, '_cinema_showtime_start_datetime', true ),
				'end_datetime'   => get_post_meta( $showtime_id, '_cinema_showtime_end_datetime', true ),
				'status'         => get_post_meta( $showtime_id, '_cinema_showtime_status', true ),
			],
			'cinema' => $this->get_single_cinema_payload(),
			'room'   => [
				'id'    => $room_id,
				'title' => $room_id ? get_the_title( $room_id ) : __( 'Phòng đang cập nhật', 'cinema-booking' ),
				'type'  => get_post_meta( $room_id, '_cinema_room_type', true ),
			],
			'seats' => $this->attach_unit_prices( $seat_labels, $showtime_id ),
		];
	}

	private function calculate_total( $showtime_id, $seat_ids ) {
		$prices    = $this->showtime_repo->get_prices( absint( $showtime_id ) );
		$seat_type = $this->seat_manager->get_seat_type_map( $seat_ids );
		$total     = 0.0;

		foreach ( $seat_ids as $seat_id ) {
			$type   = $seat_type[$seat_id] ?? 'normal';
			$total += $prices[$type] ?? $prices['normal'];
		}

		return $total;
	}

	private function generate_booking_code() {
		return strtoupper( wp_generate_password( 8, false, false ) );
	}

	private function get_single_cinema_payload() {
		return [
			'id'      => 0,
			'title'   => cinema_booking_get_single_cinema_name(),
			'city'    => cinema_booking_get_single_cinema_city(),
			'address' => cinema_booking_get_single_cinema_address(),
		];
	}

	private function normalize_customer_data( $user_id, $customer_data ) {
		$customer = [
			'name'  => sanitize_text_field( (string) ( $customer_data['name'] ?? '' ) ),
			'email' => sanitize_email( (string) ( $customer_data['email'] ?? '' ) ),
			'phone' => sanitize_text_field( (string) ( $customer_data['phone'] ?? '' ) ),
		];

		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				if ( '' === $customer['name'] ) {
					$customer['name'] = sanitize_text_field( $user->display_name );
				}
				if ( '' === $customer['email'] ) {
					$customer['email'] = sanitize_email( $user->user_email );
				}
			}
		}

		return $customer;
	}

	private function generate_external_lock_owner() {
		return absint( (string) wp_rand( 100000, 999999999 ) );
	}

	private function attach_unit_prices( $seat_labels, $showtime_id ) {
		$prices = $this->showtime_repo->get_prices( absint( $showtime_id ) );

		foreach ( $seat_labels as $seat_id => $seat_data ) {
			$type = $seat_data['seat_type'] ?? 'normal';
			$seat_labels[$seat_id]['unit_price'] = $prices[$type] ?? $prices['normal'];
		}

		return array_values( $seat_labels );
	}
}
