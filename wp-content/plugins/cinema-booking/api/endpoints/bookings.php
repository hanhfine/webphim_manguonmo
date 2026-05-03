<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_REST_Bookings {
	/**
	 * @var Cinema_Booking_Booking_Manager
	 */
	private $booking_manager;

	/**
	 * @var Cinema_Booking_Payment_Handler
	 */
	private $payment_handler;

	public function __construct($booking_manager, $payment_handler) {
		$this->booking_manager = $booking_manager;
		$this->payment_handler = $payment_handler;
	}

	public function register_routes() {
		register_rest_route(
			'cinema/v1',
			'/confirm-booking',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'confirm_booking'),
				'permission_callback' => array($this, 'can_manage_booking'),
			)
		);

		register_rest_route(
			'cinema/v1',
			'/my-bookings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_my_bookings'),
				'permission_callback' => array($this, 'can_manage_booking'),
			)
		);

		register_rest_route(
			'cinema/v1',
			'/payment-callback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'handle_payment_callback'),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function confirm_booking($request) {
		$rate_limit = $this->check_rate_limit('confirm-booking');

		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$payload        = $request->get_json_params();
		$showtime_id    = absint($payload['showtime_id'] ?? 0);
		$seat_ids       = array_map('absint', (array) ($payload['seat_ids'] ?? array()));
		$payment_method = sanitize_key($payload['payment_method'] ?? 'cash');
		$user_id        = get_current_user_id();

		$result = $this->booking_manager->create_booking($showtime_id, $seat_ids, $user_id, $payment_method);

		if (is_wp_error($result)) {
			return $result;
		}

		$payment = $this->payment_handler->create_payment(
			$result['booking_id'],
			$user_id,
			$result['total_amount'],
			$payment_method
		);

		if (is_wp_error($payment)) {
			return $payment;
		}

		return rest_ensure_response(
			array(
				'booking' => $result['booking'],
				'payment' => $payment,
			)
		);
	}

	public function get_my_bookings($request) {
		return rest_ensure_response($this->booking_manager->get_user_bookings(get_current_user_id(), 50));
	}

	public function handle_payment_callback($request) {
		$payload = $request->get_json_params();
		$result  = $this->payment_handler->handle_callback((array) $payload);

		if (is_wp_error($result)) {
			return $result;
		}

		return rest_ensure_response($result);
	}

	public function can_manage_booking($request) {
		$nonce = $request->get_header('x_wp_nonce');

		return is_user_logged_in() && current_user_can('read') && $nonce && wp_verify_nonce($nonce, 'wp_rest');
	}

	private function check_rate_limit($key) {
		$user_id       = get_current_user_id();
		$identifier    = $user_id ? 'user_' . $user_id : 'guest_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
		$transient_key = 'cinema_rate_' . md5($key . '_' . $identifier);
		$count         = (int) get_transient($transient_key);

		if ($count >= 15) {
			return new WP_Error('rate_limited', __('Too many booking attempts. Please wait a minute.', 'cinema-booking'), array('status' => 429));
		}

		set_transient($transient_key, $count + 1, MINUTE_IN_SECONDS);

		return true;
	}
}
