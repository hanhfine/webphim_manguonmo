<?php

if (! defined('ABSPATH')) {
	exit;
}
?>
<footer class="cinema-site-footer">
	<div class="cinema-shell cinema-footer-grid">
		<div>
			<h3><?php bloginfo('name'); ?></h3>
			<p><?php esc_html_e('WordPress-powered cinema booking flow with seat locking, digital tickets, and admin analytics.', 'cinema-theme'); ?></p>
		</div>
		<div>
			<h4><?php esc_html_e('Quick Links', 'cinema-theme'); ?></h4>
			<ul class="cinema-inline-links">
				<li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'cinema-theme'); ?></a></li>
				<li><a href="<?php echo esc_url(cinema_theme_get_page_url('checkout')); ?>"><?php esc_html_e('Checkout', 'cinema-theme'); ?></a></li>
				<li><a href="<?php echo esc_url(cinema_theme_get_page_url('my-bookings')); ?>"><?php esc_html_e('My Bookings', 'cinema-theme'); ?></a></li>
			</ul>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
