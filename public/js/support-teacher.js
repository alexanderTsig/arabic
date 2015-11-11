(function(pta, $, undefined) {

	pta.dispatchTable['support-teacher'] = function(pjax) {
		yepnope({
			load: [
				'/lib/osk/jquery.caret.js',
				'/lib/osk/jquery.osk.js',
				'/js/plugin/jquery-ui-1.10.3.draggable.js'
			],
			complete: function() {
				loadKeyboard();
			}
		});

		/* Show the on-screen keyboard when the sample textarea is entered */
		$("#sample").mousedown(function() {
			var osk = $("#osk");

			if (! osk.is(":visible")) {
				var box = $("textarea[name=sample]");

				osk.css({ display: "block", visibility: "hidden" });

				osk.offset({
					 top: box.offset().top + 40,
					left: box.offset().left + 160 // FIXME: figure out a way to right-align to page margin
				});
			}

			osk.css({ visibility: "visible" });
		});

		$("#sample").blur(function() {
			// Don't allow the blur event to trigger if the cursor is over the on-screen keyboard
			setTimeout(function() {
				if ($("#osk").is(":hover")) {
					$("#sample").focus();
				} else {
					$("#osk").css({ visibility: "hidden" });
				}
			}, 10);
		});

		$('form').find('textarea[name="question"]').bind('input cut paste', function() {
			$('button[type="submit"]').prop("disabled", ! isFormComplete());
		});

		$('#sample').bind('input cut paste', function() {
			$('button[type="submit"]').prop("disabled", ! isFormComplete());
		});

		$('form').submit(function(e) {
			e.preventDefault();

			$.post($(this).attr('action'), {
				nonce:            $(this).data("nonce"),
				email:            $(this).find('input[name="email"]').val(),
				phone:            $(this).find('input[name="phone"]').val(),
				category:         "teacher",
				verbose_category: "Teacher Support",
				comment:          $(this).find('textarea[name="question"]').val(),
				arabic:	          $("#sample").val()
			}, function(response) {
				if (!response || response.status != 0) {
					$('#alert').html('Your submission failed. Sorry about that. Please refresh the page and try again.').show();
				} else {
					$('#alert').attr("class", "alert alert-success").html('Thank you for contacting us. We will get back to you in due course.').show();
					$('form').find('textarea[name="question"]').val('');
					$('#sample').val('');
					$('button[type="submit"]').prop("disabled", true);
				}
			}, 'json');
		});
	}

	function isFormComplete() {
		return ($('form').find('textarea[name="question"]').val().length > 0 || $('#sample').val().length > 0);
	}

	function loadKeyboard() {
		$('#osk').loadLayout('/lib/osk/layouts/arabic-101.json', function(key) {
			var box = $('textarea[name=sample]');
			var text = box.val();
			var pos = box.caret();
		
			switch (key) {
				case '\b':
					box.val(text.substring(0, pos-1) + text.substring(pos));
					box.caret(pos-1);
					break;
				case '\3':
					box.caret(pos-1);
					break;
				case '\4':
					box.caret(pos+1);
					break;
				default:
					box.val(text.substring(0, pos) + key + text.substring(pos));
					box.caret(pos+1);
			}

			$('button[type="submit"]').prop("disabled", ! isFormComplete());
		}).draggable();
			
	}

}(window.pta = window.pta || {}, jQuery));
