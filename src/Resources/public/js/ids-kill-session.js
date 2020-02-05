(function ($) {
    $(document).ready(function () {
        $('.trigger-ids-kill-session').click(function (e) {
            let self = this;
            e.preventDefault();
            $.get("https://ids01.sac-cas.ch/authenticationendpoint/oauth2_logout.do", function (data) {
                console.log('Logged out successsfully!!!');
            });
            window.setTimeout(function(){
                window.location.href = $(self).data('href');
            },100);
        });
    });
})(jQuery);
