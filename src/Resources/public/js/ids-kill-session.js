window.onload = function () {
    // Frontend logout
    let elFeLogoutButton = document.querySelectorAll('.trigger-ids-kill-session');
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


    let path = window.location.pathname;

    // Kill session if login has been aborted due to errors
    let elError = document.querySelectorAll('.trigger-ids-kill-session.sac-oidc-error');
    if (elError.length) {
        logout('');
    }
    else if (RegExp("^/contao\/login(.*)$", "g").test(path)) {
        //logout('');
    }

    /**
     * Logout
     * @param url
     */
    function logout(url) {
        fetch('https://ids01.sac-cas.ch/oidc/logout',
            {
                credentials: 'include',
                mode: 'no-cors',
            }).then((response) => {
                if (url !== '') {
                    window.location.href = url;
                }
            }
        );
    }


};



