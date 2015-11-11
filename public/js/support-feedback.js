(function(pta, $, undefined) {
	pta.dispatchTable['support-feedback'] = function(pjax) {
		$('form').find('textarea[name="comment"]').bind('input cut paste', function() {
			$('button[type="submit"]').prop("disabled", $(this).val().length === 0);
		});

		$('form').submit(function(e) {
			e.preventDefault();

			$.post($(this).attr('action'), { 
				nonce:            $(this).data("nonce"),
				email:            $(this).find('input[name="email"]').val(),
				phone:            $(this).find('input[name="phone"]').val(),
				category:         'feedback',
				verbose_category: 'Feedback',
				comment:          $(this).find('textarea[name="comment"]').val()
			}, function(response) {
				if (!response || response.status != 0) {
					$('#alert').html('Your submission failed. Sorry about that. Please refresh the page and try again.').show();
				} else {
					$('#alert').attr("class", "alert alert-success").html('Thank you for your feedback. We really appreciate it!').show();
					$('form').find('textarea[name="comment"]').val('');
					$('button[type="submit"]').prop("disabled", true);
				}
			}, 'json');
		});
	}
}(window.pta = window.pta || {}, jQuery));
