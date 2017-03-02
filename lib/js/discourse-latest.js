(function ($) {
    var latestURL = dclt.latestURL;

    console.log('latest URL', latestURL);
    (function getLatest() {
        $.ajax({
            url: latestURL,
            success: function(response) {
                console.log(response);
            },
            complete: function() {
                setTimeout(getLatest, 10000);
            }
        });
    })();


})(jQuery);