document.addEventListener('DOMContentLoaded', () => {
	const chartNode = document.querySelector('#cinema-booking-chart');

	if (chartNode && typeof window.Chart !== 'undefined') {
		const payload = JSON.parse(chartNode.dataset.chart || '{}');

		new window.Chart(chartNode, {
			type: 'bar',
			data: {
				labels: payload.labels || [],
				datasets: [
					{
						label: 'Bookings',
						data: payload.values || [],
						backgroundColor: '#c49a27',
						borderRadius: 12
					}
				]
			},
			options: {
				responsive: true,
				plugins: {
					legend: {
						display: false
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0
						}
					}
				}
			}
		});
	}

	const seatBuilder = document.querySelector('[data-cinema-seat-builder]');

	if (!seatBuilder) {
		return;
	}

	const gridNode = seatBuilder.querySelector('[data-seat-builder-grid]');
	const summaryNode = seatBuilder.querySelector('[data-seat-builder-summary]');
	const rowInput = document.querySelector('#cinema_room_total_rows');
	const columnInput = document.querySelector('#cinema_room_total_columns');
	const vipRowsInput = document.querySelector('#cinema_room_vip_rows');
	const coupleRowsInput = document.querySelector('#cinema_room_couple_rows');
	const vipSeatsInput = document.querySelector('#cinema_room_vip_seats');
	const coupleSeatsInput = document.querySelector('#cinema_room_couple_seats');
	const inactiveSeatsInput = document.querySelector('#cinema_room_inactive_seats');

	if (!gridNode || !summaryNode || !rowInput || !columnInput) {
		return;
	}

	const parseTokens = (value) =>
		String(value || '')
			.toUpperCase()
			.split(/[\s,]+/)
			.map((token) => token.trim())
			.filter(Boolean);

	const indexToRowLabel = (index) => {
		let current = Number(index);
		let label = '';

		do {
			label = String.fromCharCode(65 + (current % 26)) + label;
			current = Math.floor(current / 26) - 1;
		} while (current >= 0);

		return label;
	};

	const renderSeatBuilder = () => {
		const rows = Math.max(1, Number(rowInput.value || 0));
		const columns = Math.max(1, Number(columnInput.value || 0));
		const vipRows = parseTokens(vipRowsInput?.value);
		const coupleRows = parseTokens(coupleRowsInput?.value);
		const vipSeats = parseTokens(vipSeatsInput?.value);
		const coupleSeats = parseTokens(coupleSeatsInput?.value);
		const inactiveSeats = parseTokens(inactiveSeatsInput?.value);
		let activeCount = 0;
		let inactiveCount = 0;

		gridNode.innerHTML = '';

		for (let rowIndex = 0; rowIndex < rows; rowIndex += 1) {
			const rowLabel = indexToRowLabel(rowIndex);
			const rowNumber = String(rowIndex + 1);
			const rowNode = document.createElement('div');
			const labelNode = document.createElement('span');
			const seatsNode = document.createElement('div');

			rowNode.className = 'cinema-seat-builder-row';
			labelNode.className = 'cinema-seat-builder-row-label';
			labelNode.textContent = rowLabel;
			seatsNode.className = 'cinema-seat-builder-row-grid';

			for (let columnIndex = 1; columnIndex <= columns; columnIndex += 1) {
				const seatCode = `${rowLabel}${columnIndex}`;
				const seatNode = document.createElement('span');
				const isInactive = inactiveSeats.includes(seatCode);
				const isCouple = coupleSeats.includes(seatCode) || coupleRows.includes(rowLabel) || coupleRows.includes(rowNumber);
				const isVip = vipSeats.includes(seatCode) || vipRows.includes(rowLabel) || vipRows.includes(rowNumber);
				let variant = 'is-normal';

				if (isInactive) {
					variant = 'is-inactive';
					inactiveCount += 1;
				} else if (isCouple) {
					variant = 'is-couple';
					activeCount += 1;
				} else if (isVip) {
					variant = 'is-vip';
					activeCount += 1;
				} else {
					activeCount += 1;
				}

				seatNode.className = `cinema-seat-builder-seat ${variant}`;
				seatNode.textContent = seatCode;
				seatsNode.appendChild(seatNode);
			}

			rowNode.appendChild(labelNode);
			rowNode.appendChild(seatsNode);
			gridNode.appendChild(rowNode);
		}

		summaryNode.textContent = `${activeCount} active seats, ${inactiveCount} inactive seats`;
	};

	[
		rowInput,
		columnInput,
		vipRowsInput,
		coupleRowsInput,
		vipSeatsInput,
		coupleSeatsInput,
		inactiveSeatsInput
	].forEach((input) => {
		if (!input) {
			return;
		}

		input.addEventListener('input', renderSeatBuilder);
		input.addEventListener('change', renderSeatBuilder);
	});

	renderSeatBuilder();
});
