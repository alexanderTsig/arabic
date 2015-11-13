(function(pta, $, undefined) {
	pta.dispatchTable['lessons'] = function(pjax) {
		if (!pjax || pta.isFirstTimeLoaded()) {
			$('#maincontent').on('click', 'input#show-old-lessons', function() {
				var enable = $(this).prop('checked');
				pta.user.pref.view.oldLessons = enable;
				refreshList();
				$.getJSON("/api/setpref/view:oldLessons/" + enable);
			});

			$('#maincontent').on('click', 'input#arabic-titles', function() {
				var enable = $(this).prop('checked');
				pta.user.pref.view.arabicText = enable;
				showArabicTitles(enable);
				$.getJSON("/api/setpref/view:arabicText/" + enable);
			});

			$('#maincontent').on('click', 'input#starred', function() {
				var enable = $(this).prop('checked');
				pta.user.pref.view.filterByStar = enable;
				refreshList();
				$.getJSON("/api/setpref/view:filterByStar/" + enable);
			});

			// FIXME: Did I comment this? (Kerin)
			/* yepnope({
				load: [
					'/js/plugin/jquery.transit.js',
					'/js/plugin/jquery.easie-min.js'
				],
				complete: function() {
					$('#maincontent').on('click', '.lesson > h2', function() {
						$(this).children("img").transition({ rotate: $(this).next("div").is(':visible') ? 0 : 90 + 'deg' }, 350, "cubic-bezier(.02, .01, .47, 1)");
						$(this).next("div").toggle({ duration: 350, easing: $.easie(.02, .01, .47, 1) });
					});
				}
			}); */
						
			$('#maincontent').on('click', '.lesson > h2', function() {
				$(this).children("img").transition({ rotate: $(this).next("div").is(':visible') ? 0 : 90 + 'deg' }, 350, "cubic-bezier(.02, .01, .47, 1)");
				$(this).next("div").toggle({ duration: 350, easing: $.easie(.02, .01, .47, 1) });
			});

			$('#maincontent').on('click', '.lesson.locked a.btn', function(e) {
				return false;
			});

			$('#maincontent').on('click', '.star', function(e) {
				e.stopPropagation();

				var lesson = $(this).parents(".lesson");
				var lesson_num = $(this).parents('.lesson').data("lesson");

				if (! lesson.is('.starred')) {
					lesson.addClass("starred");
					$.ajax({
						type: "POST",
						url: "/api/star",
						data: {
							level:  pta.level,
							lesson: lesson_num
						},
						/* success: function(response) {
							if (response.status === 0)
								lesson.addClass("starred");
						}, */
						dataType: "json"
					});
				} else {
					lesson.removeClass("starred");
					$.ajax({
						type: "POST",
						url: "/api/unstar",
						data: {
							level:  pta.level,
							lesson: lesson_num
						},
						/* success: function(response) {
							if (response.status === true)
								lesson.removeClass("starred");
						}, */
						dataType: "json"
					});
				}
			});
		}

		$('.lesson.locked a.btn').addClass('disabled');

		// If the level is incomplete then expand the next available lesson
		if (pta.level === pta.user.level) {
			$('.lesson[data-lesson="' + pta.user.maxLesson + '"] > div')
				.show()
				.parent()
				.find("img")
				.transition({ rotate: '90deg' }, 0);
		}
		$('input#show-old-lessons').prop('checked', (pta.user.progress == 100 || pta.user.level > pta.level) | pta.user.pref.view.oldLessons);
		$('input#show-old-lessons').prop("disabled", (pta.user.progress == 100 || pta.user.level > pta.level));
		$('input#arabic-titles').prop('checked', pta.user.pref.view.arabicText);
		$('input#starred').prop('checked', pta.user.pref.view.filterByStar);
		showArabicTitles(pta.user.pref.view.arabicText);
		refreshList();
	}

	function showPreviousLessons(enable) {
		$('.hero-unit').toggle(!enable);
		$('.lesson.passed').toggle(enable);
		$('.lesson.skipped').toggle(enable);
		$('.lesson.missed').toggle(enable);
	}

	function showArabicTitles(enable) {
		$('.lesson > h2 span[lang=en]').toggle(!enable);
		$('.lesson > h2 span[lang=ar]').toggle(enable);

		// Not all lessons have an Arabic description
		if ($('.lesson > div span[lang=ar]').length) {
			$('.lesson > div span[lang=en]').toggle(!enable);
			$('.lesson > div span[lang=ar]').toggle(enable);
		}
	}

	function filterByStarred(enable) {
		if (enable) {
			$('.lesson:not(.starred)')
				.filter(':visible')
				.toggle(false);
		} else {
			$('.lesson:not(.starred)').toggle(true);
		}
	}

	function refreshList() {
		if (pta.user.progress == 101 || pta.user.level > pta.level || pta.user.pref.view.oldLessons) {
			if (pta.user.pref.view.filterByStar) {
				showPreviousLessons(true);
				filterByStarred(true);
			} else {
				$('.lesson').toggle(true);
				$('.hero-unit').toggle(false);
			}
		} else {
			filterByStarred(pta.user.pref.view.filterByStar);
			showPreviousLessons(false);
		}
	}

}(window.pta = window.pta || {}, jQuery));
