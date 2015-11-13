/*
	<div id="avatar"></div>
	<div id="formdata"></div>
	<div id="progress"></div>



var avatar = document.getElementById('avatar');

avatar.ondragover = function() {
	// Activate the :hover pseudo-class so as to provide a visual cue that a drop is possible
	this.className = 'hover';
	return false;
};

avatar.ondragend = function() {
	this.className = '';
	return false;
};

avatar.ondrop = function(event) {
	event.preventDefault && event.preventDefault();
	this.className = '';

	var files = event.dataTransfer.files;
	return false;
};

function readFiles() {

	for (var i = 0; i < files.length; i++) {
		formData.append('file', files[i]);
	}
}

// Post a new XHR request
var xhr = new XMLHttpRequest();
xhr.open('POST', '/upload');

xhr.onload = function() {
	if (xhr.status === 200) {
		console.log('all done: ' + xhr.status);
	}
	else {
		console.log('Something went terribly wrong ...');
	}
};

xhr.send(formData);
