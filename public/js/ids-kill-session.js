"use strict";
window.onload = function () {
	// Frontend logout
	let feLogoutBtn = document.querySelectorAll('.trigger-ids-kill-session[data-href]');
	if (feLogoutBtn.length) {
		let i;
		for (i = 0; i < feLogoutBtn.length; ++i) {
			feLogoutBtn[i].addEventListener("click", (e) => {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				if (e.target.hasAttribute('data-href')) {
					e.target.text = 'bitte warten...'
					let href = e.target.getAttribute('data-href');
					e.target.removeAttribute('data-href');
					logout(href, false, e.target.getAttribute('data-targetpath'));
				}
			});
		}
	}

	// Backend logout
	let beLogoutBtn = document.querySelectorAll('#tmenu a[href$="contao/logout"]');
	if (beLogoutBtn.length) {
		let i;
		for (i = 0; i < beLogoutBtn.length; ++i) {
			beLogoutBtn[i].addEventListener("click", (e) => {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				logout(e.target.getAttribute('href'), false, '/contao/logout');
			});
		}
	}

	// Kill session if login has been aborted due to errors
	if (document.querySelectorAll('.trigger-ids-kill-session.sac-oidc-error').length) {
		logout('', false);
	} else if (RegExp("^/contao\/login(.*)$", "g").test(window.location.pathname)) {
		//logout('');
	}

	/**
	 * Get the logout endpoint url,
	 * then logout the user
	 * and redirect
	 *
	 * @param logoutUrl
	 * @param reload
	 * @param redirectUrl
	 */
	function logout(logoutUrl, reload = true, redirectUrl = null) {

		// Call the contao logout route
		fetch(logoutUrl);

		// Get the logout url (https://ids01.sac-cas.ch/oidc/logout or https://ids02.sac-cas.ch/oidc/logout)
		fetch('/ssoauth/get_logout_endpoint').then((response) => {
			return response.json();
		}).then(function (json) {
			// Logout
			return fetch(json.logout_endpoint_url, {
				credentials: 'include',
				mode: 'no-cors'
			}).then((response) => {
				if (redirectUrl) {
					window.setTimeout(() => window.location.href = redirectUrl, 200);
				}
			}).catch(function () {
				if (redirectUrl) {
					window.setTimeout(() => window.location.href = redirectUrl, 200);
				}
			});
		});
	}
};
