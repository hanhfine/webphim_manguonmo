<?php

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap cinema-admin-wrap">
	<h1><?php esc_html_e('Cinema Booking Dashboard', 'cinema-booking'); ?></h1>

	<div class="cinema-kpi-grid">
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Movies', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((int) $stats['total_movies'])); ?></strong>
		</div>
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Open Showtimes', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((int) $stats['open_showtimes'])); ?></strong>
		</div>
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Bookings', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((int) $stats['total_bookings'])); ?></strong>
		</div>
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Revenue', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((float) $stats['total_revenue'], 0)); ?></strong>
		</div>
	</div>

	<div class="cinema-panel">
		<h2><?php esc_html_e('Bookings Trend', 'cinema-booking'); ?></h2>
		<canvas id="cinema-booking-chart" data-chart="<?php echo esc_attr(wp_json_encode($stats['booking_chart_data'])); ?>"></canvas>
	</div>
</div>
