<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Payment_Handler {
	/**
	 * @var Cinema_Booking_Booking_Manager
	 */
	private $booking_manager;

	/**
	 * @var Cinema_Booking_Ticket_Generator
	 */
	private $ticket_generator;

	public function __construct($booking_manager, $ticket_generator) {
		$this->booking_manager = $booking_manager;
		$this->ticket_generator = $ticket_generator;
	}

	public function get_supported_methods() {
		return array(
			'cash'          => __('Pay at counter', 'cinema-booking'),
			'bank_transfer' => __('Bank transfer', 'cinema-booking'),
			'vnpay'         => __('VNPay', 'cinema-booking'),
			'momo'          => __('MoMo', 'cinema-booking'),
		);
	}

	public function create_payment($booking_id, $user_id, $amount, $method) {
		global $wpdb;

		$method = sanitize_key($method);

		if (! array_key_exists($method, $this->get_supported_methods())) {
			return new WP_Error('unsupported_payment_method', __('Unsupported payment method.', 'cinema-booking'), array('status' => 400));
		}

		$table = $wpdb->prefix . 'cinema_payments';

		$inserted = $wpdb->insert(
			$table,
			array(
				'booking_ids'     => wp_json_encode(array(absint($booking_id))),
				'user_id'         => absint($user_id),
				'total_amount'    => (float) $amount,
				'payment_method'  => $method,
				'payment_status'  => 'pending',
				'transaction_id'  => '',
			),
			array('%s', '%d', '%f', '%s', '%s', '%s')
		);

		if (! $inserted) {
			return new WP_Error('payment_create_failed', __('Could not create payment record.', 'cinema-booking'), array('status' => 500));
		}

		$payment_id = (int) $wpdb->insert_id;

		$this->booking_manager->update_booking_payment_status($booking_id, 'pending');

		if ('cash' === $method || 'bank_transfer' === $method) {
			$this->maybe_dispatch_ticket($booking_id);
		}

		return array(
			'payment_id'    => $payment_id,
			'payment_method' => $method,
			'payment_status' => 'pending',
			'checkout_url'   => $this->get_checkout_url($payment_id, $method),
			'message'        => $this->get_payment_message($method),
		);
	}

	public function handle_callback($payload) {
		global $wpdb;

		$payment_id      = absint($payload['payment_id'] ?? 0);
		$status          = sanitize_key($payload['status'] ?? '');
		$transaction_id  = sanitize_text_field($payload['transaction_id'] ?? '');
		$allowed_statuses = array('success', 'failed', 'refunded');
		$is_valid        = (bool) apply_filters('cinema_booking_validate_payment_callback', false, $payload);

		if (! $is_valid) {
			return new WP_Error('invalid_payment_signature', __('Payment callback signature is invalid.', 'cinema-booking'), array('status' => 403));
		}

		if (! $payment_id || ! in_array($status, $allowed_statuses, true)) {
			return new WP_Error('invalid_payment_callback', __('Invalid payment callback payload.', 'cinema-booking'), array('status' => 400));
		}

		$table = $wpdb->prefix . 'cinema_payments';

		$updated = $wpdb->update(
			$table,
			array(
				'payment_status' => $status,
				'transaction_id' => $transaction_id,
			),
			array('id' => $payment_id),
			array('%s', '%s'),
			array('%d')
		);

		if (false === $updated) {
			return new WP_Error('payment_update_failed', __('Could not update payment record.', 'cinema-booking'), array('status' => 500));
		}

		$booking_ids = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT booking_ids FROM {$table} WHERE id = %d",
				$payment_id
			)
		);

		$booking_ids = json_decode((string) $booking_ids, true);

		foreach ((array) $booking_ids as $booking_id) {
			$this->booking_manager->update_booking_payment_status(
				absint($booking_id),
				'success' === $status ? 'paid' : $status
			);

			if ('success' === $status) {
				$this->maybe_dispatch_ticket(absint($booking_id));
			}
		}

		return array(
			'payment_id'     => $payment_id,
			'payment_status' => $status,
			'transaction_id' => $transaction_id,
		);
	}

	private function get_checkout_url($payment_id, $method) {
		if ('cash' === $method) {
			return '';
		}

		return (string) apply_filters(
			'cinema_booking_payment_checkout_url',
			add_query_arg(
				array(
					'payment_id' => absint($payment_id),
					'gateway'    => sanitize_key($method),
				),
				home_url('/checkout')
			),
			$payment_id,
			$method
		);
	}

	private function get_payment_message($method) {
		if ('cash' === $method) {
			return __('Booking confirmed. Customer will pay at the cinema counter.', 'cinema-booking');
		}

		if ('bank_transfer' === $method) {
			return __('Booking confirmed. Customer can complete payment by bank transfer and present the receipt later.', 'cinema-booking');
		}

		return __('Payment has been created and is waiting for gateway confirmation.', 'cinema-booking');
	}

	private function maybe_dispatch_ticket($booking_id) {
		if (! $booking_id) {
			return;
		}

		$this->ticket_generator->email_ticket($booking_id);
	}
}
