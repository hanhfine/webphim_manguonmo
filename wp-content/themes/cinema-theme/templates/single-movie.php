<?php

get_header();

the_post();

$post_id           = get_the_ID();
$custom_movie_id   = get_post_meta($post_id, '_cinema_custom_id', true) ?: $post_id;
$meta              = cinema_theme_get_movie_meta($post_id);
$embed_url         = cinema_theme_get_youtube_embed_url($meta['trailer_url']);
$grouped_showtimes = cinema_theme_get_grouped_showtimes($custom_movie_id);
$movie_title       = cinema_theme_present_text(get_the_title(), __('Phim đang cập nhật', 'cinema-theme'));
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container movie-hero">
			<div class="hero-poster">
				<?php if (has_post_thumbnail()) : ?>
					<?php the_post_thumbnail('large'); ?>
				<?php else : ?>
					<div class="poster-fallback"><?php esc_html_e('Poster', 'cinema-theme'); ?></div>
				<?php endif; ?>
			</div>

			<div class="hero-copy">
				<p class="eyebrow"><?php 
					$terms = get_the_terms(get_the_ID(), 'movie_genre');
					$genre = $terms && !is_wp_error($terms) ? $terms[0]->name : __('Chưa phân loại', 'cinema-theme');
					echo esc_html($genre . ' / ' . cinema_status_label((string) ($meta['status'] ?: 'now_showing'))); 
				?></p>
				<h1><?php echo esc_html($movie_title); ?></h1>
				<div class="hero-summary">
					<?php the_content(); ?>
				</div>
				<div class="detail-pills">
					<span><?php echo esc_html((int) $meta['duration_minutes']); ?> <?php esc_html_e('phút', 'cinema-theme'); ?></span>
					<span><?php echo esc_html($meta['rating'] ?: 'TBA'); ?></span>
					<span><?php esc_html_e('Khởi chiếu', 'cinema-theme'); ?> <?php echo esc_html($meta['release_date'] ? cinema_date_only((string) $meta['release_date']) : __('TBA', 'cinema-theme')); ?></span>
				</div>
				<div class="detail-grid">
					<div>
						<strong><?php esc_html_e('Trailer', 'cinema-theme'); ?></strong>
						<span><?php echo $embed_url ? __('Có liên kết trailer', 'cinema-theme') : __('Đang cập nhật', 'cinema-theme'); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e('Trạng thái', 'cinema-theme'); ?></strong>
						<span><?php echo esc_html(cinema_status_label((string) ($meta['status'] ?: 'now_showing'))); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e('Đạo diễn', 'cinema-theme'); ?></strong>
						<span><?php echo esc_html($meta['director'] ?: __('Đang cập nhật', 'cinema-theme')); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e('Diễn viên', 'cinema-theme'); ?></strong>
						<span><?php echo esc_html($meta['cast'] ?: __('Đang cập nhật', 'cinema-theme')); ?></span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<?php if ($embed_url) : ?>
		<section class="page-band">
			<div class="container">
				<div class="cinema-video-frame">
					<iframe src="<?php echo esc_url($embed_url); ?>" title="<?php the_title_attribute(); ?>" allowfullscreen></iframe>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="page-band">
		<div class="container">
			<div class="section-head compact">
				<div>
					<p class="eyebrow"><?php esc_html_e('Suất chiếu', 'cinema-theme'); ?></p>
					<h2><?php esc_html_e('Chọn rạp và giờ chiếu', 'cinema-theme'); ?></h2>
				</div>
			</div>

			<?php if (empty($grouped_showtimes)) : ?>
				<div class="empty-state">
					<h2><?php esc_html_e('Chưa có suất chiếu mở bán', 'cinema-theme'); ?></h2>
					<p><?php esc_html_e('Bạn có thể quay lại sau khi quản trị thêm lịch chiếu trong plugin WordPress hoặc hệ dữ liệu của website.', 'cinema-theme'); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ($grouped_showtimes as $date => $items) : ?>
					<h3 style="margin-top: 2rem; margin-bottom: 1rem;"><?php echo esc_html($date); ?></h3>
					<div class="showtime-grid">
						<?php foreach ($items as $item) : ?>
							<article class="showtime-card">
								<div class="showtime-card-top">
									<div>
										<h3><?php echo esc_html($item['cinema_title']); ?></h3>
										<p><?php echo esc_html($item['room_title']); ?></p>
									</div>
									<span class="pill"><?php echo esc_html(strtoupper($item['room_title'])); ?></span>
								</div>
								<div class="showtime-main-line">
									<strong><?php echo esc_html(cinema_datetime((string) $item['start'], 'H:i')); ?></strong>
									<span><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $item['status']))); ?></span>
								</div>
								<div class="price-strip">
									<span><?php esc_html_e('Thường', 'cinema-theme'); ?> <?php echo esc_html(cinema_currency((float) $item['prices']['normal'])); ?></span>
									<span><?php esc_html_e('VIP', 'cinema-theme'); ?> <?php echo esc_html(cinema_currency((float) $item['prices']['vip'])); ?></span>
									<span><?php esc_html_e('Couple', 'cinema-theme'); ?> <?php echo esc_html(cinema_currency((float) $item['prices']['couple'])); ?></span>
								</div>
								<a class="button-primary" href="<?php echo esc_url(add_query_arg('showtime', $item['id'], cinema_theme_get_page_url('seat-selection'))); ?>">
									<?php esc_html_e('Chọn ghế', 'cinema-theme'); ?>
								</a>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
wp_reset_postdata();
get_footer();
