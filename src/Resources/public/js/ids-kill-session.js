window.onload = function () {
    let elButton = document.querySelectorAll('.trigger-ids-kill-session');
    if (elButton.length) {
        let i;
        for (i = 0; i < elButton.length; ++i) {
            elButton[i].addEventListener("click", function (e) {
                let self = this;
                e.preventDefault();
                fetch('https://ids02.sac-cas.ch/authenticationendpoint/oauth2_logout.do',
                    {
                        credentials: 'include',
                        mode: 'no-cors',
                    });
                window.setTimeout(function () {
                    window.location.href = $(self).data('href');
                }, 100);
            });
        }
    }
};

