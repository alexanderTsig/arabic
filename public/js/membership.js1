(function(pta, $, undefined) {
	pta.dispatchTable['membership'] = function(pjax) {
		// Style tables
		$(".am-main table").addClass("table table-condensed table-striped");

		$("#menu-member").parent('li').empty();

		// Collect form validation/submission errors (for when AJAX validation methods are not being used)
		var errors = $("ul.errors > li");
		if (errors.length) {
			errors.hide();
			reformatErrors(errors);
		} else {
			var errors = $("span.error");
			if (errors.length)
				reformatErrors(errors);
		}
	
		// Enable drop-down menu for affiliate options
		$("li.has-children").each(function() {
			$(this).addClass("dropdown").css("position", "relative");
			$("#menu-aff").addClass("dropdown-toggle").attr("data-toggle", "dropdown").attr("href", "#").append('&nbsp;<b class="caret"></b>');
			// $(this).find("div.arrow").remove();
			$(this).children("ul").addClass("dropdown-menu");
		});

		// Fix overlapping content in affiliate stats page
		$('svg').parent('div').css('height', 'auto');
		$('tr.aff-details > td > div').removeAttr('style');

		reformatForms();

		// The coupon form field has its own form, which is not good for usability.
		// Make it clear to the user that they can advance without having to enter
		// a coupon code.
		if ($('#login-0').length === 0 && $('#fieldset-coupons').length) {
			var username = $('#username');
			if (username.length) {
				$('#alert-info').html("You have not yet completed your registration as " + username.html() + ". Click Next to make a payment.");
			} else {
				$('#alert-info').html("If you do not have a coupon code, leave it blank and click Next to continue the registration process.").show();
			}
		}

		$('#register').addClass('current-page');

		var agreement = $('div.agreement');
		agreement.html('<pre>' + agreement.html() + '</pre>');

		$.getJSON('/api/user/membership', function(membership) {
			// The am-active-invoice div just repeats information shown in the payment
			// history table. Get rid of it but keep the link to cancel a subscription.
			var active_invoice = $('div.am-active-invoice');
			if (active_invoice.length) {
				var url = $('a.cancel-subscription').attr('href');
				$('.am-active-invoice').empty();

				if (url) {
					$('table').after('<a class="btn btn-danger" href="#">Cancel Subscription</a>');
					$('a.btn-danger').click(function() {
						pta.cancelSubscription(url);
					});
				} else {
					if (membership.hasCancelled) {
						var date = new Date(membership.expiresOn * 1000);
						var dateString = date.getDate() +  ' ' + pta.ordinalMonth[date.getMonth()];
						$('#alert-info').html('Your subscription has been cancelled. You may renew once it expires on ' + dateString + '.').show();
					}
				}
			}

			// Display informational link for those who don't know what a CCV is
			$("#cc_code-0").after('&ensp;<i class="icon-question-sign"></i> <a id="ccv-popup" href="#">What is a card security code?</a>');
			$("#ccv-popup").click(function() {
				pta.ccvPopup();
			});

			// Remove the Add/Renew Subscription tab if the user still has access. It
			// doesn't display useful content under this circumstance.
			if (membership.isValid) {
				// $('#menu-add-renew').parent('li').empty();
			} else {
				if (location.pathname === "/amember4/signup")
					$("#alert-info").html("Your subscription has expired. Please renew your subscription by making a payment.").show();

				$("#navbar div.accordion-inner").each(function() {
					if (! $(this).children("a").attr("href").match("/logout$"))
						$(this).addClass("locked");

				});
			}
		}).always(function() {
			// Make the content visible (it's initially invisible to avoid a FOUC)
			$(".am-main").css("visibility", "visible");
		});
	}

	function reformatForms() {
		if ($("div.cancel-paysystems").length && $("input[type=radio]").length === 1) {
			$("input:radio:first-child").attr('checked', true);
			$("form .control-group").hide();
			return;
		}

		$(".am-form div[id^=row]").removeClass("row");
		$(".am-form > form").addClass("form-horizontal");
		$(".am-form div[id^=row]").addClass("control-group").find("br").remove();
		$(".am-form label:not([class])").addClass("control-label");
		$(".am-form div.element").addClass("controls");
		$(".am-form div.element-title").each(function() {
			$(this).replaceWith($(this).html());
		});

		// Remove spurious breaks within radio button labels
		$("form label[class=radio]").find("br").remove();
		
		// Style the next/submit buttons in forms
		$("form input[type=submit]").addClass("btn btn-primary btn-large");
	}

	function reformatErrors(errors) {
		errors.each(function(i) {
			var msg = $(this).text();

			// Change non-descriptive errors to something more useful
			if (msg == "This field is required") {
				msg = $(this).parent().prev().text();
				msg = "Please enter " + msg.replace(/^\* (.+)$/, "$1");
			}

			// If there is only one error then insert it as-is
			if (errors.length === 1) {
				// Fix squashed sentences
				msg = msg.replace(/\.([^$])/g, ". $1");

				// Append fullstop if missing
				if (msg.slice(-1) !== ".")
					msg += ".";

				$("#alert-error").html(msg);
				return false;
			}

			// Prepare an unordered list upon the first iteration
			if (i == 0) {
				$("#alert-error").html("<ul></ul>");
			}

			// Add the error as a list item
			$("#alert-error > ul").append("<li>" + msg + "</li>");
		});
		
		$("span.error").hide();
		$("#alert-error").show();
	}

	pta.ccvPopup = function() {
		$.get("/register/ccvinfo.html", function(html) {
			bootbox.dialog(html, [
				{
					"label": "OK",
					"class": "btn-success"
				}
			]);
		});
	}

	pta.cancelSubscription = function(url) {
		bootbox.dialog('Are you sure that you want to cancel your subscription?', [
			{
				"label": "No, do not cancel",
				"class": "btn-success"
			},
			{
				"label": "Yes, please cancel",
				"class": "btn-danger",
				"callback": function() {
					window.location = url;
				}
			}
		]);
	}

}(window.pta = window.pta || {}, jQuery));
