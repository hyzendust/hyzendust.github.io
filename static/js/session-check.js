(function () {
	var path = window.location.pathname;
	if (path === '/login/' || path === '/signup/') return;

	// ── Save current page before navigating to login/signup ──
	document.querySelectorAll('a[href="/login/"], a[href="/signup/"]').forEach(function (a) {
		a.addEventListener('click', function () {
			sessionStorage.setItem('f4_login_next', path);
		});
	});

	// ── Validate session on every page load ──
	if (!localStorage.getItem('f4_username')) return;

	// Skip check if we just logged in (5 second grace period)
	var loginTime = parseInt(localStorage.getItem('f4_login_time') || '0', 10);
	if (Date.now() - loginTime < 5000) return;

	fetch('https://backend.freedoms4.org/auth.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		credentials: 'include',
		body: JSON.stringify({ action: 'check_session' }),
	})
		.then(function (r) {
			return r.json();
		})
		.then(function (data) {
			if (!data.valid) {
				// DB gone, user deleted, or session invalid — log out immediately
				localStorage.removeItem('f4_username');
				localStorage.removeItem('f4_login_time');
				localStorage.removeItem('f4_session_fails');
				window.location.reload();
			} else {
				localStorage.removeItem('f4_session_fails');
			}
		})
		.catch(function () {
			// Backend completely unreachable — force logout immediately
			localStorage.removeItem('f4_username');
			localStorage.removeItem('f4_login_time');
			localStorage.removeItem('f4_session_fails');
			window.location.reload();
		});
})();
