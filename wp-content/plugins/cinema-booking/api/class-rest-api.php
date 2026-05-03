<?php

if (! defined('ABSPATH')) {
	exit;
}

require_once CINEMA_BOOKING_PATH . 'api/endpoints/showtimes.php';
require_once CINEMA_BOOKING_PATH . 'api/endpoints/seats.php';
require_once CINEMA_BOOKING_PATH . 'api/endpoints/bookings.php';
require_once CINEMA_BOOKING_PATH . 'api/endpoints/integration.php';

class Cinema_Booking_REST_API {
	private $endpoints = array();

	public function __construct($seat_manager, $booking_manager, $payment_handler) {
		$this->endpoints[] = new Cinema_Booking_REST_Showtimes();
		$this->endpoints[] = new Cinema_Booking_REST_Seats($seat_manager);
		$this->endpoints[] = new Cinema_Booking_REST_Bookings($booking_manager, $payment_handler);
		$this->endpoints[] = new Cinema_Booking_REST_Integration($seat_manager, $booking_manager, $payment_handler);

		add_action('rest_api_init', array($this, 'register_routes'));
	}

	public function register_routes() {
		foreach ($this->endpoints as $endpoint) {
			$endpoint->register_routes();
		}
	}
}
