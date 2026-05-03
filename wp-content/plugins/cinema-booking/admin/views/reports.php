<?php

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap cinema-admin-wrap">
	<h1><?php esc_html_e('Revenue Reports', 'cinema-booking'); ?></h1>

	<div class="cinema-panel">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Movie', 'cinema-booking'); ?></th>
					<th><?php esc_html_e('Bookings', 'cinema-booking'); ?></th>
					<th><?php esc_html_e('Seats Sold', 'cinema-booking'); ?></th>
					<th><?php esc_html_e('Revenue', 'cinema-booking'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($report_rows)) : ?>
					<tr>
						<td colspan="4"><?php esc_html_e('No report data yet.', 'cinema-booking'); ?></td>
					</tr>
				<?php endif; ?>
				<?php foreach ($report_rows as $movie_title => $row) : ?>
					<tr>
						<td><?php echo esc_html($movie_title); ?></td>
						<td><?php echo esc_html(number_format_i18n((int) $row['total_bookings'])); ?></td>
						<td><?php echo esc_html(number_format_i18n((int) $row['total_seats'])); ?></td>
						<td><?php echo esc_html(number_format_i18n((float) $row['total_revenue'], 0)); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
