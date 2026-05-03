<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$email    = trim((string) ($_GET['email'] ?? ''));
$code     = trim((string) ($_GET['code'] ?? ''));
$bookings = array();

try {
	if ('' !== $code) {
		$booking = cinema_fetch_booking_by_code($code);

		if ($booking) {
			$bookings[] = $booking;
		} elseif (isset($_GET['code'])) {
			cinema_set_flash('error', 'Không tìm thấy mã đặt vé tương ứng.');
		}
	} elseif ('' !== $email) {
		$rows = cinema_fetch_booking_rows_by_email($email);

		foreach ($rows as $row) {
			$full = cinema_fetch_booking_by_code((string) $row['booking_code']);

			if ($full) {
				$bookings[] = $full;
			}
		}

		if (empty($bookings)) {
			cinema_set_flash('error', 'Email này chưa có lịch sử đặt vé.');
		}
	}
} catch (Throwable $exception) {
	cinema_set_flash('error', $exception->getMessage());
}

cinema_render_header(
	'Tra cứu vé',
	array(
		'current'     => 'lookup',
		'description' => 'Tra cứu lại vé đã đặt bằng email hoặc mã đặt vé để xem chi tiết suất chiếu và trạng thái thanh toán.',
	)
);
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container">
			<div class="section-head compact">
				<div>
					<p class="eyebrow">Tra cứu</p>
					<h1>Lịch sử vé của khách hàng</h1>
				</div>
			</div>

			<form class="lookup-toolbar" method="get">
				<div class="toolbar-field">
					<label for="email">Tìm theo email</label>
					<input id="email" type="email" name="email" value="<?php echo cinema_escape($email); ?>" placeholder="khachhang@email.com">
				</div>
				<div class="toolbar-field">
					<label for="code">Hoặc theo mã đặt vé</label>
					<input id="code" type="text" name="code" value="<?php echo cinema_escape($code); ?>" placeholder="CB123ABC">
				</div>
				<div class="toolbar-actions">
					<button class="button-primary" type="submit">Tra cứu ngay</button>
				</div>
			</form>

			<?php if (empty($bookings)) : ?>
				<div class="empty-state">
					<h2>Chưa có kết quả</h2>
					<p>Nhập email hoặc mã đặt vé để xem lại thông tin vé đã tạo.</p>
				</div>
			<?php else : ?>
				<div class="booking-list">
					<?php foreach ($bookings as $booking) : ?>
						<article class="booking-row-card">
							<div class="booking-row-main">
								<img src="<?php echo cinema_escape(cinema_poster_url($booking['poster_url'])); ?>" alt="<?php echo cinema_escape($booking['movie_title']); ?>">
								<div>
									<div class="movie-meta-line">
										<span class="pill"><?php echo cinema_escape($booking['booking_code']); ?></span>
										<span class="status-pill <?php echo cinema_escape(cinema_badge_class($booking['payment_status'])); ?>"><?php echo cinema_escape(cinema_status_label($booking['payment_status'])); ?></span>
									</div>
									<h2><?php echo cinema_escape($booking['movie_title']); ?></h2>
									<p><?php echo cinema_escape($booking['cinema_name']); ?> / <?php echo cinema_escape($booking['room_name']); ?> / <?php echo cinema_escape(cinema_datetime($booking['start_time'])); ?></p>
									<div class="ticket-seats compact">
										<?php foreach ($booking['seats'] as $seat) : ?>
											<span><?php echo cinema_escape($seat['seat_label']); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
							<div class="booking-row-side">
								<strong><?php echo cinema_escape(cinema_currency((float) $booking['total_amount'])); ?></strong>
								<span><?php echo cinema_escape(cinema_payment_label($booking['payment_method'])); ?></span>
								<a class="button-secondary" href="<?php echo cinema_escape(cinema_base_url('ticket.php?code=' . urlencode($booking['booking_code']))); ?>">Mở chi tiết</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php cinema_render_footer(); ?>
