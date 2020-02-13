window.onload = function () {
    let elButton = document.querySelectorAll('.trigger-ids-kill-session');
    if (elButton.length) {
        let i;
        for (i = 0; i < elButton.length; ++i) {
            elButton[i].addEventListener("click", function (e) {
                let self = this;
                e.preventDefault();
                logout($(self).data('href'));
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
        logout('');
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



