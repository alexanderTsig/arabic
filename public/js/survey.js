(function(pta, $, undefined) {
	var rating;

	function canSubmit() {
		return (rating && $("input:radio[name=gender]:checked").val() && $("input:checkbox:checked").length > 0);
	}

	function refreshSubmitButton() {
		// Activate the submit button if appropriate
		$('button[type="submit"]').prop("disabled", ! canSubmit());
	}

	pta.dispatchTable['survey'] = function(pjax) {
		$(".tiles.selectable > .tile").click(function() {
			$(this).siblings("div").removeClass("selected");
			$(this).addClass("selected");

			// Caculate rating on a scale of 1 - 5 depending on selected tile
			rating = $(this).index() + 1;

			// Reveal a tip describing the consequences of choosing the rating
			$(".rating-tips > p:nth-child(" + rating + ")").show();
			$(".rating-tips > p:not(:nth-child(" + rating + "))").hide();

			refreshSubmitButton();
		});

		$('input[type="radio"]').click(function() {
			refreshSubmitButton();
		});

		$('input[type="checkbox"]').click(function() {
			if ($(this).val() === "Affiliate Referral") {
				$(this).is(":checked") ? $('#affiliate-search').show() : $('#affiliate-search').hide();
			}
			refreshSubmitButton();
		});

		$('#affiliate-search').typeahead({
			source: function(query, process) {
				if (query.length < 3)
					return false;

				$.ajax({
					type: "POST",
					url: "/api/affiliate/search",
					data: { searchterm: query },
					success: function(affiliates) {
						var items = [];
						$.each(affiliates, function(i, key) {
							items.push(key[key.matched_field]);
						});
						process(items);
					},
					dataType: "json"
				});		
			}
		});

		$("form").submit(function(e) {
			e.preventDefault();
		});

		$.getJSON('/api/teachers', function(teachers) {
			var delay = 6000;
			var fadeTime = 2000;

			$.each(teachers, function(i, teacher) {
				$("#teachers > div." + teacher.gender.toLowerCase()).append('<img src="/img/mugshot/' + teacher.name.toLowerCase() + '.jpg">');
			});
			
			$("#teachers > div").each(function() {
				$(this).children("img:gt(0)").hide();
			});

			setInterval(function() {
				$("#teachers > div").each(function() {
					$(this).children("img:first-child").fadeOut(fadeTime).next("img").fadeIn(fadeTime).end().appendTo($(this));
				});
			}, delay + fadeTime);
		});

		$('button[type="submit"]').parent('form').submit(function(e) {
			e.preventDefault();

			$.ajax({
				type:  "POST",
				url:   "/api/survey",
				async:  true,
				data: JSON.stringify({
					gender:   $("input:radio[name=gender]:checked").val(),
					rating:   rating,
					referred: $("input:checkbox:checked").map(function() { return this.value; }).get()	
				}),
				success: function() {
					window.location = "/home";
				},
				dataType: "json"
			});
		});
	}
}(window.pta = window.pta || {}, jQuery));
