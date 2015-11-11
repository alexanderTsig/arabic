$(document).ready(function() {
	// Infer h1 from the document title
	var doctitle = document.title;
	doctitle = doctitle.substr(0, doctitle.length - 17);
	$('#masthead h1').html(doctitle);

	var url = location.pathname;
	
	// If the pathname begins with /level/[0-9] then truncate to eight characters (otherwise it won't match any of the buttons in the code below)					
	if (url.length >= 8 && url.substr(0,7) === "/level/")
		url = url.substr(0,8);

	if (url.substr(0,9) === "/amember4")
		url = "/amember4/member";

	// Add the active class to the navigation button and ensure that the containing accordion-body remains expanded
	$('.accordion-inner > a[href="' + url + '"]').addClass('active')
		.parents('.accordion-body').addClass('in');

	// Rotate the arrow to point down for the active accordion
	// FIXME: using addClass('active') breaks the jquery.transit plugin. Why?
	$('.accordion-body[class~=in]').prev('div').children('img').transition({ rotate: '90deg' }, 0);

	// Rotate the arrow to point right for collapsed menus with animation
	$('.accordion-body').on('show', function() {
		$(this).prev('div').children('img').transition({ rotate: '90deg' }, 350);
	});

	// Rotate the arrow to point down for collapsed menus with animation
	$('.accordion-body').on('hide', function() {
		$(this).prev('div').children('img').transition({ rotate: '0deg' }, 350);
	});
	
	$('#nav-lessons > div[class~=unlocked]').each(function() {
		$(this).click(function() {
			window.location = $(this).children('a').attr('href');
		});
	}).css('cursor', 'pointer');

	// Ensure that inaccessible levels cannot be clicked in the sidebar
	$('#nav-lessons > div[class~=locked] > a').click(false).css('cursor', 'default');
});
