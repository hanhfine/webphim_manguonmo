<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_REST_Integration {
	/**
	 * @var Cinema_Booking_Seat_Manager
	 */
	private $seat_manager;

	/**
	 * @var Cinema_Booking_Booking_Manager
	 */
	private $booking_manager;

	/**
	 * @var Cinema_Booking_Payment_Handler
	 */
	private $payment_handler;

	public function __construct($seat_manager, $booking_manager, $payment_handler) {
		$this->seat_manager    = $seat_manager;
		$this->booking_manager = $booking_manager;
		$this->payment_handler = $payment_handler;
	}

	public function register_routes() {
		register_rest_route(
			'cinema/v1/integration',
			'/movies',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_movies'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);

		register_rest_route(
			'cinema/v1/integration',
			'/movies/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_movie'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);

		register_rest_route(
			'cinema/v1/integration',
			'/showtimes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_showtimes'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);

		register_rest_route(
			'cinema/v1/integration',
			'/showtimes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_showtime'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);

		register_rest_route(
			'cinema/v1/integration',
			'/showtimes/(?P<id>\d+)/seats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_showtime_seats'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);

		register_rest_route(
			'cinema/v1/integration',
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_booking'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);

		register_rest_route(
			'cinema/v1/integration',
			'/bookings/lookup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'lookup_bookings'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);

		register_rest_route(
			'cinema/v1/integration',
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_settings'),
				'permission_callback' => array($this, 'authorize_request'),
			)
		);
	}

	public function authorize_request($request) {
		$header_key = $request->get_header('x_cinema_api_key');
		$param_key  = $request->get_param('api_key');
		$provided   = is_string($header_key) && '' !== trim($header_key) ? $header_key : $param_key;
		$expected   = cinema_booking_get_integration_key();

		if ($provided && hash_equals($expected, (string) $provided)) {
			return true;
		}

		return new WP_Error('invalid_api_key', __('Invalid integration API key.', 'cinema-booking'), array('status' => 401));
	}

	public function get_movies($request) {
		$query = $this->build_movie_query_args($request);
		$posts = get_posts($query);
		$data  = array();

		foreach ($posts as $post) {
			$data[] = $this->format_movie_item($post);
		}

		return rest_ensure_response($data);
	}

	public function get_movie($request) {
		$movie_id = absint($request['id']);
		$post     = get_post($movie_id);

		if (! $post || 'movie' !== $post->post_type || 'publish' !== $post->post_status) {
			return new WP_Error('movie_not_found', __('Movie not found.', 'cinema-booking'), array('status' => 404));
		}

		return rest_ensure_response($this->format_movie_item($post));
	}

	public function get_showtimes($request) {
		$movie_id   = absint($request->get_param('movie_id'));
		$date       = sanitize_text_field((string) $request->get_param('date'));
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'   => '_cinema_showtime_status',
				'value' => 'open',
			),
			array(
				'key'     => '_cinema_showtime_start_datetime',
				'value'   => current_time('mysql'),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		);

		if ($movie_id) {
			$meta_query[] = array(
				'key'   => '_cinema_showtime_movie_id',
				'value' => $movie_id,
			);
		}

		if ($date) {
			$meta_query[] = array(
				'key'     => '_cinema_showtime_start_datetime',
				'value'   => array($date . ' 00:00:00', $date . ' 23:59:59'),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			);
		}

		$showtimes = get_posts(
			array(
				'post_type'      => 'showtime',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => $meta_query,
				'orderby'        => 'meta_value',
				'meta_key'       => '_cinema_showtime_start_datetime',
				'order'          => 'ASC',
			)
		);

		$data = array();

		foreach ($showtimes as $showtime) {
			$data[] = $this->format_showtime_item($showtime);
		}

		return rest_ensure_response($data);
	}

	public function get_showtime($request) {
		$showtime_id = absint($request['id']);
		$post        = get_post($showtime_id);

		if (! $post || 'showtime' !== $post->post_type || 'publish' !== $post->post_status) {
			return new WP_Error('showtime_not_found', __('Showtime not found.', 'cinema-booking'), array('status' => 404));
		}

		return rest_ensure_response($this->format_showtime_item($post));
	}

	public function get_showtime_seats($request) {
		$showtime_id = absint($request['id']);
		$seat_map    = $this->seat_manager->get_showtime_seat_map($showtime_id, 0);
		$rows        = array();

		foreach ($seat_map as $row_label => $row_items) {
			foreach ($row_items as $seat) {
				$rows[] = array(
					'id'          => absint($seat['id']),
					'row_label'   => $row_label,
					'seat_number' => absint($seat['seat_number']),
					'seat_type'   => (string) $seat['seat_type'],
					'status'      => (string) $seat['status'],
					'label'       => (string) $seat['label'],
				);
			}
		}

		return rest_ensure_response(
			array(
				'showtime_id' => $showtime_id,
				'seat_map'    => $rows,
			)
		);
	}

	public function create_booking($request) {
		$payload        = (array) $request->get_json_params();
		$showtime_id    = absint($payload['showtime_id'] ?? 0);
		$seat_ids       = array_map('absint', (array) ($payload['seat_ids'] ?? array()));
		$payment_method = $this->map_external_payment_method((string) ($payload['payment_method'] ?? 'counter'));
		$customer_data  = array(
			'name'  => sanitize_text_field((string) ($payload['customer_name'] ?? '')),
			'email' => sanitize_email((string) ($payload['email'] ?? '')),
			'phone' => sanitize_text_field((string) ($payload['phone'] ?? '')),
		);

		$result = $this->booking_manager->create_external_booking($showtime_id, $seat_ids, $customer_data, $payment_method);

		if (is_wp_error($result)) {
			return $result;
		}

		$payment = $this->payment_handler->create_payment(
			$result['booking_id'],
			0,
			$result['total_amount'],
			$payment_method
		);

		if (is_wp_error($payment)) {
			return $payment;
		}

		return rest_ensure_response(
			array(
				'booking' => $this->flatten_booking_payload($result['booking']),
				'payment' => $payment,
			)
		);
	}

	public function lookup_bookings($request) {
		$code  = sanitize_text_field((string) $request->get_param('code'));
		$email = sanitize_email((string) $request->get_param('email'));

		if ($code) {
			$payload = $this->booking_manager->find_booking_by_code($code);

			if (empty($payload)) {
				return rest_ensure_response(array());
			}

			return rest_ensure_response(array($this->flatten_booking_payload($payload)));
		}

		if ($email) {
			$bookings = $this->booking_manager->find_bookings_by_customer_email($email, 50);

			return rest_ensure_response(
				array_map(array($this, 'flatten_booking_payload'), $bookings)
			);
		}

		return new WP_Error('missing_lookup_value', __('Booking code or email is required.', 'cinema-booking'), array('status' => 400));
	}

	public function get_settings() {
		return rest_ensure_response(
			array(
				'cinema_name' => cinema_booking_get_single_cinema_name(),
				'address'     => cinema_booking_get_single_cinema_address(),
				'city'        => cinema_booking_get_single_cinema_city(),
			)
		);
	}

	private function build_movie_query_args($request) {
		$search  = sanitize_text_field((string) $request->get_param('q'));
		$status  = sanitize_key((string) $request->get_param('status'));
		$genre   = sanitize_text_field((string) $request->get_param('genre'));
		$args    = array(
			'post_type'      => 'movie',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			's'              => $search,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(),
			'tax_query'      => array(),
		);

		if ($status) {
			$args['meta_query'][] = array(
				'key'   => '_cinema_movie_status',
				'value' => $status,
			);
		}

		if ($genre) {
			$args['tax_query'][] = array(
				'taxonomy' => 'movie_genre',
				'field'    => 'name',
				'terms'    => $genre,
			);
		}

		if (empty($args['meta_query'])) {
			unset($args['meta_query']);
		}

		if (empty($args['tax_query'])) {
			unset($args['tax_query']);
		}

		return $args;
	}

	private function format_movie_item($post) {
		$movie_id   = absint($post->ID);
		$showtimes  = $this->get_open_showtimes_for_movie($movie_id);
		$next_time  = '';
		$open_count = 0;

		foreach ($showtimes as $showtime) {
			$start = (string) get_post_meta($showtime->ID, '_cinema_showtime_start_datetime', true);

			if (! $next_time || strtotime($start) < strtotime($next_time)) {
				$next_time = $start;
			}

			$open_count++;
		}

		return array(
			'id'               => $movie_id,
			'title'            => get_the_title($movie_id),
			'description'      => wp_strip_all_tags((string) $post->post_content),
			'poster_url'       => get_post_meta($movie_id, '_cinema_poster_url', true) ?: (get_the_post_thumbnail_url($movie_id, 'large') ?: ''),
			'status'           => (string) get_post_meta($movie_id, '_cinema_movie_status', true),
			'genre'            => $this->get_movie_genre_label($movie_id),
			'duration_minutes' => absint(get_post_meta($movie_id, '_cinema_duration_minutes', true)),
			'release_date'     => (string) get_post_meta($movie_id, '_cinema_release_date', true),
			'end_date'         => (string) get_post_meta($movie_id, '_cinema_end_date', true),
			'rating'           => (string) get_post_meta($movie_id, '_cinema_rating', true),
			'review_score'     => (float) get_post_meta($movie_id, '_cinema_review_score', true),
			'trailer_url'      => (string) get_post_meta($movie_id, '_cinema_trailer_url', true),
			'director'         => (string) get_post_meta($movie_id, '_cinema_director', true),
			'cast'             => (string) get_post_meta($movie_id, '_cinema_cast', true),
			'open_showtimes'   => $open_count,
			'next_showtime'    => $next_time,
		);
	}

	private function format_showtime_item($post) {
		$showtime_id = absint($post->ID);
		$movie_id    = absint(get_post_meta($showtime_id, '_cinema_showtime_movie_id', true));
		$room_id     = absint(get_post_meta($showtime_id, '_cinema_showtime_room_id', true));

		return array(
			'id'            => $showtime_id,
			'movie_id'      => $movie_id,
			'movie_title'   => get_the_title($movie_id),
			'room_id'       => $room_id,
			'room_name'     => get_the_title($room_id),
			'screen_type'   => (string) get_post_meta($room_id, '_cinema_room_type', true),
			'cinema_name'   => cinema_booking_get_single_cinema_name(),
			'address'       => cinema_booking_get_single_cinema_address(),
			'city'          => cinema_booking_get_single_cinema_city(),
			'status'        => (string) get_post_meta($showtime_id, '_cinema_showtime_status', true),
			'start_time'    => (string) get_post_meta($showtime_id, '_cinema_showtime_start_datetime', true),
			'end_time'      => (string) get_post_meta($showtime_id, '_cinema_showtime_end_datetime', true),
			'price_normal'  => (float) get_post_meta($showtime_id, '_cinema_showtime_price_normal', true),
			'price_vip'     => (float) get_post_meta($showtime_id, '_cinema_showtime_price_vip', true),
			'price_couple'  => (float) get_post_meta($showtime_id, '_cinema_showtime_price_couple', true),
			'poster_url'    => get_post_meta($movie_id, '_cinema_poster_url', true) ?: (get_the_post_thumbnail_url($movie_id, 'large') ?: ''),
			'genre'         => $this->get_movie_genre_label($movie_id),
			'duration_minutes' => absint(get_post_meta($movie_id, '_cinema_duration_minutes', true)),
			'rating'        => (string) get_post_meta($movie_id, '_cinema_rating', true),
		);
	}

	private function flatten_booking_payload($payload) {
		$movie   = (array) ($payload['movie'] ?? array());
		$showtime= (array) ($payload['showtime'] ?? array());
		$cinema  = (array) ($payload['cinema'] ?? array());
		$room    = (array) ($payload['room'] ?? array());
		$seats   = array();

		foreach ((array) ($payload['seats'] ?? array()) as $seat) {
			$seats[] = array(
				'seat_label' => (string) ($seat['label'] ?? ''),
				'seat_type'  => (string) ($seat['seat_type'] ?? ''),
				'unit_price' => (float) ($seat['unit_price'] ?? 0),
			);
		}

		return array(
			'booking_id'      => absint($payload['booking_id'] ?? 0),
			'booking_code'    => (string) ($payload['booking_code'] ?? ''),
			'booking_status'  => (string) ($payload['status'] ?? ''),
			'payment_status'  => (string) ($payload['payment_status'] ?? ''),
			'total_amount'    => (float) ($payload['total_amount'] ?? 0),
			'payment_method'  => $this->map_internal_payment_method((string) ($payload['payment_method'] ?? 'cash')),
			'start_time'      => (string) ($showtime['start_datetime'] ?? ''),
			'end_time'        => (string) ($showtime['end_datetime'] ?? ''),
			'movie_title'     => (string) ($movie['title'] ?? ''),
			'poster_url'      => (string) ($movie['poster_url'] ?? ''),
			'genre'           => (string) ($movie['genre'] ?? ''),
			'rating'          => (string) ($movie['rating'] ?? ''),
			'cinema_name'     => (string) ($cinema['title'] ?? ''),
			'cinema_address'  => (string) ($cinema['address'] ?? ''),
			'cinema_city'     => (string) ($cinema['city'] ?? ''),
			'room_name'       => (string) ($room['title'] ?? ''),
			'screen_type'     => (string) ($room['type'] ?? ''),
			'customer_name'   => (string) ($payload['customer_name'] ?? ''),
			'customer_email'  => (string) ($payload['customer_email'] ?? ''),
			'customer_phone'  => (string) ($payload['customer_phone'] ?? ''),
			'seats'           => $seats,
		);
	}

	private function get_open_showtimes_for_movie($movie_id) {
		return get_posts(
			array(
				'post_type'      => 'showtime',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_cinema_showtime_movie_id',
						'value' => absint($movie_id),
					),
					array(
						'key'   => '_cinema_showtime_status',
						'value' => 'open',
					),
					array(
						'key'     => '_cinema_showtime_start_datetime',
						'value'   => current_time('mysql'),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
			)
		);
	}

	private function get_movie_genre_label($movie_id) {
		$terms = get_the_terms($movie_id, 'movie_genre');

		if (empty($terms) || is_wp_error($terms)) {
			return '';
		}

		return implode(', ', wp_list_pluck($terms, 'name'));
	}

	private function map_external_payment_method($method) {
		$map = array(
			'counter'       => 'cash',
			'bank_transfer' => 'bank_transfer',
			'cash'          => 'cash',
			'vnpay'         => 'vnpay',
			'momo'          => 'momo',
		);

		return $map[$method] ?? 'cash';
	}

	private function map_internal_payment_method($method) {
		$map = array(
			'cash'          => 'counter',
			'bank_transfer' => 'bank_transfer',
			'vnpay'         => 'vnpay',
			'momo'          => 'momo',
		);

		return $map[$method] ?? $method;
	}
}
