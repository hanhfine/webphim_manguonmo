<?php

if (! defined('ABSPATH')) {
	exit;
}
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
<header class="cinema-site-header">
	<div class="cinema-shell cinema-header-bar">
		<a class="cinema-brand" href="<?php echo esc_url(home_url('/')); ?>">
			<span class="cinema-brand-mark">CB</span>
			<span class="cinema-brand-copy">
				<strong><?php bloginfo('name'); ?></strong>
				<small><?php esc_html_e('Booking System', 'cinema-theme'); ?></small>
			</span>
		</a>

		<nav class="cinema-nav">
			<a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Now Showing', 'cinema-theme'); ?></a>
			<a href="<?php echo esc_url(cinema_theme_get_page_url('my-bookings')); ?>"><?php esc_html_e('My Tickets', 'cinema-theme'); ?></a>
			<?php if (is_user_logged_in()) : ?>
				<a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Logout', 'cinema-theme'); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url(cinema_theme_get_page_url('auth')); ?>"><?php esc_html_e('Login / Register', 'cinema-theme'); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>
