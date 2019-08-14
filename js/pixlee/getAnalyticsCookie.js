var createPixleeAnalyticsCookie = function() {
    var iframe = document.createElement('iframe');
    iframe.style.display = "none";
    iframe.src = 'https://photos.pixlee.com/getDUH';
    document.body.appendChild(iframe);

    window.addEventListener("message", function receiveMessage(event) {
        try {
            var eventData = JSON.parse(event.data);
            if (eventData.function == "pixlee_distinct_user_hash") {
                if (eventData.data) {
                    var distinct_user_hash_linker = eventData.value;
                    setCookie('pixlee_analytics_cookie', encodeURIComponent(JSON.stringify({
                        CURRENT_PIXLEE_USER_ID: distinct_user_hash_linker,
                        CURRENT_PIXLEE_ALBUM_PHOTOS: [],
                        HORIZONTAL_PAGE: [],
                        CURRENT_PIXLEE_ALBUM_PHOTOS_TIMESTAMP: []
                    })), 30);
                }
            }
        } catch(e) {
            console.log("Exception " + e);
        };
    }, false);
}

var setCookie = function(name, value, days) {
    if (days) {
        var date = new Date();
        date.setTime(date.getTime()+(days*24*60*60*1000));
        var expires = "; expires="+date.toGMTString();
    }
    else var expires = "";
    document.cookie = name+"="+value+expires+"; path=/; domain=" + window.location.host.replace('www', '');
};

window.addEventListener('load', createPixleeAnalyticsCookie);
