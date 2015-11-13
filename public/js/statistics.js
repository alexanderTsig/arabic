(function(pta, $, undefined) {
	pta.dispatchTable['statistics'] = function(pjax) {
		yepnope({
			load: ['/js/highcharts/js/highcharts.js'],
			complete: function() {
				pta.updateChart(pta.user.level);
				$('ul.nav-tabs > li').click(function() {
					var i = $(this).index();
					if (! $('#chart').is(':empty')) {
						pta.chart.destroy();
					}
					pta.updateChart(i + 1);
				});
			}
		});
	}

	pta.updateChart = function(level) {
		$.getJSON('/api/graph/level/' + level, function(chartData) {
			if (!chartData.empty) {
				$('#chart-empty').hide();
				$('#chart').show();
				pta.chart = new Highcharts.Chart(chartData);
			}
			else {
				$('#chart').hide();
				$('#chart-empty')
					.html('There is no data to be graphed for Level ' + level + ' yet.')
					.show();
			}
		});
		$('ul.nav-tabs > li').removeClass('active');
		$('ul.nav-tabs > li:nth-child(' + level + ')').addClass('active');
	}
}(window.pta = window.pta || {}, jQuery));
