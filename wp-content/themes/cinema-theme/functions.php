<?php

if (! defined('ABSPATH')) {
	exit;
}

define('CINEMA_THEME_VERSION', '0.1.0');

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support('title-tag');
		add_theme_support('post-thumbnails');
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'script',
				'style',
			)
		);

		register_nav_menus(
			array(
				'primary' => __('Primary Menu', 'cinema-theme'),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style('cinema-theme-style', get_stylesheet_uri(), array(), CINEMA_THEME_VERSION);
		wp_enqueue_style('cinema-theme-main', get_template_directory_uri() . '/assets/css/theme.css', array('cinema-theme-style'), CINEMA_THEME_VERSION);
		wp_enqueue_script('cinema-theme-main', get_template_directory_uri() . '/assets/js/theme.js', array(), CINEMA_THEME_VERSION, true);

		if (! defined('CINEMA_BOOKING_URL')) {
			return;
		}

		$rest_config = cinema_theme_get_rest_config();

		if (cinema_theme_is_seat_selection_page()) {
			wp_enqueue_style('cinema-booking-seat-map', CINEMA_BOOKING_URL . 'assets/css/seat-map.css', array('cinema-theme-main'), CINEMA_THEME_VERSION);
			wp_enqueue_style('cinema-booking-booking', CINEMA_BOOKING_URL . 'assets/css/booking.css', array('cinema-theme-main'), CINEMA_THEME_VERSION);
			wp_enqueue_script('cinema-booking-seat-selection', CINEMA_BOOKING_URL . 'assets/js/seat-selection.js', array(), CINEMA_THEME_VERSION, true);
			wp_localize_script('cinema-booking-seat-selection', 'cinemaBooking', $rest_config);
		}

		if (cinema_theme_is_checkout_page()) {
			wp_enqueue_style('cinema-booking-booking', CINEMA_BOOKING_URL . 'assets/css/booking.css', array('cinema-theme-main'), CINEMA_THEME_VERSION);
			wp_enqueue_script('cinema-booking-payment', CINEMA_BOOKING_URL . 'assets/js/payment.js', array(), CINEMA_THEME_VERSION, true);
			wp_localize_script('cinema-booking-payment', 'cinemaBooking', $rest_config);
		}
	}
);

add_action(
	'template_redirect',
	static function () {
		if (! is_page('auth') && ! is_page_template('templates/page-auth.php')) {
			return;
		}

		if (! isset($_POST['cinema_auth_action'])) {
			return;
		}

		if (! isset($_POST['cinema_auth_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cinema_auth_nonce'])), 'cinema_theme_auth')) {
			return;
		}

		$action = sanitize_key(wp_unslash($_POST['cinema_auth_action']));
		$page   = wp_get_referer() ?: home_url('/');

		if ('register' === $action) {
			$username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
			$email    = sanitize_email(wp_unslash($_POST['email'] ?? ''));
			$password = (string) wp_unslash($_POST['password'] ?? '');

			$user_id = wp_create_user($username, $password, $email);

			if (is_wp_error($user_id)) {
				wp_safe_redirect(add_query_arg('auth_error', rawurlencode($user_id->get_error_message()), $page));
				exit;
			}

			wp_update_user(
				array(
					'ID'   => $user_id,
					'role' => 'customer',
				)
			);

			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id);
			wp_safe_redirect(cinema_theme_get_page_url('my-bookings'));
			exit;
		}

		if ('login' === $action) {
			$user = wp_signon(
				array(
					'user_login'    => sanitize_text_field(wp_unslash($_POST['log'] ?? '')),
					'user_password' => (string) wp_unslash($_POST['pwd'] ?? ''),
					'remember'      => ! empty($_POST['rememberme']),
				),
				is_ssl()
			);

			if (is_wp_error($user)) {
				wp_safe_redirect(add_query_arg('auth_error', rawurlencode($user->get_error_message()), $page));
				exit;
			}

			wp_safe_redirect(cinema_theme_get_page_url('my-bookings'));
			exit;
		}
	}
);

function cinema_theme_get_rest_config() {
	return array(
		'restUrl'     => trailingslashit(rest_url('cinema/v1')),
		'restNonce'   => wp_create_nonce('wp_rest'),
		'checkoutUrl' => cinema_theme_get_page_url('checkout'),
		'successUrl'  => cinema_theme_get_page_url('booking-success'),
	);
}

function cinema_theme_is_seat_selection_page() {
	return is_page('seat-selection') || is_page_template('templates/page-seat-selection.php');
}

function cinema_theme_is_checkout_page() {
	return is_page('checkout') || is_page_template('templates/page-checkout.php');
}

function cinema_theme_get_page_url($slug) {
	$page = get_page_by_path($slug);

	if ($page instanceof WP_Post) {
		return get_permalink($page);
	}

	return home_url('/' . trim($slug, '/') . '/');
}

function cinema_theme_get_movies($args = array()) {
	$defaults = array(
		'posts_per_page' => 9,
		'movie_status'   => sanitize_key(wp_unslash($_GET['status'] ?? '')),
		'genre'          => sanitize_title(wp_unslash($_GET['genre'] ?? '')),
	);
	$args     = wp_parse_args($args, $defaults);

	$query_args = array(
		'post_type'      => 'movie',
		'post_status'    => 'publish',
		'posts_per_page' => absint($args['posts_per_page']),
	);

	if ($args['movie_status']) {
		$query_args['meta_query'] = array(
			array(
				'key'   => '_cinema_movie_status',
				'value' => $args['movie_status'],
			),
		);
	}

	if ($args['genre']) {
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'movie_genre',
				'field'    => 'slug',
				'terms'    => $args['genre'],
			),
		);
	}

	return new WP_Query($query_args);
}

function cinema_theme_get_movie_meta($movie_id) {
	return array(
		'trailer_url'      => get_post_meta($movie_id, '_cinema_trailer_url', true),
		'director'         => get_post_meta($movie_id, '_cinema_director', true),
		'cast'             => get_post_meta($movie_id, '_cinema_cast', true),
		'duration_minutes' => get_post_meta($movie_id, '_cinema_duration_minutes', true),
		'release_date'     => get_post_meta($movie_id, '_cinema_release_date', true),
		'end_date'         => get_post_meta($movie_id, '_cinema_end_date', true),
		'rating'           => get_post_meta($movie_id, '_cinema_rating', true),
		'review_score'     => get_post_meta($movie_id, '_cinema_review_score', true),
		'status'           => get_post_meta($movie_id, '_cinema_movie_status', true),
	);
}

function cinema_theme_get_grouped_showtimes($movie_id) {
	$showtimes = get_posts(
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
					'value' => array('open', 'locked'),
					'compare' => 'IN',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_cinema_showtime_start_datetime',
			'order'          => 'ASC',
		)
	);

	$grouped = array();

	foreach ($showtimes as $showtime) {
		$start    = get_post_meta($showtime->ID, '_cinema_showtime_start_datetime', true);
		$date_key = $start ? wp_date('d/m/Y', strtotime($start)) : __('Unknown date', 'cinema-theme');
		$room_id  = absint(get_post_meta($showtime->ID, '_cinema_showtime_room_id', true));
		$cinema_id = absint(get_post_meta($room_id, '_cinema_room_cinema_id', true));

		if (! isset($grouped[$date_key])) {
			$grouped[$date_key] = array();
		}

		$grouped[$date_key][] = array(
			'id'           => $showtime->ID,
			'start'        => $start,
			'end'          => get_post_meta($showtime->ID, '_cinema_showtime_end_datetime', true),
			'status'       => get_post_meta($showtime->ID, '_cinema_showtime_status', true),
			'cinema_title' => get_the_title($cinema_id),
			'room_title'   => get_the_title($room_id),
			'prices'       => array(
				'normal' => (float) get_post_meta($showtime->ID, '_cinema_showtime_price_normal', true),
				'vip'    => (float) get_post_meta($showtime->ID, '_cinema_showtime_price_vip', true),
				'couple' => (float) get_post_meta($showtime->ID, '_cinema_showtime_price_couple', true),
			),
		);
	}

	return $grouped;
}

function cinema_theme_get_booking_manager() {
	if (! class_exists('Cinema_Booking_System')) {
		return null;
	}

	return Cinema_Booking_System::instance()->booking_manager;
}

function cinema_theme_get_ticket_generator() {
	if (! class_exists('Cinema_Booking_System')) {
		return null;
	}

	return Cinema_Booking_System::instance()->ticket_generator;
}

function cinema_theme_get_booking_payload($booking_id) {
	$manager = cinema_theme_get_booking_manager();

	if (! $manager) {
		return array();
	}

	return $manager->get_booking_payload($booking_id);
}

function cinema_theme_get_ticket_download($booking_id, $generate = false) {
	$generator = cinema_theme_get_ticket_generator();

	if (! $generator) {
		return array(
			'available' => false,
		);
	}

	return $generator->get_pdf_download($booking_id, $generate);
}

function cinema_theme_get_ticket_delivery_meta($booking_id) {
	$generator = cinema_theme_get_ticket_generator();

	if (! $generator) {
		return array();
	}

	return $generator->get_ticket_delivery_meta($booking_id);
}

function cinema_theme_get_ticket_html($booking_id) {
	$generator = cinema_theme_get_ticket_generator();

	if (! $generator) {
		return '';
	}

	return $generator->generate_ticket_html($booking_id);
}

function cinema_theme_get_auth_error() {
	return rawurldecode(sanitize_text_field(wp_unslash($_GET['auth_error'] ?? '')));
}

function cinema_theme_get_youtube_embed_url($url) {
	if (! $url) {
		return '';
	}

	if (preg_match('~(?:v=|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $matches)) {
		return 'https://www.youtube.com/embed/' . $matches[1];
	}

	return '';
}
