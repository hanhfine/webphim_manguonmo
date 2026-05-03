<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$filters = array(
	'q'      => trim((string) ($_GET['q'] ?? '')),
	'genre'  => trim((string) ($_GET['genre'] ?? '')),
	'status' => trim((string) ($_GET['status'] ?? 'now_showing')),
);

$movies = array();
$genres = array();

try {
	$movies = cinema_fetch_movies($filters);
	$genres = cinema_fetch_genres();
} catch (Throwable $exception) {
	cinema_set_flash('error', $exception->getMessage());
}

cinema_render_header(
	'Phim đang chiếu',
	array(
		'current'     => 'home',
		'description' => 'Khám phá phim đang chiếu, lọc theo thể loại và chọn suất chiếu phù hợp để đặt vé nhanh trên web khách hàng đồng bộ từ hệ quản trị rạp.',
	)
);
?>
<main class="page-shell">
	<section class="page-band">
		<div class="container">
			<div class="section-head">
				<div>
					<p class="eyebrow">Lịch chiếu khách hàng</p>
					<h1>Chọn phim và đặt ghế ngay hôm nay</h1>
				</div>
				<div class="stats-strip">
					<div>
						<strong><?php echo count($movies); ?></strong>
						<span>Phim hiển thị</span>
					</div>
					<div>
						<strong><?php echo count(array_filter($movies, static fn(array $movie): bool => (int) $movie['open_showtimes'] > 0)); ?></strong>
						<span>Phim đang mở bán</span>
					</div>
				</div>
			</div>

			<form class="toolbar" method="get">
				<div class="toolbar-field">
					<label for="q">Tìm phim</label>
					<input id="q" type="search" name="q" value="<?php echo cinema_escape($filters['q']); ?>" placeholder="Tên phim hoặc mô tả">
				</div>
				<div class="toolbar-field">
					<label for="genre">Thể loại</label>
					<select id="genre" name="genre">
						<option value="">Tất cả</option>
						<?php foreach ($genres as $genre) : ?>
							<option value="<?php echo cinema_escape($genre); ?>" <?php echo $filters['genre'] === $genre ? 'selected' : ''; ?>>
								<?php echo cinema_escape($genre); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="toolbar-field">
					<label for="status">Trạng thái</label>
					<select id="status" name="status">
						<option value="">Tất cả</option>
						<option value="now_showing" <?php echo 'now_showing' === $filters['status'] ? 'selected' : ''; ?>>Đang chiếu</option>
						<option value="coming_soon" <?php echo 'coming_soon' === $filters['status'] ? 'selected' : ''; ?>>Sắp chiếu</option>
						<option value="ended" <?php echo 'ended' === $filters['status'] ? 'selected' : ''; ?>>Đã kết thúc</option>
					</select>
				</div>
				<div class="toolbar-actions">
					<button class="button-primary" type="submit">Lọc lịch chiếu</button>
				</div>
			</form>
		</div>
	</section>

	<section class="page-band">
		<div class="container">
			<div class="movie-grid">
				<?php if (empty($movies)) : ?>
					<div class="empty-state">
						<h2>Không tìm thấy phim phù hợp</h2>
						<p>Thử đổi bộ lọc hoặc từ khóa tìm kiếm để xem thêm lịch chiếu.</p>
					</div>
				<?php endif; ?>

				<?php foreach ($movies as $movie) : ?>
					<article class="movie-card">
						<a class="movie-poster" href="<?php echo cinema_escape(cinema_base_url('movie.php?id=' . (int) $movie['id'])); ?>">
							<img src="<?php echo cinema_escape(cinema_poster_url($movie['poster_url'])); ?>" alt="<?php echo cinema_escape($movie['title']); ?>">
						</a>
						<div class="movie-card-body">
							<div class="movie-meta-line">
								<span class="pill"><?php echo cinema_escape(cinema_status_label($movie['status'])); ?></span>
								<span><?php echo cinema_escape($movie['genre']); ?></span>
							</div>
							<h2><a href="<?php echo cinema_escape(cinema_base_url('movie.php?id=' . (int) $movie['id'])); ?>"><?php echo cinema_escape($movie['title']); ?></a></h2>
							<p><?php echo cinema_escape(cinema_excerpt($movie['description'], 150)); ?></p>
							<div class="movie-meta-grid">
								<div>
									<strong><?php echo (int) $movie['duration_minutes']; ?>'</strong>
									<span>Thời lượng</span>
								</div>
								<div>
									<strong><?php echo (int) $movie['open_showtimes']; ?></strong>
									<span>Suất mở bán</span>
								</div>
								<div>
									<strong><?php echo cinema_escape($movie['rating']); ?></strong>
									<span>Độ tuổi</span>
								</div>
							</div>
							<div class="movie-card-actions">
								<?php if (! empty($movie['next_showtime'])) : ?>
									<span class="schedule-chip"><?php echo cinema_escape(cinema_datetime($movie['next_showtime'])); ?></span>
								<?php endif; ?>
								<a class="button-primary" href="<?php echo cinema_escape(cinema_base_url('movie.php?id=' . (int) $movie['id'])); ?>">Xem suất chiếu</a>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
</main>
<?php cinema_render_footer(); ?>
