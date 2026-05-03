<?php

get_header();

the_post();
$meta              = cinema_theme_get_movie_meta(get_the_ID());
$embed_url         = cinema_theme_get_youtube_embed_url($meta['trailer_url']);
$grouped_showtimes = cinema_theme_get_grouped_showtimes(get_the_ID());
?>
<main class="cinema-shell">
	<section class="cinema-movie-hero">
		<div class="cinema-movie-hero-poster">
			<?php if (has_post_thumbnail()) : ?>
				<?php the_post_thumbnail('large'); ?>
			<?php else : ?>
				<div class="cinema-poster-fallback"><?php esc_html_e('Poster', 'cinema-theme'); ?></div>
			<?php endif; ?>
		</div>

		<div class="cinema-movie-hero-copy">
			<p class="cinema-kicker"><?php echo esc_html(ucwords(str_replace('_', ' ', $meta['status'] ?: 'now_showing'))); ?></p>
			<h1><?php the_title(); ?></h1>
			<div class="cinema-inline-chips">
				<span><?php echo esc_html($meta['duration_minutes']); ?> <?php esc_html_e('minutes', 'cinema-theme'); ?></span>
				<span><?php echo esc_html($meta['rating'] ?: 'TBA'); ?></span>
				<span><?php echo esc_html($meta['review_score'] ?: '0'); ?>/10</span>
			</div>
			<div class="cinema-copy-card">
				<?php the_content(); ?>
			</div>
			<div class="cinema-detail-grid">
				<div><strong><?php esc_html_e('Director', 'cinema-theme'); ?></strong><span><?php echo esc_html($meta['director'] ?: __('Updating', 'cinema-theme')); ?></span></div>
				<div><strong><?php esc_html_e('Cast', 'cinema-theme'); ?></strong><span><?php echo esc_html($meta['cast'] ?: __('Updating', 'cinema-theme')); ?></span></div>
				<div><strong><?php esc_html_e('Release', 'cinema-theme'); ?></strong><span><?php echo esc_html($meta['release_date'] ?: __('TBA', 'cinema-theme')); ?></span></div>
				<div><strong><?php esc_html_e('Run Ends', 'cinema-theme'); ?></strong><span><?php echo esc_html($meta['end_date'] ?: __('TBA', 'cinema-theme')); ?></span></div>
			</div>
		</div>
	</section>

	<?php if ($embed_url) : ?>
		<section class="cinema-panel-stack">
			<div class="cinema-video-frame">
				<iframe src="<?php echo esc_url($embed_url); ?>" title="<?php the_title_attribute(); ?>" allowfullscreen></iframe>
			</div>
		</section>
	<?php endif; ?>

	<section class="cinema-panel-stack">
		<div class="cinema-section-heading">
			<div>
				<p class="cinema-kicker"><?php esc_html_e('Showtimes', 'cinema-theme'); ?></p>
				<h2><?php esc_html_e('Choose a screening', 'cinema-theme'); ?></h2>
			</div>
		</div>

		<?php if (empty($grouped_showtimes)) : ?>
			<div class="cinema-empty-card">
				<h3><?php esc_html_e('No showtimes are open yet.', 'cinema-theme'); ?></h3>
				<p><?php esc_html_e('Add showtimes from the admin panel to start selling tickets.', 'cinema-theme'); ?></p>
			</div>
		<?php endif; ?>

		<?php foreach ($grouped_showtimes as $date => $items) : ?>
			<section class="cinema-showtime-group">
				<h3><?php echo esc_html($date); ?></h3>
				<div class="cinema-showtime-grid">
					<?php foreach ($items as $item) : ?>
						<article class="cinema-showtime-card">
							<div>
								<strong><?php echo esc_html($item['cinema_title']); ?></strong>
								<p><?php echo esc_html($item['room_title']); ?></p>
							</div>
							<div class="cinema-meta-line">
								<span><?php echo esc_html($item['start']); ?></span>
								<span><?php echo esc_html(strtoupper($item['status'])); ?></span>
							</div>
							<div class="cinema-price-strip">
								<span>N <?php echo esc_html(number_format_i18n($item['prices']['normal'], 0)); ?></span>
								<span>VIP <?php echo esc_html(number_format_i18n($item['prices']['vip'], 0)); ?></span>
								<span>C <?php echo esc_html(number_format_i18n($item['prices']['couple'], 0)); ?></span>
							</div>
							<a class="cinema-cta" href="<?php echo esc_url(add_query_arg('showtime', $item['id'], cinema_theme_get_page_url('seat-selection'))); ?>">
								<?php esc_html_e('Select Seats', 'cinema-theme'); ?>
							</a>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</section>
</main>
<?php
wp_reset_postdata();
get_footer();
