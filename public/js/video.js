(function (pta, $, undefined) {
    var playlist = {};
    var seek =0;
    pta.dispatchTable['video'] = function (pjax) {
        // Add click handlers for the teacher mugshots
        $('#teachers > li.unlocked > img').each(function (i) {
            $(this).click(function () {
                window.location = '/level/' + pta.level + '/' + pta.lesson + '/video/' + $(this).parent().data().gender;
            });
        });

        $('a.disabled').bind('click', function(e){
            e.preventDefault();
            return false;
        });
        // Load the video clip
        yepnope({
            load: [
                '/js/flowplayer/skin/minimalist.css',
                '/js/flowplayer/flowplayer.min.js',
            ],
            complete: function () {
                loadVideo(pta.level, pta.lesson, $('#player').data().gender);

                // Activate the correct tab and disable its click action
                $('li.video')
					.attr('class', 'active')
					.click(false);

                // Highlight the first chapter to begin with
                $('#chapters > a:first').attr('class', 'active');
            }
        });
    }

    function initFlowPlayer(){
        var playList = [];
        var cuepoints = [];
        if(playlist.data.length > 0){            
            for(var i in playlist.data){
                if(i==0 && /.*\.png$/.test(playlist.data[i].url) == true){
                    continue;
                }
                playList.push([{mp4:playlist.data[i].url}]);
                if(playlist.data[i].cuepoints && playlist.data[i].cuepoints.length > 0){
                    cuepoints = $.merge(cuepoints, playlist.data[i].cuepoints);
                }                
            }
        }
        $("#player").flowplayer({
            // one video: a one-member playlist
            key:"$486645316100173",            
            swf: "/js/flowplayer/flowplayer.swf",
            playlist: playList,
            ratio: 9/16,
            cuepoints:cuepoints,
            splash: true 
        }).addClass("play-button");
        $("#player").bind("progress",function(event,folwer,time){
            var player = flowplayer($("#player"));
            var current_clip = player.video.index;
            var time = player.video.time;
            refreshTopic(current_clip, time)
        })
        $("#player").bind("ready",function(event){
            if(seek != 0){
                var player = flowplayer($("#player"));
                player.seek(seek);
                seek = 0;
            }
        })         
        $("#player").bind("contextmenu",function(){
            return false;
        });         
        return true;
    }

    function initCuePoints() {
        var button_idx = 1;
        if(playlist.data.length > 0){            
            for(var i in playlist.data){
                if(i==0 && /.*\.png$/.test(playlist.data[i].url) == true){
                    continue;
                }
                var cuepoints = playlist.data[i].cuepoints;
                $.each(cuepoints, function (k, cuepoint) {
                    $("#chapters > a:nth-child(" + button_idx + ")").click(function (e) {
                        e.preventDefault();
                        loadClip($(this).data("clip"), $(this).data("cuepoint"));
                    }).attr("data-clip", i-1).attr("data-cuepoint", cuepoint);
                    button_idx++;
                });
            }
        }
        return true;
    }

    function loadVideo(level, lesson, gender) {
        // Cache playlist for 30 mins (only useful with PJAX)
        if ('data' in playlist && level === playlist.level && lesson === playlist.lesson && pta.getEpoch() - playlist.lastUpdated < 1800) {
            initFlowPlayer();
            initCuePoints();
        } else {
            $.getJSON("/video/" + level + "/" + lesson + "/" + gender + "/playlist", function (playlistData) {
                playlist = {
                    data: playlistData,
                    level: level,
                    lesson: lesson,
                    lastUpdated: pta.getEpoch()
                }
                initFlowPlayer();
                initCuePoints();
            });
        }
    }

    function refreshTopic(current_clip, time) {
        var i;
        var chapters = $("#chapters > a");
        for (i = 0; i < chapters.length; i++) {
            var chapter = chapters[i];
            // Ideally, we would use chapter.dataset.cuepoint but IE9 doesn't support it
            clip_index = chapter.getAttribute("data-clip");
            cuetime = chapter.getAttribute("data-cuepoint");
            if (time < cuetime || clip_index > current_clip.index) {
                break;
			}
        }
        $("#chapters > a").removeAttr("class");
        $("#chapters > a:nth-child(" + i + ")").attr("class", "active");
    }

    function loadClip(clip_idx, cuepoint) {
        seek =0;
        var player = flowplayer($("#player"));
        if (player.video.index !== clip_idx) {            
            player.play(clip_idx).seek(cuepoint);
            seek = cuepoint;
        } else {
            player.seek(cuepoint).play();
        }
        $("#chapters > a").removeAttr("class");
        $("#chapters>a[data-clip="+clip_idx+"][data-cuepoint="+cuepoint+"]").attr("class", "active");
    }
}(window.pta = window.pta || {}, jQuery));
