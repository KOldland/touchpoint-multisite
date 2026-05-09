/**
 * Connect Subscription Modal
 *
 * Depends on: window.khmSubData (injected by QuoteClubPortalShortcode.php)
 * Shape: {
 *   nonce, apiBase, sites[], portfolio, hasPortfolio,
 *   prices: {site, portfolio},
 *   upgradeCreditPence, upgradeCostPence, logoBase
 * }
 */
(function () {
	'use strict';

	/* ── Guard ───────────────────────────────────────────────────────────── */
	var data = window.khmSubData;
	if (!data) return;

	/* ── DOM refs ────────────────────────────────────────────────────────── */
	var modal        = document.getElementById('khm-sub-modal');
	var trigger      = document.getElementById('khm-sub-modal-trigger');
	var backdrop     = modal && modal.querySelector('.khm-sub-modal-backdrop');
	var closeBtn     = modal && modal.querySelector('.khm-sub-modal-close');
	var grid         = modal && modal.querySelector('.khm-sub-sites-grid');
	var cartBar      = modal && modal.querySelector('.khm-sub-cart-bar');
	var cartLabel    = modal && modal.querySelector('.khm-sub-cart-label');
	var cartPrice    = modal && modal.querySelector('.khm-sub-cart-price');
	var cartConfirm  = modal && modal.querySelector('.khm-sub-cart-confirm');
	var upgBanner    = modal && modal.querySelector('.khm-sub-upgrade-banner');
	var upgDesc      = modal && modal.querySelector('.khm-sub-upgrade-desc');
	var upgCreditNote= modal && modal.querySelector('.khm-sub-upgrade-credit-note');
	var upgNetPrice  = modal && modal.querySelector('.khm-sub-upgrade-net-price');
	var upgBtn       = modal && modal.querySelector('.khm-sub-upgrade-btn');
	var notice       = modal && modal.querySelector('.khm-sub-modal-notice');

	if (!modal || !trigger || !grid) return;

	/* ── State ───────────────────────────────────────────────────────────── */
	var selected = {}; // { [slug]: true } — unconnected sites checked by user

	/* ── Open / Close ────────────────────────────────────────────────────── */
	function openModal() {
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
		renderGrid();
		renderUpgradeBanner();
		renderCartBar();
		trigger.setAttribute('aria-expanded', 'true');
	}

	function closeModal() {
		modal.hidden = true;
		document.body.style.overflow = '';
		trigger.setAttribute('aria-expanded', 'false');
		selected = {};
	}

	trigger.addEventListener('click', openModal);
	if (closeBtn) closeBtn.addEventListener('click', closeModal);
	if (backdrop) backdrop.addEventListener('click', closeModal);

	document.addEventListener('keydown', function (e) {
		if (!modal.hidden && e.key === 'Escape') closeModal();
	});

	/* ── Grid ─────────────────────────────────────────────────────────────── */
	function renderGrid() {
		grid.innerHTML = '';
		data.sites.forEach(function (site) {
			var card = buildSiteCard(site);
			grid.appendChild(card);
		});
	}

	function buildSiteCard(site) {
		var isConnected = site.is_connected;
		var isPending   = site.status === 'pending' || site.status === 'pending_invoice';
		var isCancelled = site.status === 'cancelled';
		var isSelected  = !!selected[site.slug];

		var card = document.createElement('div');
		card.className = 'khm-sub-site-card' +
			(isConnected ? ' is-connected' : ' is-not-connected') +
			(isPending ? ' is-pending' : '') +
			(isCancelled ? ' is-cancelled' : '') +
			(isSelected ? ' is-selected' : '');
		card.setAttribute('role', 'listitem');

		/* Logo */
		var logoWrap = document.createElement('div');
		logoWrap.className = 'khm-sub-card-logo';
		if (site.logo_url) {
			var img = document.createElement('img');
			img.src = site.logo_url;
			img.alt = site.label + ' logo';
			logoWrap.appendChild(img);
		} else {
			var fallback = document.createElement('div');
			fallback.className = 'khm-sub-card-logo-fallback';
			fallback.textContent = site.slug.slice(0, 2).toUpperCase();
			logoWrap.appendChild(fallback);
		}
		card.appendChild(logoWrap);

		/* Label */
		var labelEl = document.createElement('div');
		labelEl.className = 'khm-sub-card-label';
		labelEl.textContent = site.label;
		card.appendChild(labelEl);

		/* Status line */
		var statusEl = document.createElement('div');
		statusEl.className = 'khm-sub-card-status';
		if (data.hasPortfolio) {
			statusEl.textContent = 'Portfolio';
		} else if (site.status === 'provider_active') {
			statusEl.textContent = 'Connected';
		} else if (isPending) {
			statusEl.textContent = 'Pending payment';
		} else if (isConnected && site.renews_on) {
			statusEl.textContent = 'Renews ' + site.renews_on;
		} else if (isCancelled && site.expires_at) {
			statusEl.textContent = 'Expires ' + site.renews_on;
		} else if (isConnected) {
			statusEl.textContent = 'Connected';
		} else {
			statusEl.textContent = '';
		}
		card.appendChild(statusEl);

		/* Actions */
		if (!data.hasPortfolio) {
			if (isConnected && !isPending && site.status !== 'cancelled') {
				/* Cancel button */
				var cancelBtn = document.createElement('button');
				cancelBtn.type = 'button';
				cancelBtn.className = 'khm-sub-card-cancel';
				cancelBtn.textContent = '×';
				cancelBtn.title = 'Cancel Site Connection';
				cancelBtn.setAttribute('aria-label', 'Cancel ' + site.label + ' connection');
				cancelBtn.addEventListener('click', function (e) {
					e.stopPropagation();
					handleCancel(site.slug, site.label);
				});
				card.appendChild(cancelBtn);
			} else if (!isConnected && !isPending) {
				/* Checkbox for cart */
				var chk = document.createElement('input');
				chk.type = 'checkbox';
				chk.className = 'khm-sub-card-check';
				chk.id = 'khm-sub-chk-' + site.slug;
				chk.checked = isSelected;
				chk.setAttribute('aria-label', 'Select ' + site.label);
				chk.addEventListener('change', function () {
					if (chk.checked) {
						selected[site.slug] = true;
					} else {
						delete selected[site.slug];
					}
					renderCartBar();
					// Update card class
					if (chk.checked) {
						card.classList.add('is-selected');
					} else {
						card.classList.remove('is-selected');
					}
				});
				card.appendChild(chk);
				/* Make whole card clickable */
				card.style.cursor = 'pointer';
				card.addEventListener('click', function (e) {
					if (e.target === chk) return; // checkbox handles its own click
					chk.checked = !chk.checked;
					chk.dispatchEvent(new Event('change'));
				});
			}
		}

		return card;
	}

	/* ── Upgrade banner ───────────────────────────────────────────────────── */
	function renderUpgradeBanner() {
		if (!upgBanner) return;
		var connectedSites = data.sites.filter(function (s) { return s.is_connected; });
		var creditPence    = data.upgradeCreditPence || 0;
		var activeSubs     = data.sites.filter(function (s) { return s.status === 'active'; });

		if (data.hasPortfolio || connectedSites.length === 0 || activeSubs.length === 0 || creditPence === 0) {
			upgBanner.hidden = true;
			return;
		}
		upgBanner.hidden = false;

		var netCost = data.upgradeCostPence || data.prices.portfolio;

		// Description line
		if (upgDesc) {
			upgDesc.textContent = 'All ' + data.sites.length + ' sites — £' + (data.prices.portfolio / 100).toFixed(0) + '/yr';
		}

		// Credit note: show breakdown only when there are subscription sites with credit
		if (upgCreditNote) {
			if (activeSubs.length > 0 && creditPence > 0) {
				upgCreditNote.textContent = activeSubs.length + ' active site' + (activeSubs.length !== 1 ? 's' : '') +
					' · £' + (creditPence / 100).toFixed(0) + ' pro-rata credit applied';
			} else {
				upgCreditNote.textContent = '';
			}
		}

		// Net price
		if (upgNetPrice) {
			upgNetPrice.textContent = 'You pay: £' + (netCost / 100).toFixed(0) + '/yr';
		}
	}

	/* ── Cart bar ─────────────────────────────────────────────────────────── */
	function renderCartBar() {
		var slugs = Object.keys(selected);
		if (slugs.length === 0) {
			cartBar.hidden = true;
			return;
		}
		cartBar.hidden = false;

		var connectedCount = data.sites.filter(function (s) { return s.is_connected; }).length;
		var isPortfolio = (connectedCount + slugs.length) >= data.sites.length;
		var pricePence  = isPortfolio ? data.prices.portfolio : slugs.length * data.prices.site;
		var priceLabel  = '£' + (pricePence / 100).toFixed(0) + '/yr';

		if (isPortfolio) {
			cartLabel.textContent = 'Portfolio (all ' + data.sites.length + ' sites)';
		} else {
			var siteNames = slugs.map(function (s) {
				var found = data.sites.find(function (x) { return x.slug === s; });
				return found ? found.label : s;
			}).join(', ');
			cartLabel.textContent = slugs.length + ' site' + (slugs.length !== 1 ? 's' : '') + ' — ' + siteNames;
		}
		cartPrice.textContent = priceLabel;

		// Update button label dynamically
		if (cartConfirm) {
			cartConfirm.textContent = slugs.length === 1 ? 'Add Site' : 'Add Sites';
		}
	}

	/* ── Cart confirm ─────────────────────────────────────────────────────── */
	if (cartConfirm) {
		cartConfirm.addEventListener('click', function () {
			var slugs = Object.keys(selected);
			if (slugs.length === 0) return;
			var payment = 'stripe';
			cartConfirm.disabled = true;
			cartConfirm.textContent = 'Processing…';

			apiPost(data.apiBase + '/cart', { sites: slugs, payment: payment })
				.then(function (res) {
					if (res.payment === 'stripe' && res.checkout_url) {
						window.location.href = res.checkout_url;
					} else {
						showNotice(res.message || 'Request submitted.', true);
						selected = {};
						cartBar.hidden = true;
						// Reload data from server.
						refreshSiteData();
					}
				})
				.catch(function (err) {
					showNotice(err.message || 'Something went wrong. Please try again.', false);
				})
				.finally(function () {
					cartConfirm.disabled = false;
					var remaining = Object.keys(selected).length;
					cartConfirm.textContent = remaining === 1 ? 'Add Site' : 'Add Sites';
				});
		});
	}

	/* ── Upgrade ──────────────────────────────────────────────────────────── */
	if (upgBtn) {
		upgBtn.addEventListener('click', function () {
			var payment = 'stripe';
			upgBtn.disabled = true;
			upgBtn.textContent = 'Processing…';

			apiPost(data.apiBase + '/upgrade', { payment: payment })
				.then(function (res) {
					if (res.payment === 'stripe' && res.checkout_url) {
						window.location.href = res.checkout_url;
					} else {
						showNotice(res.message || 'Upgrade requested.', true);
						upgBanner.hidden = true;
						refreshSiteData();
					}
				})
				.catch(function (err) {
					showNotice(err.message || 'Something went wrong. Please try again.', false);
				})
				.finally(function () {
					upgBtn.disabled = false;
					upgBtn.textContent = 'Upgrade';
				});
		});
	}

	/* ── Cancel ───────────────────────────────────────────────────────────── */
	function handleCancel(slug, label) {
		if (!confirm('Cancel your ' + label + ' subscription? You will keep access until the end of your current term.')) {
			return;
		}
		apiPost(data.apiBase + '/cancel', { site_slug: slug })
			.then(function (res) {
				showNotice(res.message || 'Subscription cancelled.', true);
				refreshSiteData();
			})
			.catch(function (err) {
				showNotice(err.message || 'Could not cancel. Please try again.', false);
			});
	}

	/* ── Refresh data from server ─────────────────────────────────────────── */
	function refreshSiteData() {
		apiFetch(data.apiBase + '/sites')
			.then(function (res) {
				if (res.sites) {
					data.sites              = res.sites;
					data.portfolio          = res.portfolio;
					data.hasPortfolio       = res.has_portfolio;
					data.prices             = res.prices;
					data.upgradeCreditPence = res.upgrade_credit_pence;
					data.upgradeCostPence   = res.upgrade_cost_pence;
					renderGrid();
					renderUpgradeBanner();
					renderCartBar();
				}
			})
			.catch(function () {
				// Silently fail — stale data shown.
			});
	}

	/* ── API helpers ──────────────────────────────────────────────────────── */
	function apiFetch(url) {
		return fetch(url, {
			headers: {
				'X-WP-Nonce': data.nonce,
				'Accept': 'application/json',
			},
			credentials: 'same-origin',
		}).then(function (r) {
			return r.json();
		});
	}

	function apiPost(url, body) {
		return fetch(url, {
			method: 'POST',
			headers: {
				'X-WP-Nonce': data.nonce,
				'Content-Type': 'application/json',
				'Accept': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify(body),
		}).then(function (r) {
			return r.json().then(function (json) {
				if (!r.ok) {
					var err = new Error(json.message || 'Request failed');
					err.code = json.code;
					throw err;
				}
				return json;
			});
		});
	}

	/* ── Notice helper ────────────────────────────────────────────────────── */
	function showNotice(msg, ok) {
		if (!notice) return;
		notice.textContent = msg;
		notice.className = 'khm-sub-modal-notice khm-sub-modal-notice--' + (ok ? 'ok' : 'err');
		notice.hidden = false;
		setTimeout(function () {
			notice.hidden = true;
			notice.textContent = '';
		}, 6000);
	}

})();
