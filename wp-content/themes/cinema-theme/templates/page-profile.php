<?php
/*
Template Name: Profile
*/

$current_user = wp_get_current_user();

if (! is_user_logged_in() || ! ($current_user instanceof WP_User)) {
	wp_safe_redirect(cinema_theme_get_page_url('auth'));
	exit;
}

$manager  = cinema_theme_get_booking_manager();
$bookings = $manager ? $manager->get_user_bookings(get_current_user_id(), 50) : array();
$phone    = (string) get_user_meta(get_current_user_id(), 'cinema_phone', true);

if ('' === $phone) {
	$phone = (string) get_user_meta(get_current_user_id(), 'billing_phone', true);
}

get_header();

?>
<main class="page-shell">
	<section class="page-band">
		<div class="container profile-layout">
			<section class="profile-card">
				<p class="eyebrow"><?php esc_html_e('Tài khoản', 'cinema-theme'); ?></p>
				<h1><?php printf(esc_html__('Xin chào, %s', 'cinema-theme'), esc_html($current_user->display_name ?: $current_user->user_login)); ?></h1>
				<div class="profile-grid">
					<div>
						<strong>Email</strong>
						<span><?php echo esc_html($current_user->user_email); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e('Số điện thoại', 'cinema-theme'); ?></strong>
						<span><?php echo esc_html($phone ?: __('Chưa cập nhật', 'cinema-theme')); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e('Thành viên từ', 'cinema-theme'); ?></strong>
						<span><?php echo esc_html(! empty($current_user->user_registered) ? cinema_datetime((string) $current_user->user_registered) : ''); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e('Số vé đã đặt', 'cinema-theme'); ?></strong>
						<span><?php echo esc_html((string) count($bookings)); ?></span>
					</div>
				</div>
				<div class="action-row">
					<a class="button-primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Đặt vé mới', 'cinema-theme'); ?></a>
					<a class="button-secondary" href="<?php echo esc_url(cinema_theme_get_page_url('my-bookings')); ?>"><?php esc_html_e('Tra cứu theo mã', 'cinema-theme'); ?></a>
				</div>
			</section>

			<section class="profile-bookings">
				<div class="section-head compact">
					<div>
						<p class="eyebrow"><?php esc_html_e('Vé đã đặt', 'cinema-theme'); ?></p>
						<h2><?php esc_html_e('Lịch sử vé của bạn', 'cinema-theme'); ?></h2>
					</div>
				</div>

				<?php if (empty($bookings)) : ?>
					<div class="empty-state">
						<h2><?php esc_html_e('Chưa có vé nào', 'cinema-theme'); ?></h2>
						<p><?php esc_html_e('Hãy chọn một bộ phim và đặt ghế để vé của bạn xuất hiện ở đây.', 'cinema-theme'); ?></p>
					</div>
				<?php else : ?>
					<div class="booking-list">
						<?php foreach ($bookings as $booking) : ?>
							<article class="booking-row-card">
								<div class="booking-row-main">
									<img src="<?php echo esc_url(cinema_poster_url($booking['movie']['poster_url'] ?? '')); ?>" alt="<?php echo esc_attr($booking['movie']['title'] ?? ''); ?>">
									<div>
									<div class="movie-meta-line">
											<span class="pill"><?php echo esc_html($booking['booking_code']); ?></span>
											<span class="status-pill <?php echo esc_attr(cinema_badge_class((string) ($booking['payment_status'] ?? 'pending'))); ?>"><?php echo esc_html(cinema_status_label((string) ($booking['payment_status'] ?? 'pending'))); ?></span>
										</div>
										<h2><?php echo esc_html(cinema_theme_present_text($booking['movie']['title'] ?? '', __('Phim đang cập nhật', 'cinema-theme'))); ?></h2>
										<p><?php echo esc_html(cinema_join_non_empty(array(cinema_theme_present_text($booking['cinema']['title'] ?? '', function_exists('cinema_booking_get_single_cinema_name') ? cinema_booking_get_single_cinema_name() : __('Rạp đang cập nhật', 'cinema-theme')), cinema_theme_present_text($booking['room']['title'] ?? '', __('Phòng đang cập nhật', 'cinema-theme')), $booking['showtime']['start_datetime'] ?? ''), ' / ')); ?></p>
										<div class="ticket-seats compact">
											<?php foreach ((array) ($booking['seats'] ?? array()) as $seat) : ?>
												<span><?php echo esc_html($seat['label'] ?? ($seat['seat_label'] ?? '')); ?></span>
											<?php endforeach; ?>
										</div>
									</div>
								</div>
								<div class="booking-row-side">
									<strong><?php echo esc_html(cinema_currency((float) ($booking['total_amount'] ?? 0))); ?></strong>
									<span><?php echo esc_html(cinema_payment_label((string) ($booking['payment_method'] ?? 'cash'))); ?></span>
									<a class="button-secondary" href="<?php echo esc_url(add_query_arg('code', urlencode((string) $booking['booking_code']), cinema_theme_get_page_url('booking-success'))); ?>"><?php esc_html_e('Xem vé', 'cinema-theme'); ?></a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		</div>
	</section>
</main>
<?php
get_footer();
