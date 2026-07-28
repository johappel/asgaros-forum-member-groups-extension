/**
 * AFSpaces ortsunabhängiges Such-Overlay.
 *
 * Nutzt die REST-Route /afspaces/v1/search und funktioniert seitenunabhängig.
 * Progressive Enhancement: Ohne JavaScript bleiben die Suchseite und die
 * Asgaros-Weiterleitung als Fallback bestehen.
 */
(function () {
	'use strict';

	var cfg = window.afspacesSearch || null;
	if (!cfg || !cfg.restUrl) {
		return;
	}

	var i18n = cfg.i18n || {};
	var overlay = null;
	var input = null;
	var resultsEl = null;
	var statusEl = null;
	var spinnerEl = null;
	var lastFocused = null;
	var debounceTimer = null;
	var currentPage = 1;
	var totalPages = 0;

	function t(key, fallback) {
		return (i18n[key] !== undefined && i18n[key] !== null) ? i18n[key] : (fallback || '');
	}

	function el(tag, attrs, text) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'class') {
					node.className = attrs[k];
				} else {
					node.setAttribute(k, attrs[k]);
				}
			});
		}
		if (text !== undefined && text !== null) {
			node.textContent = text;
		}
		return node;
	}

	function buildBody() {
		var body = overlay.querySelector('.afspaces-search-overlay__body');
		if (!body) {
			return;
		}
		body.innerHTML = '';

		var form = el('form', { 'class': 'afspaces-search-modal-form', role: 'search' });
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			scheduleSearch(0);
		});

		// Suchzeile.
		var row = el('div', { 'class': 'afspaces-search-row' });

		var qField = el('p', { 'class': 'afspaces-field afspaces-field-grow' });
		var qLabel = el('label', { 'for': 'afspaces-modal-q' }, t('placeholder', 'Suche'));
		qLabel.className = 'screen-reader-text';
		input = el('input', { type: 'search', id: 'afspaces-modal-q', name: 'q', placeholder: t('placeholder', ''), autocomplete: 'off' });
		input.addEventListener('input', function () { scheduleSearch(350); });
		qField.appendChild(qLabel);
		qField.appendChild(input);
		row.appendChild(qField);

		row.appendChild(selectField('afspaces-modal-scope', t('scope', 'Bereich'), 'scope', [
			['all', t('scopeAll', 'Alles')],
			['forum', t('scopeForum', 'Foren')],
			['wp', t('scopeWp', 'Beiträge')]
		]));

		row.appendChild(selectField('afspaces-modal-sort', t('sort', 'Sortierung'), 'sort', [
			['relevance', t('sortRel', 'Relevanz')],
			['date', t('sortDate', 'Neueste')]
		]));

		form.appendChild(row);

		// Semantik-Schalter.
		if (cfg.semanticAvailable) {
			var semP = el('p', { 'class': 'afspaces-field afspaces-field-checkbox' });
			var semLabel = el('label', { 'for': 'afspaces-modal-semantic' });
			var semInput = el('input', { type: 'checkbox', id: 'afspaces-modal-semantic', name: 'semantic', value: '1' });
			semInput.addEventListener('change', function () { scheduleSearch(0); });
			semLabel.appendChild(semInput);
			semLabel.appendChild(document.createTextNode(' ' + t('semantic', 'Semantisch')));
			semP.appendChild(semLabel);
			form.appendChild(semP);
		}

		// Wortmodus + Suchbereich (MVP 3).
		var optRow = el('div', { 'class': 'afspaces-search-row afspaces-search-options-row' });
		optRow.appendChild(selectField('afspaces-modal-mode', t('wordMode', 'Wortmodus'), 'mode', [
			['any', t('wordAny', 'Eines der Wörter')],
			['all', t('wordAll', 'Alle Wörter')]
		]));
		optRow.appendChild(selectField('afspaces-modal-in', t('searchIn', 'Suchen in'), 'in', [
			['all', t('inAll', 'Titel & Text')],
			['title', t('inTitle', 'Nur Titel')]
		]));
		form.appendChild(optRow);

		// Filter (details/summary).
		var details = el('details', { 'class': 'afspaces-search-filters' });
		details.appendChild(el('summary', null, t('filters', 'Filter')));
		var frow = el('div', { 'class': 'afspaces-search-row afspaces-search-filter-row' });

		var agOptions = [['0', t('anyGroup', 'Alle Arbeitsgruppen')]];
		(cfg.forums || []).forEach(function (f) {
			agOptions.push([String(f.id), f.name]);
		});
		frow.appendChild(selectField('afspaces-modal-ag', t('group', 'Arbeitsgruppe'), 'forum', agOptions));

		frow.appendChild(textField('afspaces-modal-author', t('author', 'Autor:in'), 'author_name', 'text', t('authorPh', '')));
		frow.appendChild(textField('afspaces-modal-from', t('dateFrom', 'Von'), 'date_from', 'date', ''));
		frow.appendChild(textField('afspaces-modal-to', t('dateTo', 'Bis'), 'date_to', 'date', ''));

		details.appendChild(frow);
		form.appendChild(details);

		// Statuszeile + Spinner.
		var statusRow = el('div', { 'class': 'afspaces-search-modal-status' });
		spinnerEl = el('span', { 'class': 'afspaces-spinner', 'aria-hidden': 'true' });
		spinnerEl.hidden = true;
		statusEl = el('div', { 'class': 'afspaces-search-status', role: 'status', 'aria-live': 'polite' });
		statusRow.appendChild(spinnerEl);
		statusRow.appendChild(statusEl);
		form.appendChild(statusRow);

		body.appendChild(form);

		resultsEl = el('div', { 'class': 'afspaces-search-modal-results' });
		body.appendChild(resultsEl);

		// Änderungen an Filtern/Selects lösen eine neue Suche aus.
		form.addEventListener('change', function (e) {
			if (e.target && (e.target.tagName === 'SELECT' || e.target.type === 'date' || e.target.type === 'text')) {
				scheduleSearch(0);
			}
		});
	}

	function selectField(id, label, name, options) {
		var p = el('p', { 'class': 'afspaces-field' });
		p.appendChild(el('label', { 'for': id }, label));
		var sel = el('select', { id: id, name: name });
		options.forEach(function (opt) {
			sel.appendChild(el('option', { value: opt[0] }, opt[1]));
		});
		p.appendChild(sel);
		return p;
	}

	function textField(id, label, name, type, placeholder) {
		var p = el('p', { 'class': 'afspaces-field' });
		p.appendChild(el('label', { 'for': id }, label));
		var attrs = { type: type, id: id, name: name, autocomplete: 'off' };
		if (placeholder) {
			attrs.placeholder = placeholder;
		}
		p.appendChild(el('input', attrs));
		return p;
	}

	function fieldValue(name) {
		var node = overlay.querySelector('[name="' + name + '"]');
		if (!node) {
			return '';
		}
		if (node.type === 'checkbox') {
			return node.checked ? '1' : '';
		}
		return node.value || '';
	}

	function fieldSet(name, value) {
		var node = overlay.querySelector('[name="' + name + '"]');
		if (!node) {
			return;
		}
		if (node.type === 'checkbox') {
			node.checked = !!value;
		} else {
			node.value = value || '';
		}
	}

	function scheduleSearch(delay) {
		if (debounceTimer) {
			window.clearTimeout(debounceTimer);
		}
		debounceTimer = window.setTimeout(function () { doSearch(1); }, delay || 0);
	}

	function doSearch(page) {
		var q = (input && input.value ? input.value : '').trim();
		if (q === '') {
			resultsEl.innerHTML = '';
			setStatus('');
			return;
		}

		currentPage = page || 1;
		setSpinner(true);
		setStatus(t('searching', 'Suche läuft …'));

		var params = new URLSearchParams();
		params.set('q', q);
		params.set('scope', fieldValue('scope') || 'all');
		params.set('sort', fieldValue('sort') || 'relevance');
		params.set('semantic', fieldValue('semantic') ? '1' : '0');
		params.set('author_name', fieldValue('author_name'));
		params.set('forum', fieldValue('forum') || '0');
		params.set('date_from', fieldValue('date_from'));
		params.set('date_to', fieldValue('date_to'));
		params.set('mode', fieldValue('mode') || 'any');
		params.set('in', fieldValue('in') || 'all');
		params.set('page', String(currentPage));
		params.set('per_page', '10');

		var headers = { 'Accept': 'application/json' };
		if (cfg.nonce) {
			headers['X-WP-Nonce'] = cfg.nonce;
		}

		fetch(cfg.restUrl + '?' + params.toString(), {
			credentials: 'same-origin',
			headers: headers
		})
			.then(function (r) {
				if (!r.ok) { throw new Error('search failed'); }
				return r.json();
			})
			.then(function (data) {
				setSpinner(false);
				renderResults(data, q);
			})
			.catch(function () {
				setSpinner(false);
				resultsEl.innerHTML = '';
				setStatus(t('error', 'Fehler.'), true);
			});
	}

	function setSpinner(on) {
		if (spinnerEl) {
			spinnerEl.hidden = !on;
		}
		if (overlay) {
			overlay.classList.toggle('is-loading', !!on);
		}
	}

	function setStatus(message, isError) {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = message || '';
		statusEl.setAttribute('role', isError ? 'alert' : 'status');
	}

	function renderResults(data, query) {
		resultsEl.innerHTML = '';
		var total = data && data.total ? parseInt(data.total, 10) : 0;
		totalPages = data && data.total_pages ? parseInt(data.total_pages, 10) : 0;

		if (!data || !data.results || data.results.length === 0) {
			setStatus(t('noResults', 'Keine Treffer.'));
			return;
		}

		setStatus((t('resultCount', '%d Treffer')).replace('%d', String(total)));

		var list = el('ol', { 'class': 'afspaces-search-results-list' });
		data.results.forEach(function (hit) {
			list.appendChild(renderHit(hit, query));
		});
		resultsEl.appendChild(list);

		if (totalPages > 1) {
			resultsEl.appendChild(renderPager());
		}
	}

	function renderHit(hit, query) {
		var li = el('li', { 'class': 'afspaces-search-result' });

		var h3 = el('h3', { 'class': 'afspaces-search-result-title' });
		var a = el('a', { href: hit.url || '#' }, hit.title || '');
		h3.appendChild(a);
		li.appendChild(h3);

		var meta = el('p', { 'class': 'afspaces-search-result-meta' });
		var sourceLabel = hit.source === 'wp' ? t('sourceWp', 'Beitrag') : t('sourceForum', 'Forum');
		var badgeClass = 'afspaces-tag afspaces-source-' + (hit.source === 'wp' ? 'wp' : 'forum');
		meta.appendChild(el('span', { 'class': badgeClass }, sourceLabel));
		if (hit.context && String(hit.context).toLowerCase() !== String(sourceLabel).toLowerCase()) {
			meta.appendChild(el('span', { 'class': 'afspaces-tag afspaces-tag-context' }, hit.context));
		}
		if (hit.author) {
			meta.appendChild(el('span', { 'class': 'afspaces-search-result-author' }, hit.author));
		}
		if (hit.date) {
			var sep = el('span', { 'aria-hidden': 'true' }, ' · ');
			meta.appendChild(sep);
			meta.appendChild(el('span', { 'class': 'afspaces-search-result-date' }, hit.date));
		}
		li.appendChild(meta);

		var snippet = el('p', { 'class': 'afspaces-search-result-snippet' });
		appendHighlighted(snippet, hit.snippet || '', query);
		li.appendChild(snippet);

		var linkP = el('p', { 'class': 'afspaces-search-result-link' });
		linkP.appendChild(el('a', { href: hit.url || '#' }, t('toResult', 'Zum Beitrag')));
		li.appendChild(linkP);

		return li;
	}

	function appendHighlighted(container, text, query) {
		var terms = String(query || '').toLowerCase().split(/\s+/).filter(function (w) { return w.length >= 2; });
		if (terms.length === 0) {
			container.textContent = text;
			return;
		}
		var lower = text.toLowerCase();
		var i = 0;
		while (i < text.length) {
			var nextPos = -1;
			var nextLen = 0;
			terms.forEach(function (term) {
				var p = lower.indexOf(term, i);
				if (p !== -1 && (nextPos === -1 || p < nextPos)) {
					nextPos = p;
					nextLen = term.length;
				}
			});
			if (nextPos === -1) {
				container.appendChild(document.createTextNode(text.substring(i)));
				break;
			}
			if (nextPos > i) {
				container.appendChild(document.createTextNode(text.substring(i, nextPos)));
			}
			var mark = el('mark', null, text.substring(nextPos, nextPos + nextLen));
			container.appendChild(mark);
			i = nextPos + nextLen;
		}
	}

	function renderPager() {
		var nav = el('nav', { 'class': 'afspaces-search-pagination', 'aria-label': t('title', 'Suche') });
		var ul = el('ul', { 'class': 'afspaces-pagination-list' });

		if (currentPage > 1) {
			var prev = el('li');
			var pa = el('button', { type: 'button', 'class': 'afspaces-button afspaces-button-link' }, t('prev', 'Zurück'));
			pa.addEventListener('click', function () { doSearch(currentPage - 1); });
			prev.appendChild(pa);
			ul.appendChild(prev);
		}

		ul.appendChild(el('li', { 'class': 'afspaces-pagination-status' }, currentPage + ' / ' + totalPages));

		if (currentPage < totalPages) {
			var next = el('li');
			var na = el('button', { type: 'button', 'class': 'afspaces-button afspaces-button-link' }, t('next', 'Weiter'));
			na.addEventListener('click', function () { doSearch(currentPage + 1); });
			next.appendChild(na);
			ul.appendChild(next);
		}

		nav.appendChild(ul);
		return nav;
	}

	function getFocusable() {
		return Array.prototype.slice.call(
			overlay.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])')
		).filter(function (n) { return n.offsetParent !== null; });
	}

	function trapFocus(e) {
		if (e.key !== 'Tab') {
			return;
		}
		var focusable = getFocusable();
		if (focusable.length === 0) {
			return;
		}
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function open(prefill, scope) {
		if (!overlay) {
			return;
		}
		lastFocused = document.activeElement;
		overlay.hidden = false;
		document.body.classList.add('afspaces-search-open');
		if (prefill && input) {
			input.value = prefill;
		}
		if (scope) {
			fieldSet('scope', scope);
		}
		window.setTimeout(function () {
			if (input) {
				input.focus();
			}
			if (input && input.value.trim() !== '') {
				doSearch(1);
			}
		}, 30);
	}

	function close() {
		if (!overlay) {
			return;
		}
		overlay.hidden = true;
		document.body.classList.remove('afspaces-search-open');
		if (lastFocused && typeof lastFocused.focus === 'function') {
			lastFocused.focus();
		}
	}

	function isSearchTrigger(node) {
		if (!node || !node.closest) {
			return null;
		}
		return node.closest('[data-afspaces-search-open], a[href$="#afspaces-search"]');
	}

	function attachGlobal() {
		document.addEventListener('click', function (e) {
			var closer = e.target.closest ? e.target.closest('[data-afspaces-search-close]') : null;
			if (closer && overlay.contains(closer)) {
				e.preventDefault();
				close();
				return;
			}
			var trigger = isSearchTrigger(e.target);
			if (trigger) {
				e.preventDefault();
				var scope = trigger.getAttribute('data-afspaces-search-scope');
				open('', scope);
			}
		});

		document.addEventListener('keydown', function (e) {
			if (overlay.hidden) {
				return;
			}
			if (e.key === 'Escape') {
				e.preventDefault();
				close();
			} else {
				trapFocus(e);
			}
		});

		// Asgaros-Forensuchfeld übernehmen (Fallback: normaler GET-Redirect).
		document.addEventListener('submit', function (e) {
			var form = e.target;
			if (!form || form.tagName !== 'FORM') {
				return;
			}
			var isForumSearch = form.closest('#forum-search') && form.querySelector('input[name="keywords"]');
			var isWpSearch = cfg.replaceWpSearch && (form.getAttribute('role') === 'search' || form.classList.contains('wp-block-search__form') || form.classList.contains('search-form')) && !form.closest('#af-wrapper') && !form.closest('.afspaces-search-overlay');
			if (!isForumSearch && !isWpSearch) {
				return;
			}
			var field = form.querySelector('input[name="keywords"], input[type="search"], input[name="s"]');
			var value = field ? field.value : '';
			e.preventDefault();
			var scope = isForumSearch ? 'forum' : null;
			open(value, scope);
		}, true);
	}

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		overlay = document.getElementById('afspaces-search-overlay');
		if (!overlay) {
			return;
		}
		buildBody();
		attachGlobal();
	});
})();
