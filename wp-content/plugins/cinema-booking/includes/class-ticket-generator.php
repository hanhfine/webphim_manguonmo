<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Ticket_Generator {
	/**
	 * @var Cinema_Booking_Booking_Manager
	 */
	private $booking_manager;

	public function __construct($booking_manager) {
		$this->booking_manager = $booking_manager;
	}

	public function generate_pdf($booking_id) {
		if (class_exists('TCPDF')) {
			return $this->generate_tcpdf_ticket($booking_id);
		}

		if (class_exists('FPDF')) {
			return $this->generate_fpdf_ticket($booking_id);
		}

		return new WP_Error(
			'missing_pdf_library',
			__('Install TCPDF or FPDF to export PDF tickets.', 'cinema-booking'),
			array('status' => 501)
		);
	}

	public function get_pdf_download($booking_id, $generate = false) {
		$payload = $this->booking_manager->get_booking_payload($booking_id);

		if (empty($payload)) {
			return array(
				'available' => false,
			);
		}

		$file_name  = 'ticket-' . $payload['booking_code'] . '.pdf';
		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit($upload_dir['basedir']) . $file_name;
		$file_url   = trailingslashit($upload_dir['baseurl']) . $file_name;

		if (file_exists($file_path)) {
			return array(
				'available' => true,
				'path'      => $file_path,
				'url'       => $file_url,
			);
		}

		if ($generate) {
			$generated = $this->generate_pdf($booking_id);

			if (! is_wp_error($generated) && ! empty($generated['path'])) {
				return array(
					'available' => true,
					'path'      => $generated['path'],
					'url'       => $generated['url'],
				);
			}
		}

		return array(
			'available' => false,
			'path'      => $file_path,
			'url'       => $file_url,
		);
	}

	public function get_ticket_delivery_meta($booking_id) {
		return array(
			'emailed_at' => get_post_meta($booking_id, '_cinema_ticket_emailed_at', true),
		);
	}

	public function email_ticket($booking_id, $email = '', $force = false) {
		$payload = $this->booking_manager->get_booking_payload($booking_id);

		if (empty($payload)) {
			return new WP_Error('invalid_booking', __('Booking not found.', 'cinema-booking'));
		}

		$emailed_at = get_post_meta($booking_id, '_cinema_ticket_emailed_at', true);

		if ($emailed_at && ! $force) {
			return true;
		}

		if (! $email) {
			$email = (string) get_post_meta($booking_id, '_cinema_customer_email', true);
		}

		if (! $email) {
			$user  = get_user_by('id', get_post_field('post_author', $booking_id));
			$email = $user ? $user->user_email : '';
		}

		if (! is_email($email)) {
			return new WP_Error('invalid_email', __('No valid email found for this booking.', 'cinema-booking'));
		}

		$subject = sprintf(__('Your movie ticket %s', 'cinema-booking'), $payload['booking_code']);
		$body    = $this->generate_ticket_html($booking_id);
		$pdf     = $this->generate_pdf($booking_id);
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$args    = array();

		if (! is_wp_error($pdf) && ! empty($pdf['path'])) {
			$args[] = $pdf['path'];
		}

		$sent = wp_mail($email, $subject, $body, $headers, $args);

		if ($sent) {
			update_post_meta($booking_id, '_cinema_ticket_emailed_at', current_time('mysql'));
		}

		return $sent;
	}

	public function generate_ticket_html($booking_id) {
		$payload = $this->booking_manager->get_booking_payload($booking_id);

		if (empty($payload)) {
			return '';
		}

		$seats      = implode(', ', wp_list_pluck($payload['seats'], 'label'));
		$code_block = $this->get_ticket_code_markup($payload['booking_code']);

		ob_start();
		?>
		<div style="font-family: Arial, sans-serif; border: 1px solid #d4af37; border-radius: 18px; max-width: 680px; overflow: hidden;">
			<div style="background: #111827; color: #f9d776; padding: 24px;">
				<h2 style="margin: 0 0 8px;">Cinema Ticket</h2>
				<p style="margin: 0;">Booking code: <?php echo esc_html($payload['booking_code']); ?></p>
			</div>
			<div style="padding: 24px;">
				<p><strong><?php esc_html_e('Movie', 'cinema-booking'); ?>:</strong> <?php echo esc_html($payload['movie']['title']); ?></p>
				<p><strong><?php esc_html_e('Cinema', 'cinema-booking'); ?>:</strong> <?php echo esc_html($payload['cinema']['title']); ?></p>
				<p><strong><?php esc_html_e('Room', 'cinema-booking'); ?>:</strong> <?php echo esc_html($payload['room']['title']); ?></p>
				<p><strong><?php esc_html_e('Showtime', 'cinema-booking'); ?>:</strong> <?php echo esc_html($payload['showtime']['start_datetime']); ?></p>
				<p><strong><?php esc_html_e('Seats', 'cinema-booking'); ?>:</strong> <?php echo esc_html($seats); ?></p>
				<p><strong><?php esc_html_e('Total', 'cinema-booking'); ?>:</strong> <?php echo esc_html(number_format_i18n($payload['total_amount'], 0)); ?></p>
				<div style="margin-top: 20px; display: flex; align-items: center; gap: 18px; flex-wrap: wrap;">
					<div><?php echo $code_block['markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div>
						<p style="margin: 0 0 8px;"><strong><?php echo esc_html($code_block['label']); ?></strong></p>
						<p style="margin: 0;"><?php echo esc_html($payload['booking_code']); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private function generate_tcpdf_ticket($booking_id) {
		$payload = $this->booking_manager->get_booking_payload($booking_id);

		if (empty($payload)) {
			return new WP_Error('invalid_booking', __('Booking not found.', 'cinema-booking'));
		}

		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit($upload_dir['basedir']) . 'ticket-' . $payload['booking_code'] . '.pdf';
		$seats      = implode(', ', wp_list_pluck($payload['seats'], 'label'));

		$pdf = new TCPDF();
		$pdf->SetCreator('Cinema Booking');
		$pdf->SetAuthor(get_bloginfo('name'));
		$pdf->SetTitle('Ticket ' . $payload['booking_code']);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 12);
		$pdf->Write(0, 'Movie: ' . $payload['movie']['title']);
		$pdf->Ln(8);
		$pdf->Write(0, 'Cinema: ' . $payload['cinema']['title']);
		$pdf->Ln(8);
		$pdf->Write(0, 'Room: ' . $payload['room']['title']);
		$pdf->Ln(8);
		$pdf->Write(0, 'Showtime: ' . $payload['showtime']['start_datetime']);
		$pdf->Ln(8);
		$pdf->Write(0, 'Seats: ' . $seats);
		$pdf->Ln(8);
		$pdf->Write(0, 'Booking code: ' . $payload['booking_code']);
		$pdf->Ln(8);
		$pdf->Write(0, 'Payment status: ' . strtoupper((string) ($payload['payment_status'] ?: 'pending')));
		$pdf->Output($file_path, 'F');

		return array(
			'path' => $file_path,
			'url'  => trailingslashit($upload_dir['baseurl']) . 'ticket-' . $payload['booking_code'] . '.pdf',
		);
	}

	private function generate_fpdf_ticket($booking_id) {
		$payload = $this->booking_manager->get_booking_payload($booking_id);

		if (empty($payload)) {
			return new WP_Error('invalid_booking', __('Booking not found.', 'cinema-booking'));
		}

		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit($upload_dir['basedir']) . 'ticket-' . $payload['booking_code'] . '.pdf';
		$seats      = implode(', ', wp_list_pluck($payload['seats'], 'label'));

		$pdf = new FPDF();
		$pdf->AddPage();
		$pdf->SetFont('Arial', '', 12);
		$pdf->Cell(0, 10, 'Movie: ' . $payload['movie']['title'], 0, 1);
		$pdf->Cell(0, 10, 'Cinema: ' . $payload['cinema']['title'], 0, 1);
		$pdf->Cell(0, 10, 'Room: ' . $payload['room']['title'], 0, 1);
		$pdf->Cell(0, 10, 'Showtime: ' . $payload['showtime']['start_datetime'], 0, 1);
		$pdf->Cell(0, 10, 'Seats: ' . $seats, 0, 1);
		$pdf->Cell(0, 10, 'Booking code: ' . $payload['booking_code'], 0, 1);
		$pdf->Cell(0, 10, 'Payment status: ' . strtoupper((string) ($payload['payment_status'] ?: 'pending')), 0, 1);
		$pdf->Output('F', $file_path);

		return array(
			'path' => $file_path,
			'url'  => trailingslashit($upload_dir['baseurl']) . 'ticket-' . $payload['booking_code'] . '.pdf',
		);
	}

	private function get_ticket_code_markup($booking_code) {
		if (class_exists('Endroid\\QrCode\\Builder\\Builder') && class_exists('Endroid\\QrCode\\Writer\\SvgWriter')) {
			try {
				$result = \Endroid\QrCode\Builder\Builder::create()
					->writer(new \Endroid\QrCode\Writer\SvgWriter())
					->data($booking_code)
					->size(180)
					->margin(8)
					->build();

				return array(
					'label'  => __('Scan code', 'cinema-booking'),
					'markup' => $result->getString(),
				);
			} catch (Throwable $exception) {
			}
		}

		return array(
			'label'  => __('Reference code', 'cinema-booking'),
			'markup' => $this->render_code_matrix_svg($booking_code),
		);
	}

	private function render_code_matrix_svg($booking_code) {
		$cells = 21;
		$size  = 180;
		$cell  = (int) floor($size / $cells);
		$bits  = '';
		$hash  = str_split(hash('sha256', $booking_code));

		foreach ($hash as $char) {
			$bits .= str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT);
		}

		while (strlen($bits) < ($cells * $cells)) {
			$bits .= $bits;
		}

		$bits  = substr($bits, 0, $cells * $cells);
		$rects = array();

		for ($row = 0; $row < $cells; $row++) {
			for ($column = 0; $column < $cells; $column++) {
				$bit = $bits[($row * $cells) + $column];

				if ('1' !== $bit) {
					continue;
				}

				$rects[] = sprintf(
					'<rect x="%1$d" y="%2$d" width="%3$d" height="%3$d" fill="#111827" />',
					$column * $cell,
					$row * $cell,
					max(1, $cell - 1)
				);
			}
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" role="img" aria-label="%2$s"><rect width="%1$d" height="%1$d" rx="18" fill="#ffffff"/>%3$s</svg>',
			$size,
			esc_attr(sprintf(__('Reference code %s', 'cinema-booking'), $booking_code)),
			implode('', $rects)
		);
	}
}
