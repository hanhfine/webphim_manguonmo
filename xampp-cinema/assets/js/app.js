document.addEventListener('DOMContentLoaded', () => {
	document.body.classList.add('js-ready');

	const showToast = (message, type = 'error') => {
		document.querySelectorAll('.toast').forEach((toastNode) => toastNode.remove());

		const toast = document.createElement('div');
		toast.className = `toast is-${type}`;
		toast.textContent = message;
		toast.setAttribute('role', 'status');
		toast.setAttribute('aria-live', 'polite');

		document.body.appendChild(toast);

		requestAnimationFrame(() => {
			toast.classList.add('is-visible');
		});

		window.setTimeout(() => {
			toast.classList.remove('is-visible');
			window.setTimeout(() => toast.remove(), 300);
		}, 3000);
	};

	const navToggle = document.querySelector('[data-nav-toggle]');
	const siteNav = document.querySelector('[data-site-nav]');

	if (navToggle instanceof HTMLButtonElement && siteNav instanceof HTMLElement) {
		const setNavOpen = (isOpen) => {
			navToggle.setAttribute('aria-expanded', String(isOpen));
			navToggle.setAttribute('aria-label', isOpen ? 'Đóng menu điều hướng' : 'Mở menu điều hướng');
			siteNav.classList.toggle('is-open', isOpen);
		};

		setNavOpen(false);

		navToggle.addEventListener('click', () => {
			const isOpen = navToggle.getAttribute('aria-expanded') === 'true';
			setNavOpen(!isOpen);
		});

		siteNav.querySelectorAll('a').forEach((link) => {
			link.addEventListener('click', () => {
				if (window.matchMedia('(max-width: 760px)').matches) {
					setNavOpen(false);
				}
			});
		});

		window.addEventListener('resize', () => {
			if (window.innerWidth > 760) {
				setNavOpen(false);
			}
		});
	}

	const bookingForm = document.querySelector('[data-booking-form]');

	if (!bookingForm) {
		return;
	}

	const seatButtons = Array.from(document.querySelectorAll('.seat-button[data-seat-id]'));
	const seatInput = bookingForm.querySelector('[data-seat-input]');
	const labelsNode = bookingForm.querySelector('[data-seat-labels]');
	const totalNode = bookingForm.querySelector('[data-seat-total]');
	const selectedIds = new Set(
		String(seatInput?.value || '')
			.split(',')
			.map((value) => Number(value.trim()))
			.filter((value) => Number.isInteger(value) && value > 0)
	);

	const formatCurrency = (value) =>
		`${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(value)} VND`;

	const renderSelection = () => {
		const selectedSeats = [];
		let total = 0;

		seatButtons.forEach((button) => {
			const seatId = Number(button.dataset.seatId || 0);
			const isSelected = selectedIds.has(seatId);

			button.classList.toggle('is-selected', isSelected);
			button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

			if (!isSelected) {
				return;
			}

			selectedSeats.push(button.dataset.seatLabel || '');
			total += Number(button.dataset.seatPrice || 0);
		});

		if (seatInput) {
			seatInput.value = Array.from(selectedIds).join(',');
		}

		if (labelsNode) {
			labelsNode.textContent = selectedSeats.length ? selectedSeats.join(', ') : 'Chưa chọn';
		}

		if (totalNode) {
			totalNode.textContent = formatCurrency(total);
		}
	};

	seatButtons.forEach((button) => {
		if (button.disabled) {
			return;
		}

		button.addEventListener('click', () => {
			const seatId = Number(button.dataset.seatId || 0);

			if (!seatId) {
				return;
			}

			if (selectedIds.has(seatId)) {
				selectedIds.delete(seatId);
			} else {
				selectedIds.add(seatId);
			}

			renderSelection();
		});
	});

	bookingForm.addEventListener('submit', (event) => {
		if (selectedIds.size > 0) {
			return;
		}

		event.preventDefault();
		showToast('Vui lòng chọn ít nhất một ghế trước khi đặt vé.');
	});

	renderSelection();
});
