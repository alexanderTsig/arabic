(function(pta, $, undefined) {
	pta.dispatchTable['support'] = function(pjax) {
		// Add click handlers for the tiles per the links defined in the navbar
		$(".tile").each(function(i) {
			$(this).click(function() {
				window.location = $("ul.nav a:eq(" + i + ")").attr("href");
			}).addClass('clickable');
		});
	}
}(window.pta = window.pta || {}, jQuery));
