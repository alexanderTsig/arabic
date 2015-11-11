(function(pta, $, undefined) {
	pta.dispatchTable['support-faq'] = function(pjax) {
		$('#category-nav').change(function() {
			location.href = '#' + $(this).find(':selected').data('anchor');
		});
	}
}(window.pta = window.pta || {}, jQuery));
