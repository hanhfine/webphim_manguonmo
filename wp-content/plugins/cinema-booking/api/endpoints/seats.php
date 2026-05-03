<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_REST_Seats {
	/**
	 * @var Cinema_Booking_Seat_Manager
	 */
	private $seat_manager;

	public function __construct($seat_manager) {
		$this->seat_manager = $seat_manager;
	}

	public function register_routes() {
		register_rest_route(
			'cinema/v1',
			'/showtimes/(?P<id>\d+)/seats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_showtime_seats'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'cinema/v1',
			'/lock-seats',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'lock_seats'),
				'permission_callback' => array($this, 'can_book'),
			)
		);
	}

	public function get_showtime_seats($request) {
		$showtime_id = absint($request['id']);
		$user_id     = get_current_user_id();

		return rest_ensure_response(
			array(
				'showtime_id' => $showtime_id,
				'seat_map'    => $this->seat_manager->get_showtime_seat_map($showtime_id, $user_id),
			)
		);
	}

	public function lock_seats($request) {
		$rate_limit = $this->check_rate_limit('seat-lock');

		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$payload     = $request->get_json_params();
		$showtime_id = absint($payload['showtime_id'] ?? 0);
		$seat_ids    = array_map('absint', (array) ($payload['seat_ids'] ?? array()));
		$user_id     = get_current_user_id();
		$result      = $this->seat_manager->lock_seats($showtime_id, $seat_ids, $user_id);

		if (is_wp_error($result)) {
			return $result;
		}

		return rest_ensure_response($result);
	}

	public function can_book($request) {
		return is_user_logged_in() && current_user_can('read') && $this->verify_rest_nonce($request);
	}

	private function check_rate_limit($key) {
		$user_id       = get_current_user_id();
		$identifier    = $user_id ? 'user_' . $user_id : 'guest_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
		$transient_key = 'cinema_rate_' . md5($key . '_' . $identifier);
		$count         = (int) get_transient($transient_key);

		if ($count >= 25) {
			return new WP_Error('rate_limited', __('Too many booking requests. Please wait a minute.', 'cinema-booking'), array('status' => 429));
		}

		set_transient($transient_key, $count + 1, MINUTE_IN_SECONDS);

		return true;
	}

	private function verify_rest_nonce($request) {
		$nonce = $request->get_header('x_wp_nonce');

		return $nonce && wp_verify_nonce($nonce, 'wp_rest');
	}
}
