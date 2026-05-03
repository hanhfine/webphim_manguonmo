<?php

if (! defined('ABSPATH')) {
	exit;
}

settings_errors('cinema-booking-settings');
?>
<div class="wrap cinema-admin-wrap">
	<h1><?php esc_html_e('Cinema Settings & Integration', 'cinema-booking'); ?></h1>

	<form method="post" class="cinema-panel" style="max-width: 960px;">
		<?php wp_nonce_field('cinema_booking_settings'); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="cinema_booking_single_cinema_name"><?php esc_html_e('Cinema Name', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="regular-text" id="cinema_booking_single_cinema_name" name="cinema_booking_single_cinema_name" value="<?php echo esc_attr($settings['cinema_name']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cinema_booking_single_cinema_address"><?php esc_html_e('Address', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="regular-text" id="cinema_booking_single_cinema_address" name="cinema_booking_single_cinema_address" value="<?php echo esc_attr($settings['address']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cinema_booking_single_cinema_city"><?php esc_html_e('City', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="regular-text" id="cinema_booking_single_cinema_city" name="cinema_booking_single_cinema_city" value="<?php echo esc_attr($settings['city']); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Integration API Base', 'cinema-booking'); ?></th>
				<td>
					<code><?php echo esc_html($settings['api_base']); ?></code>
					<p class="description"><?php esc_html_e('Use this base URL in the customer-facing PHP website so it can read movies, showtimes, seats, and bookings from this plugin.', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Integration API Key', 'cinema-booking'); ?></th>
				<td>
					<input type="text" class="large-text code" readonly value="<?php echo esc_attr($settings['api_key']); ?>">
					<p class="description"><?php esc_html_e('Store this key in the customer-facing PHP app configuration. Requests without this key will be rejected.', 'cinema-booking'); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'cinema-booking'); ?></button>
			<button type="submit" class="button" name="cinema_booking_regenerate_key" value="1"><?php esc_html_e('Regenerate API Key', 'cinema-booking'); ?></button>
		</p>
	</form>
</div>
