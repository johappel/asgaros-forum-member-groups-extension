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

		document.body.classList.add('afspaces-is-searching');

		return refreshFromUrl(url.toString()).then(function () {
			history.replaceState({}, '', url.toString());
			document.body.classList.remove('afspaces-is-searching');
		}).catch(function (err) {
			document.body.classList.remove('afspaces-is-searching');
			throw err;
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
			request_invite_link_registration: true,
			create_space: true,
			rename_space: true,
			change_space_visibility: true,
			transfer_space_owner: true,
			archive_space: true,
			reactivate_space: true,
			delete_space: true,
			approve_space: true,
			reject_space: true
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

				// Nach Mitglieder-Aktionen die Personensuche zurücksetzen, damit die
				// aktualisierte Mitgliederliste nicht durch eine alte Suche verdeckt wird.
				var memberActions = {
					add_member: true,
					remove_member: true,
					assign_manager: true,
					revoke_manager: true
				};

				if (memberActions[actionInput.value]) {
					var cleanUrl = new URL(window.location.href);
					cleanUrl.searchParams.delete('afp_search');
					cleanUrl.searchParams.delete('afp_page');
					var cleanHref = cleanUrl.toString();

					return refreshFromUrl(cleanHref)
						.then(function () {
							history.replaceState({}, '', cleanHref);
							announce(info.message || '', info.type || 'success');
						})
						.catch(function () {
							announce(info.message || '', info.type || 'success');
						});
				}

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

	// Bestätigung für destruktive Aktionen (data-afspaces-confirm).
	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-afspaces-confirm]');
		if (!button) {
			return;
		}

		var message = button.getAttribute('data-afspaces-confirm');
		if (message && !window.confirm(message)) {
			event.preventDefault();
			event.stopPropagation();
		}
	});

	// Optionaler mehrstufiger Raumassistent (Progressive Enhancement).
	// Ohne JavaScript bleibt das Formular ein zugängliches Ein-Seiten-Formular.
	function enhanceWizard(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-afspaces-step]'));
		if (steps.length < 2) {
			return;
		}

		var current = 0;

		function render() {
			steps.forEach(function (step, index) {
				step.hidden = index !== current;
			});
			if (prevBtn) {
				prevBtn.style.display = current === 0 ? 'none' : '';
			}
			if (nextBtn) {
				nextBtn.style.display = current === steps.length - 1 ? 'none' : '';
			}
			if (submitBtn) {
				submitBtn.style.display = current === steps.length - 1 ? '' : 'none';
			}
			var legend = steps[current].querySelector('legend');
			if (legend) {
				legend.setAttribute('tabindex', '-1');
				legend.focus();
			}
		}

		function stepValid() {
			var fields = steps[current].querySelectorAll('input, textarea, select');
			for (var i = 0; i < fields.length; i++) {
				if (!fields[i].checkValidity()) {
					fields[i].reportValidity();
					return false;
				}
			}
			return true;
		}

		var nav = document.createElement('p');
		nav.className = 'afspaces-wizard-nav';

		var prevBtn = document.createElement('button');
		prevBtn.type = 'button';
		prevBtn.className = 'afspaces-button afspaces-button-secondary';
		prevBtn.textContent = form.getAttribute('data-afspaces-prev-label') || 'Zurück';
		prevBtn.addEventListener('click', function () {
			if (current > 0) {
				current--;
				render();
			}
		});

		var nextBtn = document.createElement('button');
		nextBtn.type = 'button';
		nextBtn.className = 'afspaces-button';
		nextBtn.textContent = form.getAttribute('data-afspaces-next-label') || 'Weiter';
		nextBtn.addEventListener('click', function () {
			if (stepValid() && current < steps.length - 1) {
				current++;
				render();
			}
		});

		nav.appendChild(prevBtn);
		nav.appendChild(nextBtn);

		var actions = form.querySelector('.afspaces-form-actions');
		var submitBtn = actions ? actions.querySelector('button[type="submit"]') : null;
		if (actions) {
			actions.parentNode.insertBefore(nav, actions);
		}

		render();
	}

	document.querySelectorAll('form[data-afspaces-wizard]').forEach(enhanceWizard);
})();
