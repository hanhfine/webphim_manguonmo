<?php
/**
 * Plugin Name: Cinema Booking System
 * Description: Booking system for movies, screening rooms, showtimes, seats, payments, and tickets.
 * Version: 0.1.0
 * Author: Codex
 * Text Domain: cinema-booking
 */

if (! defined('ABSPATH')) {
	exit;
}

define('CINEMA_BOOKING_VERSION', '0.1.0');
define('CINEMA_BOOKING_FILE', __FILE__);
define('CINEMA_BOOKING_PATH', plugin_dir_path(__FILE__));
define('CINEMA_BOOKING_URL', plugin_dir_url(__FILE__));

function cinema_booking_get_single_cinema_name() {
	$name = get_option('cinema_booking_single_cinema_name', '');
	$name = is_string($name) ? trim($name) : '';

	if ($name) {
		return $name;
	}

	$blog_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

	return $blog_name ? $blog_name : __('Main Cinema', 'cinema-booking');
}

function cinema_booking_get_single_cinema_address() {
	$address = get_option('cinema_booking_single_cinema_address', '');

	return is_string($address) ? trim($address) : '';
}

function cinema_booking_get_single_cinema_city() {
	$city = get_option('cinema_booking_single_cinema_city', '');

	return is_string($city) ? trim($city) : '';
}

function cinema_booking_generate_integration_key() {
	return wp_generate_password(40, false, false);
}

function cinema_booking_get_integration_key() {
	$key = get_option('cinema_booking_integration_key', '');

	if (! is_string($key) || '' === trim($key)) {
		$key = cinema_booking_generate_integration_key();
		update_option('cinema_booking_integration_key', $key, false);
	}

	return $key;
}

require_once CINEMA_BOOKING_PATH . 'includes/class-database.php';
require_once CINEMA_BOOKING_PATH . 'includes/class-seat-manager.php';
require_once CINEMA_BOOKING_PATH . 'includes/class-booking-manager.php';
require_once CINEMA_BOOKING_PATH . 'includes/class-payment-handler.php';
require_once CINEMA_BOOKING_PATH . 'includes/class-ticket-generator.php';
require_once CINEMA_BOOKING_PATH . 'includes/class-post-types.php';
require_once CINEMA_BOOKING_PATH . 'includes/class-showtime-cron.php';
require_once CINEMA_BOOKING_PATH . 'admin/class-admin-menu.php';
require_once CINEMA_BOOKING_PATH . 'api/class-rest-api.php';

final class Cinema_Booking_System {
	/**
	 * @var Cinema_Booking_System|null
	 */
	private static $instance = null;

	/**
	 * @var Cinema_Booking_Seat_Manager
	 */
	public $seat_manager;

	/**
	 * @var Cinema_Booking_Booking_Manager
	 */
	public $booking_manager;

	/**
	 * @var Cinema_Booking_Payment_Handler
	 */
	public $payment_handler;

	/**
	 * @var Cinema_Booking_Ticket_Generator
	 */
	public $ticket_generator;

	/**
	 * @var Cinema_Booking_Post_Types
	 */
	public $post_types;

	/**
	 * @var Cinema_Booking_Showtime_Cron
	 */
	public $showtime_cron;

	/**
	 * @var Cinema_Booking_Admin_Menu
	 */
	public $admin_menu;

	/**
	 * @var Cinema_Booking_REST_API
	 */
	public $rest_api;

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->maybe_seed_defaults();
		$this->seat_manager     = new Cinema_Booking_Seat_Manager();
		$this->booking_manager  = new Cinema_Booking_Booking_Manager($this->seat_manager);
		$this->ticket_generator = new Cinema_Booking_Ticket_Generator($this->booking_manager);
		$this->payment_handler  = new Cinema_Booking_Payment_Handler($this->booking_manager, $this->ticket_generator);
		$this->post_types       = new Cinema_Booking_Post_Types($this->seat_manager);
		$this->showtime_cron    = new Cinema_Booking_Showtime_Cron();
		$this->admin_menu       = new Cinema_Booking_Admin_Menu($this->booking_manager);
		$this->rest_api         = new Cinema_Booking_REST_API(
			$this->seat_manager,
			$this->booking_manager,
			$this->payment_handler
		);

		add_action('init', array($this, 'register_roles'));
	}

	public function register_roles() {
		add_role(
			'cinema_admin',
			__('Cinema Admin', 'cinema-booking'),
			array(
				'read'                 => true,
				'upload_files'         => true,
				'edit_posts'           => true,
				'edit_others_posts'    => true,
				'publish_posts'        => true,
				'delete_posts'         => true,
				'manage_options'       => true,
				'manage_categories'    => true,
				'edit_theme_options'   => false,
				'manage_woocommerce'   => true,
			)
		);

		add_role(
			'cinema_staff',
			__('Cinema Staff', 'cinema-booking'),
			array(
				'read'                 => true,
				'upload_files'         => true,
				'edit_posts'           => true,
				'publish_posts'        => true,
				'delete_posts'         => false,
				'manage_categories'    => true,
			)
		);

		add_role(
			'customer',
			__('Customer', 'cinema-booking'),
			array(
				'read' => true,
			)
		);
	}

	private function maybe_seed_defaults() {
		if ('' === trim((string) get_option('cinema_booking_single_cinema_name', ''))) {
			update_option('cinema_booking_single_cinema_name', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), false);
		}

		cinema_booking_get_integration_key();
	}

	public static function activate() {
		$plugin = self::instance();

		Cinema_Booking_Database::install();
		$plugin->register_roles();
		$plugin->maybe_seed_defaults();
		$plugin->post_types->register_content_types();
		Cinema_Booking_Showtime_Cron::schedule();

		flush_rewrite_rules();
	}

	public static function deactivate() {
		Cinema_Booking_Showtime_Cron::unschedule();
		flush_rewrite_rules();
	}
}

register_activation_hook(CINEMA_BOOKING_FILE, array('Cinema_Booking_System', 'activate'));
register_deactivation_hook(CINEMA_BOOKING_FILE, array('Cinema_Booking_System', 'deactivate'));

add_action(
	'plugins_loaded',
	static function () {
		Cinema_Booking_System::instance();
	}
);
