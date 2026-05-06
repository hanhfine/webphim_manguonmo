<?php
/*
Template Name: Auth
*/

if (is_user_logged_in()) {
	wp_safe_redirect(cinema_theme_get_page_url('profile'));
	exit;
}

get_header();

$auth_error = cinema_theme_get_auth_error();
?>
<main class="page-shell page-auth-shell">
<?php
	$action = wp_unslash($_GET['action'] ?? 'login');
?>
	<div class="auth-cinematic-layout">
		<div class="auth-cinematic-bg">
			<div class="auth-cinematic-overlay"></div>
			<div class="auth-cinematic-copy">
				<p class="eyebrow"><?php esc_html_e('Tài khoản khách hàng', 'cinema-theme'); ?></p>
				<?php if ('register' === $action) : ?>
					<h1><?php esc_html_e('Đăng ký tài khoản.', 'cinema-theme'); ?></h1>
					<p class="hero-summary"><?php esc_html_e('Tạo tài khoản để đồng bộ vé của bạn, lưu lịch sử đặt vé và thanh toán nhanh chóng.', 'cinema-theme'); ?></p>
				<?php else : ?>
					<h1><?php esc_html_e('Mở khóa trải nghiệm.', 'cinema-theme'); ?></h1>
					<p class="hero-summary"><?php esc_html_e('Tài khoản giúp bạn lưu lịch sử đặt vé, mở nhanh hồ sơ cá nhân và điền sẵn thông tin khi chọn ghế.', 'cinema-theme'); ?></p>
				<?php endif; ?>
				<div class="auth-points">
					<span><?php esc_html_e('Tra cứu nhanh vé đã mua', 'cinema-theme'); ?></span>
					<span><?php esc_html_e('Lưu hồ sơ khách hàng', 'cinema-theme'); ?></span>
					<span><?php esc_html_e('Điền sẵn thông tin đặt vé', 'cinema-theme'); ?></span>
				</div>
			</div>
		</div>

		<div class="auth-forms auth-forms-brutalist">
			<?php if ($auth_error) : ?>
				<div class="toast is-error is-visible" style="position: static; margin-bottom: 24px; animation: none;">
					<?php echo esc_html($auth_error); ?>
				</div>
			<?php endif; ?>

			<?php if ('register' === $action) : ?>
				<form method="post" action="<?php echo esc_url(cinema_theme_get_auth_page_url('register')); ?>" class="auth-card-brutalist">
					<?php wp_nonce_field('cinema_theme_auth', 'cinema_auth_nonce'); ?>
					<input type="hidden" name="cinema_auth_action" value="register">
					<h2><?php esc_html_e('Đăng ký mới', 'cinema-theme'); ?></h2>
					<div class="field-grid">
						<label>
							<span><?php esc_html_e('Họ tên', 'cinema-theme'); ?></span>
							<input type="text" name="full_name" required>
						</label>
						<label>
							<span><?php esc_html_e('Email', 'cinema-theme'); ?></span>
							<input type="email" name="email" required>
						</label>
						<label>
							<span><?php esc_html_e('Số điện thoại', 'cinema-theme'); ?></span>
							<input type="text" name="phone" required>
						</label>
						<label>
							<span><?php esc_html_e('Mật khẩu', 'cinema-theme'); ?></span>
							<input type="password" name="password" required minlength="8">
						</label>
						<label>
							<span><?php esc_html_e('Xác nhận mật khẩu', 'cinema-theme'); ?></span>
							<input type="password" name="password_confirm" required minlength="8">
						</label>
					</div>
					<div class="auth-actions">
						<button type="submit" class="button-primary button-block"><?php esc_html_e('Tạo tài khoản', 'cinema-theme'); ?></button>
						<p><?php esc_html_e('Đã có tài khoản?', 'cinema-theme'); ?> <a href="<?php echo esc_url(cinema_theme_get_auth_page_url('login')); ?>"><?php esc_html_e('Đăng nhập', 'cinema-theme'); ?></a></p>
					</div>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url(cinema_theme_get_auth_page_url('login')); ?>" class="auth-card-brutalist">
					<?php wp_nonce_field('cinema_theme_auth', 'cinema_auth_nonce'); ?>
					<input type="hidden" name="cinema_auth_action" value="login">
					<h2><?php esc_html_e('Đăng nhập', 'cinema-theme'); ?></h2>
					<div class="field-grid">
						<label>
							<span><?php esc_html_e('Email', 'cinema-theme'); ?></span>
							<input type="email" name="log" required>
						</label>
						<label>
							<span><?php esc_html_e('Mật khẩu', 'cinema-theme'); ?></span>
							<input type="password" name="pwd" required>
						</label>
						<label style="display: flex; align-items: center; gap: 8px;">
							<input type="checkbox" name="rememberme" value="1">
							<span style="font-size: 14px; margin-top: 0; color: #ffffff;"><?php esc_html_e('Ghi nhớ đăng nhập', 'cinema-theme'); ?></span>
						</label>
					</div>
					<div class="auth-actions">
						<button type="submit" class="button-primary button-block"><?php esc_html_e('Đăng nhập', 'cinema-theme'); ?></button>
						<p><?php esc_html_e('Bạn chưa có tài khoản?', 'cinema-theme'); ?> <a href="<?php echo esc_url(cinema_theme_get_auth_page_url('register')); ?>"><?php esc_html_e('Đăng ký ngay', 'cinema-theme'); ?></a></p>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
