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
        logout('');
    }
    else if (RegExp("^/contao\/login(.*)$", "g").test(window.location.pathname)) {
        //logout('');
    }


    /**
     * Logout
     * @param url
     */
    function logout(url) {

        _getLogoutEndpointUrl().then((logoutendpoint) => {
            fetch(logoutendpoint,
                {
                    credentials: 'include',
                    mode: 'no-cors',
                })
                .then(function (response) {
                    if (url !== '') {
                        window.location.href = url;
                    }
                }).catch(function () {
                window.location.href = url;
            });
        }).catch(function () {
            window.location.href = url;
        });
    }

    /**
     * Get the logut endpoint url
     * @returns {Promise<Response | never | never | string>}
     */
    function _getLogoutEndpointUrl() {

        return fetch('/ssoauth/send_logout_endpoint')
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                return json.logout_endpoint_url;
            }).catch(function () {
                return '';
            });
    }
};
