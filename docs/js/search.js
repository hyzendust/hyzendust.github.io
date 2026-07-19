(function () {
	var listEl = document.querySelector('[data-search-list]');
	var statusEl = document.querySelector('[data-search-status]');
	var queryEl = document.querySelector('[data-search-query]');
	if (!listEl || !statusEl) return;

	function getQueryParam(name) {
		var params = new URLSearchParams(window.location.search);
		return params.get(name) || '';
	}

	function escapeHtml(str) {
		return str.replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function escapeRegExp(str) {
		return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	function buildSnippet(content, term) {
		var radius = 90;
		var start = 0;
		var end = Math.min(content.length, radius * 2);

		if (term) {
			var idx = content.toLowerCase().indexOf(term.toLowerCase());
			if (idx !== -1) {
				start = Math.max(0, idx - radius);
				end = Math.min(content.length, idx + term.length + radius);
			}
		}

		var snippet = content.slice(start, end);
		if (start > 0) snippet = '\u2026' + snippet;
		if (end < content.length) snippet = snippet + '\u2026';

		var escaped = escapeHtml(snippet);
		if (term) {
			var re = new RegExp('(' + escapeRegExp(term) + ')', 'ig');
			escaped = escaped.replace(re, '<mark>$1</mark>');
		}
		return escaped;
	}

	function sectionLabel(section) {
		if (section === 'blog') return 'Blog';
		if (section === 'services') return 'Service';
		return section;
	}

	var query = getQueryParam('q').trim();
	if (queryEl) queryEl.textContent = query ? 'for "' + query + '"' : '';

	if (!query) {
		statusEl.textContent = 'Enter a search term above.';
		return;
	}

	statusEl.textContent = 'Searching\u2026';

	fetch('/index.json')
		.then(function (res) {
			if (!res.ok) throw new Error('Failed to load search index');
			return res.json();
		})
		.then(function (items) {
			var terms = query.toLowerCase().split(/\s+/).filter(Boolean);

			var matches = items
				.map(function (item) {
					var titleLower = (item.title || '').toLowerCase();
					var contentLower = (item.content || '').toLowerCase();
					var score = 0;
					terms.forEach(function (term) {
						if (titleLower.indexOf(term) !== -1) score += 10;
						if (contentLower.indexOf(term) !== -1) score += 1;
					});
					return { item: item, score: score };
				})
				.filter(function (m) {
					return m.score > 0;
				})
				.sort(function (a, b) {
					return b.score - a.score;
				});

			listEl.innerHTML = '';

			if (matches.length === 0) {
				statusEl.textContent = 'No results found for "' + query + '".';
				return;
			}

			statusEl.textContent =
				matches.length + ' result' + (matches.length === 1 ? '' : 's') + ' found.';

			matches.forEach(function (m) {
				var item = m.item;

				var li = document.createElement('li');
				li.className = 'search-results__item';

				var meta = document.createElement('span');
				meta.className = 'search-results__item-meta';
				meta.textContent = sectionLabel(item.section);

				var h2 = document.createElement('h2');
				h2.className = 'search-results__item-title';
				var a = document.createElement('a');
				a.href = item.url;
				a.textContent = item.title;
				h2.appendChild(a);

				var p = document.createElement('p');
				p.className = 'search-results__item-snippet';
				p.innerHTML = buildSnippet(item.content || '', terms[0] || query);

				li.appendChild(meta);
				li.appendChild(h2);
				li.appendChild(p);
				listEl.appendChild(li);
			});
		})
		.catch(function () {
			statusEl.textContent = 'Something went wrong loading search results.';
		});
})();
