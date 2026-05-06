<?php

if (! defined('ABSPATH')) {
	exit;
}

define('CINEMA_THEME_VERSION', time()); // cache busting for development

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

function cinema_theme_ensure_default_pages(): void {
	if (get_option('cinema_theme_pages_seeded')) {
		return;
	}

	$pages = array(
		'auth'            => 'Đăng nhập / Đăng ký',
		'my-bookings'     => 'Tra cứu vé',
		'profile'         => 'Hồ sơ',
		'seat-selection'  => 'Chọn ghế',
		'checkout'        => 'Thanh toán',
		'booking-success' => 'Đặt vé thành công',
	);

	foreach ($pages as $slug => $title) {
		if (get_page_by_path($slug)) {
			continue;
		}

		wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			)
		);
	}

	update_option('cinema_theme_pages_seeded', 1, false);
}

add_action('after_switch_theme', 'cinema_theme_ensure_default_pages');
add_action('init', 'cinema_theme_ensure_default_pages');

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style('cinema-theme-style', get_stylesheet_uri(), array(), CINEMA_THEME_VERSION);
		wp_enqueue_style('cinema-theme-main', get_template_directory_uri() . '/assets/css/theme.css', array('cinema-theme-style'), CINEMA_THEME_VERSION);
		wp_enqueue_script('cinema-theme-app', get_template_directory_uri() . '/assets/js/app.js', array(), CINEMA_THEME_VERSION, true);
		wp_add_inline_script(
			'cinema-theme-app',
			'window.cinemaBooking=' . wp_json_encode(cinema_theme_get_rest_config()) . ';',
			'before'
		);
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
		$page   = cinema_theme_get_auth_page_url('register' === $action ? 'register' : 'login');

		if ('register' === $action) {
			$full_name        = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
			$email            = sanitize_email(wp_unslash($_POST['email'] ?? ''));
			$phone            = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
			$password         = (string) wp_unslash($_POST['password'] ?? '');
			$password_confirm = (string) wp_unslash($_POST['password_confirm'] ?? '');

			if ($password !== $password_confirm) {
				wp_safe_redirect(add_query_arg('auth_error', rawurlencode('Mật khẩu xác nhận không khớp.'), $page));
				exit;
			}

			$username_base = sanitize_user(current(explode('@', $email)) ?: $full_name, true);
			$username_base = '' !== $username_base ? $username_base : 'customer';
			$username      = $username_base;
			$counter       = 1;

			while (username_exists($username)) {
				$username = $username_base . $counter;
				$counter++;
			}

			$user_id = wp_create_user($username, $password, $email);

			if (is_wp_error($user_id)) {
				wp_safe_redirect(add_query_arg('auth_error', rawurlencode($user_id->get_error_message()), $page));
				exit;
			}

			wp_update_user(
				array(
					'ID'           => $user_id,
					'role'         => 'customer',
					'display_name'  => '' !== $full_name ? $full_name : $username,
					'first_name'    => trim((string) current(explode(' ', $full_name)) ?: ''),
				)
			);

			update_user_meta($user_id, 'billing_phone', $phone);
			update_user_meta($user_id, 'cinema_phone', $phone);

			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id);
			wp_safe_redirect(cinema_theme_get_page_url('profile'));
			exit;
		}

		if ('login' === $action) {
			$login_input = sanitize_text_field(wp_unslash($_POST['log'] ?? ''));
			$user_login  = $login_input;
			$user_email  = sanitize_email($login_input);
			$login_user  = $user_email ? get_user_by('email', $user_email) : null;

			if ($login_user instanceof WP_User) {
				$user_login = $login_user->user_login;
			}

			$user = wp_signon(
				array(
					'user_login'    => $user_login,
					'user_password' => (string) wp_unslash($_POST['pwd'] ?? ''),
					'remember'      => ! empty($_POST['rememberme']),
				),
				is_ssl()
			);

			if (is_wp_error($user)) {
				wp_safe_redirect(add_query_arg('auth_error', rawurlencode('Email hoặc mật khẩu không đúng.'), $page));
				exit;
			}

			wp_safe_redirect(cinema_theme_get_page_url('profile'));
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
		'lookupUrl'   => cinema_theme_get_page_url('my-bookings'),
		'profileUrl'  => cinema_theme_get_page_url('profile'),
	);
}

function cinema_theme_get_auth_page_url($action = 'login') {
	$action = 'register' === $action ? 'register' : 'login';

	return add_query_arg('action', $action, cinema_theme_get_page_url('auth'));
}

function cinema_theme_is_seat_selection_page() {
	return is_page('seat-selection') || is_page_template('templates/page-seat-selection.php');
}

function cinema_theme_is_checkout_page() {
	return is_page('checkout') || is_page_template('templates/page-checkout.php');
}

function cinema_theme_is_profile_page() {
	return is_page('profile') || is_page_template('templates/page-profile.php');
}

function cinema_theme_is_lookup_page() {
	return is_page('my-bookings') || is_page_template('templates/page-my-bookings.php');
}

function cinema_theme_get_page_url($slug) {
	$page = get_page_by_path($slug);

	if ($page instanceof WP_Post) {
		return home_url('/?page_id=' . (int) $page->ID);
	}

	return home_url('/' . trim($slug, '/') . '/');
}

function cinema_theme_get_movies( $args = array() ) {
	global $wpdb;

	$defaults = array(
		'posts_per_page' => 9,
		'search_term'    => sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ),
		'movie_status'   => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
		'genre'          => sanitize_text_field( wp_unslash( $_GET['genre'] ?? '' ) ),
	);
	$args = wp_parse_args( $args, $defaults );

	// Try custom table first (new system)
	$table = $wpdb->prefix . 'cinema_movies';
	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;

	if ( $table_exists ) {
		// Build WHERE clauses
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search_term'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search_term'] ) . '%';
			$where[]  = '(title LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['movie_status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['movie_status'];
		}

		if ( ! empty( $args['genre'] ) ) {
			$where[]  = 'genre = %s';
			$params[] = $args['genre'];
		}

		$limit    = absint( $args['posts_per_page'] );
		$params[] = $limit;

		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where );
		$sql .= ' ORDER BY created_at DESC LIMIT %d';

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: array();
	}

	// Fallback: WordPress post types
	$query_args = array(
		'post_type'      => 'movie',
		'post_status'    => 'publish',
		'posts_per_page' => absint( $args['posts_per_page'] ),
	);

	if ( ! empty( $args['search_term'] ) ) {
		$query_args['s'] = $args['search_term'];
	}

	if ( $args['movie_status'] ) {
		$query_args['meta_query'] = array(
			array(
				'key'   => '_cinema_movie_status',
				'value' => $args['movie_status'],
			),
		);
	}

	if ( $args['genre'] ) {
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'movie_genre',
				'field'    => 'slug',
				'terms'    => $args['genre'],
			),
		);
	}

	return new WP_Query( $query_args );
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

function cinema_theme_get_grouped_showtimes( $movie_id ) {
	global $wpdb;

	$now = current_time( 'mysql' );

	// Try custom table first (new system)
	$showtimes_table = $wpdb->prefix . 'cinema_showtimes';
	$table_exists    = $wpdb->get_var( "SHOW TABLES LIKE '{$showtimes_table}'" ) === $showtimes_table;

	if ( $table_exists ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, r.name AS room_name, r.type AS room_type
				FROM {$showtimes_table} s
				LEFT JOIN {$wpdb->prefix}cinema_rooms r ON r.id = s.room_id
				WHERE s.movie_id = %d
				  AND s.status = 'open'
				  AND s.start_datetime > %s
				ORDER BY s.start_datetime ASC",
				intval( $movie_id ),
				$now
			),
			ARRAY_A
		) ?: array();

		$grouped = array();
		$cinema_name = function_exists( 'cinema_booking_get_single_cinema_name' )
			? cinema_booking_get_single_cinema_name()
			: __( 'Rạp đang cập nhật', 'cinema-theme' );

		foreach ( $rows as $row ) {
			$date_key          = wp_date( 'd/m/Y', strtotime( $row['start_datetime'] ) );
			$grouped[$date_key][] = array(
				'id'           => (int) $row['id'],
				'start'        => $row['start_datetime'],
				'end'          => $row['end_datetime'],
				'status'       => $row['status'],
				'cinema_title' => $cinema_name,
				'room_title'   => cinema_theme_present_text( $row['room_name'], __( 'Phòng đang cập nhật', 'cinema-theme' ) ),
				'prices'       => array(
					'normal' => (float) $row['price_normal'],
					'vip'    => (float) $row['price_vip'],
					'couple' => (float) $row['price_couple'],
				),
			);
		}

		return $grouped;
	}

	// Fallback: WordPress post meta
	$showtimes = get_posts(
		array(
			'post_type'      => 'showtime',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_cinema_showtime_movie_id',
					'value' => absint( $movie_id ),
				),
				array(
					'key'   => '_cinema_showtime_status',
					'value' => 'open',
				),
			),
			'orderby'  => 'meta_value',
			'meta_key' => '_cinema_showtime_start_datetime',
			'order'    => 'ASC',
		)
	);

	$grouped = array();

	foreach ( $showtimes as $showtime ) {
		$start    = get_post_meta( $showtime->ID, '_cinema_showtime_start_datetime', true );
		$start_ts = $start ? strtotime( $start ) : false;

		if ( ! $start_ts || $start_ts <= current_time( 'timestamp' ) ) {
			continue;
		}

		$date_key  = wp_date( 'd/m/Y', $start_ts );
		$room_id   = absint( get_post_meta( $showtime->ID, '_cinema_showtime_room_id', true ) );
		$room_title = $room_id ? get_the_title( $room_id ) : '';

		$grouped[$date_key][] = array(
			'id'           => $showtime->ID,
			'start'        => $start,
			'end'          => get_post_meta( $showtime->ID, '_cinema_showtime_end_datetime', true ),
			'status'       => get_post_meta( $showtime->ID, '_cinema_showtime_status', true ),
			'cinema_title' => function_exists( 'cinema_booking_get_single_cinema_name' ) ? cinema_booking_get_single_cinema_name() : __( 'Rạp đang cập nhật', 'cinema-theme' ),
			'room_title'   => cinema_theme_present_text( $room_title, __( 'Phòng đang cập nhật', 'cinema-theme' ) ),
			'prices'       => array(
				'normal' => (float) get_post_meta( $showtime->ID, '_cinema_showtime_price_normal', true ),
				'vip'    => (float) get_post_meta( $showtime->ID, '_cinema_showtime_price_vip', true ),
				'couple' => (float) get_post_meta( $showtime->ID, '_cinema_showtime_price_couple', true ),
			),
		);
	}

	return $grouped;
}

function cinema_theme_count_grouped_showtimes($grouped_showtimes) {
	if (empty($grouped_showtimes) || ! is_array($grouped_showtimes)) {
		return 0;
	}

	$total = 0;

	foreach ($grouped_showtimes as $items) {
		$total += is_array($items) ? count($items) : 0;
	}

	return $total;
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

function cinema_theme_present_text($value, $fallback = '') {
	$value = trim((string) $value);

	return '' !== $value ? $value : (string) $fallback;
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

// ---------------------------------------------------------------------------
// Shared formatting helpers used by the WordPress customer-facing templates.
// ---------------------------------------------------------------------------

if (! function_exists('cinema_datetime')) {
	function cinema_datetime(string $value, string $format = 'd/m/Y H:i'): string {
		if ('' === $value) {
			return '';
		}
		$ts = strtotime($value);
		return $ts ? wp_date($format, $ts) : $value;
	}
}

if (! function_exists('cinema_date_only')) {
	function cinema_date_only(string $value): string {
		return cinema_datetime($value, 'd/m/Y');
	}
}

if (! function_exists('cinema_currency')) {
	function cinema_currency(float $amount): string {
		return number_format($amount, 0, ',', '.') . ' VND';
	}
}

if (! function_exists('cinema_poster_url')) {
	function cinema_poster_url(string $url): string {
		if ('' === $url) {
			return '';
		}
		// If already absolute URL, return as-is
		if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
			return $url;
		}
		return get_template_directory_uri() . '/' . ltrim($url, '/');
	}
}

if (! function_exists('cinema_status_label')) {
	function cinema_status_label(string $status): string {
		$labels = array(
			'now_showing' => 'Đang chiếu',
			'coming_soon' => 'Sắp chiếu',
			'ended'       => 'Đã kết thúc',
			'open'        => 'Mở bán',
			'locked'      => 'Đã khóa',
			'completed'   => 'Đã hoàn thành',
			'pending'     => 'Chờ thanh toán',
			'paid'        => 'Đã thanh toán',
			'cancelled'   => 'Đã hủy',
			'refunded'    => 'Đã hoàn tiền',
		);
		return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
	}
}

if (! function_exists('cinema_badge_class')) {
	function cinema_badge_class(string $status): string {
		$map = array(
			'paid'      => 'is-paid',
			'pending'   => 'is-pending',
			'cancelled' => 'is-cancelled',
			'refunded'  => 'is-refunded',
		);
		return $map[$status] ?? 'is-pending';
	}
}

if (! function_exists('cinema_payment_label')) {
	function cinema_payment_label(string $method): string {
		$labels = array(
			'cash'    => 'Thanh toán tại quầy',
			'counter' => 'Thanh toán tại quầy',
			'vnpay'   => 'VNPay',
			'momo'    => 'MoMo',
		);
		return $labels[$method] ?? ucwords($method);
	}
}

if (! function_exists('cinema_join_non_empty')) {
	function cinema_join_non_empty(array $parts, string $glue = ' / '): string {
		return implode($glue, array_filter($parts, fn($p) => '' !== (string) $p));
	}
}

if (! function_exists('cinema_base_url')) {
	/** Returns absolute URL to a path on this site (WP compat shim). */
	function cinema_base_url(string $path = ''): string {
		return home_url('/' . ltrim($path, '/'));
	}
}


add_action('rest_api_init', static function () {
	register_rest_route('cinema-theme/v1', '/seat-action', array(
		'methods' => WP_REST_Server::CREATABLE,
		'callback' => 'cinema_theme_handle_seat_action',
		'permission_callback' => '__return_true',
	));
});

function cinema_theme_handle_seat_action($request) {
	if (!is_user_logged_in() || !class_exists('Cinema_Booking_System')) {
		return new WP_REST_Response(array('success' => false, 'message' => 'Vui lòng đăng nhập.'), 401);
	}

	$action = sanitize_text_field($request->get_param('action'));
	$showtime_id = absint($request->get_param('showtime_id'));
	$seat_id = absint($request->get_param('seat_id'));
	$user_id = get_current_user_id();

	$manager = Cinema_Booking_System::instance()->seat_manager;
	
	if (!$manager || $showtime_id <= 0) {
		return new WP_REST_Response(array('success' => false, 'message' => 'Thiếu dữ liệu.'), 422);
	}

	try {
		if ('status' === $action) {
			$seat_map = $manager->get_showtime_seat_map($showtime_id, $user_id);
			$locked_ids = array();
			$expires_in = 0;
			
			foreach ($seat_map as $row) {
				foreach ($row as $seat) {
					if ($seat['status'] === 'selected') {
						$locked_ids[] = $seat['id'];
						$expires_in = 600; // default for simplicity if we can't extract it easily
					}
				}
			}
			
			return new WP_REST_Response(array(
				'success' => true,
				'lock_expires_at' => $expires_in > 0 ? time() + $expires_in : 0,
				'seconds_remaining' => $expires_in,
				'locked_seat_ids' => $locked_ids,
			), 200);
		}

		if ('lock' === $action) {
			$result = $manager->lock_seats($showtime_id, array($seat_id), $user_id);
			if (is_wp_error($result)) {
				return new WP_REST_Response(array('success' => false, 'message' => $result->get_error_message()), 422);
			}

			$seat_map = $manager->get_showtime_seat_map($showtime_id, $user_id);
			$locked_ids = array();
			foreach ($seat_map as $row) {
				foreach ($row as $seat) {
					if ($seat['status'] === 'selected') {
						$locked_ids[] = $seat['id'];
					}
				}
			}

			return new WP_REST_Response(array(
				'success' => true,
				'message' => 'Đã giữ ghế thành công.',
				'lock_expires_at' => strtotime($result['expires_at']),
				'seconds_remaining' => $result['expires_in'],
				'locked_seat_ids' => $locked_ids,
			), 200);
		}

		if ('unlock' === $action) {
			$manager->cancel_seat_locks($showtime_id, array($seat_id), $user_id);
			
			$seat_map = $manager->get_showtime_seat_map($showtime_id, $user_id);
			$locked_ids = array();
			foreach ($seat_map as $row) {
				foreach ($row as $seat) {
					if ($seat['status'] === 'selected') {
						$locked_ids[] = $seat['id'];
					}
				}
			}
			
			return new WP_REST_Response(array(
				'success' => true,
				'message' => 'Đã bỏ giữ ghế.',
				'lock_expires_at' => empty($locked_ids) ? 0 : time() + 600,
				'seconds_remaining' => empty($locked_ids) ? 0 : 600,
				'locked_seat_ids' => $locked_ids,
			), 200);
		}

		if ('release-all' === $action) {
			// cancel all locks for this user
			global $wpdb;
			$table = $wpdb->prefix . 'cinema_seat_bookings';
			$wpdb->query($wpdb->prepare(
				"UPDATE {$table} SET status = 'cancelled' WHERE showtime_id = %d AND user_id = %d AND status = 'pending'",
				$showtime_id, $user_id
			));

			return new WP_REST_Response(array(
				'success' => true,
				'message' => 'Đã hủy giữ ghế.',
				'lock_expires_at' => 0,
				'seconds_remaining' => 0,
				'locked_seat_ids' => array(),
			), 200);
		}
	} catch (Throwable $e) {
		return new WP_REST_Response(array('success' => false, 'message' => $e->getMessage()), 422);
	}

	return new WP_REST_Response(array('success' => false, 'message' => 'Hành động không hợp lệ.'), 422);
}
