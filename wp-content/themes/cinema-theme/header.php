<?php

if (! defined('ABSPATH')) {
	exit;
}

$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<div class="container header-inner">
		<a class="brand" href="<?php echo esc_url(home_url('/')); ?>">
			<span class="brand-mark">MY</span>
			<span class="brand-copy">
				<strong><?php echo esc_html('MYCINEMA'); ?></strong>
				<small><?php esc_html_e('Đặt vé xem phim trực tuyến', 'cinema-theme'); ?></small>
			</span>
		</a>

		<span class="source-badge is-live">
			<?php esc_html_e('WordPress + Cinema Booking', 'cinema-theme'); ?>
		</span>

		<button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="<?php esc_attr_e('Mở menu điều hướng', 'cinema-theme'); ?>" data-nav-toggle>
			<span class="nav-toggle-bar" aria-hidden="true"></span>
			<span class="nav-toggle-bar" aria-hidden="true"></span>
			<span class="nav-toggle-bar" aria-hidden="true"></span>
		</button>

		<nav class="nav-links" id="site-nav" data-site-nav>
			<a class="<?php echo (is_front_page() || is_singular('movie')) ? 'is-active' : ''; ?>" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Phim đang chiếu', 'cinema-theme'); ?></a>
			<a class="<?php echo is_page('my-bookings') ? 'is-active' : ''; ?>" href="<?php echo esc_url(cinema_theme_get_page_url('my-bookings')); ?>"><?php esc_html_e('Tra cứu vé', 'cinema-theme'); ?></a>
			<?php if (is_user_logged_in()) : ?>
				<a class="<?php echo is_page('profile') ? 'is-active' : ''; ?>" href="<?php echo esc_url(cinema_theme_get_page_url('profile')); ?>"><?php esc_html_e('Hồ sơ', 'cinema-theme'); ?></a>
			<?php else : ?>
				<a class="<?php echo is_page('auth') && 'login' === sanitize_key(wp_unslash($_GET['action'] ?? 'login')) ? 'is-active' : ''; ?>" href="<?php echo esc_url(cinema_theme_get_auth_page_url('login')); ?>"><?php esc_html_e('Đăng nhập', 'cinema-theme'); ?></a>
				<a class="<?php echo is_page('auth') && 'register' === sanitize_key(wp_unslash($_GET['action'] ?? 'login')) ? 'is-active' : ''; ?>" href="<?php echo esc_url(cinema_theme_get_auth_page_url('register')); ?>"><?php esc_html_e('Đăng ký', 'cinema-theme'); ?></a>
			<?php endif; ?>
		</nav>

		<?php if (is_user_logged_in() && $current_user instanceof WP_User) : ?>
			<div class="account-actions">
				<a class="account-chip" href="<?php echo esc_url(cinema_theme_get_page_url('profile')); ?>">
					<?php
					printf(
						esc_html__('Xin chào, %s', 'cinema-theme'),
						esc_html($current_user->display_name ?: $current_user->user_login)
					);
					?>
				</a>
				<a class="logout-link" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Đăng xuất', 'cinema-theme'); ?></a>
			</div>
		<?php endif; ?>
	</div>
</header>

<?php
$auth_error = cinema_theme_get_auth_error();
if ($auth_error && ! is_page('auth')) :
?>
	<div class="flash-stack container">
		<div class="flash-message flash-error">
			<?php echo esc_html($auth_error); ?>
		</div>
	</div>
<?php endif; ?>
