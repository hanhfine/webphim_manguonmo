<?php

if (! defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Post_Types {
	/**
	 * @var Cinema_Booking_Seat_Manager
	 */
	private $seat_manager;

	public function __construct($seat_manager) {
		$this->seat_manager = $seat_manager;

		add_action('init', array($this, 'register_content_types'));
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post', array($this, 'save_meta_boxes'));
	}

	public function register_content_types() {
		$this->register_movie_post_type();
		$this->register_room_post_type();
		$this->register_showtime_post_type();
		$this->register_booking_post_type();
		$this->register_taxonomies();
	}

	public function register_meta_boxes() {
		add_meta_box('cinema-movie-details', __('Movie Details', 'cinema-booking'), array($this, 'render_movie_meta_box'), 'movie', 'normal', 'default');
		add_meta_box('cinema-room-details', __('Room Details', 'cinema-booking'), array($this, 'render_room_meta_box'), 'room', 'normal', 'default');
		add_meta_box('cinema-showtime-details', __('Showtime Details', 'cinema-booking'), array($this, 'render_showtime_meta_box'), 'showtime', 'normal', 'default');
		add_meta_box('cinema-booking-details', __('Booking Snapshot', 'cinema-booking'), array($this, 'render_booking_meta_box'), 'booking', 'normal', 'default');
	}

	public function save_meta_boxes($post_id) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (! isset($_POST['cinema_booking_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cinema_booking_meta_nonce'])), 'cinema_booking_save_meta')) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$post_type = get_post_type($post_id);

		switch ($post_type) {
			case 'movie':
				$this->save_movie_meta($post_id);
				break;
			case 'room':
				$this->save_room_meta($post_id);
				break;
			case 'showtime':
				$this->save_showtime_meta($post_id);
				break;
		}
	}

	public function render_movie_meta_box($post) {
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');

		$status = get_post_meta($post->ID, '_cinema_movie_status', true);
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><label for="cinema_trailer_url"><?php esc_html_e('Trailer URL', 'cinema-booking'); ?></label></th>
				<td><input type="url" class="widefat" name="cinema_trailer_url" id="cinema_trailer_url" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_trailer_url', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_director"><?php esc_html_e('Director', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="widefat" name="cinema_director" id="cinema_director" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_director', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_cast"><?php esc_html_e('Cast', 'cinema-booking'); ?></label></th>
				<td><textarea class="widefat" name="cinema_cast" id="cinema_cast" rows="3"><?php echo esc_textarea(get_post_meta($post->ID, '_cinema_cast', true)); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cinema_duration_minutes"><?php esc_html_e('Duration (minutes)', 'cinema-booking'); ?></label></th>
				<td><input type="number" min="1" class="small-text" name="cinema_duration_minutes" id="cinema_duration_minutes" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_duration_minutes', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_release_date"><?php esc_html_e('Release Date', 'cinema-booking'); ?></label></th>
				<td><input type="date" name="cinema_release_date" id="cinema_release_date" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_release_date', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_end_date"><?php esc_html_e('End Date', 'cinema-booking'); ?></label></th>
				<td><input type="date" name="cinema_end_date" id="cinema_end_date" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_end_date', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_rating"><?php esc_html_e('Age Rating', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="small-text" name="cinema_rating" id="cinema_rating" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_rating', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_review_score"><?php esc_html_e('Review Score', 'cinema-booking'); ?></label></th>
				<td><input type="number" step="0.1" min="0" max="10" class="small-text" name="cinema_review_score" id="cinema_review_score" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_review_score', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_movie_status"><?php esc_html_e('Movie Status', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_movie_status" id="cinema_movie_status">
						<?php foreach (array('now_showing', 'coming_soon', 'ended') as $value) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $value))); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	public function render_cinema_meta_box($post) {
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><label for="cinema_address"><?php esc_html_e('Address', 'cinema-booking'); ?></label></th>
				<td><textarea class="widefat" name="cinema_address" id="cinema_address" rows="3"><?php echo esc_textarea(get_post_meta($post->ID, '_cinema_address', true)); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cinema_city"><?php esc_html_e('City', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="widefat" name="cinema_city" id="cinema_city" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_city', true)); ?>"></td>
			</tr>
		</table>
		<?php
	}

	public function render_room_meta_box($post) {
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><?php esc_html_e('Cinema', 'cinema-booking'); ?></th>
				<td>
					<strong><?php echo esc_html($this->get_single_cinema_name()); ?></strong>
					<p class="description"><?php esc_html_e('This plugin is configured for a single cinema. Rooms created here will belong to that cinema automatically.', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_type"><?php esc_html_e('Room Type', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_room_type" id="cinema_room_type">
						<?php foreach (array('2d', '3d', 'imax') as $value) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected(get_post_meta($post->ID, '_cinema_room_type', true), $value); ?>>
								<?php echo esc_html(strtoupper($value)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_total_rows"><?php esc_html_e('Seat Rows', 'cinema-booking'); ?></label></th>
				<td><input type="number" min="1" class="small-text" name="cinema_room_total_rows" id="cinema_room_total_rows" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_total_rows', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_room_total_columns"><?php esc_html_e('Seats per Row', 'cinema-booking'); ?></label></th>
				<td><input type="number" min="1" class="small-text" name="cinema_room_total_columns" id="cinema_room_total_columns" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_total_columns', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_room_vip_rows"><?php esc_html_e('VIP Rows', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_vip_rows" id="cinema_room_vip_rows" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_vip_rows', true)); ?>">
					<p class="description"><?php esc_html_e('Example: C,D or 3,4', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_couple_rows"><?php esc_html_e('Couple Rows', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_couple_rows" id="cinema_room_couple_rows" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_couple_rows', true)); ?>">
					<p class="description"><?php esc_html_e('Example: E,F or 5,6', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_vip_seats"><?php esc_html_e('VIP Seats', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_vip_seats" id="cinema_room_vip_seats" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_vip_seats', true)); ?>">
					<p class="description"><?php esc_html_e('Example: A1,A2,C7', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_couple_seats"><?php esc_html_e('Couple Seats', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_couple_seats" id="cinema_room_couple_seats" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_couple_seats', true)); ?>">
					<p class="description"><?php esc_html_e('Example: D5,D6,E7,E8', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_inactive_seats"><?php esc_html_e('Hidden / Broken Seats', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_inactive_seats" id="cinema_room_inactive_seats" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_inactive_seats', true)); ?>">
					<p class="description"><?php esc_html_e('Example: B4,B5,F1', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Seat Builder Preview', 'cinema-booking'); ?></th>
				<td>
					<div class="cinema-seat-builder" data-cinema-seat-builder>
						<div class="cinema-seat-builder-legend">
							<span><i class="cinema-seat-chip is-normal"></i><?php esc_html_e('Normal', 'cinema-booking'); ?></span>
							<span><i class="cinema-seat-chip is-vip"></i><?php esc_html_e('VIP', 'cinema-booking'); ?></span>
							<span><i class="cinema-seat-chip is-couple"></i><?php esc_html_e('Couple', 'cinema-booking'); ?></span>
							<span><i class="cinema-seat-chip is-inactive"></i><?php esc_html_e('Inactive', 'cinema-booking'); ?></span>
						</div>
						<div class="cinema-seat-builder-grid" data-seat-builder-grid></div>
						<p class="description" data-seat-builder-summary><?php esc_html_e('Update room settings to preview the seat layout.', 'cinema-booking'); ?></p>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	public function render_showtime_meta_box($post) {
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');

		$movies = get_posts(
			array(
				'post_type'      => 'movie',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$rooms = get_posts(
			array(
				'post_type'      => 'room',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$start_datetime = get_post_meta($post->ID, '_cinema_showtime_start_datetime', true);
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><label for="cinema_showtime_movie_id"><?php esc_html_e('Movie', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_showtime_movie_id" id="cinema_showtime_movie_id">
						<option value="0"><?php esc_html_e('Select movie', 'cinema-booking'); ?></option>
						<?php foreach ($movies as $movie) : ?>
							<option value="<?php echo esc_attr($movie->ID); ?>" <?php selected((int) get_post_meta($post->ID, '_cinema_showtime_movie_id', true), $movie->ID); ?>>
								<?php echo esc_html($movie->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_showtime_room_id"><?php esc_html_e('Room', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_showtime_room_id" id="cinema_showtime_room_id">
						<option value="0"><?php esc_html_e('Select room', 'cinema-booking'); ?></option>
						<?php foreach ($rooms as $room) : ?>
							<option value="<?php echo esc_attr($room->ID); ?>" <?php selected((int) get_post_meta($post->ID, '_cinema_showtime_room_id', true), $room->ID); ?>>
								<?php echo esc_html($room->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_showtime_start_datetime"><?php esc_html_e('Start Date & Time', 'cinema-booking'); ?></label></th>
				<td><input type="datetime-local" name="cinema_showtime_start_datetime" id="cinema_showtime_start_datetime" value="<?php echo esc_attr($start_datetime ? str_replace(' ', 'T', substr($start_datetime, 0, 16)) : ''); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_showtime_status"><?php esc_html_e('Status', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_showtime_status" id="cinema_showtime_status">
						<?php foreach (array('open', 'locked', 'completed', 'cancelled') as $value) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected(get_post_meta($post->ID, '_cinema_showtime_status', true), $value); ?>>
								<?php echo esc_html(ucfirst($value)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Pricing', 'cinema-booking'); ?></th>
				<td class="cinema-pricing-grid">
					<label><?php esc_html_e('Normal', 'cinema-booking'); ?> <input type="number" min="0" step="1000" name="cinema_showtime_price_normal" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_showtime_price_normal', true)); ?>"></label>
					<label><?php esc_html_e('VIP', 'cinema-booking'); ?> <input type="number" min="0" step="1000" name="cinema_showtime_price_vip" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_showtime_price_vip', true)); ?>"></label>
					<label><?php esc_html_e('Couple', 'cinema-booking'); ?> <input type="number" min="0" step="1000" name="cinema_showtime_price_couple" value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_showtime_price_couple', true)); ?>"></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Auto-calculated End Time', 'cinema-booking'); ?></th>
				<td><strong><?php echo esc_html((string) get_post_meta($post->ID, '_cinema_showtime_end_datetime', true)); ?></strong></td>
			</tr>
		</table>
		<?php
	}

	public function render_booking_meta_box($post) {
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');

		$seat_ids = (array) get_post_meta($post->ID, '_cinema_booking_seat_ids', true);
		$seats    = $this->seat_manager->get_seat_labels($seat_ids);
		?>
		<p><strong><?php esc_html_e('Booking Code', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_code', true)); ?></p>
		<p><strong><?php esc_html_e('Customer', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_customer_name', true)); ?></p>
		<p><strong><?php esc_html_e('Email', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_customer_email', true)); ?></p>
		<p><strong><?php esc_html_e('Phone', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_customer_phone', true)); ?></p>
		<p><strong><?php esc_html_e('Showtime ID', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_showtime_id', true)); ?></p>
		<p><strong><?php esc_html_e('Seats', 'cinema-booking'); ?>:</strong> <?php echo esc_html(implode(', ', wp_list_pluck($seats, 'label'))); ?></p>
		<p><strong><?php esc_html_e('Total Amount', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_total_amount', true)); ?></p>
		<p><strong><?php esc_html_e('Payment Method', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_payment_method', true)); ?></p>
		<p><strong><?php esc_html_e('Status', 'cinema-booking'); ?>:</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_status', true)); ?></p>
		<?php
	}

	private function register_taxonomies() {
		register_taxonomy(
			'movie_genre',
			array('movie'),
			array(
				'labels'            => array(
					'name'          => __('Genres', 'cinema-booking'),
					'singular_name' => __('Genre', 'cinema-booking'),
				),
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array('slug' => 'movie-genre'),
			)
		);
	}

	private function register_movie_post_type() {
		register_post_type(
			'movie',
			array(
				'labels'             => $this->get_post_type_labels(__('Movie', 'cinema-booking'), __('Movies', 'cinema-booking')),
				'public'             => true,
				'show_in_rest'       => true,
				'show_in_menu'       => 'cinema-booking-dashboard',
				'menu_icon'          => 'dashicons-video-alt2',
				'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
				'rewrite'            => array('slug' => 'movies'),
				'has_archive'        => true,
				'publicly_queryable' => true,
			)
		);
	}

	private function register_room_post_type() {
		register_post_type(
			'room',
			array(
				'labels'             => $this->get_post_type_labels(__('Screening Room', 'cinema-booking'), __('Screening Rooms', 'cinema-booking')),
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_in_menu'       => 'cinema-booking-dashboard',
				'menu_icon'          => 'dashicons-grid-view',
				'supports'           => array('title'),
				'publicly_queryable' => false,
			)
		);
	}

	private function register_showtime_post_type() {
		register_post_type(
			'showtime',
			array(
				'labels'             => $this->get_post_type_labels(__('Showtime', 'cinema-booking'), __('Showtimes', 'cinema-booking')),
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_in_menu'       => 'cinema-booking-dashboard',
				'menu_icon'          => 'dashicons-calendar-alt',
				'supports'           => array('title'),
				'publicly_queryable' => false,
			)
		);
	}

	private function register_booking_post_type() {
		register_post_type(
			'booking',
			array(
				'labels'             => $this->get_post_type_labels(__('Booking', 'cinema-booking'), __('Bookings', 'cinema-booking')),
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_in_menu'       => 'cinema-booking-dashboard',
				'menu_icon'          => 'dashicons-tickets-alt',
				'supports'           => array('title'),
				'publicly_queryable' => false,
			)
		);
	}

	private function save_movie_meta($post_id) {
		update_post_meta($post_id, '_cinema_trailer_url', esc_url_raw(wp_unslash($_POST['cinema_trailer_url'] ?? '')));
		update_post_meta($post_id, '_cinema_director', sanitize_text_field(wp_unslash($_POST['cinema_director'] ?? '')));
		update_post_meta($post_id, '_cinema_cast', sanitize_textarea_field(wp_unslash($_POST['cinema_cast'] ?? '')));
		update_post_meta($post_id, '_cinema_duration_minutes', absint($_POST['cinema_duration_minutes'] ?? 0));
		update_post_meta($post_id, '_cinema_release_date', sanitize_text_field(wp_unslash($_POST['cinema_release_date'] ?? '')));
		update_post_meta($post_id, '_cinema_end_date', sanitize_text_field(wp_unslash($_POST['cinema_end_date'] ?? '')));
		update_post_meta($post_id, '_cinema_rating', sanitize_text_field(wp_unslash($_POST['cinema_rating'] ?? '')));
		update_post_meta($post_id, '_cinema_review_score', (float) ($_POST['cinema_review_score'] ?? 0));
		update_post_meta($post_id, '_cinema_movie_status', sanitize_key(wp_unslash($_POST['cinema_movie_status'] ?? 'now_showing')));
	}

	private function save_room_meta($post_id) {
		$room_type     = sanitize_key(wp_unslash($_POST['cinema_room_type'] ?? '2d'));
		$total_rows    = absint($_POST['cinema_room_total_rows'] ?? 0);
		$total_columns = absint($_POST['cinema_room_total_columns'] ?? 0);
		$vip_rows      = sanitize_text_field(wp_unslash($_POST['cinema_room_vip_rows'] ?? ''));
		$couple_rows   = sanitize_text_field(wp_unslash($_POST['cinema_room_couple_rows'] ?? ''));
		$vip_seats     = sanitize_text_field(wp_unslash($_POST['cinema_room_vip_seats'] ?? ''));
		$couple_seats  = sanitize_text_field(wp_unslash($_POST['cinema_room_couple_seats'] ?? ''));
		$inactive_seats = sanitize_text_field(wp_unslash($_POST['cinema_room_inactive_seats'] ?? ''));

		delete_post_meta($post_id, '_cinema_room_cinema_id');
		update_post_meta($post_id, '_cinema_room_type', $room_type);
		update_post_meta($post_id, '_cinema_room_total_rows', $total_rows);
		update_post_meta($post_id, '_cinema_room_total_columns', $total_columns);
		update_post_meta($post_id, '_cinema_room_vip_rows', $vip_rows);
		update_post_meta($post_id, '_cinema_room_couple_rows', $couple_rows);
		update_post_meta($post_id, '_cinema_room_vip_seats', $vip_seats);
		update_post_meta($post_id, '_cinema_room_couple_seats', $couple_seats);
		update_post_meta($post_id, '_cinema_room_inactive_seats', $inactive_seats);

		if ($total_rows && $total_columns) {
			$this->seat_manager->sync_room_layout(
				$post_id,
				array(
					'total_rows'     => $total_rows,
					'total_columns'  => $total_columns,
					'vip_rows'       => $vip_rows,
					'couple_rows'    => $couple_rows,
					'vip_seats'      => $vip_seats,
					'couple_seats'   => $couple_seats,
					'inactive_seats' => $inactive_seats,
				)
			);
		}
	}

	private function save_showtime_meta($post_id) {
		$movie_id       = absint($_POST['cinema_showtime_movie_id'] ?? 0);
		$room_id        = absint($_POST['cinema_showtime_room_id'] ?? 0);
		$status         = sanitize_key(wp_unslash($_POST['cinema_showtime_status'] ?? 'open'));
		$start_datetime = sanitize_text_field(wp_unslash($_POST['cinema_showtime_start_datetime'] ?? ''));
		$start_datetime = $start_datetime ? str_replace('T', ' ', $start_datetime) . ':00' : '';
		$duration       = absint(get_post_meta($movie_id, '_cinema_duration_minutes', true));
		$end_timestamp  = $start_datetime ? strtotime($start_datetime) + ($duration * MINUTE_IN_SECONDS) : 0;
		$end_datetime   = $end_timestamp ? wp_date('Y-m-d H:i:s', $end_timestamp) : '';

		update_post_meta($post_id, '_cinema_showtime_movie_id', $movie_id);
		update_post_meta($post_id, '_cinema_showtime_room_id', $room_id);
		update_post_meta($post_id, '_cinema_showtime_status', $status);
		update_post_meta($post_id, '_cinema_showtime_start_datetime', $start_datetime);
		update_post_meta($post_id, '_cinema_showtime_end_datetime', $end_datetime);
		update_post_meta($post_id, '_cinema_showtime_price_normal', (float) ($_POST['cinema_showtime_price_normal'] ?? 0));
		update_post_meta($post_id, '_cinema_showtime_price_vip', (float) ($_POST['cinema_showtime_price_vip'] ?? 0));
		update_post_meta($post_id, '_cinema_showtime_price_couple', (float) ($_POST['cinema_showtime_price_couple'] ?? 0));

		remove_action('save_post', array($this, 'save_meta_boxes'));
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $this->build_showtime_title($movie_id, $room_id, $start_datetime),
			)
		);
		add_action('save_post', array($this, 'save_meta_boxes'));
	}

	private function build_showtime_title($movie_id, $room_id, $start_datetime) {
		$parts = array_filter(
			array(
				get_the_title($movie_id),
				get_the_title($room_id),
				$start_datetime,
			)
		);

		return implode(' | ', $parts);
	}

	private function get_post_type_labels($singular, $plural) {
		return array(
			'name'                     => $plural,
			'singular_name'            => $singular,
			'menu_name'                => $plural,
			'name_admin_bar'           => $singular,
			'add_new'                  => sprintf(__('Add %s', 'cinema-booking'), $singular),
			'add_new_item'             => sprintf(__('Add New %s', 'cinema-booking'), $singular),
			'edit_item'                => sprintf(__('Edit %s', 'cinema-booking'), $singular),
			'new_item'                 => sprintf(__('New %s', 'cinema-booking'), $singular),
			'view_item'                => sprintf(__('View %s', 'cinema-booking'), $singular),
			'view_items'               => sprintf(__('View %s', 'cinema-booking'), $plural),
			'search_items'             => sprintf(__('Search %s', 'cinema-booking'), $plural),
			'not_found'                => sprintf(__('No %s found.', 'cinema-booking'), strtolower((string) $plural)),
			'not_found_in_trash'       => sprintf(__('No %s found in Trash.', 'cinema-booking'), strtolower((string) $plural)),
			'all_items'                => sprintf(__('All %s', 'cinema-booking'), $plural),
			'archives'                 => sprintf(__('%s Archives', 'cinema-booking'), $singular),
			'attributes'               => sprintf(__('%s Attributes', 'cinema-booking'), $singular),
			'insert_into_item'         => sprintf(__('Insert into %s', 'cinema-booking'), strtolower((string) $singular)),
			'uploaded_to_this_item'    => sprintf(__('Uploaded to this %s', 'cinema-booking'), strtolower((string) $singular)),
			'filter_items_list'        => sprintf(__('Filter %s list', 'cinema-booking'), strtolower((string) $plural)),
			'items_list_navigation'    => sprintf(__('%s list navigation', 'cinema-booking'), $plural),
			'items_list'               => sprintf(__('%s list', 'cinema-booking'), $plural),
			'item_published'           => sprintf(__('%s published.', 'cinema-booking'), $singular),
			'item_updated'             => sprintf(__('%s updated.', 'cinema-booking'), $singular),
		);
	}

	private function get_single_cinema_name() {
		return cinema_booking_get_single_cinema_name();
	}
}
