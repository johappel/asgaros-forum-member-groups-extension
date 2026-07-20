(function () {
	'use strict';

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || form.tagName !== 'FORM' || String(form.method).toLowerCase() !== 'post') {
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
			.then(function () {
				window.location.reload();
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
})();
