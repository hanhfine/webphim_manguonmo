(function () {
	const app = document.querySelector('[data-cinema-seat-app]');
	const notify = (message) => {
		if (typeof window.cinemaShowToast === 'function') {
			window.cinemaShowToast(message);
			return;
		}

		window.alert(message);
	};

	if (!app || typeof window.fetch !== 'function') {
		return;
	}

	const showtimeId = Number(app.dataset.showtimeId || 0);
	const summaryNode = document.querySelector('[data-seat-summary]');
	const totalNode = document.querySelector('[data-seat-total]');
	const actionNode = document.querySelector('[data-seat-lock]');
	const selectedSeats = new Map();

	const renderSummary = () => {
		if (summaryNode) {
			summaryNode.textContent = Array.from(selectedSeats.values()).join(', ') || 'Chua chon ghe';
		}

		if (totalNode) {
			totalNode.textContent = String(selectedSeats.size);
		}
	};

	const renderSeatMap = (seatMap) => {
		app.innerHTML = '';

		Object.entries(seatMap || {}).forEach(([rowLabel, seats]) => {
			const row = document.createElement('div');
			row.className = 'cinema-seat-row';
			row.innerHTML = `<span class="cinema-seat-row-label">${rowLabel}</span><div class="cinema-seat-row-grid"></div>`;
			const grid = row.querySelector('.cinema-seat-row-grid');

			seats.forEach((seat) => {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = `cinema-seat is-${seat.seat_type}`;
				button.dataset.seatId = seat.id;
				button.dataset.seatLabel = seat.label;
				button.textContent = seat.label;

				if (seat.status !== 'available' && seat.status !== 'selected') {
					button.classList.add(`is-${seat.status}`);
					button.disabled = true;
				}

				if (seat.status === 'selected') {
					button.classList.add('is-selected');
					selectedSeats.set(String(seat.id), seat.label);
				}

				button.addEventListener('click', () => {
					const key = String(seat.id);

					if (selectedSeats.has(key)) {
						selectedSeats.delete(key);
						button.classList.remove('is-selected');
					} else {
						selectedSeats.set(key, seat.label);
						button.classList.add('is-selected');
					}

					renderSummary();
				});

				grid.appendChild(button);
			});

			app.appendChild(row);
		});

		renderSummary();
	};

	const loadSeats = async () => {
		const response = await fetch(`${window.cinemaBooking.restUrl}showtimes/${showtimeId}/seats`, {
			credentials: 'same-origin'
		});
		const payload = await response.json();
		renderSeatMap(payload.seat_map || {});
	};

	const lockSeats = async () => {
		if (!selectedSeats.size) {
			notify('Vui lòng chọn ít nhất một ghế.');
			return;
		}

		const response = await fetch(`${window.cinemaBooking.restUrl}lock-seats`, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.cinemaBooking.restNonce
			},
			body: JSON.stringify({
				showtime_id: showtimeId,
				seat_ids: Array.from(selectedSeats.keys()).map(Number)
			})
		});

		const payload = await response.json();

		if (!response.ok) {
			notify(payload.message || 'Không thể giữ ghế lúc này.');
			return;
		}

		window.sessionStorage.setItem(
			'cinema_booking_checkout',
			JSON.stringify({
				showtime_id: showtimeId,
				seat_ids: Array.from(selectedSeats.keys()).map(Number)
			})
		);

		if (window.cinemaBooking.checkoutUrl) {
			window.location.href = window.cinemaBooking.checkoutUrl;
		}
	};

	if (actionNode) {
		actionNode.addEventListener('click', lockSeats);
	}

	loadSeats().catch(() => {
		app.innerHTML = '<p>Khong tai duoc so do ghe.</p>';
	});
})();
