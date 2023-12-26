"use strict";
window.onload = function () {
    // Frontend logout
    let feLogoutBtn = document.querySelectorAll('.trigger-ids-kill-session[data-href]');
    if (feLogoutBtn.length) {
        let i;
        for (i = 0; i < feLogoutBtn.length; ++i) {
            feLogoutBtn[i].addEventListener("click", (e) => {
                e.preventDefault();
                logout(e.target.getAttribute('data-href'));
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
                logout(e.target.getAttribute('href'));
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
     * Get logout endpoint, logout and redirect
     * @param url
     * @param reload
     */
    function logout(url, reload = true) {

        url = url == '' ? '/' : url;

        // Call contao logout route
        fetch('/_contao/logout');

        // Get logout endpoint
        fetch('/ssoauth/get_logout_endpoint').then((response) => {
            return response.json();
        }).then(function (json) {
            // Logout
            return fetch(json.logout_endpoint_url, {
                credentials: 'include',
                mode: 'no-cors'
            }).then((response) => {
                if (reload) {
                    window.location.href = url;
                }
            }).catch(function () {
                if (reload) {
                    window.location.href = url;
                }
            });
        });
    }
};
