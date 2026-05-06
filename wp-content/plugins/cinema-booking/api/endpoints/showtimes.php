<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_REST_Showtimes {
	public function register_routes() {
		register_rest_route(
			'cinema/v1',
			'/showtimes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_items($request) {
		$movie_id   = absint($request->get_param('movie_id'));
		$date       = sanitize_text_field($request->get_param('date'));
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

		$data = array_map(
			function ($showtime) {
				$movie_id  = absint(get_post_meta($showtime->ID, '_cinema_showtime_movie_id', true));
				$room_id   = absint(get_post_meta($showtime->ID, '_cinema_showtime_room_id', true));

				return array(
					'id'             => $showtime->ID,
					'title'          => $showtime->post_title,
					'status'         => get_post_meta($showtime->ID, '_cinema_showtime_status', true),
					'start_datetime' => get_post_meta($showtime->ID, '_cinema_showtime_start_datetime', true),
					'end_datetime'   => get_post_meta($showtime->ID, '_cinema_showtime_end_datetime', true),
					'movie'          => array(
						'id'    => $movie_id,
						'title' => get_the_title($movie_id),
					),
					'room'           => array(
						'id'    => $room_id,
						'title' => get_the_title($room_id),
					),
					'cinema'         => $this->get_single_cinema_payload(),
					'prices'         => array(
						'normal' => (float) get_post_meta($showtime->ID, '_cinema_showtime_price_normal', true),
						'vip'    => (float) get_post_meta($showtime->ID, '_cinema_showtime_price_vip', true),
						'couple' => (float) get_post_meta($showtime->ID, '_cinema_showtime_price_couple', true),
					),
				);
			},
			$showtimes
		);

		return rest_ensure_response($data);
	}

	private function get_single_cinema_payload() {
		$name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

		return array(
			'id'    => 0,
			'title' => $name ? $name : __('Main Cinema', 'cinema-booking'),
		);
	}
}
