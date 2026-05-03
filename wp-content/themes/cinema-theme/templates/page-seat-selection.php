<?php
/*
Template Name: Seat Selection
*/

get_header();

$showtime_id = absint(wp_unslash($_GET['showtime'] ?? 0));
$movie_id    = absint(get_post_meta($showtime_id, '_cinema_showtime_movie_id', true));
$room_id     = absint(get_post_meta($showtime_id, '_cinema_showtime_room_id', true));
$cinema_id   = absint(get_post_meta($room_id, '_cinema_room_cinema_id', true));
?>
<main class="cinema-shell">
	<section class="cinema-page-hero">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('Seat Selection', 'cinema-theme'); ?></p>
			<h1><?php echo esc_html($showtime_id ? get_the_title($movie_id) : __('Choose a showtime first', 'cinema-theme')); ?></h1>
			<p><?php echo esc_html($showtime_id ? get_the_title($cinema_id) . ' | ' . get_the_title($room_id) : __('Start from a movie detail page and choose a screening to load the seat map.', 'cinema-theme')); ?></p>
		</div>
	</section>

	<?php if (! $showtime_id) : ?>
		<div class="cinema-empty-card">
			<h3><?php esc_html_e('Missing showtime.', 'cinema-theme'); ?></h3>
			<p><?php esc_html_e('Open a movie and click Select Seats on a showtime card.', 'cinema-theme'); ?></p>
		</div>
	<?php else : ?>
		<div class="cinema-booking-shell">
			<section class="cinema-booking-panel">
				<div class="cinema-screen-banner"><?php esc_html_e('SCREEN', 'cinema-theme'); ?></div>
				<div class="cinema-seat-map" data-cinema-seat-app data-showtime-id="<?php echo esc_attr($showtime_id); ?>">
					<p><?php esc_html_e('Loading seat map...', 'cinema-theme'); ?></p>
				</div>
			</section>

			<aside class="cinema-booking-panel">
				<h2><?php esc_html_e('Your Selection', 'cinema-theme'); ?></h2>
				<ul class="cinema-booking-summary-list">
					<li><span><?php esc_html_e('Movie', 'cinema-theme'); ?></span><strong><?php echo esc_html(get_the_title($movie_id)); ?></strong></li>
					<li><span><?php esc_html_e('Showtime', 'cinema-theme'); ?></span><strong><?php echo esc_html((string) get_post_meta($showtime_id, '_cinema_showtime_start_datetime', true)); ?></strong></li>
					<li><span><?php esc_html_e('Seats', 'cinema-theme'); ?></span><strong data-seat-summary><?php esc_html_e('None selected', 'cinema-theme'); ?></strong></li>
					<li><span><?php esc_html_e('Count', 'cinema-theme'); ?></span><strong data-seat-total>0</strong></li>
				</ul>

				<?php if (is_user_logged_in()) : ?>
					<button class="cinema-cta cinema-cta-block" type="button" data-seat-lock><?php esc_html_e('Lock Seats & Continue', 'cinema-theme'); ?></button>
				<?php else : ?>
					<a class="cinema-cta cinema-cta-block" href="<?php echo esc_url(cinema_theme_get_page_url('auth')); ?>"><?php esc_html_e('Login To Book', 'cinema-theme'); ?></a>
				<?php endif; ?>
			</aside>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
