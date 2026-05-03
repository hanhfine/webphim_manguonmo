<?php

declare(strict_types=1);

function cinema_render_header(string $title, array $options = array()): void {
	$appName     = cinema_config()['app_name'] ?? 'MYCINEMA';
	$pageName    = trim($title) !== '' ? $title . ' | ' . $appName : $appName;
	$description = trim((string) ($options['description'] ?? ''));
	$description = '' !== $description ? $description : 'Đặt vé xem phim online nhanh chóng, chọn ghế theo sơ đồ rạp trực quan và tra cứu vé dễ dàng.';
	$canonical   = trim((string) ($options['canonical'] ?? cinema_current_url()));
	$flashes     = cinema_get_flashes();
	$current     = $options['current'] ?? '';
	$dataSource  = cinema_data_source_badge();
	$brandNote   = cinema_use_wordpress_api()
		? 'Dữ liệu đang lấy từ plugin WordPress'
		: 'Đặt vé xem phim bằng PHP + MySQL';
	?>
	<!DOCTYPE html>
	<html lang="vi">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php echo cinema_escape($pageName); ?></title>
		<meta name="description" content="<?php echo cinema_escape($description); ?>">
		<meta property="og:title" content="<?php echo cinema_escape($pageName); ?>">
		<meta property="og:description" content="<?php echo cinema_escape($description); ?>">
		<meta property="og:type" content="website">
		<meta property="og:locale" content="vi_VN">
		<meta property="og:url" content="<?php echo cinema_escape($canonical); ?>">
		<link rel="canonical" href="<?php echo cinema_escape($canonical); ?>">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="<?php echo cinema_escape(cinema_asset_url('assets/css/site.css')); ?>">
	</head>
	<body>
		<header class="site-header">
			<div class="container header-inner">
				<a class="brand" href="<?php echo cinema_escape(cinema_base_url('index.php')); ?>">
					<span class="brand-mark">MY</span>
					<span class="brand-copy">
						<strong><?php echo cinema_escape($appName); ?></strong>
						<small><?php echo cinema_escape($brandNote); ?></small>
					</span>
				</a>
				<span class="source-badge <?php echo cinema_escape($dataSource['class']); ?>">
					<?php echo cinema_escape($dataSource['label']); ?>
				</span>
				<button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Mở menu điều hướng" data-nav-toggle>
					<span class="nav-toggle-bar" aria-hidden="true"></span>
					<span class="nav-toggle-bar" aria-hidden="true"></span>
					<span class="nav-toggle-bar" aria-hidden="true"></span>
				</button>
				<nav class="nav-links" id="site-nav" data-site-nav>
					<a class="<?php echo 'home' === $current ? 'is-active' : ''; ?>" href="<?php echo cinema_escape(cinema_base_url('index.php')); ?>">Phim đang chiếu</a>
					<a class="<?php echo 'lookup' === $current ? 'is-active' : ''; ?>" href="<?php echo cinema_escape(cinema_base_url('my-bookings.php')); ?>">Tra cứu vé</a>
				</nav>
			</div>
		</header>
		<?php if (! empty($flashes)) : ?>
			<div class="flash-stack container">
				<?php foreach ($flashes as $flash) : ?>
					<div class="flash-message <?php echo cinema_escape('flash-' . ($flash['type'] ?? 'info')); ?>">
						<?php echo cinema_escape($flash['message'] ?? ''); ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php
}

function cinema_render_footer(): void {
	$appName = cinema_config()['app_name'] ?? 'MYCINEMA';
	?>
		<footer class="site-footer">
			<div class="container footer-inner">
				<strong><?php echo cinema_escape($appName); ?></strong>
			</div>
		</footer>
		<script src="<?php echo cinema_escape(cinema_asset_url('assets/js/app.js')); ?>"></script>
	</body>
	</html>
	<?php
}
