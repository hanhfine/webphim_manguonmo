<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Seat_Manager {
	const LOCK_TIMEOUT = 600;

	public function sync_room_layout($room_id, $layout) {
		global $wpdb;

		$table         = $wpdb->prefix . 'cinema_seats';
		$total_rows    = max(1, absint($layout['total_rows'] ?? 0));
		$total_columns = max(1, absint($layout['total_columns'] ?? 0));
		$vip_rows      = $this->normalize_row_tokens($layout['vip_rows'] ?? '');
		$couple_rows   = $this->normalize_row_tokens($layout['couple_rows'] ?? '');
		$vip_seats     = $this->normalize_seat_tokens($layout['vip_seats'] ?? '');
		$couple_seats  = $this->normalize_seat_tokens($layout['couple_seats'] ?? '');
		$inactive_seats = $this->normalize_seat_tokens($layout['inactive_seats'] ?? '');
		$row_labels    = array();

		for ($index = 0; $index < $total_rows; $index++) {
			$row_labels[] = $this->index_to_row_label($index);
		}

		$existing_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, row_label, seat_number FROM {$table} WHERE room_id = %d",
				$room_id
			),
			ARRAY_A
		);

		$existing_map = array();

		foreach ($existing_rows as $existing_row) {
			$key                = $existing_row['row_label'] . ':' . $existing_row['seat_number'];
			$existing_map[$key] = absint($existing_row['id']);
		}

		$active_keys = array();

		foreach ($row_labels as $row_label) {
			$row_number = array_search($row_label, $row_labels, true);
			$row_number = false === $row_number ? 0 : ((int) $row_number + 1);

			for ($column = 1; $column <= $total_columns; $column++) {
				$key       = $row_label . ':' . $column;
				$seat_code = $row_label . $column;
				$seat_type = 'normal';
				$is_active = ! in_array($seat_code, $inactive_seats, true);

				if (in_array($seat_code, $couple_seats, true) || $this->row_matches_tokens($row_label, $row_number, $couple_rows)) {
					$seat_type = 'couple';
				} elseif (in_array($seat_code, $vip_seats, true) || $this->row_matches_tokens($row_label, $row_number, $vip_rows)) {
					$seat_type = 'vip';
				}

				$active_keys[] = $key;

				if (isset($existing_map[$key])) {
					$wpdb->update(
						$table,
						array(
							'seat_type' => $seat_type,
							'is_active' => $is_active ? 1 : 0,
						),
						array(
							'id' => $existing_map[$key],
						),
						array('%s', '%d'),
						array('%d')
					);
					continue;
				}

				$wpdb->insert(
					$table,
					array(
						'room_id'      => $room_id,
						'row_label'    => $row_label,
						'seat_number'  => $column,
						'seat_type'    => $seat_type,
						'is_active'    => $is_active ? 1 : 0,
					),
					array('%d', '%s', '%d', '%s', '%d')
				);
			}
		}

		foreach ($existing_map as $key => $seat_id) {
			if (in_array($key, $active_keys, true)) {
				continue;
			}

			$wpdb->update(
				$table,
				array('is_active' => 0),
				array('id' => $seat_id),
				array('%d'),
				array('%d')
			);
		}
	}

	public function get_room_seats($room_id) {
		global $wpdb;

		$table = $wpdb->prefix . 'cinema_seats';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, row_label, seat_number, seat_type
				FROM {$table}
				WHERE room_id = %d AND is_active = 1
				ORDER BY LENGTH(row_label) ASC, row_label ASC, seat_number ASC",
				$room_id
			),
			ARRAY_A
		);
	}

	public function get_showtime_seat_map($showtime_id, $user_id = 0) {
		global $wpdb;

		$this->cleanup_expired_locks();

		$room_id = absint(get_post_meta($showtime_id, '_cinema_showtime_room_id', true));

		if (! $room_id) {
			return array();
		}

		$seats                = $this->get_room_seats($room_id);
		$seat_ids             = wp_list_pluck($seats, 'id');
		$bookings_by_seat_ids = array();

		if (! empty($seat_ids)) {
			$placeholders = implode(',', array_fill(0, count($seat_ids), '%d'));
			$table        = $wpdb->prefix . 'cinema_seat_bookings';
			$query_args   = array_merge(
				array($showtime_id),
				$seat_ids
			);

			$booking_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT seat_id, user_id, status, locked_at, booking_code
					FROM {$table}
					WHERE showtime_id = %d
						AND seat_id IN ({$placeholders})
						AND (
							status = 'confirmed'
							OR (status = 'pending' AND locked_at >= %s)
						)",
					array_merge(
						$query_args,
						array(gmdate('Y-m-d H:i:s', time() - self::LOCK_TIMEOUT))
					)
				),
				ARRAY_A
			);

			foreach ($booking_rows as $booking_row) {
				$bookings_by_seat_ids[absint($booking_row['seat_id'])] = $booking_row;
			}
		}

		$grouped_rows = array();

		foreach ($seats as $seat) {
			$seat_id      = absint($seat['id']);
			$status       = 'available';
			$booking_code = '';

			if (isset($bookings_by_seat_ids[$seat_id])) {
				$active_booking = $bookings_by_seat_ids[$seat_id];

				if ('confirmed' === $active_booking['status']) {
					$status = 'booked';
				} elseif ($user_id && absint($active_booking['user_id']) === $user_id) {
					$status       = 'selected';
					$booking_code = $active_booking['booking_code'];
				} else {
					$status = 'locked';
				}
			}

			$row_label = $seat['row_label'];

			if (! isset($grouped_rows[$row_label])) {
				$grouped_rows[$row_label] = array();
			}

			$grouped_rows[$row_label][] = array(
				'id'           => $seat_id,
				'label'        => $row_label . $seat['seat_number'],
				'row_label'    => $row_label,
				'seat_number'  => absint($seat['seat_number']),
				'seat_type'    => $seat['seat_type'],
				'status'       => $status,
				'booking_code' => $booking_code,
			);
		}

		return $grouped_rows;
	}

	public function lock_seats($showtime_id, $seat_ids, $user_id) {
		global $wpdb;

		$this->cleanup_expired_locks();

		$seat_ids = array_values(array_unique(array_filter(array_map('absint', (array) $seat_ids))));

		if (empty($seat_ids)) {
			return new WP_Error('invalid_seats', __('Please choose at least one seat.', 'cinema-booking'), array('status' => 400));
		}

		$valid_seats = $this->get_valid_seat_ids_for_showtime($showtime_id, $seat_ids);

		if (count($valid_seats) !== count($seat_ids)) {
			return new WP_Error('invalid_seat_map', __('One or more seats are not valid for this showtime.', 'cinema-booking'), array('status' => 400));
		}

		$table        = $wpdb->prefix . 'cinema_seat_bookings';
		$placeholders = implode(',', array_fill(0, count($seat_ids), '%d'));
		$query_args   = array_merge(array($showtime_id), $seat_ids);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, seat_id, user_id, status, locked_at
				FROM {$table}
				WHERE showtime_id = %d AND seat_id IN ({$placeholders})",
				$query_args
			),
			ARRAY_A
		);

		$rows_by_seat = array();

		foreach ($rows as $row) {
			$rows_by_seat[absint($row['seat_id'])] = $row;
		}

		$lock_time = current_time('mysql', true);

		foreach ($seat_ids as $seat_id) {
			if (! isset($rows_by_seat[$seat_id])) {
				$wpdb->insert(
					$table,
					array(
						'showtime_id' => $showtime_id,
						'seat_id'     => $seat_id,
						'user_id'     => $user_id,
						'status'      => 'pending',
						'locked_at'   => $lock_time,
					),
					array('%d', '%d', '%d', '%s', '%s')
				);
				continue;
			}

			$current_row = $rows_by_seat[$seat_id];
			$is_expired  = ! empty($current_row['locked_at']) && strtotime($current_row['locked_at'] . ' UTC') < (time() - self::LOCK_TIMEOUT);

			if ('confirmed' === $current_row['status']) {
				return new WP_Error('seat_unavailable', __('One or more seats were already booked.', 'cinema-booking'), array('status' => 409));
			}

			if ('pending' === $current_row['status'] && absint($current_row['user_id']) !== $user_id && ! $is_expired) {
				return new WP_Error('seat_locked', __('One or more seats are currently locked by another customer.', 'cinema-booking'), array('status' => 409));
			}

			$wpdb->update(
				$table,
				array(
					'user_id'      => $user_id,
					'status'       => 'pending',
					'locked_at'    => $lock_time,
					'confirmed_at' => null,
				),
				array(
					'id' => absint($current_row['id']),
				),
				array('%d', '%s', '%s', '%s'),
				array('%d')
			);
		}

		return array(
			'expires_in' => self::LOCK_TIMEOUT,
			'expires_at' => gmdate('c', time() + self::LOCK_TIMEOUT),
			'seat_map'   => $this->get_showtime_seat_map($showtime_id, $user_id),
		);
	}

	public function confirm_seats($showtime_id, $seat_ids, $user_id, $booking_code) {
		global $wpdb;

		$seat_ids = array_values(array_unique(array_filter(array_map('absint', (array) $seat_ids))));

		if (empty($seat_ids)) {
			return new WP_Error('invalid_seats', __('Please choose at least one seat.', 'cinema-booking'));
		}

		$table        = $wpdb->prefix . 'cinema_seat_bookings';
		$placeholders = implode(',', array_fill(0, count($seat_ids), '%d'));
		$query_args   = array_merge(array($showtime_id, $user_id), $seat_ids);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, seat_id, status, locked_at
				FROM {$table}
				WHERE showtime_id = %d
					AND user_id = %d
					AND seat_id IN ({$placeholders})",
				$query_args
			),
			ARRAY_A
		);

		if (count($rows) !== count($seat_ids)) {
			return new WP_Error('lock_missing', __('Seat lock has expired. Please select seats again.', 'cinema-booking'));
		}

		foreach ($rows as $row) {
			if ('confirmed' === $row['status']) {
				continue;
			}

			if (empty($row['locked_at']) || strtotime($row['locked_at'] . ' UTC') < (time() - self::LOCK_TIMEOUT)) {
				return new WP_Error('lock_expired', __('Seat lock has expired. Please select seats again.', 'cinema-booking'));
			}
		}

		foreach ($rows as $row) {
			$wpdb->update(
				$table,
				array(
					'status'       => 'confirmed',
					'booking_code' => $booking_code,
					'confirmed_at' => current_time('mysql', true),
				),
				array(
					'id' => absint($row['id']),
				),
				array('%s', '%s', '%s'),
				array('%d')
			);
		}

		return true;
	}

	public function cancel_seat_locks($showtime_id, $seat_ids, $user_id) {
		global $wpdb;

		$seat_ids = array_values(array_unique(array_filter(array_map('absint', (array) $seat_ids))));

		if (empty($seat_ids)) {
			return;
		}

		$table        = $wpdb->prefix . 'cinema_seat_bookings';
		$placeholders = implode(',', array_fill(0, count($seat_ids), '%d'));

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = 'cancelled'
				WHERE showtime_id = %d
					AND user_id = %d
					AND seat_id IN ({$placeholders})
					AND status = 'pending'",
				array_merge(array($showtime_id, $user_id), $seat_ids)
			)
		);
	}

	public function cleanup_expired_locks() {
		global $wpdb;

		$table = $wpdb->prefix . 'cinema_seat_bookings';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = 'cancelled'
				WHERE status = 'pending' AND locked_at < %s",
				gmdate('Y-m-d H:i:s', time() - self::LOCK_TIMEOUT)
			)
		);
	}

	public function get_seat_labels($seat_ids) {
		global $wpdb;

		$seat_ids = array_values(array_unique(array_filter(array_map('absint', (array) $seat_ids))));

		if (empty($seat_ids)) {
			return array();
		}

		$table        = $wpdb->prefix . 'cinema_seats';
		$placeholders = implode(',', array_fill(0, count($seat_ids), '%d'));

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, row_label, seat_number, seat_type
				FROM {$table}
				WHERE id IN ({$placeholders})",
				$seat_ids
			),
			ARRAY_A
		);

		$result = array();

		foreach ($rows as $row) {
			$result[absint($row['id'])] = array(
				'label'     => $row['row_label'] . $row['seat_number'],
				'seat_type' => $row['seat_type'],
			);
		}

		return $result;
	}

	public function get_seat_type_map($seat_ids) {
		return wp_list_pluck($this->get_seat_labels($seat_ids), 'seat_type');
	}

	private function get_valid_seat_ids_for_showtime($showtime_id, $seat_ids) {
		global $wpdb;

		$room_id = absint(get_post_meta($showtime_id, '_cinema_showtime_room_id', true));

		if (! $room_id || empty($seat_ids)) {
			return array();
		}

		$table        = $wpdb->prefix . 'cinema_seats';
		$placeholders = implode(',', array_fill(0, count($seat_ids), '%d'));

		return array_map(
			'absint',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT id
					FROM {$table}
					WHERE room_id = %d
						AND is_active = 1
						AND id IN ({$placeholders})",
					array_merge(array($room_id), $seat_ids)
				)
			)
		);
	}

	private function normalize_row_tokens($value) {
		$value = is_array($value) ? implode(',', $value) : (string) $value;
		$rows  = preg_split('/[\s,]+/', strtoupper($value));

		return array_values(array_filter(array_map('trim', (array) $rows)));
	}

	private function row_matches_tokens($row_label, $row_number, $tokens) {
		return in_array($row_label, $tokens, true) || in_array((string) $row_number, $tokens, true);
	}

	private function normalize_seat_tokens($value) {
		$value = is_array($value) ? implode(',', $value) : (string) $value;
		$rows  = preg_split('/[\s,]+/', strtoupper($value));

		return array_values(array_filter(array_map('trim', (array) $rows)));
	}

	private function index_to_row_label($index) {
		$index = absint($index);
		$label = '';

		do {
			$label = chr(65 + ($index % 26)) . $label;
			$index = (int) floor($index / 26) - 1;
		} while ($index >= 0);

		return strtoupper($label);
	}
}
