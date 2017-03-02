(function ($) {
    var latestURL = dclt.latestURL;

    (function getLatest() {
        $.ajax({
            url: latestURL,
            success: function(response) {
                $('.dclt-topiclist').html(response);
            },
            complete: function() {
                setTimeout(getLatest, 10000);
            }
        });
    })();
})(jQuery);