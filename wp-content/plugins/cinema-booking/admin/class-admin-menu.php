<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Admin_Menu {
	/**
	 * @var Cinema_Booking_Booking_Manager
	 */
	private $booking_manager;

	public function __construct($booking_manager) {
		$this->booking_manager = $booking_manager;

		add_action('admin_menu', array($this, 'register_menu_pages'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	public function register_menu_pages() {
		add_menu_page(
			__('MYCINEMA Admin', 'cinema-booking'),
			__('MYCINEMA', 'cinema-booking'),
			'edit_posts',
			'cinema-booking-dashboard',
			array($this, 'render_dashboard_page'),
			'dashicons-format-video',
			25
		);

		add_submenu_page(
			'cinema-booking-dashboard',
			__('Tổng quan', 'cinema-booking'),
			__('Tổng quan', 'cinema-booking'),
			'edit_posts',
			'cinema-booking-dashboard',
			array($this, 'render_dashboard_page')
		);

		add_submenu_page(
			'cinema-booking-dashboard',
			__('Timeline suất chiếu', 'cinema-booking'),
			__('Timeline suất chiếu', 'cinema-booking'),
			'edit_posts',
			'cinema-booking-timeline',
			array($this, 'render_timeline_page')
		);

		add_submenu_page(
			'cinema-booking-dashboard',
			__('Doanh thu', 'cinema-booking'),
			__('Doanh thu', 'cinema-booking'),
			'edit_posts',
			'cinema-booking-reports',
			array($this, 'render_reports_page')
		);

		add_submenu_page(
			'cinema-booking-dashboard',
			__('Cài đặt rạp', 'cinema-booking'),
			__('Cài đặt rạp', 'cinema-booking'),
			'manage_options',
			'cinema-booking-settings',
			array($this, 'render_settings_page')
		);
	}

	public function enqueue_assets($hook) {
		if (false === strpos($hook, 'cinema-booking')) {
			return;
		}

		wp_enqueue_style('cinema-booking-admin', CINEMA_BOOKING_URL . 'admin/assets/admin.css', array(), CINEMA_BOOKING_VERSION);
		wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.2', true);
		wp_enqueue_script('cinema-booking-admin', CINEMA_BOOKING_URL . 'admin/assets/admin.js', array('chart-js'), CINEMA_BOOKING_VERSION, true);
	}

	public function render_dashboard_page() {
		$stats = $this->get_dashboard_stats();
		$quick_actions = $this->get_dashboard_actions();
		include CINEMA_BOOKING_PATH . 'admin/views/dashboard.php';
	}

	public function render_timeline_page() {
		$selected_date = sanitize_text_field(wp_unslash($_GET['date'] ?? wp_date('Y-m-d')));
		$rows          = $this->get_timeline_rows($selected_date);
		include CINEMA_BOOKING_PATH . 'admin/views/timeline-grid.php';
	}

	public function render_reports_page() {
		$report_rows = $this->get_report_rows();
		include CINEMA_BOOKING_PATH . 'admin/views/reports.php';
	}

	public function render_settings_page() {
		if ('POST' === $_SERVER['REQUEST_METHOD']) {
			$this->handle_settings_submit();
		}

		$settings = array(
			'cinema_name' => cinema_booking_get_single_cinema_name(),
			'address'     => cinema_booking_get_single_cinema_address(),
			'city'        => cinema_booking_get_single_cinema_city(),
			'api_key'     => cinema_booking_get_integration_key(),
			'api_base'    => rest_url('cinema/v1/integration'),
		);

		include CINEMA_BOOKING_PATH . 'admin/views/settings.php';
	}

	private function get_dashboard_stats() {
		global $wpdb;

		$payments_table = $wpdb->prefix . 'cinema_payments';
		$movie_counts   = wp_count_posts('movie');
		$booking_counts = wp_count_posts('booking');

		return array(
			'total_movies'       => isset($movie_counts->publish) ? (int) $movie_counts->publish : 0,
			'open_showtimes'     => count(
				get_posts(
					array(
						'post_type'      => 'showtime',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_key'       => '_cinema_showtime_status',
						'meta_value'     => 'open',
					)
				)
			),
			'total_bookings'     => isset($booking_counts->publish) ? (int) $booking_counts->publish : 0,
			'total_revenue'      => (float) $wpdb->get_var(
				"SELECT COALESCE(SUM(total_amount), 0) FROM {$payments_table} WHERE payment_status = 'success'"
			),
			'booking_chart_data' => $this->get_monthly_booking_chart_data(),
		);
	}

	private function get_monthly_booking_chart_data() {
		global $wpdb;

		$months = array();

		for ($i = 5; $i >= 0; $i--) {
			$months[] = wp_date('Y-m', strtotime("-{$i} months"));
		}

		$data = array_fill_keys($months, 0);

		$query = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS booking_month, COUNT(ID) AS total
				FROM {$wpdb->posts}
				WHERE post_type = %s
					AND post_status = %s
					AND post_date >= %s
				GROUP BY booking_month
				ORDER BY booking_month ASC",
				'booking',
				'publish',
				wp_date('Y-m-01', strtotime('-5 months'))
			),
			ARRAY_A
		);

		foreach ($query as $row) {
			$data[$row['booking_month']] = (int) $row['total'];
		}

		return array(
			'labels' => array_keys($data),
			'values' => array_values($data),
		);
	}

	private function get_timeline_rows($selected_date) {
		$movies = get_posts(
			array(
				'post_type'      => 'movie',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$timeline = array();

		foreach ($movies as $movie) {
			$showtimes = get_posts(
				array(
					'post_type'      => 'showtime',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'   => '_cinema_showtime_movie_id',
							'value' => $movie->ID,
						),
						array(
							'key'     => '_cinema_showtime_start_datetime',
							'value'   => array($selected_date . ' 00:00:00', $selected_date . ' 23:59:59'),
							'compare' => 'BETWEEN',
							'type'    => 'DATETIME',
						),
					),
				)
			);

			$timeline[] = array(
				'movie'        => $movie,
				'cinema_label' => $this->get_single_cinema_name(),
				'items'        => array_map(
					static function ($showtime) {
						$room_id = absint(get_post_meta($showtime->ID, '_cinema_showtime_room_id', true));
						$title   = get_the_title($room_id);

						return array(
							'id'       => $showtime->ID,
							'title'    => $title ? $title : __('Phòng đang cập nhật', 'cinema-booking'),
							'status'   => get_post_meta($showtime->ID, '_cinema_showtime_status', true),
							'start'    => get_post_meta($showtime->ID, '_cinema_showtime_start_datetime', true),
							'end'      => get_post_meta($showtime->ID, '_cinema_showtime_end_datetime', true),
						);
					},
					$showtimes
				),
			);
		}

		return $timeline;
	}

	private function get_report_rows() {
		$bookings = get_posts(
			array(
				'post_type'      => 'booking',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$movie_report = array();

		foreach ($bookings as $booking) {
			$booking_payload = $this->booking_manager->get_booking_payload($booking->ID);
			$movie_title     = $booking_payload['movie']['title'] ?? __('Unknown', 'cinema-booking');

			if (! isset($movie_report[$movie_title])) {
				$movie_report[$movie_title] = array(
					'total_bookings' => 0,
					'total_seats'    => 0,
					'total_revenue'  => 0,
				);
			}

			$movie_report[$movie_title]['total_bookings']++;
			$movie_report[$movie_title]['total_seats']   += count($booking_payload['seats'] ?? array());
			$movie_report[$movie_title]['total_revenue'] += (float) ($booking_payload['total_amount'] ?? 0);
		}

		return $movie_report;
	}

	private function get_dashboard_actions() {
		return array(
			array(
				'title'       => __('Quản lý phim', 'cinema-booking'),
				'description' => __('Thêm phim, poster, trailer, thời lượng và trạng thái chiếu.', 'cinema-booking'),
				'url'         => admin_url('edit.php?post_type=movie'),
				'button'      => __('Mở danh sách phim', 'cinema-booking'),
			),
			array(
				'title'       => __('Sơ đồ phòng chiếu', 'cinema-booking'),
				'description' => __('Tạo phòng, số hàng ghế, ghế VIP, couple và ghế hỏng.', 'cinema-booking'),
				'url'         => admin_url('edit.php?post_type=room'),
				'button'      => __('Quản lý phòng', 'cinema-booking'),
			),
			array(
				'title'       => __('Suất chiếu', 'cinema-booking'),
				'description' => __('Gắn phim với phòng, giờ chiếu, giá vé và trạng thái mở bán.', 'cinema-booking'),
				'url'         => admin_url('edit.php?post_type=showtime'),
				'button'      => __('Quản lý suất chiếu', 'cinema-booking'),
			),
			array(
				'title'       => __('Đơn đặt vé', 'cinema-booking'),
				'description' => __('Theo dõi mã vé, khách hàng, ghế đã đặt và thanh toán.', 'cinema-booking'),
				'url'         => admin_url('edit.php?post_type=booking'),
				'button'      => __('Xem đơn đặt vé', 'cinema-booking'),
			),
			array(
				'title'       => __('Web khách hàng', 'cinema-booking'),
				'description' => __('Mở giao diện đặt vé chạy trực tiếp bằng theme WordPress.', 'cinema-booking'),
				'url'         => home_url('/'),
				'button'      => __('Xem website', 'cinema-booking'),
			),
		);
	}

	private function get_single_cinema_name() {
		return cinema_booking_get_single_cinema_name();
	}

	private function handle_settings_submit() {
		if (! current_user_can('manage_options')) {
			return;
		}

		check_admin_referer('cinema_booking_settings');

		if (isset($_POST['cinema_booking_regenerate_key'])) {
			update_option('cinema_booking_integration_key', cinema_booking_generate_integration_key(), false);
		}

		update_option('cinema_booking_single_cinema_name', sanitize_text_field(wp_unslash($_POST['cinema_booking_single_cinema_name'] ?? '')), false);
		update_option('cinema_booking_single_cinema_address', sanitize_text_field(wp_unslash($_POST['cinema_booking_single_cinema_address'] ?? '')), false);
		update_option('cinema_booking_single_cinema_city', sanitize_text_field(wp_unslash($_POST['cinema_booking_single_cinema_city'] ?? '')), false);

		add_settings_error('cinema-booking-settings', 'settings_updated', __('Đã lưu cài đặt.', 'cinema-booking'), 'updated');
	}
}
