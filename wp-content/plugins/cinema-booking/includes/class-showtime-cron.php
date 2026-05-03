<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Showtime_Cron {
	const HOOK = 'cinema_booking_update_showtime_statuses';

	public function __construct() {
		add_filter('cron_schedules', array($this, 'register_interval'));
		add_action(self::HOOK, array($this, 'process_showtimes'));
	}

	public function register_interval($schedules) {
		$schedules['cinema_booking_five_minutes'] = array(
			'interval' => 300,
			'display'  => __('Every 5 minutes', 'cinema-booking'),
		);

		return $schedules;
	}

	public function process_showtimes() {
		$showtime_ids = get_posts(
			array(
				'post_type'      => 'showtime',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_cinema_showtime_status',
						'value'   => array('open', 'locked'),
						'compare' => 'IN',
					),
				),
			)
		);

		$now_timestamp = current_time('timestamp');

		foreach ($showtime_ids as $showtime_id) {
			$start_datetime = get_post_meta($showtime_id, '_cinema_showtime_start_datetime', true);
			$end_datetime   = get_post_meta($showtime_id, '_cinema_showtime_end_datetime', true);
			$status         = get_post_meta($showtime_id, '_cinema_showtime_status', true);
			$start_ts       = $start_datetime ? strtotime($start_datetime) : 0;
			$end_ts         = $end_datetime ? strtotime($end_datetime) : 0;

			if ('open' === $status && $start_ts && ($start_ts - (15 * MINUTE_IN_SECONDS)) <= $now_timestamp) {
				update_post_meta($showtime_id, '_cinema_showtime_status', 'locked');
				$status = 'locked';
			}

			if (in_array($status, array('open', 'locked'), true) && $end_ts && $end_ts <= $now_timestamp) {
				update_post_meta($showtime_id, '_cinema_showtime_status', 'completed');
			}
		}
	}

	public static function schedule() {
		if (! wp_next_scheduled(self::HOOK)) {
			wp_schedule_event(time() + 300, 'cinema_booking_five_minutes', self::HOOK);
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled(self::HOOK);

		if ($timestamp) {
			wp_unschedule_event($timestamp, self::HOOK);
		}
	}
}
