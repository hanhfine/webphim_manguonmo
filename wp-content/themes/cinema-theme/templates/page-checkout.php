<?php
/*
Template Name: Checkout
*/

get_header();
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container">
			<div class="section-head compact">
				<div>
					<p class="eyebrow"><?php esc_html_e('Thanh toán', 'cinema-theme'); ?></p>
					<h1><?php esc_html_e('Xác nhận thanh toán và xuất vé', 'cinema-theme'); ?></h1>
				</div>
			</div>
		</div>
	</section>

	<section class="page-band">
		<div class="container booking-layout" data-cinema-checkout-shell>
			<section class="seat-panel">
				<h2><?php esc_html_e('Phương thức thanh toán', 'cinema-theme'); ?></h2>

				<?php if (is_user_logged_in()) : ?>
					<form data-cinema-checkout-form class="booking-form">
						<div class="field-grid">
							<label>
								<span><?php esc_html_e('Chọn phương thức', 'cinema-theme'); ?></span>
								<select name="payment_method">
									<option value="cash"><?php esc_html_e('Thanh toán tại quầy', 'cinema-theme'); ?></option>
									<option value="vnpay"><?php esc_html_e('VNPay', 'cinema-theme'); ?></option>
									<option value="momo"><?php esc_html_e('MoMo', 'cinema-theme'); ?></option>
								</select>
							</label>
						</div>
						<button type="submit" class="button-primary button-block"><?php esc_html_e('Xác nhận đặt vé', 'cinema-theme'); ?></button>
					</form>
				<?php else : ?>
					<div class="empty-state">
						<h2><?php esc_html_e('Vui lòng đăng nhập trước', 'cinema-theme'); ?></h2>
						<p><?php esc_html_e('Cần có tài khoản khách hàng để hoàn tất thanh toán và lưu lịch sử vé.', 'cinema-theme'); ?></p>
						<a class="button-primary" href="<?php echo esc_url(cinema_theme_get_auth_page_url('login')); ?>"><?php esc_html_e('Đăng nhập', 'cinema-theme'); ?></a>
					</div>
				<?php endif; ?>
			</section>

			<aside class="booking-summary">
				<h2><?php esc_html_e('Ghế đã giữ', 'cinema-theme'); ?></h2>
				<ul class="summary-list">
					<li><span><?php esc_html_e('Suất chiếu', 'cinema-theme'); ?></span><strong data-checkout-showtime>-</strong></li>
					<li><span><?php esc_html_e('Ghế', 'cinema-theme'); ?></span><strong data-checkout-seats>-</strong></li>
					<li><span><?php esc_html_e('Tổng tiền', 'cinema-theme'); ?></span><strong data-checkout-total>-</strong></li>
				</ul>
			</aside>
		</div>
	</section>
</main>
<?php
get_footer();
