<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$showtimeId = (int) ($_GET['showtime_id'] ?? $_POST['showtime_id'] ?? 0);
$showtime   = null;

try {
	$showtime = cinema_fetch_showtime($showtimeId);
} catch (Throwable $exception) {
	cinema_set_flash('error', $exception->getMessage());
	cinema_redirect(cinema_base_url('index.php'));
}

if (! $showtime) {
	cinema_set_flash('error', 'Suất chiếu không tồn tại, đã bị xóa hoặc chưa thể tải từ hệ quản trị.');
	cinema_redirect(cinema_base_url('index.php'));
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
	try {
		if (! cinema_verify_csrf($_POST['csrf_token'] ?? null)) {
			throw new RuntimeException('Phiên đặt vé đã hết hạn. Vui lòng tải lại trang.');
		}

		$selectedSeats = array_filter(array_map('intval', explode(',', (string) ($_POST['seat_ids'] ?? ''))));
		$booking       = cinema_create_booking(
			array(
				'showtime_id'    => $showtimeId,
				'seat_ids'       => $selectedSeats,
				'customer_name'  => $_POST['customer_name'] ?? '',
				'email'          => $_POST['email'] ?? '',
				'phone'          => $_POST['phone'] ?? '',
				'payment_method' => $_POST['payment_method'] ?? 'counter',
			)
		);

		cinema_set_flash('success', 'Đặt vé thành công. Mã đặt vé của bạn đã được tạo.');
		cinema_redirect(cinema_base_url('ticket.php?code=' . urlencode((string) $booking['booking_code'])));
	} catch (Throwable $exception) {
		cinema_set_flash('error', $exception->getMessage());
	}
}

try {
	$seats = cinema_fetch_seats_for_showtime($showtimeId, (int) $showtime['room_id']);
} catch (Throwable $exception) {
	cinema_set_flash('error', $exception->getMessage());
	cinema_redirect(cinema_base_url('movie.php?id=' . (int) $showtime['movie_id']));
}

$seatRows = cinema_group_seats($seats);
$selectedSeatCsv = trim((string) ($_POST['seat_ids'] ?? ''));
$paymentMethods  = cinema_available_payment_methods();

cinema_render_header(
	'Chọn ghế',
	array(
		'current'     => 'home',
		'description' => 'Chọn ghế trực quan theo sơ đồ rạp, xem tổng tiền và xác nhận đặt vé chỉ trong một bước.',
	)
);
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container booking-layout">
			<section class="seat-panel">
				<div class="seat-panel-head">
					<div>
						<p class="eyebrow">Chọn ghế</p>
						<h1><?php echo cinema_escape($showtime['movie_title']); ?></h1>
						<p><?php echo cinema_escape($showtime['cinema_name']); ?> / <?php echo cinema_escape($showtime['room_name']); ?> / <?php echo cinema_escape(cinema_datetime($showtime['start_time'])); ?></p>
					</div>
					<div class="legend-list">
						<span><span class="legend-dot is-normal" aria-hidden="true"></span>Thường</span>
						<span><span class="legend-dot is-vip" aria-hidden="true"></span>VIP</span>
						<span><span class="legend-dot is-couple" aria-hidden="true"></span>Couple</span>
						<span><span class="legend-dot is-booked" aria-hidden="true"></span>Đã đặt</span>
						<span><span class="legend-dot is-selected" aria-hidden="true"></span>Đang chọn</span>
					</div>
				</div>

				<div class="screen-mark">MÀN HÌNH</div>

				<div class="seat-map" data-seat-map>
					<?php foreach ($seatRows as $rowLabel => $rowSeats) : ?>
						<div class="seat-row">
							<div class="seat-row-label"><?php echo cinema_escape($rowLabel); ?></div>
							<div class="seat-row-grid">
								<?php foreach ($rowSeats as $seat) : ?>
									<?php
									$seatLabel     = $seat['row_label'] . $seat['seat_number'];
									$seatType      = $seat['seat_type'];
									$price         = (float) ('vip' === $seatType ? $showtime['price_vip'] : ('couple' === $seatType ? $showtime['price_couple'] : $showtime['price_normal']));
									$isBooked      = (int) $seat['is_booked'] === 1;
									$seatTypeLabel = cinema_seat_type_label($seatType);
									$seatAriaLabel = $isBooked
										? sprintf('Ghế %s - %s - đã được đặt', $seatLabel, $seatTypeLabel)
										: sprintf('Ghế %s - %s - %s', $seatLabel, $seatTypeLabel, cinema_currency($price));
									?>
									<button
										type="button"
										class="seat-button <?php echo cinema_escape('is-' . $seatType); ?> <?php echo $isBooked ? 'is-booked' : ''; ?>"
										data-seat-id="<?php echo (int) $seat['id']; ?>"
										data-seat-label="<?php echo cinema_escape($seatLabel); ?>"
										data-seat-type="<?php echo cinema_escape($seatType); ?>"
										data-seat-price="<?php echo cinema_escape((string) $price); ?>"
										aria-label="<?php echo cinema_escape($seatAriaLabel); ?>"
										aria-pressed="false"
										<?php echo $isBooked ? 'disabled' : ''; ?>
									>
										<?php echo cinema_escape($seatLabel); ?>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<aside class="booking-summary">
				<form method="post" class="booking-form" data-booking-form>
					<input type="hidden" name="csrf_token" value="<?php echo cinema_escape(cinema_csrf_token()); ?>">
					<input type="hidden" name="showtime_id" value="<?php echo (int) $showtimeId; ?>">
					<input type="hidden" name="seat_ids" value="<?php echo cinema_escape($selectedSeatCsv); ?>" data-seat-input>

					<div class="summary-card">
						<h2>Tạm tính</h2>
						<ul class="summary-list">
							<li><span>Phim</span><strong><?php echo cinema_escape($showtime['movie_title']); ?></strong></li>
							<li><span>Rạp</span><strong><?php echo cinema_escape($showtime['cinema_name']); ?></strong></li>
							<li><span>Giờ chiếu</span><strong><?php echo cinema_escape(cinema_datetime($showtime['start_time'])); ?></strong></li>
							<li><span>Ghế</span><strong data-seat-labels>Chưa chọn</strong></li>
							<li><span>Tổng tiền</span><strong data-seat-total>0 VND</strong></li>
						</ul>
					</div>

					<div class="form-card">
						<h2>Thông tin khách hàng</h2>
						<div class="field-grid">
							<label>
								<span>Họ tên</span>
								<input type="text" name="customer_name" value="<?php echo cinema_escape(cinema_old('customer_name')); ?>" required>
							</label>
							<label>
								<span>Email</span>
								<input type="email" name="email" value="<?php echo cinema_escape(cinema_old('email')); ?>" required>
							</label>
							<label>
								<span>Số điện thoại</span>
								<input type="text" name="phone" value="<?php echo cinema_escape(cinema_old('phone')); ?>" required>
							</label>
							<label>
								<span>Thanh toán</span>
								<select name="payment_method">
									<?php foreach ($paymentMethods as $paymentKey => $paymentLabel) : ?>
										<option value="<?php echo cinema_escape($paymentKey); ?>" <?php echo $paymentKey === cinema_old('payment_method', 'counter') ? 'selected' : ''; ?>>
											<?php echo cinema_escape($paymentLabel); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>
						<button class="button-primary button-block" type="submit">Xác nhận đặt vé</button>
					</div>
				</form>
			</aside>
		</div>
	</section>
</main>
<?php cinema_render_footer(); ?>
