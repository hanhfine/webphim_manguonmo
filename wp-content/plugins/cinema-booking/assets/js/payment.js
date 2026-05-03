(function () {
	const form = document.querySelector('[data-cinema-checkout-form]');

	if (!form || typeof window.fetch !== 'function') {
		return;
	}

	form.addEventListener('submit', async (event) => {
		event.preventDefault();

		const bookingPayload = JSON.parse(window.sessionStorage.getItem('cinema_booking_checkout') || '{}');
		const paymentMethod = form.querySelector('[name="payment_method"]')?.value || 'cash';

		const response = await fetch(`${window.cinemaBooking.restUrl}confirm-booking`, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.cinemaBooking.restNonce
			},
			body: JSON.stringify({
				showtime_id: bookingPayload.showtime_id,
				seat_ids: bookingPayload.seat_ids || [],
				payment_method: paymentMethod
			})
		});

		const payload = await response.json();

		if (!response.ok) {
			window.alert(payload.message || 'Khong the xac nhan dat ve.');
			return;
		}

		window.sessionStorage.removeItem('cinema_booking_checkout');

		if (payload.payment?.checkout_url && paymentMethod !== 'cash') {
			window.location.href = payload.payment.checkout_url;
			return;
		}

		if (window.cinemaBooking.successUrl) {
			window.location.href = `${window.cinemaBooking.successUrl}?booking=${payload.booking.booking_id}`;
		}
	});
})();
