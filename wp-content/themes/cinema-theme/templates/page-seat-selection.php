<?php
/*
Template Name: Seat Selection
*/

get_header();

$showtime_id = absint(wp_unslash($_GET['showtime'] ?? 0));
$user_id     = get_current_user_id();

$plugin = class_exists('Cinema_Booking_System') ? Cinema_Booking_System::instance() : null;
$showtime_data = $plugin ? $plugin->showtime_repo->find($showtime_id) : null;

if ($showtime_data) {
	$movie_id        = (int) $showtime_data['movie_id'];
	$room_id         = (int) $showtime_data['room_id'];
	$showtime_start  = $showtime_data['start_datetime'];
	$showtime_status = $showtime_data['status'];
	$movie_title     = cinema_theme_present_text($showtime_data['movie_title'], __('Phim đang cập nhật', 'cinema-theme'));
	$room_title      = cinema_theme_present_text($showtime_data['room_name'], __('Phòng đang cập nhật', 'cinema-theme'));
	$cinema_title    = function_exists('cinema_booking_get_single_cinema_name') ? cinema_booking_get_single_cinema_name() : __('Rạp đang cập nhật', 'cinema-theme');
	$is_valid_showtime = 'open' === $showtime_status && $showtime_start && strtotime($showtime_start) > current_time('timestamp');
	
	$price_normal = (float) $showtime_data['price_normal'];
	$price_vip    = (float) $showtime_data['price_vip'];
	$price_couple = (float) $showtime_data['price_couple'];
} else {
	// Fallback/invalid
	$is_valid_showtime = false;
	$movie_id = $room_id = 0;
	$showtime_start = $showtime_status = '';
	$movie_title = $room_title = $cinema_title = '';
	$price_normal = $price_vip = $price_couple = 0;
}

$seat_manager = $plugin ? $plugin->seat_manager : null;
$seatRows     = $is_valid_showtime && $seat_manager ? $seat_manager->get_showtime_seat_map($showtime_id, $user_id) : array();

$holdDuration    = 600;
$expires_at      = 0;
$selected_ids    = array();
$selected_labels = array();
$total_amount    = 0;

// Get actual lock expiry from DB for current user
if ($showtime_id && $user_id) {
	global $wpdb;
	$table      = $wpdb->prefix . 'cinema_seat_bookings';
	$locked_at  = $wpdb->get_var( $wpdb->prepare(
		"SELECT locked_at FROM {$table}
		 WHERE showtime_id = %d AND user_id = %d AND status = 'pending'
		 ORDER BY locked_at DESC LIMIT 1",
		$showtime_id, $user_id
	) );
	if ($locked_at) {
		$expires_at = strtotime($locked_at . ' UTC') + $holdDuration;
	}
}

foreach ($seatRows as $rowLabel => $rowSeats) {
	foreach ($rowSeats as $seat) {
		if ('selected' === $seat['status']) {
			$selected_ids[]    = $seat['id'];
			$selected_labels[] = $seat['label'];
			$seat_price        = 'vip' === $seat['seat_type'] ? $price_vip : ('couple' === $seat['seat_type'] ? $price_couple : $price_normal);
			$total_amount     += $seat_price;
		}
	}
}

$holdRemaining = max(0, $expires_at - time());
$holdPercent   = $holdDuration > 0 ? min(100, max(0, ($holdRemaining / $holdDuration) * 100)) : 0;
?>

<main class="page-shell">
	<?php if (! $showtime_id || ! $is_valid_showtime) : ?>
		<section class="page-band">
			<div class="container">
				<div class="empty-state">
					<h2><?php esc_html_e('Suất chiếu không còn mở bán', 'cinema-theme'); ?></h2>
					<p><?php esc_html_e('Suất chiếu này đã hết hạn hoặc chưa được mở bán. Hãy quay lại trang phim để chọn suất khác.', 'cinema-theme'); ?></p>
				</div>
			</div>
		</section>
	<?php else : ?>
		<section class="page-band">
			<div class="container booking-layout">
				<section class="seat-panel">
					<div class="seat-panel-head">
						<div>
							<p class="eyebrow"><?php esc_html_e('Chọn ghế', 'cinema-theme'); ?></p>
							<h1><?php echo esc_html($movie_title); ?></h1>
							<p><?php echo esc_html($cinema_title); ?> / <?php echo esc_html($room_title); ?> / <?php echo esc_html(cinema_datetime($showtime_start)); ?></p>
						</div>
						<div class="booking-note">
							<strong><?php esc_html_e('Hồ sơ đặt vé', 'cinema-theme'); ?></strong>
							<p><?php esc_html_e('Vé sẽ tự động lưu vào tài khoản của bạn.', 'cinema-theme'); ?></p>
							<p><a href="<?php echo esc_url(cinema_theme_get_page_url('profile')); ?>"><?php esc_html_e('Xem hồ sơ', 'cinema-theme'); ?></a></p>
						</div>
						<div class="legend-list">
							<span><span class="legend-dot is-normal" aria-hidden="true"></span><?php esc_html_e('Thường', 'cinema-theme'); ?></span>
							<span><span class="legend-dot is-vip" aria-hidden="true"></span><?php esc_html_e('VIP', 'cinema-theme'); ?></span>
							<span><span class="legend-dot is-couple" aria-hidden="true"></span><?php esc_html_e('Couple', 'cinema-theme'); ?></span>
							<span><span class="legend-dot is-booked" aria-hidden="true"></span><?php esc_html_e('Đã đặt', 'cinema-theme'); ?></span>
							<span><span class="legend-dot is-selected" aria-hidden="true"></span><?php esc_html_e('Đang chọn', 'cinema-theme'); ?></span>
						</div>
					</div>

					<div class="screen-mark"><?php esc_html_e('MÀN HÌNH', 'cinema-theme'); ?></div>
					<div class="seat-timer" data-lock-countdown data-lock-expires-at="<?php echo (int) $expires_at; ?>" data-lock-total-seconds="<?php echo (int) $holdDuration; ?>" role="timer" aria-live="polite">
						<span><?php esc_html_e('Thời gian giữ ghế', 'cinema-theme'); ?></span>
						<strong data-lock-countdown-value><?php echo esc_html(sprintf('%02d:%02d', intdiv($holdRemaining, 60), $holdRemaining % 60)); ?></strong>
						<div class="seat-timer-track" role="progressbar" aria-label="<?php esc_attr_e('Tiến trình thời gian giữ ghế', 'cinema-theme'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $holdPercent; ?>">
							<span data-lock-progress style="width: <?php echo (int) $holdPercent; ?>%;"></span>
						</div>
						<small data-lock-countdown-message><?php esc_html_e('Phiên giữ ghế sẽ tự động hết hạn sau 10 phút.', 'cinema-theme'); ?></small>
					</div>

					<div class="seat-map" data-seat-map>
						<?php foreach ($seatRows as $rowLabel => $rowSeats) : ?>
							<div class="seat-row">
								<div class="seat-row-label"><?php echo esc_html($rowLabel); ?></div>
								<div class="seat-row-grid">
									<?php foreach ($rowSeats as $seat) : ?>
										<?php
										$seatLabel     = $seat['label'];
										$seatType      = $seat['seat_type'];
										$price         = (float) ('vip' === $seatType ? $price_vip : ('couple' === $seatType ? $price_couple : $price_normal));
										$isBooked      = 'booked' === $seat['status'];
										$isLockedSelf  = 'selected' === $seat['status'];
										$isLockedOther = 'locked' === $seat['status'];
										
										$seatTypeLabel = ucwords(str_replace('_', ' ', $seatType));
										$seatAriaLabel = $isBooked || $isLockedOther
											? sprintf(__('Ghế %s - %s - đã được đặt/giữ', 'cinema-theme'), $seatLabel, $seatTypeLabel)
											: sprintf(__('Ghế %s - %s - %s', 'cinema-theme'), $seatLabel, $seatTypeLabel, cinema_currency($price));
											
										$seatButtonClass = array(
											'seat-button',
											'is-' . $seatType,
										);

										if ($isBooked || $isLockedOther) {
											$seatButtonClass[] = 'is-booked';
										}

										if ($isLockedSelf) {
											$seatButtonClass[] = 'is-selected';
										}
										?>
										<button
											type="button"
											class="<?php echo esc_attr(implode(' ', $seatButtonClass)); ?>"
											data-seat-id="<?php echo (int) $seat['id']; ?>"
											data-seat-label="<?php echo esc_attr($seatLabel); ?>"
											data-seat-type="<?php echo esc_attr($seatType); ?>"
											data-seat-price="<?php echo esc_attr((string) $price); ?>"
											data-seat-locked="<?php echo $isLockedSelf ? 'self' : ($isLockedOther ? 'other' : 'none'); ?>"
											aria-label="<?php echo esc_attr($seatAriaLabel); ?>"
											aria-pressed="<?php echo $isLockedSelf ? 'true' : 'false'; ?>"
											<?php echo $isBooked || $isLockedOther ? 'disabled' : ''; ?>
										>
											<?php echo esc_html($seatLabel); ?>
										</button>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<aside class="booking-summary">
					<form method="post" class="booking-form" action="<?php echo esc_url(cinema_theme_get_page_url('checkout')); ?>" data-booking-form>
						<input type="hidden" name="showtime_id" value="<?php echo (int) $showtime_id; ?>">
						<input type="hidden" name="seat_ids" value="<?php echo esc_attr(implode(',', $selected_ids)); ?>" data-seat-input>
						<input type="hidden" value="<?php echo esc_attr($movie_title); ?>" data-checkout-movie-title>
						<input type="hidden" value="<?php echo esc_attr($cinema_title); ?>" data-checkout-cinema-title>
						<input type="hidden" value="<?php echo esc_attr($room_title); ?>" data-checkout-room-title>
						<input type="hidden" value="<?php echo esc_attr(cinema_datetime($showtime_start)); ?>" data-checkout-showtime-label>
						<input type="hidden" value="<?php echo (int) $expires_at; ?>" data-lock-expires-at>
						<input type="hidden" value="<?php echo time(); ?>" data-server-time>
						<input type="hidden" value="<?php echo (int) $holdDuration; ?>" data-lock-total-seconds>
						<input type="hidden" value="<?php echo esc_url(rest_url('cinema-theme/v1/seat-action')); ?>" data-seat-lock-endpoint>
						<input type="hidden" name="csrf_token" value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">

						<h2><?php esc_html_e('Lựa chọn của bạn', 'cinema-theme'); ?></h2>
						
						<ul class="summary-list">
							<li><span><?php esc_html_e('Phim', 'cinema-theme'); ?></span><strong><?php echo esc_html($movie_title); ?></strong></li>
							<li><span><?php esc_html_e('Suất chiếu', 'cinema-theme'); ?></span><strong><?php echo esc_html(cinema_datetime($showtime_start)); ?></strong></li>
							<li><span><?php esc_html_e('Rạp', 'cinema-theme'); ?></span><strong><?php echo esc_html($cinema_title); ?></strong></li>
							<li><span><?php esc_html_e('Phòng', 'cinema-theme'); ?></span><strong><?php echo esc_html($room_title); ?></strong></li>
							<li><span><?php esc_html_e('Ghế', 'cinema-theme'); ?></span><strong data-seat-labels><?php echo esc_html(empty($selected_labels) ? __('Chưa chọn', 'cinema-theme') : implode(', ', $selected_labels)); ?></strong></li>
						</ul>

						<div class="summary-total">
							<span><?php esc_html_e('Tổng tiền', 'cinema-theme'); ?></span>
							<strong data-seat-total><?php echo esc_html(cinema_currency($total_amount)); ?></strong>
						</div>

						<?php if (is_user_logged_in()) : ?>
							<button class="button-primary button-block" type="submit" <?php echo empty($selected_ids) ? 'disabled' : ''; ?>>
								<?php esc_html_e('Giữ ghế và tiếp tục', 'cinema-theme'); ?>
							</button>
						<?php else : ?>
							<a class="button-primary button-block" href="<?php echo esc_url(cinema_theme_get_auth_page_url('login')); ?>">
								<?php esc_html_e('Đăng nhập để đặt vé', 'cinema-theme'); ?>
							</a>
						<?php endif; ?>
					</form>
				</aside>
			</div>
		</section>
	<?php endif; ?>
</main>
<?php
get_footer();
