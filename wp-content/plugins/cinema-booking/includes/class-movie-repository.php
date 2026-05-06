<?php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * CRUD repository for cinema_movies table.
 */
class Cinema_Movie_Repository {

	/** @return string */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'cinema_movies';
	}

	/**
	 * List movies with optional filters.
	 *
	 * @param array $args {
	 *   @type string $search        Search in title/description.
	 *   @type string $status        Filter by status.
	 *   @type string $genre         Filter by genre.
	 *   @type int    $limit         Max rows.
	 *   @type string $order_by      Column to order by.
	 *   @type string $order         ASC | DESC.
	 * }
	 * @return array<int,array>
	 */
	public function find_all( array $args = [] ): array {
		global $wpdb;
		$table = $this->table();

		$where  = array('1=1');
		$params = array();

		$search = sanitize_text_field( $args['search'] ?? '' );
		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(title LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$status = sanitize_key( $args['status'] ?? '' );
		if ( $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$genre = sanitize_text_field( $args['genre'] ?? '' );
		if ( $genre ) {
			$where[]  = 'genre = %s';
			$params[] = $genre;
		}

		$limit_val = absint( $args['limit'] ?? 50 );
		$order_col = in_array( $args['order_by'] ?? '', ['id','title','status','release_date','created_at'], true )
			? $args['order_by']
			: 'created_at';
		$order_dir = 'ASC' === strtoupper( $args['order'] ?? 'DESC' ) ? 'ASC' : 'DESC';

		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where );
		$sql .= " ORDER BY {$order_col} {$order_dir}";
		$sql .= " LIMIT %d";
		$params[] = $limit_val;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];
	}

	/**
	 * Find a single movie by ID.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Find a single movie by slug.
	 *
	 * @param string $slug
	 * @return array|null
	 */
	public function find_by_slug( string $slug ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE slug = %s", $slug ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Insert a new movie.
	 *
	 * @param array $data
	 * @return int  New movie ID, or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert( $this->table(), $this->sanitize( $data ), $this->formats( $data ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing movie.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$result = $wpdb->update(
			$this->table(),
			$this->sanitize( $data ),
			array( 'id' => $id ),
			$this->formats( $data ),
			array( '%d' )
		);
		return false !== $result;
	}

	/**
	 * Delete a movie by ID.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Total count (with optional status filter).
	 */
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
	 * Get distinct genres.
	 *
	 * @return string[]
	 */
	public function get_genres(): array {
		global $wpdb;
		$rows = $wpdb->get_col(
			"SELECT DISTINCT genre FROM {$this->table()} WHERE genre != '' ORDER BY genre ASC"
		);
		return $rows ?: [];
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function sanitize( array $data ): array {
		$clean = array();
		$map   = $this->field_map();
		foreach ( $map as $field => $_ ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}
			$clean[$field] = $data[$field];
		}
		return $clean;
	}

	private function formats( array $data ): array {
		$map  = $this->field_map();
		$fmts = array();
		foreach ( $data as $field => $_ ) {
			$fmts[] = $map[$field] ?? '%s';
		}
		return $fmts;
	}

	private function field_map(): array {
		return array(
			'title'            => '%s',
			'slug'             => '%s',
			'description'      => '%s',
			'genre'            => '%s',
			'duration_minutes' => '%d',
			'poster_url'       => '%s',
			'trailer_url'      => '%s',
			'director'         => '%s',
			'cast_list'        => '%s',
			'rating'           => '%s',
			'review_score'     => '%f',
			'release_date'     => '%s',
			'end_date'         => '%s',
			'status'           => '%s',
		);
	}
}
