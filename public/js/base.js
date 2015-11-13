(function(pta, $, undefined) {
	pta.page = 'undefined';
	pta.user = {};
	pta.userCopy = {};
	pta.ordinalMonth = [
		'January',
		'February',
		'March',
		'April',
		'May',
		'June',
		'July',
		'August',
		'September',
		'October',
		'November',
		'December'
	];

	var pageLoadCount = {};
	var resizeEvent;

	pta.dispatchTable = {
		base: function(pjax) {
			// Set the body class attribute to the name of the template (can be useful in constructing CSS selectors)
			$('body').addClass(pta.page);
			
			// Lazy initialisation of of the pta.user object
			if (Object.keys(pta.user).length === 0) {
				pta.refreshUserData();
			}
			if (!pjax || pta.isFirstTimeLoaded('base')) {
				pta.initSidebar();
			}

			pta.updateSidebar();
			initNote();
			
			// If the pathname begins with /level/[0-9] then truncate to eight characters
			var url = location.pathname;
			if (url.length >= 8 && url.substr(0,7) === "/level/") {
				url = url.substr(0,8);
			}
			if (url.substr(0,9) === "/amember4") {
				url = "/amember4/member";
			}

			// Add the active class to the navigation button and ensure that the containing accordion-body remains expanded
			$('.accordion-inner > a[href="' + url + '"]').addClass('active')
				.parents('.accordion-body').addClass('in');

			// Rotate the arrow to point down for the active accordion. FIXME: using addClass('active') breaks the jquery.transit plugin. Why?
			$('.accordion-body[class~=in]').prev('div').children('img').transition({ rotate: '90deg' }, 0);
		}
	}

	pta.updateDocument = function(pjax) {
		// Unbind all window resize event handlers
		$(window).unbind('resize');
		resizeEvent = undefined;

		determineLesson();
		incrementPageLoadCount('base');
		pta.dispatchTable['base'](pjax);
		yepnope({
			test: pta.page in pta.dispatchTable,
			nope: ['/js/' + pta.page + '.js'],
			complete: function() {
				incrementPageLoadCount(pta.page);
				pta.dispatchTable[pta.page](pjax);
			}
		});
		pta.setTileDimensions();
		$('.tiles').css('visibility', 'visible');
		if (typeof resizeEvent === 'undefined') {
			pta.windowResizeEvent();
		}
		attachTipHandlers();
	}

	function attachTipHandlers() {
		$('.tip .btn-primary').each(function() {
			$(this).click(function() {
				var alert = $(this).parents('.alert');
				$.getJSON('/api/tip/' + alert.data('tip') + '/dismiss');
				alert.alert('close');
			});
			$(this).next('.btn').click(function() {
				var alert = $(this).parents('.alert');
				$.getJSON('/api/tip/' + alert.data('tip') + '/defer');
				alert.alert('close');
			});
		});
		$('.alert.tip').alert();
	}

	pta.windowResizeEvent = function(event) {
		if (typeof event === 'function') {
			resizeEvent = { during: undefined, after: event };
		}
		else {
			resizeEvent = (typeof event === 'object') ? event : {};
		}
		yepnope({
			load: ['/js/plugin/jquery.ba-dotimeout.min.js'],
			complete: function(event) {
				$(window).on('resize', function() {
					$('.tiles').css('visibility', 'hidden'); // FIXME: should also apply to leaderboard
					$('#leaderboard').css('visibility', 'hidden');
					if (typeof resizeEvent.during === 'function') {
						resizeEvent.during();
					}
					$.doTimeout('resize', 300, function() {
						pta.setTileDimensions();
						if (typeof resizeEvent.after === 'function') {
							resizeEvent.after();
						}
						$('.tiles').css('visibility', 'visible');
						$('#leaderboard').css('visibility', 'visible');
					});
				});
			}
		});
	}

	pta.isFirstTimeLoaded = function(page) {
		page = typeof page !== 'undefined' ? page : pta.page;
		// This is intended to avoid unnecessary repeat usage of jQuery's .on binding method with PJAX
		return (page in pageLoadCount && pageLoadCount[page] == 1)
	}

	function incrementPageLoadCount(page) {
		if (page in pageLoadCount) {
			pageLoadCount[page]++;
		} else {
			pageLoadCount[page] = 1;
		}
	}

	pta.setTileDimensions = function(margin) {
		margin = typeof margin !== 'undefined' ? margin : 24;
		$(".tiles").each(function() {
			var parent_width = $(this).parent().width();
			var tile_count = $(this).children(".tile").length;

			// FIXME: Overflow occurs in Firefox for some parent_width values
			parent_width--;

			// Determine the tile width (being careful to take into account the desired tile margin)
			var tile_width = Math.floor((parent_width - margin * (tile_count - 1)) / tile_count);

			// Iterate over each tile
			$(this).children('.tile').each(function(i) {
				// The aspect ratio may be set with a data-ratio attribute. If it's set to zero, the height will not be altered.
				var aspect_ratio = $(this).data('ratio');
				if (typeof aspect_ratio === 'undefined') {
					aspect_ratio = 1
				};
				if (! pta.isNumber(aspect_ratio)) {
					aspect_ratio = 0
				};

				// Set the tile width as calculated above
				$(this).css('width', tile_width);

				// Set the tile height to the tile width, minus the height of the caption area (otherwise it won't be square).
				// Also set the width to ensure that content can be centered even with display:table-cell in effect.
				var content_height = aspect_ratio !== 0
					? Math.round((tile_width - $(this).children('.caption').outerHeight()) * aspect_ratio)
					: 'auto';

				$(this).children('.content').css({
					'height': content_height,
					'width': tile_width
				});

				// Apply right margin to all but the last tile
				if (i < tile_count - 1) {
					$(this).css('margin-right', margin);
				}

				var imgsrc = $(this).data("imgsrc");
				if (imgsrc) {
					$(this)
						.children("img")
						.attr("src", imgsrc + "/" + tile_width);
				}
			});

			// $(this).addClass('clearfix').css({ 'visibility': 'visible', 'margin-bottom': margin });
			$(this)
				.addClass('clearfix')
				.css('margin-bottom', margin);
		});

		// FIXME: document this section
		$('.tile').each(function() {
			var ratio = $(this).data('ratio').toString();
			var row_id = (ratio.substr(0, 1) === '#') ? ratio.substr(1, ratio.length  - 1) : undefined;
			if (row_id) {
				var max_content_height = 0;
				$('#' + row_id + ' .tile > .content').each(function() {
					if ($(this).height() > max_content_height) {
						max_content_height = $(this).outerHeight();
					}
				});
				var tile_content = $(this).children('.content');
				if (tile_content.outerHeight() < max_content_height) {
					tile_content.css('height', max_content_height);
				}
			}
		});
	}

	pta.supportsHtmlVideo = function() {
		return !!document.createElement('video').canPlayType;
	}

	pta.supportsH264 = function() {
		if (! pta.supportsHtmlVideo()) {
			return false;
		}
		var v = document.createElement("video");
		return v.canPlayType('video/mp4; codecs="avc1.42E01E, mp4a.40.2"');
	}

	// NOTE: This is dead code (for now)
	pta.isInteger = function(n) {
		return !isNaN(parseInt(n)) && isFinite(n);
	}

	pta.isNumber = function(n) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	}

	pta.getEpoch = function() {
		return Math.round(new Date().valueOf() / 1000);
	}

	pta.recursePreferences = function(callback) {
		recurseObject(pta.user.pref, callback);
	}

	pta.setPreference = function(pref, value) {
		// Backup the pta.user object
		if (Object.keys(pta.userCopy).length === 0) {
			pta.userCopy = $.extend(true, {}, pta.user)
		}

		// Find the relevant key and update its value
		recurseObject(pta.user.pref, function(obj, key) {
			obj[key] = value;
		}, pref);
	}

	pta.undoPreferences = function() {
		// Restore the pta.user object
		if (Object.keys(pta.userCopy).length > 0) {
			pta.user = $.extend(true, {}, pta.userCopy)
			pta.userCopy = {};
		}
	}

	// FIXME: is this dead code?
	pta.submitPreferences = function() {
		// If successful ...
		pta.userCopy = {};
	}

	pta.setAspectRatio = function(obj, x, y, visibleObj) {
		visibleObj = typeof visibleObj !== "undefined" ? visibleObj : obj;
		obj.css("width", obj.parent("div").width());
		obj.css("height", Math.round(obj.width() * y / x).toString() + 'px');
		visibleObj.css('visibility', 'visible');
	}

	pta.initSidebar = function() {
		// Rotate the arrow to point right for collapsed menus with animation
		$('.accordion-body').on('show', function() {
			$(this).prev('div').children('img').transition({ rotate: '90deg' }, 350);
		});

		// Rotate the arrow to point down for collapsed menus with animation
		$('.accordion-body').on('hide', function() {
			$(this).prev('div').children('img').transition({ rotate: '0deg' }, 350);
		});

		$('#navbar a:not([href$="/logout"])').click(function(e) {
			// Disable clicking a locked level
			if ($(this).parent('div').hasClass('locked')) {
				return false;
			}
			$('#navbar a').removeClass('active');
			$(this).addClass('active');
		});

		$('#navbar a[href$="/logout"]').click(function(e) {
			e.preventDefault();
			confirmSignout($(this).attr('href'));
		});
	}

	pta.updateSidebar = function() {
		// Lock inaccessible levels
		$('#nav-lessons .accordion-inner > a').each(function(i) {
			var level = i + 1;
			if (level >= pta.user.startLevel && level <= pta.user.level) {
				$(this)
					.parent('div')
					.addClass('unlocked')
					.removeClass('locked');
			}
			else {
				$(this)
					.parent('div')
					.addClass('locked')
					.removeClass('unlocked');
			}
		});
	}

	function confirmSignout(signoutUrl) {
		bootbox.confirm("Are you sure that you want to sign out?", function(result) {
			if (result) {
				window.location = signoutUrl;
			}
		});
	}

	function determineLesson() {
		var matches = location.pathname.match(/^\/level\/(\d+)\/?(\d+)?/);
		if (matches) {
			pta.level = parseInt(matches[1]);
			pta.lesson = parseInt(matches[2]);
		} else {
			pta.level = undefined;
			pta.lesson = undefined;
		}
	}

	pta.refreshUserData = function(bypassCache) {
		bypassCache = typeof bypasscache !== "undefined" ? bypassCache : false;
		$.ajax({
			type: 'GET',
			url: '/api/user/data?nocache=' + (bypassCache ? '1' : '0'),
			async: false,
			success: function(userData) {
				pta.user = userData;
			}
		});
	}

	function recurseObject(obj, callback, matchKey) {
		if (! obj) {
			return;
		}
		for (var key in obj) {
			if (typeof obj[key] === "object") {
				recurseObject(obj[key], callback, matchKey);
			} else if (typeof obj[key] !== "function") {
				if (typeof matchKey !== "undefined") {
					if (key === matchKey) {
						callback(obj, key);
						break;
					}
				} else {
					callback(obj, key);
				}
			}
		}
	}

	function initNote() {
		var note = $('#note');
		if (! note.length) {
			return false;
		}

		// Obtain the note text
		$.ajax({
			type: "GET",
			url: "/api/get/note/" + pta.level + "/" + pta.lesson,
			success: function(response) {
				note.text(response);
			}
		});

		// Prime the note for automatic submission
		note.keyup(function() {
			delay(function() {
				result = $.post("/api/post/note/" + pta.level + "/" + pta.lesson, {
					"text": note.val()
				});
			}, 1000);
		});
	}

}(window.pta = window.pta || {}, jQuery));

var delay = (function() {
	var timer = 0;
	return function(callback, ms) {
		clearTimeout(timer);
		timer = setTimeout(callback, ms);
	};
})();

if (location.pathname.substr(0,9) !== "/amember4") {
	var pjaxOptions = { timeout: 5000 };

	// Use PJAX for all navbar links except those that navigate into /amember4
	$(document).pjax('.accordion a:not([href^="/amember4"])', "#maincontent", pjaxOptions);

	/* DEAD CODE
	$(document).pjax(".lesson .btn", "#maincontent", pjaxOptions);
	$(document).pjax('.navbar a', "#maincontent", pjaxOptions);
	*/
} else {
	pta.page = "membership";
}

$(document).ready(function() {
	pta.updateDocument(false);
	if (location.pathname.substr(0,9) !== "/amember4") {
		$('#maincontent').on('pjax:end', function() {
			pta.updateDocument(true);
		});
	}
});
