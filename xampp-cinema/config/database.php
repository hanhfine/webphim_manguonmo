<?php

return array(
	'app_name'    => 'MYCINEMA',
	'app_url'     => null,
	'data_source' => 'wordpress_api',
	'wordpress_api' => array(
		'base_url' => 'http://localhost:8080/index.php?rest_route=/cinema/v1/integration',
		'api_key'  => 'psjAaqCbkW8cGS6mHMmXKFBIUPHHYwwMlGQPhxOC',
		'timeout'  => 12,
	),
	'db' => array(
		'host'     => '127.0.0.1',
		'port'     => 3306,
		'database' => 'cinema_booking_xampp',
		'username' => 'root',
		'password' => '',
		'charset'  => 'utf8mb4',
	),
);
