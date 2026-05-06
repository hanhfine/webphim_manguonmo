<?php
/*
Template Name: Booking Success
*/

get_header();

$booking_id   = absint(wp_unslash($_GET['booking'] ?? 0));
$booking_code = sanitize_text_field(wp_unslash($_GET['code'] ?? ''));
$payload      = $booking_id ? cinema_theme_get_booking_payload($booking_id) : array();

if (empty($payload) && '' !== $booking_code) {
	$manager = cinema_theme_get_booking_manager();
	$payload = $manager ? $manager->find_booking_by_code($booking_code) : array();
	$booking_id = absint($payload['booking_id'] ?? 0);
}

$ticket_pdf  = $booking_id ? cinema_theme_get_ticket_download($booking_id, true) : array();
$delivery    = $booking_id ? cinema_theme_get_ticket_delivery_meta($booking_id) : array();
$ticket_html = $booking_id ? cinema_theme_get_ticket_html($booking_id) : '';
?>
<main class="cinema-shell">
	<section class="cinema-page-hero">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('Đặt vé thành công', 'cinema-theme'); ?></p>
			<h1><?php esc_html_e('Vé của bạn đã sẵn sàng.', 'cinema-theme'); ?></h1>
		</div>
	</section>

	<div class="cinema-panel-stack">
		<?php if (empty($payload)) : ?>
			<div class="cinema-empty-card">
				<h3><?php esc_html_e('Không tìm thấy đơn đặt vé.', 'cinema-theme'); ?></h3>
				<p><?php esc_html_e('Bạn có thể vào trang Hồ sơ hoặc Tra cứu vé để xem lại lịch sử sau khi hoàn tất đặt chỗ.', 'cinema-theme'); ?></p>
			</div>
		<?php else : ?>
			<article class="cinema-ticket-card">
				<div class="cinema-ticket-header">
					<div>
						<p class="cinema-kicker"><?php esc_html_e('Mã đặt vé', 'cinema-theme'); ?></p>
						<h2><?php echo esc_html($payload['booking_code']); ?></h2>
					</div>
					<span class="cinema-badge"><?php echo esc_html(strtoupper($payload['status'] ?: 'confirmed')); ?></span>
				</div>
				<div class="cinema-detail-grid">
					<div><strong><?php esc_html_e('Phim', 'cinema-theme'); ?></strong><span><?php echo esc_html(cinema_theme_present_text($payload['movie']['title'] ?? '', __('Phim đang cập nhật', 'cinema-theme'))); ?></span></div>
					<div><strong><?php esc_html_e('Rạp', 'cinema-theme'); ?></strong><span><?php echo esc_html(cinema_theme_present_text($payload['cinema']['title'] ?? '', function_exists('cinema_booking_get_single_cinema_name') ? cinema_booking_get_single_cinema_name() : __('Rạp đang cập nhật', 'cinema-theme'))); ?></span></div>
					<div><strong><?php esc_html_e('Phòng', 'cinema-theme'); ?></strong><span><?php echo esc_html(cinema_theme_present_text($payload['room']['title'] ?? '', __('Phòng đang cập nhật', 'cinema-theme'))); ?></span></div>
					<div><strong><?php esc_html_e('Suất chiếu', 'cinema-theme'); ?></strong><span><?php echo esc_html($payload['showtime']['start_datetime'] ?? ''); ?></span></div>
					<div><strong><?php esc_html_e('Ghế', 'cinema-theme'); ?></strong><span><?php echo esc_html(implode(', ', wp_list_pluck($payload['seats'], 'label'))); ?></span></div>
					<div><strong><?php esc_html_e('Tổng tiền', 'cinema-theme'); ?></strong><span><?php echo esc_html(number_format_i18n((float) ($payload['total_amount'] ?? 0), 0)); ?></span></div>
					<div><strong><?php esc_html_e('Trạng thái thanh toán', 'cinema-theme'); ?></strong><span><?php echo esc_html(strtoupper((string) ($payload['payment_status'] ?? 'pending'))); ?></span></div>
					<div><strong><?php esc_html_e('Phương thức', 'cinema-theme'); ?></strong><span><?php echo esc_html(strtoupper((string) ($payload['payment_method'] ?? 'cash'))); ?></span></div>
				</div>
				<div class="cinema-ticket-actions">
					<a class="cinema-ghost-link" href="<?php echo esc_url(cinema_theme_get_page_url('profile')); ?>"><?php esc_html_e('Xem hồ sơ', 'cinema-theme'); ?></a>
					<?php if (! empty($ticket_pdf['available']) && ! empty($ticket_pdf['url'])) : ?>
						<a class="cinema-cta" href="<?php echo esc_url($ticket_pdf['url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Tải PDF', 'cinema-theme'); ?></a>
					<?php endif; ?>
				</div>
				<?php if (! empty($delivery['emailed_at'])) : ?>
					<p class="cinema-status-copy"><?php echo esc_html(sprintf(__('Vé đã được gửi email lúc %s', 'cinema-theme'), $delivery['emailed_at'])); ?></p>
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
