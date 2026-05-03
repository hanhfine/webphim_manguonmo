<?php

get_header();

$movies = cinema_theme_get_movies();
$genres = get_terms(
	array(
		'taxonomy'   => 'movie_genre',
		'hide_empty' => false,
	)
);
?>
<main class="cinema-shell">
	<section class="cinema-page-hero cinema-page-hero-home">
		<div class="cinema-hero-copy">
			<p class="cinema-kicker"><?php esc_html_e('Spotlight Tonight', 'cinema-theme'); ?></p>
			<h1><?php esc_html_e('Book the next premiere before the lights go down.', 'cinema-theme'); ?></h1>
			<p><?php esc_html_e('A cinema-first storefront with showtime discovery, seat selection, and one flow from poster to ticket.', 'cinema-theme'); ?></p>
		</div>
		<div class="cinema-hero-card">
			<span><?php esc_html_e('Live Filters', 'cinema-theme'); ?></span>
			<form method="get" class="cinema-filter-form">
				<select name="genre">
					<option value=""><?php esc_html_e('All genres', 'cinema-theme'); ?></option>
					<?php foreach ($genres as $genre) : ?>
						<option value="<?php echo esc_attr($genre->slug); ?>" <?php selected(sanitize_title(wp_unslash($_GET['genre'] ?? '')), $genre->slug); ?>>
							<?php echo esc_html($genre->name); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php esc_html_e('All statuses', 'cinema-theme'); ?></option>
					<?php foreach (array('now_showing', 'coming_soon', 'ended') as $status) : ?>
						<option value="<?php echo esc_attr($status); ?>" <?php selected(sanitize_key(wp_unslash($_GET['status'] ?? '')), $status); ?>>
							<?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit"><?php esc_html_e('Apply', 'cinema-theme'); ?></button>
			</form>
		</div>
	</section>

	<section class="cinema-section-heading">
		<div>
			<p class="cinema-kicker"><?php esc_html_e('Movie Catalog', 'cinema-theme'); ?></p>
			<h2><?php esc_html_e('Films ready for booking', 'cinema-theme'); ?></h2>
		</div>
	</section>

	<section class="cinema-movie-grid">
		<?php if ($movies->have_posts()) : ?>
			<?php while ($movies->have_posts()) : $movies->the_post(); ?>
				<?php $meta = cinema_theme_get_movie_meta(get_the_ID()); ?>
				<article class="cinema-movie-card">
					<a class="cinema-movie-poster" href="<?php the_permalink(); ?>">
						<?php if (has_post_thumbnail()) : ?>
							<?php the_post_thumbnail('large'); ?>
						<?php else : ?>
							<span><?php esc_html_e('Poster pending', 'cinema-theme'); ?></span>
						<?php endif; ?>
					</a>
					<div class="cinema-movie-card-copy">
						<div class="cinema-meta-line">
							<span class="cinema-badge"><?php echo esc_html(ucwords(str_replace('_', ' ', $meta['status'] ?: 'now_showing'))); ?></span>
							<span><?php echo esc_html($meta['duration_minutes']); ?> <?php esc_html_e('min', 'cinema-theme'); ?></span>
						</div>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>
						<div class="cinema-meta-line">
							<span><?php echo esc_html($meta['rating'] ?: 'TBA'); ?></span>
							<span><?php echo esc_html($meta['review_score'] ?: '0'); ?>/10</span>
						</div>
					</div>
				</article>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="cinema-empty-card">
				<h3><?php esc_html_e('No movies matched the current filter.', 'cinema-theme'); ?></h3>
				<p><?php esc_html_e('Try another genre or movie status.', 'cinema-theme'); ?></p>
			</div>
		<?php endif; ?>
	</section>
</main>
<?php
get_footer();
