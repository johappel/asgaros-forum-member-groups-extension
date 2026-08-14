(function () {
	'use strict';

	function normalizeHex(value) {
		var raw = String(value || '').trim().replace(/^#/, '');

		if (!/^[0-9a-f]{3}$|^[0-9a-f]{6}$/i.test(raw)) {
			return '';
		}

		if (raw.length === 3) {
			raw = raw.charAt(0) + raw.charAt(0) + raw.charAt(1) + raw.charAt(1) + raw.charAt(2) + raw.charAt(2);
		}

		return '#' + raw.toUpperCase();
	}

	function initColorControl(control) {
		var picker = control.querySelector('[data-afspaces-color-picker]');
		var hexInput = control.querySelector('[data-afspaces-hex-input]');

		if (!picker || !hexInput) {
			return;
		}

		function setValidity() {
			var normalized = normalizeHex(hexInput.value);
			hexInput.setCustomValidity(normalized ? '' : 'Bitte einen gültigen Hex-Farbwert eingeben, zum Beispiel #2D5D7F.');
			return normalized;
		}

		picker.addEventListener('input', function () {
			hexInput.value = normalizeHex(picker.value);
			setValidity();
		});
		picker.addEventListener('change', function () {
			hexInput.value = normalizeHex(picker.value);
			setValidity();
		});
		hexInput.addEventListener('input', setValidity);
		hexInput.addEventListener('blur', function () {
			var normalized = setValidity();
			if (normalized) {
				hexInput.value = normalized;
				picker.value = normalized.toLowerCase();
			}
		});

		setValidity();
	}

	document.querySelectorAll('[data-afspaces-color-control]').forEach(initColorControl);
}());
