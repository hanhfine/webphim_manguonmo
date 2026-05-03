document.addEventListener('DOMContentLoaded', () => {
	const checkoutShell = document.querySelector('[data-cinema-checkout-shell]');

	if (checkoutShell) {
		const payload = JSON.parse(window.sessionStorage.getItem('cinema_booking_checkout') || '{}');
		const showtimeNode = checkoutShell.querySelector('[data-checkout-showtime]');
		const seatsNode = checkoutShell.querySelector('[data-checkout-seats]');

		if (showtimeNode) {
			showtimeNode.textContent = payload.showtime_id || '-';
		}

		if (seatsNode) {
			seatsNode.textContent = Array.isArray(payload.seat_ids) && payload.seat_ids.length ? payload.seat_ids.join(', ') : '-';
		}
	}
});
