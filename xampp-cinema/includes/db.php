<?php

declare(strict_types=1);

function cinema_config(): array {
	static $config = null;

	if (null === $config) {
		$config = require __DIR__ . '/../config/database.php';
	}

	return $config;
}

function cinema_db(): PDO {
	static $pdo = null;

	if ($pdo instanceof PDO) {
		return $pdo;
	}

	$db  = cinema_config()['db'];
	$dsn = sprintf(
		'mysql:host=%s;port=%d;dbname=%s;charset=%s',
		$db['host'],
		(int) $db['port'],
		$db['database'],
		$db['charset']
	);

	$pdo = new PDO(
		$dsn,
		$db['username'],
		$db['password'],
		array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		)
	);

	return $pdo;
}
