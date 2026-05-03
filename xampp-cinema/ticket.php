<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$code    = trim((string) ($_GET['code'] ?? ''));
$booking = null;

try {
	$booking = '' !== $code ? cinema_fetch_booking_by_code($code) : null;
} catch (Throwable $exception) {
	cinema_set_flash('error', $exception->getMessage());
	cinema_redirect(cinema_base_url('my-bookings.php'));
}

if (! $booking) {
	cinema_set_flash('error', 'Không tìm thấy mã đặt vé bạn vừa tra cứu.');
	cinema_redirect(cinema_base_url('my-bookings.php'));
}

cinema_render_header(
	'Vé của bạn',
	array(
		'current'     => 'lookup',
		'description' => 'Xem chi tiết vé, ghế đã đặt, phương thức thanh toán và in vé nhanh ngay trên trình duyệt.',
	)
);
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container ticket-layout">
			<article class="ticket-card">
				<div class="ticket-head">
					<div>
						<p class="eyebrow">Mã đặt vé</p>
						<h1><?php echo cinema_escape($booking['booking_code']); ?></h1>
					</div>
					<div class="status-stack">
						<span class="status-pill <?php echo cinema_escape(cinema_badge_class($booking['booking_status'])); ?>"><?php echo cinema_escape(cinema_status_label($booking['booking_status'])); ?></span>
						<span class="status-pill <?php echo cinema_escape(cinema_badge_class($booking['payment_status'])); ?>"><?php echo cinema_escape(cinema_status_label($booking['payment_status'])); ?></span>
					</div>
				</div>

				<div class="ticket-grid">
					<div>
						<strong>Phim</strong>
						<span><?php echo cinema_escape($booking['movie_title']); ?></span>
					</div>
					<div>
						<strong>Rạp</strong>
						<span><?php echo cinema_escape($booking['cinema_name']); ?></span>
					</div>
					<div>
						<strong>Phòng</strong>
						<span><?php echo cinema_escape($booking['room_name']); ?> / <?php echo cinema_escape(strtoupper($booking['screen_type'])); ?></span>
					</div>
					<div>
						<strong>Giờ chiếu</strong>
						<span><?php echo cinema_escape(cinema_datetime($booking['start_time'])); ?></span>
					</div>
					<div>
						<strong>Khách hàng</strong>
						<span><?php echo cinema_escape($booking['customer_name']); ?> / <?php echo cinema_escape($booking['customer_phone']); ?></span>
					</div>
					<div>
						<strong>Thanh toán</strong>
						<span><?php echo cinema_escape(cinema_payment_label($booking['payment_method'])); ?></span>
					</div>
				</div>

				<div class="ticket-seats">
					<?php foreach ($booking['seats'] as $seat) : ?>
						<span><?php echo cinema_escape($seat['seat_label']); ?> / <?php echo cinema_escape(cinema_currency((float) $seat['unit_price'])); ?></span>
					<?php endforeach; ?>
				</div>

				<div class="ticket-actions">
					<div class="ticket-total">
						<span>Tổng thanh toán</span>
						<strong><?php echo cinema_escape(cinema_currency((float) $booking['total_amount'])); ?></strong>
					</div>
					<div class="action-row">
						<a class="button-secondary" href="<?php echo cinema_escape(cinema_base_url('my-bookings.php?code=' . urlencode($booking['booking_code']))); ?>">Tra cứu lại</a>
						<button class="button-primary" type="button" onclick="window.print()">In vé</button>
					</div>
				</div>
			</article>

			<aside class="lookup-side-card">
				<img src="<?php echo cinema_escape(cinema_poster_url($booking['poster_url'])); ?>" alt="<?php echo cinema_escape($booking['movie_title']); ?>">
				<div>
					<h2><?php echo cinema_escape($booking['movie_title']); ?></h2>
					<p><?php echo cinema_escape(cinema_join_non_empty(array($booking['cinema_name'] ?? '', $booking['cinema_city'] ?? ''))); ?></p>
					<?php if (! empty($booking['cinema_address'])) : ?>
						<p><?php echo cinema_escape($booking['cinema_address']); ?></p>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</section>
</main>
<?php cinema_render_footer(); ?>
