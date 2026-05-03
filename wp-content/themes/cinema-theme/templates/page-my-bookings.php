<?php
/*
Template Name: My Bookings
*/

get_header();

$manager  = cinema_theme_get_booking_manager();
$bookings = is_user_logged_in() && $manager ? $manager->get_user_bookings(get_current_user_id(), 50) : array();
$user     = wp_get_current_user();
?>
<main class="cinema-shell">
	<section class="cinema-page-hero">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('My Account', 'cinema-theme'); ?></p>
			<h1><?php echo esc_html(is_user_logged_in() ? $user->display_name : __('Customer Tickets', 'cinema-theme')); ?></h1>
			<p><?php echo esc_html(is_user_logged_in() ? $user->user_email : __('Sign in to access your ticket history and profile details.', 'cinema-theme')); ?></p>
		</div>
	</section>

	<?php if (! is_user_logged_in()) : ?>
		<div class="cinema-empty-card">
			<h3><?php esc_html_e('Please log in to view bookings.', 'cinema-theme'); ?></h3>
			<p><a class="cinema-cta" href="<?php echo esc_url(cinema_theme_get_page_url('auth')); ?>"><?php esc_html_e('Open Login / Register', 'cinema-theme'); ?></a></p>
		</div>
	<?php else : ?>
		<section class="cinema-booking-history">
			<?php if (empty($bookings)) : ?>
				<div class="cinema-empty-card">
					<h3><?php esc_html_e('No bookings yet.', 'cinema-theme'); ?></h3>
					<p><?php esc_html_e('Once you complete a booking, your e-ticket history will appear here.', 'cinema-theme'); ?></p>
				</div>
			<?php endif; ?>

			<?php foreach ($bookings as $booking) : ?>
				<?php $ticket_pdf = cinema_theme_get_ticket_download($booking['booking_id'], false); ?>
				<?php $delivery = cinema_theme_get_ticket_delivery_meta($booking['booking_id']); ?>
				<article class="cinema-history-card">
					<div class="cinema-ticket-header">
						<div>
							<p class="cinema-kicker"><?php esc_html_e('Booking Code', 'cinema-theme'); ?></p>
							<h2><?php echo esc_html($booking['booking_code']); ?></h2>
						</div>
						<a class="cinema-ghost-link" href="<?php echo esc_url(add_query_arg('booking', $booking['booking_id'], cinema_theme_get_page_url('booking-success'))); ?>">
							<?php esc_html_e('Open Ticket', 'cinema-theme'); ?>
						</a>
					</div>
					<div class="cinema-detail-grid">
						<div><strong><?php esc_html_e('Movie', 'cinema-theme'); ?></strong><span><?php echo esc_html($booking['movie']['title'] ?? ''); ?></span></div>
						<div><strong><?php esc_html_e('Showtime', 'cinema-theme'); ?></strong><span><?php echo esc_html($booking['showtime']['start_datetime'] ?? ''); ?></span></div>
						<div><strong><?php esc_html_e('Seats', 'cinema-theme'); ?></strong><span><?php echo esc_html(implode(', ', wp_list_pluck($booking['seats'], 'label'))); ?></span></div>
						<div><strong><?php esc_html_e('Total', 'cinema-theme'); ?></strong><span><?php echo esc_html(number_format_i18n((float) ($booking['total_amount'] ?? 0), 0)); ?></span></div>
						<div><strong><?php esc_html_e('Payment Status', 'cinema-theme'); ?></strong><span><?php echo esc_html(strtoupper((string) ($booking['payment_status'] ?? 'pending'))); ?></span></div>
						<div><strong><?php esc_html_e('Payment Method', 'cinema-theme'); ?></strong><span><?php echo esc_html(strtoupper((string) ($booking['payment_method'] ?? 'cash'))); ?></span></div>
					</div>
					<div class="cinema-ticket-actions">
						<?php if (! empty($ticket_pdf['available']) && ! empty($ticket_pdf['url'])) : ?>
							<a class="cinema-ghost-link" href="<?php echo esc_url($ticket_pdf['url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Download PDF', 'cinema-theme'); ?></a>
						<?php endif; ?>
						<?php if (! empty($delivery['emailed_at'])) : ?>
							<span class="cinema-status-copy"><?php echo esc_html(sprintf(__('Emailed at %s', 'cinema-theme'), $delivery['emailed_at'])); ?></span>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
</main>
<?php
get_footer();
