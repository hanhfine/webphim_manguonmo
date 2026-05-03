<?php
/*
Template Name: Checkout
*/

get_header();
?>
<main class="cinema-shell">
	<section class="cinema-page-hero">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('Checkout', 'cinema-theme'); ?></p>
			<h1><?php esc_html_e('Confirm payment and issue the ticket.', 'cinema-theme'); ?></h1>
		</div>
	</section>

	<div class="cinema-booking-shell" data-cinema-checkout-shell>
		<section class="cinema-booking-panel">
			<h2><?php esc_html_e('Payment Method', 'cinema-theme'); ?></h2>

			<?php if (is_user_logged_in()) : ?>
				<form data-cinema-checkout-form class="cinema-form-stack">
					<label>
						<?php esc_html_e('Choose payment', 'cinema-theme'); ?>
						<select name="payment_method">
							<option value="cash"><?php esc_html_e('Pay at counter', 'cinema-theme'); ?></option>
							<option value="vnpay"><?php esc_html_e('VNPay', 'cinema-theme'); ?></option>
							<option value="momo"><?php esc_html_e('MoMo', 'cinema-theme'); ?></option>
						</select>
					</label>
					<button type="submit" class="cinema-cta cinema-cta-block"><?php esc_html_e('Confirm Booking', 'cinema-theme'); ?></button>
				</form>
			<?php else : ?>
				<div class="cinema-empty-card">
					<h3><?php esc_html_e('Please log in first.', 'cinema-theme'); ?></h3>
					<p><?php esc_html_e('A signed-in customer account is required to complete checkout and save ticket history.', 'cinema-theme'); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<aside class="cinema-booking-panel">
			<h2><?php esc_html_e('Locked Seats', 'cinema-theme'); ?></h2>
			<ul class="cinema-booking-summary-list">
				<li><span><?php esc_html_e('Showtime ID', 'cinema-theme'); ?></span><strong data-checkout-showtime>-</strong></li>
				<li><span><?php esc_html_e('Seat IDs', 'cinema-theme'); ?></span><strong data-checkout-seats>-</strong></li>
				<li><span><?php esc_html_e('Payment Flow', 'cinema-theme'); ?></span><strong><?php esc_html_e('REST booking + payment record', 'cinema-theme'); ?></strong></li>
			</ul>
		</aside>
	</div>
</main>
<?php
get_footer();
