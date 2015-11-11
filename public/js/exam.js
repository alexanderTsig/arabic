function quiz_DoFSCommand(command, args) {
    pta.articulateCommand(command, args);
}

function player_DoJSCommand(command, args) {
    pta.articulateCommand(command, args);
}

(function(pta, $, undefined) {
    var articulate = {};
    var reviewableQuestions = [];
    pta.dispatchTable['exam'] = function(pjax) {
        $('#alert').css('padding-right', '14px');
        $('li.exam').attr('class', 'active').click(false);
        $("a.full-screen-btn").click(function () {
            $(".stageplayer").toggleClass("fullscreen")
        });
        resetTest();
        $.getJSON('/api/level/' + pta.level + '/' + pta.lesson + '/questions/reviewable', function(questions) {
            // Show review prompt for first question, if one is available.
            reviewableQuestions = questions;
            if (pta.level == 4 || reviewableQuestions.indexOf("1") >= 0) {
                showReviewPrompt(1);
			}
        });
        $('#review button').click(function() {
            $('#review').modal('hide');
        });
        var usingModernIE = (!!navigator.userAgent.match(/(MSIE 10|Trident.+; rv:11\.)/));
        var swf_params = {
            allowScriptAccess: "always",
            scale:             "default",
            quality:           "best",
            bgcolor:           "#ffffff",
            wmode:             "direct",
            flashvars:         "vHtmlContainer=true&vUseFSCommand=" + (usingModernIE ? "false" : "true") + "&vLMSPresent=false&vAOSupport=false"
        }
        var player = $('#quiz');
        var swf_attrs = {
            "id":          "quiz",
            "name":        "quiz",
            "data-nonce":  player.data('nonce')
        }
        swfobject.embedSWF(player.data('url'), "quiz", "720", "450", "10.1.0", "playerProductInstall.swf", {}, swf_params, swf_attrs, onMovieLoad);
    }

    function onMovieLoad(e) {
        // Activate the correct tab and disable its click action
        $('li.exam').attr('class', 'active').click(false);

        // Resize the player now that swfobject has initialized it
        pta.setAspectRatio($('#quiz'), 4, 3);

        pta.windowResizeEvent({
            during: function() {
                // Render the Flash object invisible (otherwise it will lock up and crash)
                if (! $('#review').is(':visible')) {
                    $('#quiz').css('visibility', 'hidden');
				}
            },
            after: function() {
                if (! $('#review').is(':visible')) {
                    pta.setAspectRatio($('#quiz'), 4, 3);
				}
            }
        });

        $('#review').on('shown', function() {
            /*var swf_params = {  
                allowScriptAccess: "never",
                scale:             "default",
                quality:           "best",
                bgcolor:           "#f2f2f2",
                wmode:             "direct"
            }

            var swf_attrs = {
                "id":   "review-player",
                "name": "review-player"
            }

            var url = "//ptaexercise.s3.amazonaws.com/flash/" + pta.level + "/content_review/" + pta.lesson + "/movie.swf";
    
            swfobject.embedSWF(url, "review-player", "720", "540", "10.1.0", "playerProductInstall.swf", {}, swf_params, swf_attrs);
            */

            var video_html = "<iframe src='http://ptaexercise.s3.amazonaws.com/v2/" + pta.level + "/content_review/" + pta.lesson + "/story_html5.html' style='border:none' width='100%' height='100%'></iframe>";

            $('#review-player').html(video_html);
        });

        $('#review').on('hidden', function() {
            $('#quiz').css('visibility', 'visible');
        });

    }

    pta.articulateCommand = function(command, args) {
        // WARNING: IE10 doesn't seem to set the delimeter succesfully until the first full page refresh (hence the fallback delimiter).
        var argv = ("delimeter" in articulate.conf) ? args.split(articulate.conf.delimeter) : args.split('|~|');
        switch (command) {
            case "CC_SetInteractionDelim":
                articulate.conf.interactionDelimeter = args;
                break;
            case "CC_SetDelim":
                articulate.conf.delimeter = args;
                break;
            /* case "CC_StoreQuestionResult":
                storeTestResponse(argv);
                break; */
            case "CC_StoreQuizResult":
                submitTestResults(argv);
                break;
            case "CC_ClosePlayer":
                if (typeof pta.finishUrl === "undefined") {
                    pta.finishUrl = "/level/" + pta.level;
				}
                window.location = pta.finishUrl;
                break;
            case "CC_LogInteraction":
                showReviewPrompt(lastQuestionNumber(argv));
                break;
            default:
                console.log(command + " handler not implemented!");
                break;
        }
    }

    function lastQuestionNumber(argv) {
        var matches = argv[2].match(/^Question(\d+)/);
        return (matches) ? parseInt(matches[1]) + 1 : undefined;
    }

    function showReviewPrompt(question) {
        question = question.toString();
        /*if (pta.level == 4 || reviewableQuestions.indexOf(question) >= 0) {
            $('#alert').attr("class", "alert alert-info clearfix")
                .html('<i class="icon icon-lightbulb"></i>&ensp;This question concerns reading/conversational skills. <a href="#" style="font-weight:bold;text-decoration:underline">Click here</a> if you need to review the material.').show();

            $('#alert a').click(function() {
                showReviewPopup();
            });

            $('#video_content').height($('#video_content').height() - $('#alert').height() - 40);
        } else {
            $('#alert').attr("class", "alert").html('').hide();
        }*/
        $('#alert').attr("class", "alert").html('').hide();
    }

    function showReviewPopup() {
        $('#quiz').css('visibility', 'hidden');
        $('#review').modal();
    }

    function resetTest() {
        articulate.conf = {};
        articulate.results = {};
        pta.finishUrl = undefined;
        reviewableQuestions = [];
    }

    function submitTestResults(argv) {
        var test = {
            result:       argv[0],
            score:        argv[1],
            passingScore: argv[2],
            minScore:     argv[3],
            maxScore:     argv[4],
            points:       argv[5],
            maxPoints:    argv[6],
            title:        argv[7]
        }
        var submit_url = "//portal.pathtoarabic.com/level/" + pta.level + "/" + pta.lesson + "/exam";
        $.post(submit_url, {
            id:            pta.user.id,
            nonce:         $("#quiz").data("nonce"),
            score:         test.score,
            passing_score: test.passingScore,
            min_score:     test.minScore,
            max_score:     test.maxScore,
            points:        test.points,
            max_points:    test.maxPoints
        }, function(response) {
            if (response.code !== 0) {
                $('#alert')
					.attr("class", "alert alert-error")
					.html('We are very sorry but your results could not be submitted. <a href="/support/contact">Contact Support</a>')
					.show();
                return;
            }
            if (response.pass === true) {
                if (response.level > response.oldlevel && response.level > response.startlevel) {
                    $('#alert')
						.attr("class", "alert alert-success")
						.html('Well done! You have successfully activated the <a href="/level/' + response.level + '>next level</a>.')
						.show();
                }
                else {
                    $('#alert')
						.attr("class", "alert alert-success")
						.html('Well done! You have successfully activated the <a href="/level/' + response.level + '/' + response.lesson + '/video">next lesson</a>.')
						.show();
                }
                var nextlesson = '/level/' + response.level + '/' + response.lesson + '/video';
                pta.finishUrl = "/level/" + response.level;
                $(".next .pagecontroll").attr("href", nextlesson);
                $(".next .pagecontroll").removeClass("disabled");
            }
            else {
                $('#alert')
					.html('Your score didn\'t quite make the grade. Perhaps you would like to <a href="#" onClick="window.location.reload()">try again?</a>')
					.show();
            }
            pta.refreshUserData(true);
            pta.updateSidebar();
            resetTest();
        });
    }

    /* function storeTestResponse(argv) {
        if (argv.length !== 10) {
            console.log("error"); // FIXME: Bad error handling
		}

        var response = {
            questionNum:     parseFloat(argv[0]),
            question:        argv[1],
            result:          argv[2],
            correctResponse: argv[3],
            studentResponse: argv[4],
            points:          argv[5],
            interactionId:   argv[6],
            objectiveId:     argv[7],
            type:            argv[8],
            latency:         argv[9]
        }

        var i = 0;

        // Check whether a response for the question was already submitted and needs to be overwritten
        for (i = 0; i < articulate.results.length; i++) {
            if (articulate.results[i].questionNum === response.questionNum && articulate.results[i].question === reponse.question) {
                break;
			}
        }
        articulate.results[i] = response;
    } */
}(window.pta = window.pta || {}, jQuery));
