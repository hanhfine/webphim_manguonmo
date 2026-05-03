# Cinema Booking System for WordPress

Scaffold for a movie ticket booking platform built with WordPress, custom PHP plugin logic, and a dedicated frontend theme.

## Also Included

- `xampp-cinema/`
  - Standalone PHP + MySQL customer booking app for XAMPP
  - Uses phpMyAdmin to create/import the database
  - Setup guide: `xampp-cinema/README-XAMPP.md`

## Included

- `wp-content/plugins/cinema-booking`
  - CPTs: `movie`, `cinema`, `room`, `showtime`, `booking`
  - Custom tables: `wp_cinema_seats`, `wp_cinema_seat_bookings`, `wp_cinema_payments`
  - Admin dashboard, timeline grid, revenue reports
  - REST API for showtimes, seat map, seat locking, booking confirmation, payment callback
  - Seat layout sync, showtime cron, payment record creation, ticket email/PDF hooks
- `wp-content/themes/cinema-theme`
  - Homepage movie catalog
  - Movie detail with showtimes
  - Seat selection flow
  - Checkout page
  - Booking success page
  - My bookings page
  - Custom login/register page
- `docker-compose.yml`
  - Local WordPress + MySQL setup that mounts this repo's `wp-content`
  - MySQL is kept internal to Docker by default to avoid port `3306` conflicts on your machine

## Quick Start

1. Copy `.env.example` to `.env` and adjust values if needed.
2. Start the stack:

```bash
docker compose up -d
```

3. Open:

- WordPress: `http://localhost:8080`
- phpMyAdmin equivalent is not included in this scaffold.
- MySQL is not exposed to the host by default. WordPress still connects to it internally through Docker.

4. Complete WordPress installation in the browser.
5. Activate plugin `Cinema Booking System`.
6. Activate theme `Cinema Theme`.
7. Create these pages in WordPress admin:

- `seat-selection`
- `checkout`
- `booking-success`
- `my-bookings`
- `auth`

8. Assign templates where needed if WordPress does not auto-pick them from the slug.

## Core Flow

1. Add movies, cinemas, rooms, and showtimes from WP Admin.
2. Configure room row and column counts to auto-generate seats.
3. Use room-level fields `VIP Rows`, `Couple Rows`, `VIP Seats`, `Couple Seats`, and `Hidden / Broken Seats` to refine the layout.
3. Open a movie on the frontend and choose a showtime.
4. Lock seats through `POST /wp-json/cinema/v1/lock-seats`.
5. Confirm the booking through `POST /wp-json/cinema/v1/confirm-booking`.
6. Payment records are stored in `wp_cinema_payments`.
7. Ticket email and PDF export use `Cinema_Booking_Ticket_Generator`.

## Payment Callback Security

Payment callbacks are intentionally blocked until you validate the gateway signature.

Add a filter like this in a mu-plugin or project plugin:

```php
add_filter('cinema_booking_validate_payment_callback', function ($is_valid, $payload) {
	$expected = hash_hmac('sha256', wp_json_encode($payload), 'your-shared-secret');
	return isset($payload['signature']) && hash_equals($expected, $payload['signature']);
}, 10, 2);
```

Then extend `Cinema_Booking_Payment_Handler` or hook your VNPay or MoMo integration into the payment flow.

## Important Notes

- PDF export only works when `TCPDF` or `FPDF` is installed in the WordPress runtime.
- If `endroid/qr-code` is available in the WordPress runtime, ticket emails render a real QR. Otherwise the system falls back to a local visual reference code block.
- Online gateway redirect URLs are placeholders until VNPay or MoMo is wired in.
- The host shell may not have `php` CLI, but syntax checks can be run inside the WordPress container with `docker compose exec -T wordpress php -l`.
- This scaffold assumes standard WordPress paths, especially for `wp-content/plugins/cinema-booking`.

## Suggested Next Steps

- Wire a real gateway service for VNPay or MoMo.
- Add WooCommerce order sync if you want native order management.
- Add webhook signature verification specific to the selected gateway.
- Add test coverage with a WordPress local runtime.
- Vendor PDF and QR libraries through Composer inside the plugin.
