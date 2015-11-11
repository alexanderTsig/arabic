// Required for drag and drop file access
jQuery.event.props.push('dataTransfer');

(function() {
	avatar = {};
	avatar.init = function() {
		Avatar.init();
	}

	var s;
	var Avatar = {
		settings: {
			bod: $("body"),
			img: $("#avatar > img"),
			fileInput: $("#uploader")
		},

		init: function() {
			s = Avatar.settings;
			Avatar.bindUIActions();
		},

		bindUIActions: function() {
			var timer;

			s.bod.on("dragover", function(event) {
				clearTimeout(timer);
				if (event.currentTarget == s.bod[0]) {
					Avatar.showDroppableArea();
				}

				// Required for drop to work
				return false;
			});

			s.bod.on('dragleave', function(event) {
				if (event.currentTarget == s.bod[0]) {
		  			// Flicker protection
					timer = setTimeout(function() {
						Avatar.hideDroppableArea();
					}, 200);
				}
			});

			s.bod.on('drop', function(event) {
				// Or else the browser will open the file
				event.preventDefault();

				Avatar.handleDrop(event.dataTransfer.files);
			});

			s.fileInput.on('change', function(event) {
				Avatar.handleDrop(event.target.files);
			});
		},

		showDroppableArea: function() {
			s.bod.addClass("droppable");
		},

		hideDroppableArea: function() {
			s.bod.removeClass("droppable");
		},

		handleDrop: function(files) {
			// Avatar.hideDroppableArea();

			// Multiple files can be dropped. Lets only deal with the "first" one.
			var file = files[0];

			if (typeof file !== 'undefined' && file.type.match('image.*')) {
				// Show spinner while scaling and upload operations are underway
				$('#avatar').addClass("loading");

				Avatar.hideDroppableArea();

				Avatar.resizeImage(file, 160, function(dataUrl) {
					$.ajax({
						url:         "/api/user/avatar",
						type:        "POST",
						processData: false,
						data:        dataUrl,
						async:       false
					});
					
					Avatar.placeImage(dataUrl);

					// De-activate the spinner
					$('#avatar').removeClass("loading");
				});

			} else {
				console.log("That file wasn't an image.");
				Avatar.hideDroppableArea();
			}
		},

		resizeImage: function(file, size, callback) {
			var fileTracker = new FileReader;

			fileTracker.onload = function() {
				Resample(this.result, size, size, callback);
	  		}
	  
	  		fileTracker.readAsDataURL(file);

			fileTracker.onabort = function() {
				alert("The upload was aborted.");
			}
			
			fileTracker.onerror = function() {
				alert("An error occured while reading the file.");
			}
		},

		placeImage: function(data) {
			s.img.attr("src", data);
		}
	}

	return avatar;

	// Avatar.init();
})();
