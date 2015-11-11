(function($) {
	// create a keyboard
	// layoutURL: URL for the keyboard layout
	// callback_keypress: each character generated by the keyboard will be passed to this function
	// callback_loaded: once the layout has loaded and the keyboard initialised, this function is called
	$.fn.loadLayout = function(layoutURL, callback_keypress, callback_loaded) {
		var keyboard = new Keyboard($(this[0]));
		
		if (callback_keypress)
			keyboard.callback = callback_keypress;
			
		$.getJSON(layoutURL, function(layout) {
			addKeys(layout, keyboard);
			draw(keyboard);
			if(callback_loaded)
				callback_loaded.apply(this);
		});
		
		return this;
	};

	// add keys to the keyboard
	function addKeys(layout, keyboard) {
		for (var i = 0; i < layout.length; i++) {
			var row = [];
			for (var j = 0; j < layout[i].length; j++) {
				row[j] = new Key(layout[i][j]);
				keyboard.keyIDs[row[j].id] = row[j];
			}
			keyboard.keys[i] = row;
		}
	}

	// draw the keyboard
	function draw(keyboard) {
		keyboard.board.empty();
		keyboard.board.removeClass("shift");
		keyboard.board.removeClass("altGr");
		keyboard.board.removeClass("capsLock");
		
		for (var i = 0; i < keyboard.keys.length; i++) {
			var row = $("<div></div>");
			for (var j = 0; j < keyboard.keys[i].length; j++) {
				row.append(keyboard.keys[i][j].draw(keyboard));
			}
			keyboard.board.append(row);
		}
	}

	// Keyboard object
	function Keyboard(board) {
		//DOM element which contains the keyboard
		this.board = board;

		//map ids to keys
		this.keyIDs = {};

		//callback for keypresses
		this.callback = function(key) {alert(key)};

		//state of the keyboard modifiers
		this.modifiers = {
			shift: false,
			altGr: false,
			capsLock: false
		};

		//jagged array of keys
		this.keys = [];
	}

	// Key object
	function Key(keyObj) {
		//key data from the layout file
		$.extend(this, keyObj);
		
		//add defaults for missing values
		this.sLabel = this.sLabel || this.label.toUpperCase();
		this.altLabel = this.altLabel || this.label;
		this.sAltLabel = this.sAltLabel || this.altLabel.toUpperCase();
		this.func = this.func || "typelabel";
		this.sFunc = this.sFunc || this.func;
		this.isLetter = this.label.toUpperCase() != this.label.toLowerCase();
		this.altIsLetter = this.altLabel.toUpperCase() != this.altLabel.toLowerCase();
	}

	// generate an HTML button for a key
	Key.prototype.draw = function(keyboard) {
		var button = this.button = $("<button id='" + this.id + "'>" + this.label + "</button>");
		
		//wire click event to the keypress handler
		this.button.mousedown(function() {
			keyboard.keyIDs[this.id].activate(keyboard, button)}
		);
		
		return this.button;
	};

	// apply modifiers to the key's label
	Key.prototype.modify = function(modifiers) {
		var modsVal = (modifiers.shift?1:0) + (modifiers.altGr?2:0) + (modifiers.capsLock?4:0);
		var text;
		switch (modsVal) {
			case 0:
				text = this.label;
				break;
			case 1:
				text = this.sLabel;
				break;
			case 2:
				text = this.altLabel;
				break;
			case 3:
				text = this.sAltLabel;
				break;
			//caps lock only effects letters
			case 4:
				text = this.isLetter ? this.sLabel : this.label;
				break;
			case 5:
				text = this.isLetter ? this.label : this.sLabel;
				break;
			case 6:
				text = this.isLetter ? this.sAltLabel : text = this.altLabel;
				break;
			case 7:
				text = this.isLetter ? this.altLabel : text = this.sAltLabel;
				break;
		}
		this.button.text(text);
	};

	// handle the keypress event
	Key.prototype.activate = function(keyboard, button) {
		if (keyboard.modifiers.shift) {
			handlers[this.sFunc](keyboard, button);
		} else {
			handlers[this.func](keyboard, button);
		}
	};

	// handle keypresses
	var handlers = {
		space: function(keyboard) {
			keyboard.callback(' ');
		},

		tab: function(keyboard) {
			keyboard.callback('\t');
		},

		backspace: function(keyboard) {
			keyboard.callback('\b');
		},

		left: function(keyboard) {
			keyboard.callback('\3');
		},

		right: function(keyboard) {
			keyboard.callback('\4');
		},

		enter: function(keyboard) {
			keyboard.callback('\n');
		},

		shift: function(keyboard) {
			keyboard.modifiers.shift = !keyboard.modifiers.shift;
			keyboard.board.toggleClass("shift");
			modify(keyboard);
			return keyboard.modifiers.shift;
		},

		altgr: function(keyboard) {
			keyboard.modifiers.altGr = !keyboard.modifiers.altGr;
			keyboard.board.toggleClass("altGr");
			modify(keyboard);
			return keyboard.modifiers.altGr;
		},

		capslock: function(keyboard) {
			keyboard.modifiers.capsLock = !keyboard.modifiers.capsLock;
			keyboard.board.toggleClass("capsLock");
			modify(keyboard);
			return keyboard.modifiers.capsLock;
		},

		typelabel: function(keyboard, button) {
		keyboard.callback(button.text());
		
		if (keyboard.modifiers.shift)
			this.shift(keyboard);
			
		if (keyboard.modifiers.altGr)
			this.altgr(keyboard);
		}
	}

	// apply keyboard modifiers
	function modify(keyboard) {
		for (var id in keyboard.keyIDs) {
			keyboard.keyIDs[id].modify(keyboard.modifiers);
		}
	}
})(jQuery)
