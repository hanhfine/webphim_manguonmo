<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Booking_Manager {
	/**
	 * @var Cinema_Booking_Seat_Manager
	 */
	private $seat_manager;

	public function __construct($seat_manager) {
		$this->seat_manager = $seat_manager;
	}

	public function create_booking($showtime_id, $seat_ids, $user_id, $payment_method = 'cash') {
		return $this->create_booking_record($showtime_id, $seat_ids, $user_id, $payment_method, array(), null);
	}

	public function create_external_booking($showtime_id, $seat_ids, $customer_data, $payment_method = 'cash') {
		$lock_owner_id = $this->generate_external_lock_owner();
		$lock_result   = $this->seat_manager->lock_seats($showtime_id, $seat_ids, $lock_owner_id);

		if (is_wp_error($lock_result)) {
			return $lock_result;
		}

		return $this->create_booking_record($showtime_id, $seat_ids, 0, $payment_method, $customer_data, $lock_owner_id);
	}

	public function find_booking_by_code($booking_code) {
		$posts = get_posts(
			array(
				'post_type'      => 'booking',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_cinema_booking_code',
				'meta_value'     => sanitize_text_field((string) $booking_code),
			)
		);

		if (empty($posts)) {
			return array();
		}

		return $this->get_booking_payload((int) $posts[0]);
	}

	public function find_bookings_by_customer_email($email, $limit = 20) {
		$posts = get_posts(
			array(
				'post_type'      => 'booking',
				'post_status'    => 'publish',
				'posts_per_page' => absint($limit),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'meta_key'       => '_cinema_customer_email',
				'meta_value'     => sanitize_email((string) $email),
			)
		);

		return array_map(array($this, 'get_booking_payload'), $posts);
	}

	private function create_booking_record($showtime_id, $seat_ids, $user_id, $payment_method, $customer_data, $lock_owner_id) {
		$showtime_id = absint($showtime_id);
		$user_id     = absint($user_id);
		$seat_ids    = array_values(array_unique(array_filter(array_map('absint', (array) $seat_ids))));
		$customer    = $this->normalize_customer_data($user_id, (array) $customer_data);
		$lock_owner_id = null === $lock_owner_id ? $user_id : absint($lock_owner_id);

		if (! $showtime_id || empty($seat_ids) || ! $lock_owner_id) {
			return new WP_Error('invalid_booking_payload', __('Booking payload is incomplete.', 'cinema-booking'), array('status' => 400));
		}

		if (empty($customer['email']) || ! is_email($customer['email'])) {
			return new WP_Error('invalid_customer_email', __('Customer email is required.', 'cinema-booking'), array('status' => 400));
		}

		$booking_code = $this->generate_booking_code();
		$confirmed    = $this->seat_manager->confirm_seats($showtime_id, $seat_ids, $lock_owner_id, $booking_code);

		if (is_wp_error($confirmed)) {
			return $confirmed;
		}

		$total_amount = $this->calculate_total($showtime_id, $seat_ids);
		$booking_id   = wp_insert_post(
			array(
				'post_type'   => 'booking',
				'post_status' => 'publish',
				'post_author' => $user_id,
				'post_title'  => sprintf(__('Booking %s', 'cinema-booking'), $booking_code),
			),
			true
		);

		if (is_wp_error($booking_id)) {
			$this->seat_manager->cancel_seat_locks($showtime_id, $seat_ids, $lock_owner_id);
			return $booking_id;
		}

		update_post_meta($booking_id, '_cinema_booking_code', $booking_code);
		update_post_meta($booking_id, '_cinema_booking_showtime_id', $showtime_id);
		update_post_meta($booking_id, '_cinema_booking_seat_ids', $seat_ids);
		update_post_meta($booking_id, '_cinema_booking_total_amount', $total_amount);
		update_post_meta($booking_id, '_cinema_booking_payment_method', sanitize_key($payment_method));
		update_post_meta($booking_id, '_cinema_booking_status', 'confirmed');
		update_post_meta($booking_id, '_cinema_booking_payment_status', 'pending');
		update_post_meta($booking_id, '_cinema_booking_booked_at', current_time('mysql'));
		update_post_meta($booking_id, '_cinema_customer_name', $customer['name']);
		update_post_meta($booking_id, '_cinema_customer_email', $customer['email']);
		update_post_meta($booking_id, '_cinema_customer_phone', $customer['phone']);

		return array(
			'booking_id'    => $booking_id,
			'booking_code'  => $booking_code,
			'total_amount'  => $total_amount,
			'payment_method'=> sanitize_key($payment_method),
			'booking'       => $this->get_booking_payload($booking_id),
		);
	}

	public function get_booking_payload($booking_id) {
		$booking_id = absint($booking_id);

		if (! $booking_id || 'booking' !== get_post_type($booking_id)) {
			return array();
		}

		$showtime_id   = absint(get_post_meta($booking_id, '_cinema_booking_showtime_id', true));
		$seat_ids      = (array) get_post_meta($booking_id, '_cinema_booking_seat_ids', true);
		$booking_code  = (string) get_post_meta($booking_id, '_cinema_booking_code', true);
		$total_amount  = (float) get_post_meta($booking_id, '_cinema_booking_total_amount', true);
		$payment_method= (string) get_post_meta($booking_id, '_cinema_booking_payment_method', true);
		$movie_id      = absint(get_post_meta($showtime_id, '_cinema_showtime_movie_id', true));
		$room_id       = absint(get_post_meta($showtime_id, '_cinema_showtime_room_id', true));
		$seat_labels   = $this->seat_manager->get_seat_labels($seat_ids);
		$customer_name = (string) get_post_meta($booking_id, '_cinema_customer_name', true);
		$customer_email= (string) get_post_meta($booking_id, '_cinema_customer_email', true);
		$customer_phone= (string) get_post_meta($booking_id, '_cinema_customer_phone', true);

		return array(
			'booking_id'      => $booking_id,
			'booking_code'    => $booking_code,
			'status'          => get_post_meta($booking_id, '_cinema_booking_status', true),
			'payment_status'  => get_post_meta($booking_id, '_cinema_booking_payment_status', true),
			'total_amount'    => $total_amount,
			'payment_method'  => $payment_method,
			'booked_at'       => get_post_meta($booking_id, '_cinema_booking_booked_at', true),
			'customer_name'   => $customer_name,
			'customer_email'  => $customer_email,
			'customer_phone'  => $customer_phone,
			'movie'           => array(
				'id'    => $movie_id,
				'title' => get_the_title($movie_id),
				'poster_url' => $movie_id ? (get_the_post_thumbnail_url($movie_id, 'large') ?: '') : '',
				'genre' => $movie_id ? $this->get_movie_genre_label($movie_id) : '',
				'rating' => (string) get_post_meta($movie_id, '_cinema_rating', true),
			),
			'showtime'        => array(
				'id'             => $showtime_id,
				'title'          => get_the_title($showtime_id),
				'start_datetime' => get_post_meta($showtime_id, '_cinema_showtime_start_datetime', true),
				'end_datetime'   => get_post_meta($showtime_id, '_cinema_showtime_end_datetime', true),
				'status'         => get_post_meta($showtime_id, '_cinema_showtime_status', true),
			),
			'cinema'          => $this->get_single_cinema_payload(),
			'room'            => array(
				'id'    => $room_id,
				'title' => get_the_title($room_id),
				'type'  => get_post_meta($room_id, '_cinema_room_type', true),
			),
			'seats'           => $this->attach_unit_prices($seat_labels, $showtime_id),
		);
	}

	public function get_user_bookings($user_id, $limit = 20) {
		$posts = get_posts(
			array(
				'post_type'      => 'booking',
				'author'         => absint($user_id),
				'post_status'    => 'publish',
				'posts_per_page' => absint($limit),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array_map(array($this, 'get_booking_payload'), wp_list_pluck($posts, 'ID'));
	}

	public function update_booking_payment_status($booking_id, $status) {
		$allowed_statuses = array('pending', 'paid', 'failed', 'refunded');

		if (! in_array($status, $allowed_statuses, true)) {
			return;
		}

		update_post_meta($booking_id, '_cinema_booking_payment_status', $status);
	}

	private function calculate_total($showtime_id, $seat_ids) {
		$prices    = $this->get_showtime_prices($showtime_id);
		$seat_type = $this->seat_manager->get_seat_type_map($seat_ids);
		$total     = 0;

		foreach ($seat_ids as $seat_id) {
			$type  = $seat_type[$seat_id] ?? 'normal';
			$total += isset($prices[$type]) ? (float) $prices[$type] : (float) $prices['normal'];
		}

		return $total;
	}

	private function get_showtime_prices($showtime_id) {
		return array(
			'normal' => (float) get_post_meta($showtime_id, '_cinema_showtime_price_normal', true),
			'vip'    => (float) get_post_meta($showtime_id, '_cinema_showtime_price_vip', true),
			'couple' => (float) get_post_meta($showtime_id, '_cinema_showtime_price_couple', true),
		);
	}

	private function generate_booking_code() {
		return strtoupper(wp_generate_password(8, false, false));
	}

	private function get_single_cinema_payload() {
		return array(
			'id'    => 0,
			'title' => cinema_booking_get_single_cinema_name(),
			'city'  => cinema_booking_get_single_cinema_city(),
			'address' => cinema_booking_get_single_cinema_address(),
		);
	}

	private function normalize_customer_data($user_id, $customer_data) {
		$customer = array(
			'name'  => sanitize_text_field((string) ($customer_data['name'] ?? '')),
			'email' => sanitize_email((string) ($customer_data['email'] ?? '')),
			'phone' => sanitize_text_field((string) ($customer_data['phone'] ?? '')),
		);

		if ($user_id > 0) {
			$user = get_user_by('id', $user_id);

			if ($user) {
				if ('' === $customer['name']) {
					$customer['name'] = sanitize_text_field($user->display_name);
				}

				if ('' === $customer['email']) {
					$customer['email'] = sanitize_email($user->user_email);
				}
			}
		}

		return $customer;
	}

	private function generate_external_lock_owner() {
		return absint((string) wp_rand(100000, 999999999));
	}

	private function attach_unit_prices($seat_labels, $showtime_id) {
		$prices = $this->get_showtime_prices($showtime_id);

		foreach ($seat_labels as $seat_id => $seat_data) {
			$type = $seat_data['seat_type'] ?? 'normal';
			$seat_labels[$seat_id]['unit_price'] = isset($prices[$type]) ? (float) $prices[$type] : (float) $prices['normal'];
		}

		return array_values($seat_labels);
	}

	private function get_movie_genre_label($movie_id) {
		$terms = get_the_terms($movie_id, 'movie_genre');

		if (empty($terms) || is_wp_error($terms)) {
			return '';
		}

		return implode(', ', wp_list_pluck($terms, 'name'));
	}
}
