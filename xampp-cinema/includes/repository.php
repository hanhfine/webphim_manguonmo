<?php

declare(strict_types=1);

function cinema_fetch_movies(array $filters = array()): array {
	if (cinema_use_wordpress_api()) {
		$query = array_filter(
			array(
				'q'      => trim((string) ($filters['q'] ?? '')),
				'genre'  => trim((string) ($filters['genre'] ?? '')),
				'status' => trim((string) ($filters['status'] ?? '')),
			),
			static fn($value): bool => '' !== (string) $value
		);

		return cinema_wp_api_get('/movies', $query);
	}

	$pdo        = cinema_db();
	$params     = array();
	$conditions = array('1 = 1');

	if (! empty($filters['status'])) {
		$conditions[]     = 'm.status = :status';
		$params['status'] = $filters['status'];
	}

	if (! empty($filters['genre'])) {
		$conditions[]    = 'm.genre = :genre';
		$params['genre'] = $filters['genre'];
	}

	if (! empty($filters['q'])) {
		$conditions[]  = '(m.title LIKE :search OR m.description LIKE :search)';
		$params['search'] = '%' . $filters['q'] . '%';
	}

	$sql = "
		SELECT
			m.*,
			COUNT(s.id) AS open_showtimes,
			MIN(s.start_time) AS next_showtime
		FROM movies m
		LEFT JOIN showtimes s
			ON s.movie_id = m.id
			AND s.status = 'open'
			AND s.start_time >= NOW()
		WHERE " . implode(' AND ', $conditions) . "
		GROUP BY m.id
		ORDER BY FIELD(m.status, 'now_showing', 'coming_soon', 'ended'), m.release_date DESC, m.title ASC
	";

	$statement = $pdo->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll();
}

function cinema_fetch_movie(int $movieId): ?array {
	if (cinema_use_wordpress_api()) {
		try {
			$movie = cinema_wp_api_get('/movies/' . $movieId);
		} catch (Throwable $exception) {
			return null;
		}

		return is_array($movie) && ! empty($movie) ? $movie : null;
	}

	$pdo = cinema_db();

	$statement = $pdo->prepare('SELECT * FROM movies WHERE id = :id LIMIT 1');
	$statement->execute(array('id' => $movieId));
	$movie = $statement->fetch();

	return $movie ?: null;
}

function cinema_fetch_showtimes_for_movie(int $movieId): array {
	if (cinema_use_wordpress_api()) {
		return cinema_wp_api_get('/showtimes', array('movie_id' => $movieId));
	}

	$pdo = cinema_db();

	$statement = $pdo->prepare(
		"
		SELECT
			s.*,
			r.name AS room_name,
			r.screen_type,
			c.name AS cinema_name,
			c.city,
			c.address
		FROM showtimes s
		INNER JOIN rooms r ON r.id = s.room_id
		INNER JOIN cinemas c ON c.id = r.cinema_id
		WHERE s.movie_id = :movie_id
			AND s.status = 'open'
			AND s.start_time >= NOW()
		ORDER BY s.start_time ASC
		"
	);
	$statement->execute(array('movie_id' => $movieId));

	return $statement->fetchAll();
}

function cinema_fetch_showtime(int $showtimeId): ?array {
	if (cinema_use_wordpress_api()) {
		try {
			$showtime = cinema_wp_api_get('/showtimes/' . $showtimeId);
		} catch (Throwable $exception) {
			return null;
		}

		return is_array($showtime) && ! empty($showtime) ? $showtime : null;
	}

	$pdo = cinema_db();

	$statement = $pdo->prepare(
		"
		SELECT
			s.*,
			m.title AS movie_title,
			m.genre,
			m.poster_url,
			m.duration_minutes,
			m.rating,
			r.name AS room_name,
			r.screen_type,
			r.cinema_id,
			c.name AS cinema_name,
			c.city,
			c.address
		FROM showtimes s
		INNER JOIN movies m ON m.id = s.movie_id
		INNER JOIN rooms r ON r.id = s.room_id
		INNER JOIN cinemas c ON c.id = r.cinema_id
		WHERE s.id = :id
		LIMIT 1
		"
	);
	$statement->execute(array('id' => $showtimeId));
	$showtime = $statement->fetch();

	return $showtime ?: null;
}

function cinema_fetch_seats_for_showtime(int $showtimeId, int $roomId): array {
	if (cinema_use_wordpress_api()) {
		$payload = cinema_wp_api_get('/showtimes/' . $showtimeId . '/seats');
		$rows    = array();

		foreach ((array) ($payload['seat_map'] ?? array()) as $seat) {
			$status = (string) ($seat['status'] ?? 'available');

			$rows[] = array(
				'id'          => (int) ($seat['id'] ?? 0),
				'row_label'   => (string) ($seat['row_label'] ?? ''),
				'seat_number' => (int) ($seat['seat_number'] ?? 0),
				'seat_type'   => (string) ($seat['seat_type'] ?? 'normal'),
				'is_booked'   => in_array($status, array('booked', 'locked'), true) ? 1 : 0,
			);
		}

		return $rows;
	}

	$pdo = cinema_db();

	$statement = $pdo->prepare(
		"
		SELECT
			seat.id,
			seat.row_label,
			seat.seat_number,
			seat.seat_type,
			CASE
				WHEN EXISTS (
					SELECT 1
					FROM booking_seats bs
					INNER JOIN bookings b ON b.id = bs.booking_id
					WHERE bs.seat_id = seat.id
						AND b.showtime_id = :showtime_id
						AND b.booking_status = 'confirmed'
				) THEN 1
				ELSE 0
			END AS is_booked
		FROM seats seat
		WHERE seat.room_id = :room_id
			AND seat.is_active = 1
		ORDER BY LENGTH(seat.row_label) ASC, seat.row_label ASC, seat.seat_number ASC
		"
	);
	$statement->execute(
		array(
			'showtime_id' => $showtimeId,
			'room_id'     => $roomId,
		)
	);

	return $statement->fetchAll();
}

function cinema_group_seats(array $seats): array {
	$rows = array();

	foreach ($seats as $seat) {
		$rowLabel = $seat['row_label'];

		if (! isset($rows[$rowLabel])) {
			$rows[$rowLabel] = array();
		}

		$rows[$rowLabel][] = $seat;
	}

	return $rows;
}

function cinema_fetch_genres(): array {
	if (cinema_use_wordpress_api()) {
		$movies  = cinema_wp_api_get('/movies');
		$genres  = array();

		foreach ($movies as $movie) {
			$parts = array_filter(array_map('trim', explode(',', (string) ($movie['genre'] ?? ''))));

			foreach ($parts as $part) {
				$genres[$part] = $part;
			}
		}

		ksort($genres);

		return array_values($genres);
	}

	$pdo = cinema_db();

	$statement = $pdo->query("SELECT DISTINCT genre FROM movies WHERE genre <> '' ORDER BY genre ASC");

	return array_column($statement->fetchAll(), 'genre');
}

function cinema_generate_booking_code(PDO $pdo): string {
	do {
		$code      = 'CB' . strtoupper(bin2hex(random_bytes(3)));
		$statement = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE booking_code = :code');
		$statement->execute(array('code' => $code));
	} while ((int) $statement->fetchColumn() > 0);

	return $code;
}

function cinema_build_placeholders(array $values, string $prefix): array {
	$placeholders = array();
	$params       = array();

	foreach (array_values($values) as $index => $value) {
		$key            = $prefix . $index;
		$placeholders[] = ':' . $key;
		$params[$key]   = $value;
	}

	return array($placeholders, $params);
}

function cinema_create_booking(array $input): array {
	if (cinema_use_wordpress_api()) {
		$showtimeId      = (int) ($input['showtime_id'] ?? 0);
		$selectedSeatIds = array_values(array_unique(array_map('intval', $input['seat_ids'] ?? array())));
		$customerName    = trim((string) ($input['customer_name'] ?? ''));
		$email           = trim((string) ($input['email'] ?? ''));
		$phone           = trim((string) ($input['phone'] ?? ''));
		$paymentMethod   = trim((string) ($input['payment_method'] ?? 'counter'));

		if ($showtimeId <= 0 || empty($selectedSeatIds)) {
			throw new RuntimeException('Vui lòng chọn suất chiếu và ít nhất một ghế.');
		}

		if ('' === $customerName || ! filter_var($email, FILTER_VALIDATE_EMAIL) || '' === $phone) {
			throw new RuntimeException('Thông tin khách hàng chưa hợp lệ.');
		}

		if (! in_array($paymentMethod, array('counter', 'bank_transfer', 'vnpay', 'momo'), true)) {
			throw new RuntimeException('Phương thức thanh toán không hợp lệ.');
		}

		$response = cinema_wp_api_post(
			'/bookings',
			array(
				'showtime_id'    => $showtimeId,
				'seat_ids'       => $selectedSeatIds,
				'customer_name'  => $customerName,
				'email'          => $email,
				'phone'          => $phone,
				'payment_method' => $paymentMethod,
			)
		);

		$booking = (array) ($response['booking'] ?? array());

		if (empty($booking['booking_code'])) {
			throw new RuntimeException('Không thể tạo đặt vé từ hệ thống WordPress.');
		}

		cinema_cache_booking_payload($booking);

		return $booking;
	}

	$pdo             = cinema_db();
	$showtimeId      = (int) ($input['showtime_id'] ?? 0);
	$selectedSeatIds = array_values(array_unique(array_map('intval', $input['seat_ids'] ?? array())));
	$customerName    = trim((string) ($input['customer_name'] ?? ''));
	$email           = trim((string) ($input['email'] ?? ''));
	$phone           = trim((string) ($input['phone'] ?? ''));
	$paymentMethod   = trim((string) ($input['payment_method'] ?? 'counter'));

	if ($showtimeId <= 0 || empty($selectedSeatIds)) {
		throw new RuntimeException('Vui lòng chọn suất chiếu và ít nhất một ghế.');
	}

	if ('' === $customerName || ! filter_var($email, FILTER_VALIDATE_EMAIL) || '' === $phone) {
		throw new RuntimeException('Thông tin khách hàng chưa hợp lệ.');
	}

	if (! in_array($paymentMethod, array('counter', 'bank_transfer'), true)) {
		throw new RuntimeException('Phương thức thanh toán không hợp lệ.');
	}

	$showtime = cinema_fetch_showtime($showtimeId);

	if (! $showtime || 'open' !== $showtime['status']) {
		throw new RuntimeException('Suất chiếu này hiện không mở bán.');
	}

	if (strtotime((string) $showtime['start_time']) <= time()) {
		throw new RuntimeException('Suất chiếu này đã qua giờ đặt vé.');
	}

	$pdo->beginTransaction();

	try {
		list($seatPlaceholders, $seatParams) = cinema_build_placeholders($selectedSeatIds, 'seat_');
		$seatSql = "
			SELECT id, row_label, seat_number, seat_type
			FROM seats
			WHERE room_id = :room_id
				AND is_active = 1
				AND id IN (" . implode(', ', $seatPlaceholders) . ")
			FOR UPDATE
		";

		$seatStatement = $pdo->prepare($seatSql);
		$seatStatement->execute(array_merge(array('room_id' => $showtime['room_id']), $seatParams));
		$seatRows = $seatStatement->fetchAll();

		if (count($seatRows) !== count($selectedSeatIds)) {
			throw new RuntimeException('Một vài ghế không tồn tại trong phòng chiếu này.');
		}

		$bookedSql = "
			SELECT bs.seat_id
			FROM booking_seats bs
			INNER JOIN bookings b ON b.id = bs.booking_id
			WHERE b.showtime_id = :showtime_id
				AND b.booking_status = 'confirmed'
				AND bs.seat_id IN (" . implode(', ', $seatPlaceholders) . ")
			FOR UPDATE
		";

		$bookedStatement = $pdo->prepare($bookedSql);
		$bookedStatement->execute(array_merge(array('showtime_id' => $showtimeId), $seatParams));
		$bookedSeatIds = $bookedStatement->fetchAll(PDO::FETCH_COLUMN);

		if (! empty($bookedSeatIds)) {
			throw new RuntimeException('Một trong các ghế vừa chọn đã được đặt bởi khách khác.');
		}

		$priceMap    = array(
			'normal' => (float) $showtime['price_normal'],
			'vip'    => (float) $showtime['price_vip'],
			'couple' => (float) $showtime['price_couple'],
		);
		$totalAmount = 0.0;

		foreach ($seatRows as &$seatRow) {
			$seatType               = $seatRow['seat_type'];
			$unitPrice              = $priceMap[$seatType] ?? $priceMap['normal'];
			$seatRow['seat_label']  = $seatRow['row_label'] . $seatRow['seat_number'];
			$seatRow['unit_price']  = $unitPrice;
			$totalAmount           += $unitPrice;
		}
		unset($seatRow);

		$bookingCode      = cinema_generate_booking_code($pdo);
		$bookingStatement = $pdo->prepare(
			"
			INSERT INTO bookings (
				booking_code,
				showtime_id,
				customer_name,
				customer_email,
				customer_phone,
				total_amount,
				payment_method,
				payment_status,
				booking_status,
				created_at
			) VALUES (
				:booking_code,
				:showtime_id,
				:customer_name,
				:customer_email,
				:customer_phone,
				:total_amount,
				:payment_method,
				:payment_status,
				:booking_status,
				NOW()
			)
			"
		);
		$bookingStatement->execute(
			array(
				'booking_code'   => $bookingCode,
				'showtime_id'    => $showtimeId,
				'customer_name'  => $customerName,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'total_amount'   => $totalAmount,
				'payment_method' => $paymentMethod,
				'payment_status' => 'counter' === $paymentMethod ? 'unpaid' : 'pending',
				'booking_status' => 'confirmed',
			)
		);

		$bookingId  = (int) $pdo->lastInsertId();
		$seatInsert = $pdo->prepare(
			"
			INSERT INTO booking_seats (
				booking_id,
				seat_id,
				seat_label,
				seat_type,
				unit_price
			) VALUES (
				:booking_id,
				:seat_id,
				:seat_label,
				:seat_type,
				:unit_price
			)
			"
		);

		foreach ($seatRows as $seatRow) {
			$seatInsert->execute(
				array(
					'booking_id' => $bookingId,
					'seat_id'    => $seatRow['id'],
					'seat_label' => $seatRow['seat_label'],
					'seat_type'  => $seatRow['seat_type'],
					'unit_price' => $seatRow['unit_price'],
				)
			);
		}

		$pdo->commit();

		return cinema_fetch_booking_by_code($bookingCode) ?? array();
	} catch (Throwable $exception) {
		$pdo->rollBack();
		throw $exception;
	}
}

function cinema_fetch_booking_rows_by_email(string $email): array {
	if (cinema_use_wordpress_api()) {
		$bookings = cinema_wp_api_get('/bookings/lookup', array('email' => $email));

		foreach ($bookings as $booking) {
			if (is_array($booking)) {
				cinema_cache_booking_payload($booking);
			}
		}

		return array_map(
			static function (array $booking): array {
				return array(
					'booking_code' => (string) ($booking['booking_code'] ?? ''),
				);
			},
			(array) $bookings
		);
	}

	$pdo = cinema_db();

	$statement = $pdo->prepare(
		"
		SELECT
			b.*,
			m.title AS movie_title,
			m.poster_url,
			s.start_time,
			s.end_time,
			c.name AS cinema_name,
			r.name AS room_name
		FROM bookings b
		INNER JOIN showtimes s ON s.id = b.showtime_id
		INNER JOIN movies m ON m.id = s.movie_id
		INNER JOIN rooms r ON r.id = s.room_id
		INNER JOIN cinemas c ON c.id = r.cinema_id
		WHERE b.customer_email = :email
		ORDER BY b.created_at DESC
		"
	);
	$statement->execute(array('email' => $email));

	return $statement->fetchAll();
}

function cinema_fetch_booking_by_code(string $code): ?array {
	if (cinema_use_wordpress_api()) {
		$cached = cinema_get_cached_booking_payload($code);

		if (null !== $cached) {
			return $cached;
		}

		$rows = cinema_wp_api_get('/bookings/lookup', array('code' => $code));

		if (empty($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
			return null;
		}

		cinema_cache_booking_payload($rows[0]);

		return $rows[0];
	}

	$pdo = cinema_db();

	$statement = $pdo->prepare(
		"
		SELECT
			b.*,
			m.title AS movie_title,
			m.poster_url,
			m.genre,
			m.rating,
			s.start_time,
			s.end_time,
			c.name AS cinema_name,
			c.address AS cinema_address,
			c.city AS cinema_city,
			r.name AS room_name,
			r.screen_type
		FROM bookings b
		INNER JOIN showtimes s ON s.id = b.showtime_id
		INNER JOIN movies m ON m.id = s.movie_id
		INNER JOIN rooms r ON r.id = s.room_id
		INNER JOIN cinemas c ON c.id = r.cinema_id
		WHERE b.booking_code = :booking_code
		LIMIT 1
		"
	);
	$statement->execute(array('booking_code' => $code));
	$row = $statement->fetch();

	if (! $row) {
		return null;
	}

	$row['seats'] = cinema_fetch_booking_seats((int) $row['id']);

	return $row;
}

function cinema_fetch_booking_seats(int $bookingId): array {
	if (cinema_use_wordpress_api()) {
		return array();
	}

	$pdo = cinema_db();

	$statement = $pdo->prepare(
		"
		SELECT seat_label, seat_type, unit_price
		FROM booking_seats
		WHERE booking_id = :booking_id
		ORDER BY seat_label ASC
		"
	);
	$statement->execute(array('booking_id' => $bookingId));

	return $statement->fetchAll();
}

function cinema_wp_api_get(string $path, array $query = array()) {
	return cinema_wp_api_request('GET', $path, array(), $query);
}

function cinema_wp_api_post(string $path, array $payload = array()) {
	return cinema_wp_api_request('POST', $path, $payload);
}

function cinema_wp_api_request(string $method, string $path, array $payload = array(), array $query = array()) {
	$config = cinema_wordpress_api_config();

	if ('' === $config['base_url'] || '' === $config['api_key']) {
		throw new RuntimeException('Chưa cấu hình kết nối plugin WordPress trong file config.');
	}

	$url = $config['base_url'] . '/' . ltrim($path, '/');

	if (! empty($query)) {
		$url .= (false !== strpos($url, '?') ? '&' : '?') . http_build_query($query);
	}

	$headers = array(
		'Accept: application/json',
		'X-Cinema-Api-Key: ' . $config['api_key'],
	);

	if ('POST' === strtoupper($method)) {
		$headers[] = 'Content-Type: application/json';
	}

	if (function_exists('curl_init')) {
		$response = cinema_wp_api_request_with_curl($url, $method, $payload, $headers, $config['timeout']);
	} else {
		$response = cinema_wp_api_request_with_stream($url, $method, $payload, $headers, $config['timeout']);
	}

	$status = (int) ($response['status'] ?? 0);
	$body   = (string) ($response['body'] ?? '');
	$data   = json_decode($body, true);

	if ($status >= 400) {
		$message = is_array($data) && ! empty($data['message']) ? (string) $data['message'] : 'Không thể kết nối tới plugin WordPress.';
		throw new RuntimeException($message);
	}

	if (null === $data && '' !== trim($body)) {
		throw new RuntimeException('Plugin WordPress trả về dữ liệu không hợp lệ.');
	}

	return $data ?? array();
}

function cinema_wp_api_request_with_curl(string $url, string $method, array $payload, array $headers, int $timeout): array {
	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

	if ('POST' === strtoupper($method)) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	$body = curl_exec($ch);

	if (false === $body) {
		$error = curl_error($ch);
		curl_close($ch);
		throw new RuntimeException('Không thể gọi plugin WordPress: ' . $error);
	}

	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	return array(
		'status' => $status,
		'body'   => (string) $body,
	);
}

function cinema_wp_api_request_with_stream(string $url, string $method, array $payload, array $headers, int $timeout): array {
	$context = stream_context_create(
		array(
			'http' => array(
				'method'        => strtoupper($method),
				'header'        => implode("\r\n", $headers),
				'timeout'       => $timeout,
				'ignore_errors' => true,
				'content'       => 'POST' === strtoupper($method) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
			),
		)
	);

	$body = @file_get_contents($url, false, $context);

	if (false === $body) {
		throw new RuntimeException('Không thể gọi plugin WordPress.');
	}

	$status = 200;

	foreach ((array) ($http_response_header ?? array()) as $header) {
		if (preg_match('~HTTP/\S+\s+(\d{3})~', $header, $matches)) {
			$status = (int) $matches[1];
			break;
		}
	}

	return array(
		'status' => $status,
		'body'   => (string) $body,
	);
}

function cinema_cache_booking_payload(array $payload): void {
	$code = trim((string) ($payload['booking_code'] ?? ''));

	if ('' === $code) {
		return;
	}

	if (! isset($GLOBALS['cinema_booking_payload_cache']) || ! is_array($GLOBALS['cinema_booking_payload_cache'])) {
		$GLOBALS['cinema_booking_payload_cache'] = array();
	}

	$GLOBALS['cinema_booking_payload_cache'][$code] = $payload;
}

function cinema_get_cached_booking_payload(string $code): ?array {
	$cache = $GLOBALS['cinema_booking_payload_cache'] ?? array();

	if (! is_array($cache) || ! isset($cache[$code]) || ! is_array($cache[$code])) {
		return null;
	}

	return $cache[$code];
}
