<?php

declare(strict_types=1);

function cinema_base_url(string $path = ''): string {
	$config = cinema_config();
	$base   = trim((string) ($config['app_url'] ?? ''));

	if ('' === $base) {
		$base = dirname($_SERVER['SCRIPT_NAME'] ?? '');
		$base = '/' . trim(str_replace('\\', '/', (string) $base), '/.');
		$base = '/' === $base ? '' : $base;
	}

	$base = rtrim($base, '/');

	if ('' === $path) {
		return '' === $base ? '/' : $base . '/';
	}

	return ('' === $base ? '' : $base) . '/' . ltrim($path, '/');
}

function cinema_asset_url(string $path): string {
	return cinema_base_url(ltrim($path, '/'));
}

function cinema_data_source(): string {
	$config = cinema_config();
	$source = trim((string) ($config['data_source'] ?? 'database'));

	return '' !== $source ? $source : 'database';
}

function cinema_use_wordpress_api(): bool {
	return 'wordpress_api' === cinema_data_source();
}

function cinema_wordpress_api_ready(): bool {
	$config  = cinema_config();
	$api     = $config['wordpress_api'] ?? array();
	$baseUrl = trim((string) ($api['base_url'] ?? ''));
	$apiKey  = trim((string) ($api['api_key'] ?? ''));

	return '' !== $baseUrl && '' !== $apiKey;
}

function cinema_wordpress_api_config(): array {
	$config = cinema_config();
	$api    = $config['wordpress_api'] ?? array();

	return array(
		'base_url' => rtrim(trim((string) ($api['base_url'] ?? '')), '/'),
		'api_key'  => trim((string) ($api['api_key'] ?? '')),
		'timeout'  => max(3, (int) ($api['timeout'] ?? 12)),
	);
}

function cinema_data_source_badge(): array {
	if (cinema_use_wordpress_api()) {
		if (cinema_wordpress_api_ready()) {
			return array(
				'label' => 'Đang đồng bộ với plugin WordPress',
				'class' => 'is-live',
			);
		}

		return array(
			'label' => 'WordPress API chưa cấu hình đủ',
			'class' => 'is-warning',
		);
	}

	return array(
		'label' => 'Đang dùng dữ liệu MySQL cục bộ',
		'class' => 'is-local',
	);
}

function cinema_available_payment_methods(): array {
	$methods = array(
		'counter'       => 'Thanh toán tại quầy',
		'bank_transfer' => 'Chuyển khoản',
	);

	if (cinema_use_wordpress_api()) {
		$methods['vnpay'] = 'VNPay';
		$methods['momo']  = 'MoMo';
	}

	return $methods;
}

function cinema_current_url(): string {
	$scheme = (! empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
	$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$uri    = $_SERVER['REQUEST_URI'] ?? cinema_base_url();

	if (preg_match('~^https?://~i', (string) $uri)) {
		return (string) $uri;
	}

	return $scheme . '://' . $host . $uri;
}

function cinema_escape(?string $value): string {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cinema_currency(float $amount): string {
	return number_format($amount, 0, ',', '.') . ' VND';
}

function cinema_datetime(string $value, string $format = 'd/m/Y H:i'): string {
	$timestamp = strtotime($value);

	return $timestamp ? date($format, $timestamp) : $value;
}

function cinema_date_only(string $value): string {
	return cinema_datetime($value, 'd/m/Y');
}

function cinema_redirect(string $path): void {
	header('Location: ' . $path);
	exit;
}

function cinema_set_flash(string $type, string $message): void {
	if (! isset($_SESSION['cinema_flashes']) || ! is_array($_SESSION['cinema_flashes'])) {
		$_SESSION['cinema_flashes'] = array();
	}

	$_SESSION['cinema_flashes'][] = array(
		'type'    => $type,
		'message' => $message,
	);
}

function cinema_get_flashes(): array {
	$flashes = $_SESSION['cinema_flashes'] ?? array();
	unset($_SESSION['cinema_flashes']);

	return is_array($flashes) ? $flashes : array();
}

function cinema_csrf_token(): string {
	if (empty($_SESSION['cinema_csrf_token'])) {
		$_SESSION['cinema_csrf_token'] = bin2hex(random_bytes(32));
	}

	return (string) $_SESSION['cinema_csrf_token'];
}

function cinema_verify_csrf(?string $token): bool {
	$sessionToken = $_SESSION['cinema_csrf_token'] ?? '';

	return is_string($token) && '' !== $sessionToken && hash_equals($sessionToken, $token);
}

function cinema_old(string $key, string $default = ''): string {
	return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function cinema_payment_label(string $method): string {
	$labels = array(
		'counter'       => 'Thanh toán tại quầy',
		'cash'          => 'Thanh toán tại quầy',
		'bank_transfer' => 'Chuyển khoản',
		'vnpay'         => 'VNPay',
		'momo'          => 'MoMo',
	);

	return $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

function cinema_status_label(string $status): string {
	$labels = array(
		'now_showing' => 'Đang chiếu',
		'coming_soon' => 'Sắp chiếu',
		'ended'       => 'Đã kết thúc',
		'confirmed'   => 'Đã xác nhận',
		'cancelled'   => 'Đã hủy',
		'pending'     => 'Chờ thanh toán',
		'paid'        => 'Đã thanh toán',
		'unpaid'      => 'Chưa thanh toán',
		'failed'      => 'Thanh toán thất bại',
		'refunded'    => 'Đã hoàn tiền',
		'success'     => 'Thành công',
	);

	return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function cinema_seat_type_label(string $seatType): string {
	$labels = array(
		'normal' => 'Thường',
		'vip'    => 'VIP',
		'couple' => 'Couple',
	);

	return $labels[$seatType] ?? ucfirst(str_replace('_', ' ', $seatType));
}

function cinema_badge_class(string $status): string {
	$map = array(
		'confirmed' => 'is-success',
		'paid'      => 'is-success',
		'success'   => 'is-success',
		'pending'   => 'is-warning',
		'unpaid'    => 'is-warning',
		'refunded'  => 'is-neutral',
		'cancelled' => 'is-danger',
		'failed'    => 'is-danger',
	);

	return $map[$status] ?? 'is-neutral';
}

function cinema_poster_url(?string $posterPath): string {
	$posterPath = trim((string) $posterPath);

	if ('' === $posterPath) {
		return cinema_asset_url('assets/images/poster-fallback.svg');
	}

	if (preg_match('~^https?://~i', $posterPath)) {
		return $posterPath;
	}

	return cinema_asset_url($posterPath);
}

function cinema_excerpt(string $text, int $length = 150): string {
	$clean = trim(preg_replace('/\s+/', ' ', strip_tags($text)));

	if (function_exists('mb_strimwidth')) {
		return mb_strimwidth($clean, 0, $length, '...');
	}

	if (strlen($clean) <= $length) {
		return $clean;
	}

	return substr($clean, 0, max(0, $length - 3)) . '...';
}

function cinema_join_non_empty(array $parts, string $glue = ', '): string {
	$items = array_values(
		array_filter(
			array_map(
				static fn($value): string => trim((string) $value),
				$parts
			),
			static fn($value): bool => '' !== $value
		)
	);

	return implode($glue, $items);
}
