(function () {
	'use strict';

	function getLiveRegion() {
		var region = document.getElementById('afspaces-live-region');
		if (region) {
			return region;
		}

		region = document.createElement('div');
		region.id = 'afspaces-live-region';
		region.className = 'screen-reader-text';
		region.setAttribute('role', 'status');
		region.setAttribute('aria-live', 'polite');
		region.setAttribute('aria-atomic', 'true');
		document.body.appendChild(region);
		return region;
	}

	function announce(message, type) {
		if (!message) {
			return;
		}

		var region = getLiveRegion();
		region.setAttribute('role', type === 'error' ? 'alert' : 'status');
		region.textContent = message;
	}

	function refreshHubDom() {
		return fetch(window.location.href, {
			credentials: 'same-origin'
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Refresh failed');
				}
				return response.text();
			})
			.then(function (html) {
				var parser = new DOMParser();
				var doc = parser.parseFromString(html, 'text/html');
				var currentWrapper = document.querySelector('#af-wrapper.afspaces-wrapper');
				var newWrapper = doc.querySelector('#af-wrapper.afspaces-wrapper');

				if (!currentWrapper || !newWrapper) {
					throw new Error('Wrapper not found');
				}

				currentWrapper.replaceWith(newWrapper);
			});
	}

	function refreshFromUrl(url) {
		return fetch(url, {
			credentials: 'same-origin'
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Refresh failed');
				}
				return response.text();
			})
			.then(function (html) {
				var parser = new DOMParser();
				var doc = parser.parseFromString(html, 'text/html');
				var currentWrapper = document.querySelector('#af-wrapper.afspaces-wrapper');
				var newWrapper = doc.querySelector('#af-wrapper.afspaces-wrapper');

				if (!currentWrapper || !newWrapper) {
					throw new Error('Wrapper not found');
				}

				currentWrapper.replaceWith(newWrapper);
			});
	}

	function isAjaxSearchForm(form) {
		return !!form.closest('#af-wrapper.afspaces-wrapper') && (form.classList.contains('afspaces-search') || form.classList.contains('afspaces-filter'));
	}

	function scheduleAutoSearch(form) {
		if (!isAjaxSearchForm(form) || !form.classList.contains('afspaces-search')) {
			return;
		}

		var existingTimer = form.__afspacesAutoSearchTimer;
		if (existingTimer) {
			window.clearTimeout(existingTimer);
		}

		form.__afspacesAutoSearchTimer = window.setTimeout(function () {
			handleAjaxGetForm(form).catch(function () {
				form.submit();
			});
		}, 350);
	}

	function handleAjaxGetForm(form) {
		var url = new URL(form.action || window.location.href, window.location.origin);
		var params = new URLSearchParams(new FormData(form));

		params.forEach(function (value, key) {
			url.searchParams.set(key, value);
		});

		return refreshFromUrl(url.toString()).then(function () {
			history.replaceState({}, '', url.toString());
		});
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || form.tagName !== 'FORM' || String(form.method).toLowerCase() !== 'post') {
			if (!form || form.tagName !== 'FORM' || String(form.method).toLowerCase() !== 'get' || !isAjaxSearchForm(form)) {
				return;
			}

			event.preventDefault();
			handleAjaxGetForm(form).catch(function () {
				form.submit();
			});
			return;
		}

		var actionInput = form.querySelector('input[name="afspaces_action"]');
		if (!actionInput) {
			return;
		}

		var nonAjaxActions = {
			accept_invitation: true,
			decline_invitation: true,
			use_invite_link: true,
			request_invite_link_registration: true
		};

		if (nonAjaxActions[actionInput.value]) {
			return;
		}

		if (!window.afspacesFrontend || !window.afspacesFrontend.ajaxUrl) {
			return;
		}

		event.preventDefault();

		var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
		if (submitButton) {
			submitButton.disabled = true;
		}

		var data = new FormData(form);
		data.append('action', 'afspaces_action');
		data.append('afspaces_ajax', '1');

		fetch(window.afspacesFrontend.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Request failed');
				}
				return response.json();
			})
			.then(function (payload) {
				var info = payload && payload.data ? payload.data : {};
				return refreshHubDom()
					.then(function () {
						announce(info.message || '', info.type || 'success');
					})
					.catch(function () {
						announce(info.message || '', info.type || 'success');
					});
			})
			.catch(function () {
				form.submit();
			})
			.finally(function () {
				if (submitButton) {
					submitButton.disabled = false;
				}
			});
	});

	document.addEventListener('input', function (event) {
		var target = event.target;
		if (!target || target.tagName !== 'INPUT') {
			return;
		}

		if (String(target.type).toLowerCase() !== 'search') {
			return;
		}

		var form = target.closest('form.afspaces-search');
		if (!form) {
			return;
		}

		scheduleAutoSearch(form);
	});

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (!target || target.tagName !== 'SELECT') {
			return;
		}

		var form = target.closest('form.afspaces-filter');
		if (!form) {
			return;
		}

		handleAjaxGetForm(form).catch(function () {
			form.submit();
		});
	});
})();
