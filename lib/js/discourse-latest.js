(function ($) {
    var latestURL = dclt.latestURL,
        ajaxTimeout = dclt.ajaxTimeout;

    (function getLatest() {
        $.ajax({
            url: latestURL,
            success: function (response) {
                $('.dclt-topiclist').html(response);
            },
            complete: function () {
                setTimeout(getLatest, ajaxTimeout * 1000);
            }
        });
    })();
})(jQuery);