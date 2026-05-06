<?php

if (!defined('ABSPATH')) {
	exit;
}

class Cinema_Booking_Post_Types
{
	/**
	 * @var Cinema_Booking
	 */
	private $plugin;

	public function __construct($plugin)
	{
		$this->plugin = $plugin;

		add_action('init', array($this, 'register_content_types'));
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('save_post', array($this, 'save_meta_boxes'));
	}

	public function register_content_types()
	{
		$this->register_movie_post_type();
		$this->register_room_post_type();
		$this->register_showtime_post_type();
		$this->register_booking_post_type();
		$this->register_taxonomies();
	}

	public function register_meta_boxes()
	{
		add_meta_box('cinema-movie-details', __('Chi tiết Phim', 'cinema-booking'), array($this, 'render_movie_meta_box'), 'movie', 'normal', 'default');
		add_meta_box('cinema-room-details', __('Chi tiết Phòng chiếu', 'cinema-booking'), array($this, 'render_room_meta_box'), 'room', 'normal', 'default');
		add_meta_box('cinema-showtime-details', __('Chi tiết Suất chiếu', 'cinema-booking'), array($this, 'render_showtime_meta_box'), 'showtime', 'normal', 'default');
		add_meta_box('cinema-booking-details', __('Chi tiết Đơn đặt vé', 'cinema-booking'), array($this, 'render_booking_meta_box'), 'booking', 'normal', 'default');
	}

	public function save_meta_boxes($post_id)
	{
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!isset($_POST['cinema_booking_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cinema_booking_meta_nonce'])), 'cinema_booking_save_meta')) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
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

	public function render_movie_meta_box($post)
	{
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');

		$status = get_post_meta($post->ID, '_cinema_movie_status', true);
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><label
						for="cinema_poster_url"><?php esc_html_e('Poster URL (Link ảnh trực tiếp)', 'cinema-booking'); ?></label>
				</th>
				<td>
					<input type="url" class="widefat" name="cinema_poster_url" id="cinema_poster_url"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_poster_url', true)); ?>">
					<p class="description">
						<?php esc_html_e('Dùng link này thay thế cho Ảnh đại diện (Featured Image) nếu có.', 'cinema-booking'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_trailer_url"><?php esc_html_e('Link Trailer', 'cinema-booking'); ?></label></th>
				<td><input type="url" class="widefat" name="cinema_trailer_url" id="cinema_trailer_url"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_trailer_url', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_director"><?php esc_html_e('Đạo diễn', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="widefat" name="cinema_director" id="cinema_director"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_director', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_cast"><?php esc_html_e('Diễn viên', 'cinema-booking'); ?></label></th>
				<td><textarea class="widefat" name="cinema_cast" id="cinema_cast"
						rows="3"><?php echo esc_textarea(get_post_meta($post->ID, '_cinema_cast', true)); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cinema_duration_minutes"><?php esc_html_e('Thời lượng (phút)', 'cinema-booking'); ?></label>
				</th>
				<td><input type="number" min="1" class="small-text" name="cinema_duration_minutes" id="cinema_duration_minutes"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_duration_minutes', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_release_date"><?php esc_html_e('Ngày khởi chiếu', 'cinema-booking'); ?></label></th>
				<td><input type="date" name="cinema_release_date" id="cinema_release_date"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_release_date', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_end_date"><?php esc_html_e('Ngày kết thúc', 'cinema-booking'); ?></label></th>
				<td><input type="date" name="cinema_end_date" id="cinema_end_date"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_end_date', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_rating"><?php esc_html_e('Độ tuổi', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="small-text" name="cinema_rating" id="cinema_rating"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_rating', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_review_score"><?php esc_html_e('Điểm đánh giá', 'cinema-booking'); ?></label></th>
				<td><input type="number" step="0.1" min="0" max="10" class="small-text" name="cinema_review_score"
						id="cinema_review_score"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_review_score', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_movie_status"><?php esc_html_e('Trạng thái phim', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_movie_status" id="cinema_movie_status">
						<option value="now_showing" <?php selected($status, 'now_showing'); ?>>Đang chiếu</option>
						<option value="coming_soon" <?php selected($status, 'coming_soon'); ?>>Sắp chiếu</option>
						<option value="ended" <?php selected($status, 'ended'); ?>>Đã kết thúc</option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	public function render_cinema_meta_box($post)
	{
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><label for="cinema_address"><?php esc_html_e('Địa chỉ', 'cinema-booking'); ?></label></th>
				<td><textarea class="widefat" name="cinema_address" id="cinema_address"
						rows="3"><?php echo esc_textarea(get_post_meta($post->ID, '_cinema_address', true)); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cinema_city"><?php esc_html_e('Thành phố', 'cinema-booking'); ?></label></th>
				<td><input type="text" class="widefat" name="cinema_city" id="cinema_city"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_city', true)); ?>"></td>
			</tr>
		</table>
		<?php
	}

	public function render_room_meta_box($post)
	{
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><?php esc_html_e('Rạp', 'cinema-booking'); ?></th>
				<td>
					<strong><?php echo esc_html($this->get_single_cinema_name()); ?></strong>
					<p class="description">
						<?php esc_html_e('Hệ thống được cấu hình cho một rạp duy nhất. Tất cả phòng chiếu sẽ tự động thuộc rạp này.', 'cinema-booking'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_type"><?php esc_html_e('Loại phòng', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_room_type" id="cinema_room_type">
						<?php foreach (array('2d', '3d', 'imax') as $value): ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected(get_post_meta($post->ID, '_cinema_room_type', true), $value); ?>>
								<?php echo esc_html(strtoupper($value)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_total_rows"><?php esc_html_e('Số hàng ghế', 'cinema-booking'); ?></label></th>
				<td><input type="number" min="1" class="small-text" name="cinema_room_total_rows" id="cinema_room_total_rows"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_total_rows', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_room_total_columns"><?php esc_html_e('Số ghế mỗi hàng', 'cinema-booking'); ?></label></th>
				<td><input type="number" min="1" class="small-text" name="cinema_room_total_columns"
						id="cinema_room_total_columns"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_total_columns', true)); ?>"></td>
			</tr>
			<tr>
				<th><label for="cinema_room_vip_rows"><?php esc_html_e('Hàng ghế VIP', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_vip_rows" id="cinema_room_vip_rows"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_vip_rows', true)); ?>">
					<p class="description"><?php esc_html_e('Ví dụ: C,D hoặc 3,4', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_couple_rows"><?php esc_html_e('Hàng ghế couple', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_couple_rows" id="cinema_room_couple_rows"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_couple_rows', true)); ?>">
					<p class="description"><?php esc_html_e('Ví dụ: E,F hoặc 5,6', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_vip_seats"><?php esc_html_e('Ghế VIP riêng lẻ', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_vip_seats" id="cinema_room_vip_seats"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_vip_seats', true)); ?>">
					<p class="description"><?php esc_html_e('Ví dụ: A1,A2,C7', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_room_couple_seats"><?php esc_html_e('Ghế couple riêng lẻ', 'cinema-booking'); ?></label></th>
				<td>
					<input type="text" class="widefat" name="cinema_room_couple_seats" id="cinema_room_couple_seats"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_couple_seats', true)); ?>">
					<p class="description"><?php esc_html_e('Ví dụ: D5,D6,E7,E8', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label
						for="cinema_room_inactive_seats"><?php esc_html_e('Ghế ẩn / ghế hỏng', 'cinema-booking'); ?></label>
				</th>
				<td>
					<input type="text" class="widefat" name="cinema_room_inactive_seats" id="cinema_room_inactive_seats"
						value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_room_inactive_seats', true)); ?>">
					<p class="description"><?php esc_html_e('Ví dụ: B4,B5,F1', 'cinema-booking'); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Xem trước sơ đồ ghế', 'cinema-booking'); ?></th>
				<td>
					<div class="cinema-seat-builder" data-cinema-seat-builder>
						<div class="cinema-seat-builder-legend">
							<span><i
									class="cinema-seat-chip is-normal"></i><?php esc_html_e('Thường', 'cinema-booking'); ?></span>
							<span><i class="cinema-seat-chip is-vip"></i><?php esc_html_e('VIP', 'cinema-booking'); ?></span>
							<span><i
									class="cinema-seat-chip is-couple"></i><?php esc_html_e('Couple', 'cinema-booking'); ?></span>
							<span><i
									class="cinema-seat-chip is-inactive"></i><?php esc_html_e('Không hoạt động', 'cinema-booking'); ?></span>
						</div>
						<div class="cinema-seat-builder-grid" data-seat-builder-grid></div>
						<p class="description" data-seat-builder-summary>
							<?php esc_html_e('Cập nhật cấu hình phòng để xem trước sơ đồ ghế.', 'cinema-booking'); ?></p>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	public function render_showtime_meta_box($post)
	{
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');

		$movies = get_posts(
			array(
				'post_type' => 'movie',
				'post_status' => 'publish',
				'posts_per_page' => -1,
			)
		);
		$rooms = get_posts(
			array(
				'post_type' => 'room',
				'post_status' => 'publish',
				'posts_per_page' => -1,
			)
		);
		$start_datetime = get_post_meta($post->ID, '_cinema_showtime_start_datetime', true);
		?>
		<table class="form-table cinema-form-table">
			<tr>
				<th><label for="cinema_showtime_movie_id"><?php esc_html_e('Phim', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_showtime_movie_id" id="cinema_showtime_movie_id">
						<option value="0"><?php esc_html_e('Chọn phim', 'cinema-booking'); ?></option>
						<?php foreach ($movies as $movie): ?>
							<option value="<?php echo esc_attr($movie->ID); ?>" <?php selected((int) get_post_meta($post->ID, '_cinema_showtime_movie_id', true), $movie->ID); ?>>
								<?php echo esc_html($this->get_title_or_fallback($movie->ID, __('Phim chưa có tên', 'cinema-booking'))); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cinema_showtime_room_id"><?php esc_html_e('Phòng chiếu', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_showtime_room_id" id="cinema_showtime_room_id">
						<option value="0"><?php esc_html_e('Chọn phòng', 'cinema-booking'); ?></option>
						<?php foreach ($rooms as $room): ?>
							<option value="<?php echo esc_attr($room->ID); ?>" <?php selected((int) get_post_meta($post->ID, '_cinema_showtime_room_id', true), $room->ID); ?>>
								<?php echo esc_html($this->get_title_or_fallback($room->ID, __('Phòng chưa có tên', 'cinema-booking'))); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label
						for="cinema_showtime_start_datetime"><?php esc_html_e('Ngày giờ bắt đầu', 'cinema-booking'); ?></label>
				</th>
				<td><input type="datetime-local" name="cinema_showtime_start_datetime" id="cinema_showtime_start_datetime"
						value="<?php echo esc_attr($start_datetime ? str_replace(' ', 'T', substr($start_datetime, 0, 16)) : ''); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="cinema_showtime_status"><?php esc_html_e('Trạng thái', 'cinema-booking'); ?></label></th>
				<td>
					<select name="cinema_showtime_status" id="cinema_showtime_status">
						<?php foreach (array('open', 'locked', 'completed', 'cancelled') as $value): ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected(get_post_meta($post->ID, '_cinema_showtime_status', true), $value); ?>>
								<?php echo esc_html($this->get_showtime_status_label($value)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Giá vé', 'cinema-booking'); ?></th>
				<td class="cinema-pricing-grid">
					<label><?php esc_html_e('Thường', 'cinema-booking'); ?> <input type="number" min="0" step="1000"
							name="cinema_showtime_price_normal"
							value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_showtime_price_normal', true)); ?>"></label>
					<label><?php esc_html_e('VIP', 'cinema-booking'); ?> <input type="number" min="0" step="1000"
							name="cinema_showtime_price_vip"
							value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_showtime_price_vip', true)); ?>"></label>
					<label><?php esc_html_e('Couple', 'cinema-booking'); ?> <input type="number" min="0" step="1000"
							name="cinema_showtime_price_couple"
							value="<?php echo esc_attr(get_post_meta($post->ID, '_cinema_showtime_price_couple', true)); ?>"></label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Giờ kết thúc tự tính', 'cinema-booking'); ?></th>
				<td><strong><?php echo esc_html((string) get_post_meta($post->ID, '_cinema_showtime_end_datetime', true)); ?></strong>
				</td>
			</tr>
		</table>
		<?php
	}

	public function render_booking_meta_box($post)
	{
		wp_nonce_field('cinema_booking_save_meta', 'cinema_booking_meta_nonce');

		$seat_ids = (array) get_post_meta($post->ID, '_cinema_booking_seat_ids', true);
		$seats = $this->plugin->seat_manager->get_seat_labels($seat_ids);
		?>
		<p><strong><?php esc_html_e('Mã đặt vé', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_code', true)); ?></p>
		<p><strong><?php esc_html_e('Khách hàng', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_customer_name', true)); ?></p>
		<p><strong><?php esc_html_e('Email', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_customer_email', true)); ?></p>
		<p><strong><?php esc_html_e('Số điện thoại', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_customer_phone', true)); ?></p>
		<p><strong><?php esc_html_e('Mã suất chiếu', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_showtime_id', true)); ?></p>
		<p><strong><?php esc_html_e('Ghế', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html(implode(', ', wp_list_pluck($seats, 'label'))); ?></p>
		<p><strong><?php esc_html_e('Tổng tiền', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_total_amount', true)); ?></p>
		<p><strong><?php esc_html_e('Phương thức thanh toán', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_payment_method', true)); ?></p>
		<p><strong><?php esc_html_e('Trạng thái', 'cinema-booking'); ?>:</strong>
			<?php echo esc_html((string) get_post_meta($post->ID, '_cinema_booking_status', true)); ?></p>
		<?php
	}

	private function register_taxonomies()
	{
		register_taxonomy(
			'movie_genre',
			array('movie'),
			array(
				'labels' => array(
					'name' => 'Thể loại',
					'singular_name' => 'Thể loại',
				),
				'hierarchical' => true,
				'show_admin_column' => true,
				'rewrite' => array('slug' => 'movie-genre'),
			)
		);
	}

	private function register_movie_post_type()
	{
		register_post_type(
			'movie',
			array(
				'labels' => $this->get_post_type_labels('Phim', 'Phim'),
				'public' => true,
				'show_in_rest' => true,
				'show_in_menu' => 'cinema-booking-dashboard',
				'menu_icon' => 'dashicons-video-alt2',
				'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
				'rewrite' => array('slug' => 'movies'),
				'has_archive' => true,
				'publicly_queryable' => true,
			)
		);
	}

	private function register_room_post_type()
	{
		register_post_type(
			'room',
			array(
				'labels' => $this->get_post_type_labels('Phòng chiếu', 'Phòng chiếu'),
				'public' => false,
				'show_ui' => true,
				'show_in_rest' => true,
				'show_in_menu' => 'cinema-booking-dashboard',
				'menu_icon' => 'dashicons-grid-view',
				'supports' => array('title'),
				'publicly_queryable' => false,
			)
		);
	}

	private function register_showtime_post_type()
	{
		register_post_type(
			'showtime',
			array(
				'labels' => $this->get_post_type_labels('Suất chiếu', 'Suất chiếu'),
				'public' => false,
				'show_ui' => true,
				'show_in_rest' => true,
				'show_in_menu' => 'cinema-booking-dashboard',
				'menu_icon' => 'dashicons-calendar-alt',
				'supports' => array('title'),
				'publicly_queryable' => false,
			)
		);
	}

	private function register_booking_post_type()
	{
		register_post_type(
			'booking',
			array(
				'labels' => $this->get_post_type_labels('Đơn đặt vé', 'Đơn đặt vé'),
				'public' => false,
				'show_ui' => true,
				'show_in_rest' => true,
				'show_in_menu' => 'cinema-booking-dashboard',
				'menu_icon' => 'dashicons-tickets-alt',
				'supports' => array('title'),
				'publicly_queryable' => false,
			)
		);
	}

	private function save_movie_meta($post_id)
	{
		update_post_meta($post_id, '_cinema_trailer_url', esc_url_raw(wp_unslash($_POST['cinema_trailer_url'] ?? '')));
		update_post_meta($post_id, '_cinema_director', sanitize_text_field(wp_unslash($_POST['cinema_director'] ?? '')));
		update_post_meta($post_id, '_cinema_cast', sanitize_textarea_field(wp_unslash($_POST['cinema_cast'] ?? '')));
		update_post_meta($post_id, '_cinema_duration_minutes', absint($_POST['cinema_duration_minutes'] ?? 0));
		update_post_meta($post_id, '_cinema_release_date', sanitize_text_field(wp_unslash($_POST['cinema_release_date'] ?? '')));
		update_post_meta($post_id, '_cinema_end_date', sanitize_text_field(wp_unslash($_POST['cinema_end_date'] ?? '')));
		update_post_meta($post_id, '_cinema_rating', sanitize_text_field(wp_unslash($_POST['cinema_rating'] ?? '')));
		update_post_meta($post_id, '_cinema_review_score', (float) ($_POST['cinema_review_score'] ?? 0));
		update_post_meta($post_id, '_cinema_movie_status', sanitize_key(wp_unslash($_POST['cinema_movie_status'] ?? 'now_showing')));
		update_post_meta($post_id, '_cinema_poster_url', esc_url_raw(wp_unslash($_POST['cinema_poster_url'] ?? '')));

		// Sync to custom table
		$custom_id = get_post_meta($post_id, '_cinema_custom_id', true);
		$post = get_post($post_id);
		$data = array(
			'title'            => $post->post_title,
			'slug'             => $post->post_name ?: sanitize_title($post->post_title),
			'description'      => $post->post_content,
			'poster_url'       => esc_url_raw(wp_unslash($_POST['cinema_poster_url'] ?? '')),
			'trailer_url'      => esc_url_raw(wp_unslash($_POST['cinema_trailer_url'] ?? '')),
			'director'         => sanitize_text_field(wp_unslash($_POST['cinema_director'] ?? '')),
			'cast_list'        => sanitize_textarea_field(wp_unslash($_POST['cinema_cast'] ?? '')),
			'duration_minutes' => absint($_POST['cinema_duration_minutes'] ?? 0),
			'release_date'     => sanitize_text_field(wp_unslash($_POST['cinema_release_date'] ?? '')) ?: null,
			'end_date'         => sanitize_text_field(wp_unslash($_POST['cinema_end_date'] ?? '')) ?: null,
			'rating'           => sanitize_text_field(wp_unslash($_POST['cinema_rating'] ?? '')),
			'review_score'     => (float) ($_POST['cinema_review_score'] ?? 0),
			'status'           => sanitize_key(wp_unslash($_POST['cinema_movie_status'] ?? 'now_showing')),
		);

		$terms = get_the_terms($post_id, 'movie_genre');
		if ($terms && !is_wp_error($terms)) {
			$data['genre'] = $terms[0]->name;
		}

		if ($custom_id) {
			$this->plugin->movie_repo->update($custom_id, $data);
		} else {
			$new_id = $this->plugin->movie_repo->create($data);
			update_post_meta($post_id, '_cinema_custom_id', $new_id);
		}
	}

	private function save_room_meta($post_id)
	{
		$room_type = sanitize_key(wp_unslash($_POST['cinema_room_type'] ?? '2d'));
		$total_rows = absint($_POST['cinema_room_total_rows'] ?? 0);
		$total_columns = absint($_POST['cinema_room_total_columns'] ?? 0);
		$vip_rows = sanitize_text_field(wp_unslash($_POST['cinema_room_vip_rows'] ?? ''));
		$couple_rows = sanitize_text_field(wp_unslash($_POST['cinema_room_couple_rows'] ?? ''));
		$vip_seats = sanitize_text_field(wp_unslash($_POST['cinema_room_vip_seats'] ?? ''));
		$couple_seats = sanitize_text_field(wp_unslash($_POST['cinema_room_couple_seats'] ?? ''));
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

		// Sync to custom table
		$custom_id = get_post_meta($post_id, '_cinema_custom_id', true);
		$post = get_post($post_id);
		$data = array(
			'name' => $post->post_title,
			'type' => $room_type,
		);

		if ($custom_id) {
			$this->plugin->room_repo->update($custom_id, $data);
		} else {
			$custom_id = $this->plugin->room_repo->create($data);
			update_post_meta($post_id, '_cinema_custom_id', $custom_id);
		}

		if ($total_rows && $total_columns && $custom_id) {
			$this->plugin->seat_manager->sync_room_layout(
				$custom_id,
				array(
					'total_rows' => $total_rows,
					'total_columns' => $total_columns,
					'vip_rows' => $vip_rows,
					'couple_rows' => $couple_rows,
					'vip_seats' => $vip_seats,
					'couple_seats' => $couple_seats,
					'inactive_seats' => $inactive_seats,
				)
			);
		}
	}

	private function save_showtime_meta($post_id)
	{
		$movie_id = absint($_POST['cinema_showtime_movie_id'] ?? 0);
		$room_id = absint($_POST['cinema_showtime_room_id'] ?? 0);
		$status = sanitize_key(wp_unslash($_POST['cinema_showtime_status'] ?? 'open'));
		$start_datetime = sanitize_text_field(wp_unslash($_POST['cinema_showtime_start_datetime'] ?? ''));
		$start_datetime = $start_datetime ? str_replace('T', ' ', $start_datetime) . ':00' : '';
		$duration = absint(get_post_meta($movie_id, '_cinema_duration_minutes', true));
		$end_timestamp = $start_datetime ? strtotime($start_datetime) + ($duration * MINUTE_IN_SECONDS) : 0;
		$end_datetime = $end_timestamp ? wp_date('Y-m-d H:i:s', $end_timestamp) : '';

		update_post_meta($post_id, '_cinema_showtime_movie_id', $movie_id);
		update_post_meta($post_id, '_cinema_showtime_room_id', $room_id);
		update_post_meta($post_id, '_cinema_showtime_status', $status);
		update_post_meta($post_id, '_cinema_showtime_start_datetime', $start_datetime);
		update_post_meta($post_id, '_cinema_showtime_end_datetime', $end_datetime);
		update_post_meta($post_id, '_cinema_showtime_price_normal', (float) ($_POST['cinema_showtime_price_normal'] ?? 0));
		update_post_meta($post_id, '_cinema_showtime_price_vip', (float) ($_POST['cinema_showtime_price_vip'] ?? 0));
		update_post_meta($post_id, '_cinema_showtime_price_couple', (float) ($_POST['cinema_showtime_price_couple'] ?? 0));

		// Sync to custom table
		$custom_id = get_post_meta($post_id, '_cinema_custom_id', true);
		$custom_movie_id = get_post_meta($movie_id, '_cinema_custom_id', true);
		$custom_room_id = get_post_meta($room_id, '_cinema_custom_id', true);

		if ($custom_movie_id && $custom_room_id && $start_datetime) {
			$data = array(
				'movie_id'       => $custom_movie_id,
				'room_id'        => $custom_room_id,
				'start_datetime' => $start_datetime,
				'end_datetime'   => $end_datetime,
				'status'         => $status,
				'price_normal'   => (float) ($_POST['cinema_showtime_price_normal'] ?? 0),
				'price_vip'      => (float) ($_POST['cinema_showtime_price_vip'] ?? 0),
				'price_couple'   => (float) ($_POST['cinema_showtime_price_couple'] ?? 0),
			);

			if ($custom_id) {
				$this->plugin->showtime_repo->update($custom_id, $data);
			} else {
				$new_id = $this->plugin->showtime_repo->create($data);
				update_post_meta($post_id, '_cinema_custom_id', $new_id);
			}
		}

		remove_action('save_post', array($this, 'save_meta_boxes'));
		wp_update_post(
			array(
				'ID' => $post_id,
				'post_title' => $this->build_showtime_title($movie_id, $room_id, $start_datetime),
			)
		);
		add_action('save_post', array($this, 'save_meta_boxes'));
	}

	private function build_showtime_title($movie_id, $room_id, $start_datetime)
	{
		$parts = array_filter(
			array(
				$this->get_title_or_fallback($movie_id, __('Phim chưa có tên', 'cinema-booking')),
				$this->get_title_or_fallback($room_id, __('Phòng chưa có tên', 'cinema-booking')),
				$start_datetime,
			)
		);

		return implode(' | ', $parts);
	}

	private function get_title_or_fallback($post_id, $fallback)
	{
		$title = get_the_title($post_id);

		return '' !== trim((string) $title) ? $title : $fallback;
	}

	private function get_post_type_labels($singular, $plural)
	{
		$lower_singular = function_exists('mb_strtolower') ? mb_strtolower((string) $singular) : strtolower((string) $singular);
		$lower_plural = function_exists('mb_strtolower') ? mb_strtolower((string) $plural) : strtolower((string) $plural);

		return array(
			'name' => $plural,
			'singular_name' => $singular,
			'menu_name' => $plural,
			'name_admin_bar' => $singular,
			'add_new' => __('Thêm mới', 'cinema-booking'),
			'add_new_item' => sprintf(__('Thêm %s mới', 'cinema-booking'), $lower_singular),
			'edit_item' => sprintf(__('Sửa %s', 'cinema-booking'), $lower_singular),
			'new_item' => sprintf(__('%s mới', 'cinema-booking'), $singular),
			'view_item' => sprintf(__('Xem %s', 'cinema-booking'), $lower_singular),
			'view_items' => sprintf(__('Xem %s', 'cinema-booking'), $lower_plural),
			'search_items' => sprintf(__('Tìm %s', 'cinema-booking'), $lower_plural),
			'not_found' => sprintf(__('Chưa có %s.', 'cinema-booking'), $lower_plural),
			'not_found_in_trash' => sprintf(__('Không có %s trong thùng rác.', 'cinema-booking'), $lower_plural),
			'all_items' => sprintf(__('Tất cả %s', 'cinema-booking'), $lower_plural),
			'archives' => sprintf(__('Lưu trữ %s', 'cinema-booking'), $lower_singular),
			'attributes' => sprintf(__('Thuộc tính %s', 'cinema-booking'), $lower_singular),
			'insert_into_item' => sprintf(__('Chèn vào %s', 'cinema-booking'), $lower_singular),
			'uploaded_to_this_item' => sprintf(__('Tải lên cho %s này', 'cinema-booking'), $lower_singular),
			'filter_items_list' => sprintf(__('Lọc danh sách %s', 'cinema-booking'), $lower_plural),
			'items_list_navigation' => sprintf(__('Điều hướng danh sách %s', 'cinema-booking'), $lower_plural),
			'items_list' => sprintf(__('Danh sách %s', 'cinema-booking'), $lower_plural),
			'item_published' => sprintf(__('%s đã xuất bản.', 'cinema-booking'), $singular),
			'item_updated' => sprintf(__('%s đã cập nhật.', 'cinema-booking'), $singular),
		);
	}

	private function get_showtime_status_label($status)
	{
		$labels = array(
			'open' => __('Mở bán', 'cinema-booking'),
			'locked' => __('Đã khóa', 'cinema-booking'),
			'completed' => __('Đã hoàn thành', 'cinema-booking'),
			'cancelled' => __('Đã hủy', 'cinema-booking'),
		);

		return $labels[$status] ?? $status;
	}

	private function get_single_cinema_name()
	{
		return cinema_booking_get_single_cinema_name();
	}
}
