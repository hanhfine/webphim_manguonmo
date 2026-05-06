<?php

get_header();

$movies_result = cinema_theme_get_movies();
$movies        = array();

// Handle both custom table (array) and WP_Query fallback
if ( is_array( $movies_result ) ) {
	// New: custom table — $movies_result is already array of rows
	foreach ( $movies_result as $movie_row ) {
		$movie_id          = (int) $movie_row['id'];
		$grouped_showtimes = cinema_theme_get_grouped_showtimes( $movie_id );

		// Resolve WordPress post permalink (post still exists in wp_posts for URL routing)
		$_wp_movie_post    = get_page_by_path( $movie_row['slug'], OBJECT, 'movie' );
		$movie_row['permalink'] = $_wp_movie_post
			? get_permalink( $_wp_movie_post )
			: home_url( '/movies/' . $movie_row['slug'] . '/' );

		$movies[] = array(
			'post'           => null,
			'meta'           => array(
				'trailer_url'      => $movie_row['trailer_url'] ?? '',
				'director'         => $movie_row['director'] ?? '',
				'cast'             => $movie_row['cast_list'] ?? '',
				'duration_minutes' => $movie_row['duration_minutes'] ?? 0,
				'release_date'     => $movie_row['release_date'] ?? '',
				'end_date'         => $movie_row['end_date'] ?? '',
				'rating'           => $movie_row['rating'] ?? '',
				'review_score'     => $movie_row['review_score'] ?? 0,
				'status'           => $movie_row['status'] ?? 'now_showing',
			),
			'showtime_count' => cinema_theme_count_grouped_showtimes( $grouped_showtimes ),
			'row'            => $movie_row,
		);
	}

} else {
	// Fallback: WP_Query
	$movies_query = $movies_result;
	$genres       = get_terms(
		array(
			'taxonomy'   => 'movie_genre',
			'hide_empty' => false,
		)
	);

	if ( $movies_query->have_posts() ) {
		while ( $movies_query->have_posts() ) {
			$movies_query->the_post();
			$grouped_showtimes = cinema_theme_get_grouped_showtimes( get_the_ID() );
			$movies[] = array(
				'post'           => get_post(),
				'meta'           => cinema_theme_get_movie_meta( get_the_ID() ),
				'showtime_count' => cinema_theme_count_grouped_showtimes( $grouped_showtimes ),
				'row'            => null,
			);
		}
		wp_reset_postdata();
	}
}

// Genres for filter (try custom table first)
if ( empty( $genres ) ) {
	global $wpdb;
	$genre_rows = $wpdb->get_col(
		"SELECT DISTINCT genre FROM {$wpdb->prefix}cinema_movies WHERE genre != '' ORDER BY genre ASC"
	);
	// Fake term objects
	$genres = array_map( static function ( $g ) {
		$t = new stdClass();
		$t->name = $g;
		$t->slug = sanitize_title( $g );
		return $t;
	}, $genre_rows ?: [] );
}
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container">
			<div class="section-head">
				<div>
					<p class="eyebrow"><?php esc_html_e('Lịch chiếu khách hàng', 'cinema-theme'); ?></p>
					<h1><?php esc_html_e('Chọn phim và đặt ghế ngay hôm nay', 'cinema-theme'); ?></h1>
				</div>
				<div class="stats-strip">
					<div>
						<strong><?php echo esc_html((string) count($movies)); ?></strong>
						<span><?php esc_html_e('Phim hiển thị', 'cinema-theme'); ?></span>
					</div>
				</div>
			</div>

			<form class="toolbar" method="get">
				<div class="toolbar-field">
					<label for="q"><?php esc_html_e('Tìm phim', 'cinema-theme'); ?></label>
					<input id="q" type="search" name="q" value="<?php echo esc_attr(wp_unslash($_GET['q'] ?? '')); ?>" placeholder="<?php esc_attr_e('Tên phim hoặc mô tả', 'cinema-theme'); ?>">
				</div>
				<div class="toolbar-field">
					<label for="genre"><?php esc_html_e('Thể loại', 'cinema-theme'); ?></label>
					<select id="genre" name="genre">
						<option value=""><?php esc_html_e('Tất cả', 'cinema-theme'); ?></option>
						<?php foreach ($genres as $genre) : ?>
							<option value="<?php echo esc_attr($genre->slug); ?>" <?php selected(sanitize_title(wp_unslash($_GET['genre'] ?? '')), $genre->slug); ?>>
								<?php echo esc_html($genre->name); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="toolbar-field">
					<label for="status"><?php esc_html_e('Trạng thái', 'cinema-theme'); ?></label>
					<select id="status" name="status">
						<option value=""><?php esc_html_e('Tất cả', 'cinema-theme'); ?></option>
						<?php foreach (array('now_showing', 'coming_soon', 'ended') as $status) : ?>
							<option value="<?php echo esc_attr($status); ?>" <?php selected(sanitize_key(wp_unslash($_GET['status'] ?? '')), $status); ?>>
								<?php echo esc_html(cinema_status_label($status)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="toolbar-actions">
					<button class="button-primary" type="submit"><?php esc_html_e('Lọc lịch chiếu', 'cinema-theme'); ?></button>
				</div>
			</form>
		</div>
	</section>

	<section class="page-band">
		<div class="container">
			<div class="movie-grid">
				<?php if (! empty($movies)) : ?>
					<?php foreach ($movies as $movie_item) : ?>
						<?php
						$meta       = $movie_item['meta'];
						$custom_row = $movie_item['row'] ?? null;
						$wp_post    = $movie_item['post'] ?? null;

						if ( $custom_row ) {
							// New system: custom table row
							$movie_title = cinema_theme_present_text( $custom_row['title'], __( 'Phim đang cập nhật', 'cinema-theme' ) );
							$movie_url   = $custom_row['permalink'] ?? home_url( '/movies/' . $custom_row['slug'] . '/' );
							$poster_url  = $custom_row['poster_url'] ?? '';
							$genre_label = $custom_row['genre'] ?? '';
							$excerpt     = wp_trim_words( $custom_row['description'] ?? '', 22 );
						} else {
							// Fallback: WordPress post
							setup_postdata( $wp_post );
							$movie_title = cinema_theme_present_text( get_the_title(), __( 'Phim đang cập nhật', 'cinema-theme' ) );
							$movie_url   = get_permalink( $wp_post );
							$poster_url  = get_post_meta( $wp_post->ID, '_cinema_poster_url', true ) ?: get_the_post_thumbnail_url( $wp_post, 'large' );
							$terms       = get_the_terms( $wp_post->ID, 'movie_genre' );
							$genre_label = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
							$excerpt     = wp_trim_words( get_the_excerpt(), 22 );
						}
						?>
						<article class="movie-card">
							<a class="movie-poster" href="<?php echo esc_url( $movie_url ); ?>">
								<?php if ( $poster_url ) : ?>
									<img src="<?php echo esc_url( $poster_url ); ?>" alt="<?php echo esc_attr( $movie_title ); ?>">
								<?php elseif ( $wp_post && has_post_thumbnail( $wp_post ) ) : ?>
									<?php echo get_the_post_thumbnail( $wp_post, 'large' ); ?>
								<?php else : ?>
									<div class="poster-fallback"><?php esc_html_e('Poster đang cập nhật', 'cinema-theme'); ?></div>
								<?php endif; ?>
							</a>
							<div class="movie-card-body">
								<div class="movie-meta-line">
									<span class="pill"><?php echo esc_html(cinema_status_label((string) ($meta['status'] ?: 'now_showing'))); ?></span>
									<span><?php echo $genre_label ? esc_html( $genre_label ) : '-'; ?></span>
								</div>
								<h2><a href="<?php echo esc_url( $movie_url ); ?>"><?php echo esc_html($movie_title); ?></a></h2>
								<p><?php echo esc_html( $excerpt ); ?></p>
								<div class="movie-meta-grid">
									<div>
										<strong><?php echo esc_html($meta['duration_minutes']); ?>'</strong>
										<span><?php esc_html_e('Thời lượng', 'cinema-theme'); ?></span>
									</div>
									<div>
										<strong><?php echo esc_html($meta['rating'] ?: 'TBA'); ?></strong>
										<span><?php esc_html_e('Độ tuổi', 'cinema-theme'); ?></span>
									</div>
									<div>
										<strong><?php echo esc_html($meta['release_date'] ? cinema_datetime((string) $meta['release_date']) : __('TBA', 'cinema-theme')); ?></strong>
										<span><?php esc_html_e('Khởi chiếu', 'cinema-theme'); ?></span>
									</div>
									<div>
										<strong><?php echo esc_html((string) $movie_item['showtime_count']); ?></strong>
										<span><?php esc_html_e('Suất mở bán', 'cinema-theme'); ?></span>
									</div>
								</div>
								<div class="movie-card-actions">
									<a class="button-primary" href="<?php echo esc_url( $movie_url ); ?>"><?php esc_html_e('Xem suất chiếu', 'cinema-theme'); ?></a>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="empty-state">
						<h2><?php esc_html_e('Không tìm thấy phim phù hợp', 'cinema-theme'); ?></h2>
						<p><?php esc_html_e('Thử đổi bộ lọc hoặc từ khóa tìm kiếm để xem thêm lịch chiếu.', 'cinema-theme'); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
