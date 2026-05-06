<?php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * CRUD repository for cinema_showtimes table.
 */
class Cinema_Showtime_Repository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cinema_showtimes';
	}

	/**
	 * Get all open future showtimes for a given movie, grouped by date.
	 *
	 * @param int $movie_id
	 * @return array  Grouped by 'd/m/Y' date key.
	 */
	public function find_open_by_movie( int $movie_id ): array {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, r.name AS room_name, r.type AS room_type
				FROM {$this->table()} s
				LEFT JOIN {$wpdb->prefix}cinema_rooms r ON r.id = s.room_id
				WHERE s.movie_id = %d
				  AND s.status = 'open'
				  AND s.start_datetime > %s
				ORDER BY s.start_datetime ASC",
				$movie_id,
				$now
			),
			ARRAY_A
		) ?: [];

		$grouped = [];
		foreach ( $rows as $row ) {
			$date_key             = wp_date( 'd/m/Y', strtotime( $row['start_datetime'] ) );
			$grouped[$date_key][] = $row;
		}

		return $grouped;
	}

	/**
	 * Count open future showtimes for a movie.
	 */
	public function count_open_by_movie( int $movie_id ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table()} WHERE movie_id = %d AND status = 'open' AND start_datetime > %s",
				$movie_id,
				$now
			)
		);
	}

	/**
	 * Get all showtimes for a given date (for timeline).
	 *
	 * @param string $date  'Y-m-d'
	 * @return array[]  Grouped by room_id.
	 */
	public function find_by_date( string $date ): array {
		global $wpdb;

		$start = $date . ' 00:00:00';
		$end   = $date . ' 23:59:59';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, m.title AS movie_title, r.name AS room_name
				FROM {$this->table()} s
				LEFT JOIN {$wpdb->prefix}cinema_movies m ON m.id = s.movie_id
				LEFT JOIN {$wpdb->prefix}cinema_rooms  r ON r.id = s.room_id
				WHERE s.start_datetime BETWEEN %s AND %s
				ORDER BY s.room_id ASC, s.start_datetime ASC",
				$start,
				$end
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get all showtimes with optional filters (for admin list).
	 */
	public function find_all( array $args = [] ): array {
		global $wpdb;

		$where  = ['1=1'];
		$params = [];

		$movie_id = absint( $args['movie_id'] ?? 0 );
		if ( $movie_id ) {
			$where[]  = 's.movie_id = %d';
			$params[] = $movie_id;
		}

		$status = sanitize_key( $args['status'] ?? '' );
		if ( $status ) {
			$where[]  = 's.status = %s';
			$params[] = $status;
		}

		$limit    = absint( $args['limit'] ?? 50 );
		$params[] = $limit;

		$sql = "SELECT s.*, m.title AS movie_title, r.name AS room_name
			FROM {$this->table()} s
			LEFT JOIN {$wpdb->prefix}cinema_movies m ON m.id = s.movie_id
			LEFT JOIN {$wpdb->prefix}cinema_rooms  r ON r.id = s.room_id
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY s.start_datetime DESC
			LIMIT %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];
	}

	/**
	 * Find a single showtime by ID.
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, m.title AS movie_title, m.duration_minutes, m.poster_url, m.rating, m.genre,
				        r.name AS room_name, r.type AS room_type
				FROM {$this->table()} s
				LEFT JOIN {$wpdb->prefix}cinema_movies m ON m.id = s.movie_id
				LEFT JOIN {$wpdb->prefix}cinema_rooms  r ON r.id = s.room_id
				WHERE s.id = %d",
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Get room_id for a showtime (used by seat manager).
	 */
	public function get_room_id( int $showtime_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT room_id FROM {$this->table()} WHERE id = %d", $showtime_id )
		);
	}

	/**
	 * Get price set for a showtime.
	 *
	 * @return array{normal:float,vip:float,couple:float}
	 */
	public function get_prices( int $showtime_id ): array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT price_normal, price_vip, price_couple FROM {$this->table()} WHERE id = %d",
				$showtime_id
			),
			ARRAY_A
		);
		return $row
			? [ 'normal' => (float) $row['price_normal'], 'vip' => (float) $row['price_vip'], 'couple' => (float) $row['price_couple'] ]
			: [ 'normal' => 0.0, 'vip' => 0.0, 'couple' => 0.0 ];
	}

	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert( $this->table(), $this->sanitize( $data ) );
		return (int) $wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;
		$result = $wpdb->update(
			$this->table(),
			$this->sanitize( $data ),
			[ 'id' => $id ],
			null,
			[ '%d' ]
		);
		return false !== $result;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
	}

	public function count( string $status = '' ): int {
		global $wpdb;
		if ( $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE status = %s", $status )
			);
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
	}

	/**
	 * Transition status (e.g., cron jobs).
	 */
	public function update_status( int $id, string $status ): bool {
		return $this->update( $id, [ 'status' => $status ] );
	}

	private function sanitize( array $data ): array {
		$allowed = [ 'movie_id', 'room_id', 'start_datetime', 'end_datetime', 'status', 'price_normal', 'price_vip', 'price_couple' ];
		return array_intersect_key( $data, array_flip( $allowed ) );
	}
}
