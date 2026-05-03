<?php
/*
Template Name: Booking Success
*/

get_header();

$booking_id = absint(wp_unslash($_GET['booking'] ?? 0));
$payload    = $booking_id ? cinema_theme_get_booking_payload($booking_id) : array();
$ticket_pdf = $booking_id ? cinema_theme_get_ticket_download($booking_id, true) : array();
$delivery   = $booking_id ? cinema_theme_get_ticket_delivery_meta($booking_id) : array();
$ticket_html = $booking_id ? cinema_theme_get_ticket_html($booking_id) : '';
?>
<main class="cinema-shell">
	<section class="cinema-page-hero">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('Booking Complete', 'cinema-theme'); ?></p>
			<h1><?php esc_html_e('Your ticket is ready.', 'cinema-theme'); ?></h1>
		</div>
	</section>

	<div class="cinema-panel-stack">
		<?php if (empty($payload)) : ?>
			<div class="cinema-empty-card">
				<h3><?php esc_html_e('Booking not found.', 'cinema-theme'); ?></h3>
				<p><?php esc_html_e('Use the My Tickets page to review your booking history after checkout.', 'cinema-theme'); ?></p>
			</div>
		<?php else : ?>
			<article class="cinema-ticket-card">
				<div class="cinema-ticket-header">
					<div>
						<p class="cinema-kicker"><?php esc_html_e('Booking Code', 'cinema-theme'); ?></p>
						<h2><?php echo esc_html($payload['booking_code']); ?></h2>
					</div>
					<span class="cinema-badge"><?php echo esc_html(strtoupper($payload['status'] ?: 'confirmed')); ?></span>
				</div>
				<div class="cinema-detail-grid">
					<div><strong><?php esc_html_e('Movie', 'cinema-theme'); ?></strong><span><?php echo esc_html($payload['movie']['title'] ?? ''); ?></span></div>
					<div><strong><?php esc_html_e('Cinema', 'cinema-theme'); ?></strong><span><?php echo esc_html($payload['cinema']['title'] ?? ''); ?></span></div>
					<div><strong><?php esc_html_e('Room', 'cinema-theme'); ?></strong><span><?php echo esc_html($payload['room']['title'] ?? ''); ?></span></div>
					<div><strong><?php esc_html_e('Showtime', 'cinema-theme'); ?></strong><span><?php echo esc_html($payload['showtime']['start_datetime'] ?? ''); ?></span></div>
					<div><strong><?php esc_html_e('Seats', 'cinema-theme'); ?></strong><span><?php echo esc_html(implode(', ', wp_list_pluck($payload['seats'], 'label'))); ?></span></div>
					<div><strong><?php esc_html_e('Total', 'cinema-theme'); ?></strong><span><?php echo esc_html(number_format_i18n((float) ($payload['total_amount'] ?? 0), 0)); ?></span></div>
					<div><strong><?php esc_html_e('Payment Status', 'cinema-theme'); ?></strong><span><?php echo esc_html(strtoupper((string) ($payload['payment_status'] ?? 'pending'))); ?></span></div>
					<div><strong><?php esc_html_e('Payment Method', 'cinema-theme'); ?></strong><span><?php echo esc_html(strtoupper((string) ($payload['payment_method'] ?? 'cash'))); ?></span></div>
				</div>
				<div class="cinema-ticket-actions">
					<a class="cinema-ghost-link" href="<?php echo esc_url(cinema_theme_get_page_url('my-bookings')); ?>"><?php esc_html_e('View My Tickets', 'cinema-theme'); ?></a>
					<?php if (! empty($ticket_pdf['available']) && ! empty($ticket_pdf['url'])) : ?>
						<a class="cinema-cta" href="<?php echo esc_url($ticket_pdf['url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Download PDF', 'cinema-theme'); ?></a>
					<?php endif; ?>
				</div>
				<?php if (! empty($delivery['emailed_at'])) : ?>
					<p class="cinema-status-copy"><?php echo esc_html(sprintf(__('Ticket email sent at %s', 'cinema-theme'), $delivery['emailed_at'])); ?></p>
				<?php endif; ?>
			</article>

			<?php if ($ticket_html) : ?>
				<section class="cinema-ticket-preview">
					<?php echo $ticket_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
