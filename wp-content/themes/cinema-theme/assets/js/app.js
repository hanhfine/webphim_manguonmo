document.addEventListener('DOMContentLoaded', () => {
	document.body.classList.add('js-ready');

	const checkoutStorageKey = 'cinema_theme_checkout';
	const restConfig = window.cinemaBooking || {};

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

	const parseErrorMessage = async (response) => {
		try {
			const data = await response.json();
			if (data && typeof data.message === 'string' && data.message.trim()) {
				return data.message;
			}
			if (data && typeof data.code === 'string' && data.code.trim()) {
				return data.code;
			}
		} catch (error) {
			// Ignore JSON parse failures and fall back to a generic message.
		}

		return 'Không thể xử lý yêu cầu lúc này.';
	};

	const formatCurrency = (value) =>
		`${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(Number(value || 0))} VND`;

	const readCheckoutPayload = () => {
		try {
			const rawPayload = window.sessionStorage.getItem(checkoutStorageKey);

			if (!rawPayload) {
				return null;
			}

			const payload = JSON.parse(rawPayload);

			return payload && typeof payload === 'object' ? payload : null;
		} catch (error) {
			return null;
		}
	};

	const writeCheckoutPayload = (payload) => {
		window.sessionStorage.setItem(checkoutStorageKey, JSON.stringify(payload));
	};

	const clearCheckoutPayload = () => {
		window.sessionStorage.removeItem(checkoutStorageKey);
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

	const checkoutShell = document.querySelector('[data-cinema-checkout-shell]');
	const checkoutForm = document.querySelector('[data-cinema-checkout-form]');

	if (checkoutShell instanceof HTMLElement) {
		const payload = readCheckoutPayload();
		const showtimeNode = checkoutShell.querySelector('[data-checkout-showtime]');
		const seatsNode = checkoutShell.querySelector('[data-checkout-seats]');
		const totalNode = checkoutShell.querySelector('[data-checkout-total]');

		if (showtimeNode instanceof HTMLElement) {
			showtimeNode.textContent = payload
				? [payload.movie_title, payload.cinema_title, payload.room_title, payload.showtime_label].filter(Boolean).join(' / ') || `Suất chiếu #${payload.showtime_id || '-'}`
				: 'Chưa có thông tin giữ ghế';
		}

		if (seatsNode instanceof HTMLElement) {
			seatsNode.textContent = payload && Array.isArray(payload.seat_labels) && payload.seat_labels.length > 0
				? payload.seat_labels.join(', ')
				: 'Chưa chọn';
		}

		if (totalNode instanceof HTMLElement) {
			totalNode.textContent = payload ? formatCurrency(payload.total_amount || 0) : '-';
		}

		if (checkoutForm instanceof HTMLFormElement) {
			const submitButton = checkoutForm.querySelector('button[type="submit"]');

			if (!payload || !payload.showtime_id || !Array.isArray(payload.seat_ids) || 0 === payload.seat_ids.length) {
				if (submitButton instanceof HTMLButtonElement) {
					submitButton.disabled = true;
				}
			}

			checkoutForm.addEventListener('submit', async (event) => {
				event.preventDefault();

				const currentPayload = readCheckoutPayload();
				const paymentMethodField = checkoutForm.querySelector('[name="payment_method"]');
				const paymentMethod = paymentMethodField instanceof HTMLSelectElement ? paymentMethodField.value : 'cash';

				if (!currentPayload || !currentPayload.showtime_id || !Array.isArray(currentPayload.seat_ids) || 0 === currentPayload.seat_ids.length) {
					showToast('Phiên giữ ghế không còn hiệu lực. Vui lòng quay lại chọn ghế.');
					return;
				}

				if (!restConfig.restUrl || !restConfig.restNonce) {
					showToast('Thiếu cấu hình kết nối đặt vé.');
					return;
				}

				if (submitButton instanceof HTMLButtonElement) {
					submitButton.disabled = true;
				}

				try {
					const response = await fetch(`${restConfig.restUrl}confirm-booking`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': restConfig.restNonce,
						},
						credentials: 'same-origin',
						body: JSON.stringify({
							showtime_id: currentPayload.showtime_id,
							seat_ids: currentPayload.seat_ids,
							payment_method: paymentMethod,
						}),
					});

					if (!response.ok) {
						throw new Error(await parseErrorMessage(response));
					}

					const data = await response.json();
					const bookingCode = String(data?.booking?.booking_code || '');
					const checkoutUrl = String(data?.payment?.checkout_url || '');

					if (!bookingCode) {
						throw new Error('Không thể tạo mã đặt vé.');
					}

					clearCheckoutPayload();

					if (checkoutUrl && 'cash' !== paymentMethod) {
						window.location.assign(checkoutUrl);
						return;
					}

					const successUrl = new URL(restConfig.successUrl || window.location.href, window.location.origin);
					successUrl.searchParams.set('code', bookingCode);
					window.location.assign(successUrl.toString());
				} catch (error) {
					showToast(error instanceof Error ? error.message : 'Không thể hoàn tất đặt vé.');
					if (submitButton instanceof HTMLButtonElement) {
						submitButton.disabled = false;
					}
				}
			});
		}
	}

	const bookingForm = document.querySelector('[data-booking-form]');

	if (!bookingForm) {
		return;
	}

	const seatButtons = Array.from(document.querySelectorAll('.seat-button[data-seat-id]'));
	const seatInput = bookingForm.querySelector('[data-seat-input]');
	const labelsNode = bookingForm.querySelector('[data-seat-labels]');
	const totalNode = bookingForm.querySelector('[data-seat-total]');
	const submitButton = bookingForm.querySelector('button[type="submit"]');
	const csrfToken = String(bookingForm.querySelector('input[name="csrf_token"]')?.value || '');
	const showtimeId = Number(bookingForm.querySelector('input[name="showtime_id"]')?.value || 0);
	const seatLockEndpoint = String(bookingForm.querySelector('[data-seat-lock-endpoint]')?.value || '');
	const lockExpiresInput = bookingForm.querySelector('[data-lock-expires-at]');
	const lockDurationInput = bookingForm.querySelector('[data-lock-total-seconds]');
	const countdownNodes = Array.from(document.querySelectorAll('[data-lock-countdown-value]'));
	const countdownMessageNodes = Array.from(document.querySelectorAll('[data-lock-countdown-message]'));
	const progressNodes = Array.from(document.querySelectorAll('[data-lock-progress]'));
	const timerBlocks = Array.from(document.querySelectorAll('[data-lock-countdown]'));
	const selectedIds = new Set(
		String(seatInput?.value || '')
			.split(',')
			.map((value) => Number(value.trim()))
			.filter((value) => Number.isInteger(value) && value > 0)
	);
	let lockExpiresAt = Number(lockExpiresInput?.value || 0);
	const serverTimeInput = bookingForm.querySelector('[data-server-time]');
	const serverTimeOffset = serverTimeInput ? Number(serverTimeInput.value) * 1000 - Date.now() : 0;
	let lockDurationSeconds = Math.max(1, Number(lockDurationInput?.value || 600));
	let lockExpired = false;
	const pendingSeatIds = new Set();

	const getSelectionSnapshot = () => {
		const selectedSeatButtons = seatButtons.filter((button) => {
			const seatId = Number(button.dataset.seatId || 0);
			return selectedIds.has(seatId);
		});

		const seatLabels = selectedSeatButtons
			.map((button) => String(button.dataset.seatLabel || ''))
			.filter(Boolean);

		const totalAmount = selectedSeatButtons.reduce(
			(total, button) => total + Number(button.dataset.seatPrice || 0),
			0
		);

		return {
			seatIds: Array.from(selectedIds),
			seatLabels,
			totalAmount,
		};
	};

	const formatCountdown = (seconds) => {
		const safeSeconds = Math.max(0, Math.floor(seconds));
		const minutes = String(Math.floor(safeSeconds / 60)).padStart(2, '0');
		const remainSeconds = String(safeSeconds % 60).padStart(2, '0');

		return `${minutes}:${remainSeconds}`;
	};

	const syncSelectionToIds = (seatIds) => {
		selectedIds.clear();

		(seatIds || []).forEach((seatId) => {
			const numericId = Number(seatId);

			if (Number.isInteger(numericId) && numericId > 0) {
				selectedIds.add(numericId);
			}
		});

		renderSelection();
	};

	const renderSelection = () => {
		const snapshot = getSelectionSnapshot();

		seatButtons.forEach((button) => {
			const seatId = Number(button.dataset.seatId || 0);
			const isSelected = selectedIds.has(seatId);

			button.classList.toggle('is-selected', isSelected);
			button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
		});

		if (seatInput) {
			seatInput.value = snapshot.seatIds.join(',');
		}

		if (labelsNode) {
			labelsNode.textContent = snapshot.seatLabels.length ? snapshot.seatLabels.join(', ') : 'Chưa chọn';
		}

		if (totalNode) {
			totalNode.textContent = formatCurrency(snapshot.totalAmount);
		}

		if (submitButton instanceof HTMLButtonElement) {
			submitButton.disabled = lockExpired || 0 === snapshot.seatIds.length;
		}
	};

	const releaseAllSeats = () => {
		if (!seatLockEndpoint || showtimeId <= 0) {
			return Promise.resolve();
		}

		const releasePayload = new URLSearchParams({
			action: 'release-all',
			showtime_id: String(showtimeId),
			csrf_token: csrfToken,
		});

		return fetch(seatLockEndpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
				'X-WP-Nonce': csrfToken,
			},
			credentials: 'same-origin',
			body: releasePayload.toString(),
		}).catch(() => null);
	};

	const updateCountdown = () => {
		if (!lockExpiresAt) {
			if (submitButton instanceof HTMLButtonElement) {
				submitButton.disabled = 0 === selectedIds.size;
			}
			return;
		}

		const now = Date.now() + serverTimeOffset;
		const remaining = Math.max(0, Math.floor((lockExpiresAt * 1000 - now) / 1000));
		const progress = lockDurationSeconds > 0 ? Math.max(0, Math.min(100, (remaining / lockDurationSeconds) * 100)) : 0;
		const isUrgent = remaining > 0 && remaining <= 60;
		const countdownMessage = remaining > 0
			? (isUrgent
				? `Còn ${formatCountdown(remaining)} để hoàn tất đặt vé.`
				: 'Phiên giữ ghế đang chạy. Hãy chọn ghế và xác nhận trong 10 phút.')
			: 'Phiên giữ ghế đã hết hạn. Vui lòng tải lại trang.';

		timerBlocks.forEach((block) => {
			block.classList.toggle('is-urgent', isUrgent);
			block.classList.toggle('is-expired', 0 === remaining);
		});

		countdownNodes.forEach((node) => {
			node.textContent = formatCountdown(remaining);
		});

		progressNodes.forEach((node) => {
			node.style.width = `${progress}%`;
			const progressBar = node.parentElement;

			if (progressBar) {
				progressBar.setAttribute('aria-valuenow', String(Math.round(progress)));
				progressBar.setAttribute('aria-valuetext', `${formatCountdown(remaining)} còn lại`);
			}
		});

		countdownMessageNodes.forEach((node) => {
			node.textContent = countdownMessage;
		});

		if (0 === remaining && !lockExpired) {
			lockExpired = true;

			seatButtons.forEach((button) => {
				if (!button.disabled) {
					button.disabled = true;
				}
			});

			if (submitButton instanceof HTMLButtonElement) {
				submitButton.disabled = true;
			}

			showToast('Phiên giữ ghế đã hết hạn. Vui lòng tải lại trang.');
			selectedIds.clear();
			renderSelection();
			clearCheckoutPayload();
			releaseAllSeats();
		}
	};

	updateCountdown();
	window.setInterval(updateCountdown, 1000);

	const updateLockStateFromResponse = (response) => {
		if (!response || typeof response !== 'object') {
			return;
		}

		if (Array.isArray(response.locked_seat_ids)) {
			syncSelectionToIds(response.locked_seat_ids);
		}

		if (Number.isInteger(Number(response.lock_expires_at))) {
			lockExpiresAt = Math.max(0, Number(response.lock_expires_at));
			lockExpired = false;
			updateCountdown();
		}
	};

	const sendSeatAction = async (action, seatId) => {
		if (!seatLockEndpoint || !showtimeId) {
			throw new Error('Thiếu cấu hình giữ ghế.');
		}

		const payload = new URLSearchParams({
			action,
			showtime_id: String(showtimeId),
			seat_id: String(seatId),
			csrf_token: csrfToken,
		});

		const response = await fetch(seatLockEndpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
				'X-Requested-With': 'fetch',
				'X-WP-Nonce': csrfToken,
			},
			credentials: 'same-origin',
			body: payload.toString(),
		});

		if (!response.ok) {
			throw new Error(await parseErrorMessage(response));
		}

		const data = await response.json();

		if (!data.success) {
			throw new Error(data.message || 'Không thể cập nhật giữ ghế.');
		}

		updateLockStateFromResponse(data);
		return data;
	};

	seatButtons.forEach((button) => {
		if (button.disabled) {
			return;
		}

		const seatId = Number(button.dataset.seatId || 0);
		const seatLockedOwner = String(button.dataset.seatLocked || 'none');

		if (seatLockedOwner === 'self' && seatId > 0) {
			selectedIds.add(seatId);
		}

		button.addEventListener('click', async () => {
			if (!seatId || lockExpired) {
				return;
			}

			if (pendingSeatIds.has(seatId)) {
				return;
			}

			pendingSeatIds.add(seatId);
			button.classList.add('is-loading');

			try {
				const action = selectedIds.has(seatId) ? 'unlock' : 'lock';
				const data = await sendSeatAction(action, seatId);

				if (Array.isArray(data.locked_seat_ids)) {
					syncSelectionToIds(data.locked_seat_ids);
				} else if ('lock' === action) {
					selectedIds.add(seatId);
					renderSelection();
				} else {
					selectedIds.delete(seatId);
					renderSelection();
				}
			} catch (error) {
				showToast(error instanceof Error ? error.message : 'Không thể cập nhật ghế.');
			}

			pendingSeatIds.delete(seatId);
			button.classList.remove('is-loading');
			updateCountdown();
		});
	});

	bookingForm.addEventListener('submit', (event) => {
		if (lockExpired || (lockExpiresAt && Date.now() + serverTimeOffset >= lockExpiresAt * 1000)) {
			event.preventDefault();
			showToast('Phiên giữ ghế đã hết hạn. Vui lòng tải lại trang.');
			return;
		}

		if (0 === selectedIds.size) {
			event.preventDefault();
			showToast('Vui lòng chọn ít nhất một ghế trước khi đặt vé.');
			return;
		}

		event.preventDefault();

		const snapshot = getSelectionSnapshot();
		const movieTitle = String(bookingForm.querySelector('[data-checkout-movie-title]')?.value || '');
		const cinemaTitle = String(bookingForm.querySelector('[data-checkout-cinema-title]')?.value || '');
		const roomTitle = String(bookingForm.querySelector('[data-checkout-room-title]')?.value || '');
		const showtimeLabel = String(bookingForm.querySelector('[data-checkout-showtime-label]')?.value || '');

		writeCheckoutPayload({
			showtime_id: showtimeId,
			seat_ids: snapshot.seatIds,
			seat_labels: snapshot.seatLabels,
			total_amount: snapshot.totalAmount,
			movie_title: movieTitle,
			cinema_title: cinemaTitle,
			room_title: roomTitle,
			showtime_label: showtimeLabel,
		});

		window.location.assign(restConfig.checkoutUrl || bookingForm.getAttribute('action') || window.location.href);
	});

	renderSelection();
});
