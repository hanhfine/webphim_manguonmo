<?php
/*
Template Name: Tra cứu vé
*/

get_header();

$manager  = cinema_theme_get_booking_manager();
$code     = trim((string) ($_GET['code'] ?? ''));
$bookings = array();

if ($manager) {
	try {
		if ('' !== $code) {
			$booking = $manager->find_booking_by_code($code);

			if (! empty($booking)) {
				$bookings[] = $booking;
			} elseif (isset($_GET['code'])) {
				cinema_set_flash('error', 'Không tìm thấy mã đặt vé tương ứng.');
			}
		}
	} catch (Throwable $exception) {
		cinema_set_flash('error', $exception->getMessage());
	}
}
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container">
			<div class="section-head compact">
				<div>
					<p class="eyebrow"><?php esc_html_e('Tra cứu vé', 'cinema-theme'); ?></p>
					<h1><?php esc_html_e('Lịch sử vé của khách hàng', 'cinema-theme'); ?></h1>
					<p><?php esc_html_e('Nhập mã đặt vé để xem lại thông tin suất chiếu, ghế và trạng thái thanh toán.', 'cinema-theme'); ?></p>
				</div>
			</div>

			<form class="toolbar" method="get">
				<div class="toolbar-field">
					<label for="code"><?php esc_html_e('Mã đặt vé', 'cinema-theme'); ?></label>
					<input id="code" type="text" name="code" value="<?php echo esc_attr($code); ?>" placeholder="CB123ABC">
				</div>
				<div class="toolbar-actions">
					<button class="button-primary" type="submit"><?php esc_html_e('Tra cứu ngay', 'cinema-theme'); ?></button>
				</div>
			</form>

			<?php if (empty($bookings)) : ?>
				<div class="empty-state">
					<h2><?php esc_html_e('Chưa có kết quả', 'cinema-theme'); ?></h2>
					<p><?php esc_html_e('Nhập mã đặt vé để xem lại thông tin vé đã tạo.', 'cinema-theme'); ?></p>
					<?php if (is_user_logged_in()) : ?>
						<p><a class="button-secondary" href="<?php echo esc_url(cinema_theme_get_page_url('profile')); ?>"><?php esc_html_e('Vào hồ sơ của tôi', 'cinema-theme'); ?></a></p>
					<?php endif; ?>
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
								<a class="button-secondary" href="<?php echo esc_url(add_query_arg('code', urlencode((string) $booking['booking_code']), cinema_theme_get_page_url('booking-success'))); ?>"><?php esc_html_e('Mở chi tiết', 'cinema-theme'); ?></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
