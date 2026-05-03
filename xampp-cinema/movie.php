<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$movieId   = (int) ($_GET['id'] ?? 0);
$movie     = null;
$showtimes = array();

try {
	$movie     = cinema_fetch_movie($movieId);
	$showtimes = $movie ? cinema_fetch_showtimes_for_movie($movieId) : array();
} catch (Throwable $exception) {
	cinema_set_flash('error', $exception->getMessage());
	cinema_redirect(cinema_base_url('index.php'));
}

if (! $movie) {
	cinema_set_flash('error', 'Không tìm thấy phim bạn vừa mở.');
	cinema_redirect(cinema_base_url('index.php'));
}

cinema_render_header(
	$movie['title'],
	array(
		'current'     => 'home',
		'description' => 'Xem chi tiết phim, rạp chiếu và chọn suất chiếu phù hợp trước khi đặt ghế.',
	)
);
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container movie-hero">
			<div class="hero-poster">
				<img src="<?php echo cinema_escape(cinema_poster_url($movie['poster_url'])); ?>" alt="<?php echo cinema_escape($movie['title']); ?>">
			</div>
			<div class="hero-copy">
				<p class="eyebrow"><?php echo cinema_escape($movie['genre']); ?> / <?php echo cinema_escape(cinema_status_label($movie['status'])); ?></p>
				<h1><?php echo cinema_escape($movie['title']); ?></h1>
				<p class="hero-summary"><?php echo cinema_escape($movie['description']); ?></p>
				<div class="detail-pills">
					<span><?php echo (int) $movie['duration_minutes']; ?> phút</span>
					<span><?php echo cinema_escape($movie['rating']); ?></span>
					<span>Khởi chiếu <?php echo cinema_escape(cinema_date_only($movie['release_date'])); ?></span>
				</div>
				<div class="detail-grid">
					<div>
						<strong>Trailer</strong>
						<span><?php echo $movie['trailer_url'] ? 'Có liên kết trailer' : 'Đang cập nhật'; ?></span>
					</div>
					<div>
						<strong>Trạng thái</strong>
						<span><?php echo cinema_escape(cinema_status_label($movie['status'])); ?></span>
					</div>
					<div>
						<strong>Thể loại</strong>
						<span><?php echo cinema_escape($movie['genre']); ?></span>
					</div>
					<div>
						<strong>Đánh giá</strong>
						<span><?php echo cinema_escape($movie['rating']); ?></span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="page-band">
		<div class="container">
			<div class="section-head compact">
				<div>
					<p class="eyebrow">Suất chiếu</p>
					<h2>Chọn rạp và giờ chiếu</h2>
				</div>
			</div>

			<?php if (empty($showtimes)) : ?>
				<div class="empty-state">
					<h2>Chưa có suất chiếu mở bán</h2>
					<p>Bạn có thể quay lại sau khi quản trị thêm lịch chiếu trong plugin WordPress hoặc hệ dữ liệu của website.</p>
				</div>
			<?php else : ?>
				<div class="showtime-grid">
					<?php foreach ($showtimes as $showtime) : ?>
						<article class="showtime-card">
							<div class="showtime-card-top">
								<div>
									<h3><?php echo cinema_escape($showtime['cinema_name']); ?></h3>
									<p><?php echo cinema_escape(cinema_join_non_empty(array($showtime['address'] ?? '', $showtime['city'] ?? ''))); ?></p>
								</div>
								<span class="pill"><?php echo cinema_escape(strtoupper($showtime['screen_type'])); ?></span>
							</div>
							<div class="showtime-main-line">
								<strong><?php echo cinema_escape(cinema_datetime($showtime['start_time'])); ?></strong>
								<span><?php echo cinema_escape($showtime['room_name']); ?></span>
							</div>
							<div class="price-strip">
								<span>Thường <?php echo cinema_escape(cinema_currency((float) $showtime['price_normal'])); ?></span>
								<span>VIP <?php echo cinema_escape(cinema_currency((float) $showtime['price_vip'])); ?></span>
								<span>Couple <?php echo cinema_escape(cinema_currency((float) $showtime['price_couple'])); ?></span>
							</div>
							<a class="button-primary" href="<?php echo cinema_escape(cinema_base_url('booking.php?showtime_id=' . (int) $showtime['id'])); ?>">Chọn ghế</a>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php cinema_render_footer(); ?>
