<?php

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap cinema-admin-wrap">
	<h1><?php esc_html_e('Timeline suất chiếu', 'cinema-booking'); ?></h1>

	<form method="get" class="cinema-filter-bar">
		<input type="hidden" name="page" value="cinema-booking-timeline">
		<label>
			<?php esc_html_e('Ngày chiếu', 'cinema-booking'); ?>
			<input type="date" name="date" value="<?php echo esc_attr($selected_date); ?>">
		</label>
		<button type="submit" class="button button-primary"><?php esc_html_e('Lọc lịch', 'cinema-booking'); ?></button>
	</form>

	<div class="cinema-panel">
		<?php foreach ($rows as $row) : ?>
			<div class="cinema-timeline-row">
				<div class="cinema-timeline-room">
					<strong><?php echo esc_html($row['movie']->post_title); ?></strong>
					<span><?php echo esc_html($row['cinema_label']); ?></span>
				</div>
				<div class="cinema-timeline-items">
					<?php if (empty($row['items'])) : ?>
						<div class="cinema-timeline-empty"><?php esc_html_e('Chưa có suất chiếu', 'cinema-booking'); ?></div>
					<?php endif; ?>
					<?php foreach ($row['items'] as $item) : ?>
						<div class="cinema-timeline-item cinema-status-<?php echo esc_attr($item['status']); ?>">
							<strong><?php echo esc_html($item['title']); ?></strong>
							<span><?php echo esc_html($item['start']); ?> - <?php echo esc_html($item['end']); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
