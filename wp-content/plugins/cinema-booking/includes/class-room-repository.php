<?php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * CRUD repository for cinema_rooms table.
 */
class Cinema_Room_Repository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cinema_rooms';
	}

	public function find_all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->table()} ORDER BY name ASC", ARRAY_A ) ?: [];
	}

	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ),
			ARRAY_A
		);
		return $row ?: null;
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
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
		return false !== $result;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	public function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
	}

	private function sanitize( array $data ): array {
		$allowed = [ 'name', 'type', 'total_rows', 'total_columns', 'vip_rows', 'couple_rows', 'vip_seats', 'couple_seats', 'inactive_seats' ];
		return array_intersect_key( $data, array_flip( $allowed ) );
	}
}
