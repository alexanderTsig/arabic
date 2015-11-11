(function (pta, $, undefined) {

    pta.dispatchTable['exercise'] = function (pjax) {
        // Activate the correct tab and disable its click action
        $('li.exercise').attr('class', 'active').click(false);
        $("a.full-screen-btn").click(function(){
            $(".stageplayer").toggleClass("fullscreen")    
        });

		// FIXME: Half-arsed implementation ...
        yepnope({
            load: [
                '/js/iframeResizer/iframeResizer.min.js'
            ],
            complete: function () {
                iFrameResize({
                    log: true, // Enable console logging
                    enablePublicMethods: true,
                    enableInPageLinks: true,
                    resizedCallback: function (messageData) { // Callback fn when resize is received
                        console.log("Whaha");
                    },
                    messageCallback: function (messageData) {

                    },
                    closedCallback: function (id) {

                    }
                });
            }
        });

		// FIXME: Sigh.
        return true;

        // Load the flash movie (hopefully switch to HTML5 native later)
        loadFlashMovie();

        // Resize the player now that the document is ready
        pta.setAspectRatio($('#exercise'), 4, 3);

        pta.windowResizeEvent({
            during: function () {
                // Render the player invisible to avoid layout artifacts pending resize
                $('#exercise').css('visibility', 'hidden');
            },
            after: function () {
                pta.setAspectRatio($('#exercise'), 4, 3);
            }
        });
    }

    function loadFlashMovie() {
        var swf_params = {
            allowScriptAccess: "never",
            bgcolor:           "#f2f2f2",
            quality:           "best",
            scale:             "default",
            wmode:             "direct"
        }
        var swf_attrs = {
            "id": "exercise",
            "name": "exercise"
        }
        // http://code.google.com/p/swfobject/issues/detail?id=428#c8
        swfobject.embedSWF($('#exercise').data('url'), "exercise", "720", "450", "10.1.0", "playerProductInstall.swf", {}, swf_params, swf_attrs); // , onMovieLoad);
    }

}(window.pta = window.pta || {}, jQuery));
