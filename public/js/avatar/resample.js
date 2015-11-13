var Resample = (function (canvas) {
	// (C) WebReflection Mit Style License

	function Resample(img, width, height, onresample) {
		var load = typeof img === "string";
		var i = load || img;

		// If a string, a new Image is needed
		if (load) {
			i = new Image;
			i.onload = onload;
			i.onerror = onerror;
		}

		i._onresample = onresample;
		i._width = width;
		i._height = height;
		load ? (i.src = img) : onload.call(img);
	}

	function onerror() {
		throw ("not found: " + this.src);
	}

	function onload() {
		var img = this;
		var width = img._width;
		var height = img._height;
		var onresample = img._onresample;
		
		// If both height and width are specified, the image is resized accordingly.
		// If only one dimension is specified, the aspect ratio is preserved.
		var minValue = Math.min(img.height, img.width);
		width === null && (width = Math.round(img.width * height / img.height));
		height === null && (height = Math.round(img.height * width / img.width));

		delete img._onresample;
		delete img._width;
		delete img._height;

		// When we reassign a canvas size, it clears automatically. The size should
		// be exactly the same as the final image so that the toDataURL ctx method
		// will return the whole canvas as a PNG without empty space or lines.
		canvas.width = width;
		canvas.height = height;
		
		// drawImage has different overloads. In this case we need the following one ...
		context.drawImage(img, 0, 0, minValue, minValue, 0, 0, width, height);

		// Retrieve the canvas content as base4 encoded PNG image and pass the result to the callback
		onresample(canvas.toDataURL("image/png"));
	}

	var context = canvas.getContext("2d");

	return Resample;
}(this.document.createElement("canvas")));
