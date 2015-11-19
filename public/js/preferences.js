(function (pta, $, undefined) {
	pta.dispatchTable['preferences'] = function (pjax) {
		pta.recursePreferences(function (obj, key) {
			updateForm(key, obj[key]);
		});
		
		$('button.underchange').prop('disabled', true);
		
		if (! pjax || pta.isFirstTimeLoaded()) {
			$('body').on('change', '#preferences input[type="radio"]', function (e) {
				// $('#alert').hide();
				var name = $(this).attr("name");
				if(name != "level"){
					pta.setPreference($(this).attr('name'), $(this).val());
				}else{
					pta.user.rating = {
						"level": $(this).attr("data-level"),
						"lesson" : $(this).attr("data-lesson")
					};
				}
				$('button').prop('disabled', false);
			});
			$('body').on('change', '#preferences input[type="checkbox"]', function (e) {
				// $('#alert').hide();
				pta.setPreference($(this).attr('id'), $(this).prop('checked'));
				$('button').prop('disabled', false);
			});
		}

		/* DEAD CODE
		$('input[type="radio"][name="level"]').change(function () {
			var level = $(this).attr('value');
			var lesson = $(this).attr('data-lesson');
			if (level !== pta.user.startLevel || (level === pta.user.startLevel && lesson !== pta.user.startLesson)) {
				$('#alert')
				.html("WARNING: If youchoose to switch your start level then all existing test results will be destroyed!")
				.attr("class", "alert alert-error")
				.show().delay(10000).fadeOut();
			} else {
				$('#alert').hide();
			}
		}); //.eq(pta.user.level - 1).prop('checked', true);
		*/

		$('form').submit(function (e) {
			e.preventDefault();
			$.post($(this).attr('action'), JSON.stringify(pta.user), function (response) {
				if (response.ok) {
					$('#alert')
						.html("Your settings have been saved.")
						.attr("class", "alert alert-info")
						.show()
						.delay(10000)
						.fadeOut();
					$('button.underchange').prop('disabled', true);
					pta.userCopy = {};
				} else {
					$('#alert')
						.html("Your settings were not successfully saved.")
						.attr("class", "alert alert-error")
						.show();
				}
			});
		});

		$('button.underchange').click(function (e) {
			e.preventDefault();
			if ($(this).prop('disabled') === false) {
				pta.undoPreferences();
				pta.recursePreferences(function (obj, key) {
					updateForm(key, obj[key]);
				});
				$('button.underchange').prop('disabled', true);
			}
		});

		// FIXME: Needs review. Why are <input> tags being abused to show images?
		$("#background_image").on("special-change", function () {
			var url = $(this).val();
			pta.setPreference("background_image", url);
			if (url == "") {
				var defaultImage = $("input[name=default_background_image]").val();
				$('.backImg').attr('src', defaultImage);
			} else {
				$('.backImg').attr('src', url);
			}
		})

		var time = function () {
			return '?' + new Date().getTime()
		};

		yepnope({
			// '/js/imgPicker/assets/css/imgpicker.css',
			// '/js/imgPicker/assets/css/bootstrap.css',
			load: [
				'/js/imgPicker/assets/js/jquery.Jcrop.min.js',
				'/js/imgPicker/assets/js/jquery.imgpicker.js'
			],
			complete: function() {
				// FIXME: Hard-coding the aspect ratio makes no sense (server doesn't know viewport dimensions).
				// Also, #imageModal is a questionable name.
				$('#imageModal').imgPicker({
					url: '/js/imgPicker/server/upload_bg.php',
					aspectRatio: 1.45,
					deleteComplete: function() {
						var defaultImage = $("input[name=default_background_image]").val();
						$("input[name=background_image]").val('');
						$('.backImg').attr('src', defaultImage);
						$('button[type=submit]').prop('disabled', false);
						setTimeout(function () {
							$("#background_image").trigger("special-change");
						})
						this.modal('hide');
					},
					uploadSuccess: function (image) {
						// Calculate the default selection for the cropper
						var select = (image.width > image.height)
								? [ (image.width - image.height) / 2, 0, image.height, image.height ]
								: [ 0, (image.height - image.width) / 2, image.width, image.width ];
						this.options.setSelect = select;
					},
					cropSuccess: function (image) {
						var url = image.versions.bg.url;
						$('.backImg').attr('src', url + time());
						$("input[name=background_image]").val(url);
						$('button[type=submit]').prop('disabled', false);
						setTimeout(function() {
							$("#background_image").trigger("special-change");
						})
						this.modal('hide');
					}
				});

				// Single-click handler for built-in wallpaper (changes focus)
				$('a.thumbnail').click(function () {
					$('a.thumbnail').removeClass("selected");
					$(this).addClass("selected");
				})
				// Double-click handler for built-in wallpaper (chooses selected)
				$('a.thumbnail').dblclick(function () {
					chooseSelectedWallpaper();
				})
				// OK button click handler for built-in wallpaper (chooses selected)
				$('button.selectImg').click(function () {
					chooseSelectedWallpaper();
				})
			}
		});
		/* DEAD CODE
		$('.navbar-toggle').on('click', function () {
			$('.navbar-nav').toggleClass('navbar-collapse')
		});
		*/
	} 

	function updateForm(pref, value) {
		switch (typeof value) {
			case "boolean":
				// Set checkboxes
				$('#' + pref).prop('checked', value);
				break;
			case "string":
				var control = $('input#' + pref);
				// Set text fields (currently dead code)
				if (control.length) {
					control.val(value);
				} else {
					// Set radio buttons
					$('input[type="radio"][name="' + pref + '"]').each(function () {
						$(this).prop('checked', $(this).attr('value') === value);
					});
				}
				// Triggers special-change event  n so that the backround image thumbnail is shown.
				// FIXME: What is the event called special-change? Why not call a function?
				setTimeout(function () {
					$('input#' + pref).trigger("special-change");
				}, 100)
				break;
		}
		/* DEAD CODE
		// FIXME: bolted on - not behaving like a normal preference
		$('input[type="radio"][value="' + pta.user.startLevel + '"][data-lesson="' + pta.user.startLesson + '"]').prop('checked', 'true');
		*/
	}

	// Activates the wallpaper selected from the #myModal dialog (FIXME: #myModal is a stupid name)
	function chooseSelectedWallpaper() {
		if ($('a.thumbnail.selected').length > 0) {
			var url = $('a.thumbnail.selected img').attr("src");
			$('.backImg').attr('src', url);
			$("input[name=background_image]").val(url);
			$('button[type=submit]').prop('disabled', false);
			setTimeout(function () {
				$("#background_image").trigger("special-change");
			}, 100);
			$('#myModal').modal('hide')
		}
	}
}(window.pta = window.pta || {}, jQuery));
