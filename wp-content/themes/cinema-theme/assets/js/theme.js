document.addEventListener('DOMContentLoaded', () => {
	document.body.classList.add('js-ready');

	const showToast = (message, type = 'error') => {
		document.querySelectorAll('.toast').forEach((node) => node.remove());

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

	window.cinemaShowToast = showToast;

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

	const countdownBlocks = Array.from(document.querySelectorAll('[data-lock-countdown]'));

	if (countdownBlocks.length) {
		const parseNumber = (value, fallback = 0) => {
			const parsed = Number(value);
			return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
		};

		const formatCountdown = (seconds) => {
			const safeSeconds = Math.max(0, Math.floor(seconds));
			const minutes = String(Math.floor(safeSeconds / 60)).padStart(2, '0');
			const remainSeconds = String(safeSeconds % 60).padStart(2, '0');

			return `${minutes}:${remainSeconds}`;
		};

		let lockExpired = false;

		const getExpiresAt = () => {
			const source = countdownBlocks[0];
			const hiddenField = document.querySelector('[data-lock-expires-at]');
			return parseNumber(
				hiddenField?.value || source?.dataset.lockExpiresAt || source?.getAttribute('data-lock-expires-at'),
				0
			);
		};

		const lockExpiresAt = getExpiresAt();
		const lockDurationSeconds = parseNumber(
			document.querySelector('[data-lock-total-seconds]')?.value || countdownBlocks[0]?.dataset.lockTotalSeconds || countdownBlocks[0]?.getAttribute('data-lock-total-seconds'),
			600
		);
		const serverTimeInput = document.querySelector('[data-server-time]');
		const serverTimeOffset = serverTimeInput ? (parseNumber(serverTimeInput.value, Math.floor(Date.now() / 1000)) * 1000) - Date.now() : 0;
		const seatButtons = () => Array.from(document.querySelectorAll('.cinema-seat[data-seat-id], .seat-button[data-seat-id]'));
		const actionButtons = () => Array.from(document.querySelectorAll('[data-seat-lock], [data-booking-form] button[type="submit"]'));
		const countdownValueNodes = Array.from(document.querySelectorAll('[data-lock-countdown-value]'));
		const countdownMessageNodes = Array.from(document.querySelectorAll('[data-lock-countdown-message]'));
		const progressNodes = Array.from(document.querySelectorAll('[data-lock-progress]'));
		const timerBlocks = countdownBlocks;

		const updateCountdown = () => {
			if (!lockExpiresAt) {
				return;
			}

			const now = Date.now() + serverTimeOffset;
			const remaining = Math.max(0, Math.floor((lockExpiresAt * 1000 - now) / 1000));
			const percent = lockDurationSeconds > 0 ? Math.max(0, Math.min(100, (remaining / lockDurationSeconds) * 100)) : 0;
			const isUrgent = remaining > 0 && remaining <= 60;
			const isExpired = 0 === remaining;
			const message = isExpired
				? 'Phiên giữ ghế đã hết hạn. Vui lòng tải lại trang.'
				: isUrgent
					? `Còn ${formatCountdown(remaining)} để hoàn tất đặt vé.`
					: 'Phiên giữ ghế đang chạy. Hãy hoàn tất đặt vé trong 10 phút.';

			timerBlocks.forEach((block) => {
				block.classList.toggle('is-urgent', isUrgent);
				block.classList.toggle('is-expired', isExpired);
			});

			countdownValueNodes.forEach((node) => {
				node.textContent = formatCountdown(remaining);
			});

			countdownMessageNodes.forEach((node) => {
				node.textContent = message;
			});

			progressNodes.forEach((node) => {
				node.style.width = `${percent}%`;
				const progressBar = node.parentElement;

				if (progressBar) {
					progressBar.setAttribute('aria-valuenow', String(Math.round(percent)));
					progressBar.setAttribute('aria-valuetext', `${formatCountdown(remaining)} còn lại`);
				}
			});

			if (isExpired && !lockExpired) {
				lockExpired = true;

				seatButtons().forEach((button) => {
					button.disabled = true;
				});

				actionButtons().forEach((button) => {
					button.disabled = true;
				});

				showToast('Phiên giữ ghế đã hết hạn. Vui lòng tải lại trang.');
			}
		};

		const lockButton = document.querySelector('[data-seat-lock]');

		if (lockButton instanceof HTMLButtonElement) {
			lockButton.addEventListener(
				'click',
				(event) => {
					if (lockExpired) {
						event.preventDefault();
						event.stopImmediatePropagation();
						showToast('Phiên giữ ghế đã hết hạn. Vui lòng tải lại trang.');
					}
				},
				true
			);
		}

		updateCountdown();
		window.setInterval(updateCountdown, 1000);
	}
});
