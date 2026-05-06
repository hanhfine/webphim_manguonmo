<?php

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap cinema-admin-wrap">
	<div class="cinema-admin-hero">
		<div>
			<p class="cinema-admin-kicker"><?php esc_html_e('Bảng điều khiển MYCINEMA', 'cinema-booking'); ?></p>
			<h1><?php esc_html_e('Quản lý đặt vé xem phim bằng WordPress', 'cinema-booking'); ?></h1>
			<p><?php esc_html_e('Admin dùng plugin để quản lý phim, phòng, sơ đồ ghế, suất chiếu, đơn đặt vé và doanh thu. Khách hàng đặt vé ngay trên theme WordPress.', 'cinema-booking'); ?></p>
		</div>
		<a class="cinema-admin-cta" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener">
			<?php esc_html_e('Xem web khách', 'cinema-booking'); ?>
		</a>
	</div>

	<div class="cinema-kpi-grid">
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Phim', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((int) $stats['total_movies'])); ?></strong>
		</div>
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Suất đang mở bán', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((int) $stats['open_showtimes'])); ?></strong>
		</div>
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Đơn đặt vé', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((int) $stats['total_bookings'])); ?></strong>
		</div>
		<div class="cinema-kpi-card">
			<span><?php esc_html_e('Doanh thu đã thanh toán', 'cinema-booking'); ?></span>
			<strong><?php echo esc_html(number_format_i18n((float) $stats['total_revenue'], 0)); ?></strong>
		</div>
	</div>

	<div class="cinema-action-grid">
		<?php foreach ($quick_actions as $action) : ?>
			<a class="cinema-action-card" href="<?php echo esc_url($action['url']); ?>">
				<strong><?php echo esc_html($action['title']); ?></strong>
				<span><?php echo esc_html($action['description']); ?></span>
				<em><?php echo esc_html($action['button']); ?></em>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="cinema-panel">
		<h2><?php esc_html_e('Xu hướng đặt vé', 'cinema-booking'); ?></h2>
		<canvas id="cinema-booking-chart" data-chart="<?php echo esc_attr(wp_json_encode($stats['booking_chart_data'])); ?>"></canvas>
	</div>
</div>
