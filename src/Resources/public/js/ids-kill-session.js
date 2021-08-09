"use strict";
window.onload = function () {
    // Frontend logout
    let elFeLogoutButton = document.querySelectorAll('.trigger-ids-kill-session[data-href]');
    if (elFeLogoutButton.length) {
        let i;
        for (i = 0; i < elFeLogoutButton.length; ++i) {
            elFeLogoutButton[i].addEventListener("click", function (e) {
                e.preventDefault();
                logout(e.target.getAttribute('data-href'));
            });
        }
    }

    // Backend logout
    let elBeLogoutButton = document.querySelectorAll('#tmenu a[href$="contao/logout"]');
    if (elBeLogoutButton.length) {
        let i;
        for (i = 0; i < elBeLogoutButton.length; ++i) {
            elBeLogoutButton[i].addEventListener("click", function (e) {
                e.preventDefault();
                logout(e.target.getAttribute('href'));
            });
        }
    }

    // Kill session if login has been aborted due to errors
    if (document.querySelectorAll('.trigger-ids-kill-session.sac-oidc-error').length) {
        logout('',false);
    }
    else if (RegExp("^/contao\/login(.*)$", "g").test(window.location.pathname)) {
        //logout('');
    }

    /**
     * Get logout endpoint, logout and redirect
     * @param url
     */
    function logout(url, reload = true) {

        url = url == '' ? '/' : url;

        // Call contao logout route
        fetch('/_contao/logout');

        // Get logout endpoint
        fetch('/ssoauth/send_logout_endpoint').then(function (response) {
            return response.json();
        }).then(function (json) {
            // Logout
            return fetch(json.logout_endpoint_url, {
                credentials: 'include',
                mode: 'no-cors'
            }).then(function (response) {
                if(reload){
                    window.location.href = url;
                }
            }).catch(function () {
                if(reload){
                    window.location.href = url;
                }
            });
        });
    }
};
