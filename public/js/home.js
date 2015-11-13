(function (pta, $, undefined) {
	pta.dispatchTable['home'] = function (pjax) {
		$('#levels .tile').click(function () {
			window.location = '/level/' + ($('#levels .tile').index($(this)) + 1);
		}).addClass('clickable');

		$('.tile.stats').click(function () {
			window.location = '/statistics';
		}).addClass('clickable');

		$('.tile.help').click(function () {
			window.location = '/support';
		}).addClass('clickable');

		// NOTE: Re-located imgpicker.css because yepnope requires a plugin to lazy-load CSS
		yepnope({
			load: [
				'/js/imgPicker/assets/js/jquery.Jcrop.min.js',
				'/js/imgPicker/assets/js/jquery.imgpicker.js'
			],
			complete: function () {
				// avatar.init();
				$('#avatarModal').imgPicker({
					url: '/js/imgPicker/server/upload_bg.php',
					aspectRatio: 1,
					deleteComplete: function () {
						// FIXME: Should set avatar img src to default
						this.modal('hide');
					},
					uploadSuccess: function (image) {
						// Calculate the default selection for the cropper
						if (image.width > image.height) {
							this.options.setSelect = [
								(image.width - image.height) / 2,
								0,
								image.height,
								image.height	
							]
						} else {
							this.options.setSelect = [
								0,
								(image.height - image.width) / 2,
								image.width,
								image.width
							]
						}
						/*
						this.options.setSelect = (image.width > image.height)
							? [ (image.width - image.height) / 2, 0, image.height, image.height]
							: [ 0, (image.height - image.width) / 2, image.width, image.width];
						*/
					},
					cropSuccess: function (image) {
						var url = image.versions.bg.url;
						$("#avatar > img").attr('src', url);						
						$.ajax({
							type: "POST",
							url: '/api/user/avatarimg',
							data: { path: url },
							success: function() {
							
							}
						});
						// $("input[name=background_image]").val(url);
						// $('button[type=submit]').prop('disabled', false);
						// setTimeout(function () {
						// 	$("#background_image").trigger("special-change");
						// })
						this.modal('hide');
						
					}
				});
			}
		});
	}
}(window.pta = window.pta || {}, jQuery));
