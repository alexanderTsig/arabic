<?php
namespace PTA;

class Validate {
	private static $valid = [];

	public static function level($level, $method_name = 'undefined_method') {
		if (array_key_exists($level, self::$valid))
			return true;

		if (Number::isInteger($level) && $level > 0 && $level <= count(App::getLevels())) {
			self::$valid[$level] = [];
			return true;
		}
			
		Log::error("Invalid level $level in $method_name()");
		return false;
	}

	public static function lesson($level, $lesson, $method_name = 'undefined_method') {
		if (array_key_exists($level, self::$valid) && array_key_exists($lesson, self::$valid[$level]))
			return true;

		if (! Validate::level($level, $method_name))
			return false;

		$final_lesson = max(App::getLessons($level));

		if (Number::isInteger($lesson) && $lesson > 0 && $lesson <= $final_lesson['lesson']) {
			self::$valid[$level][$lesson] = true;
			return true;
		}

		Log::error("Invalid level, lesson tuple ($level, $lesson) in $method_name()");
		return false;
	}

	public static function gender($gender) {
		return ($gender !== false && ($gender === 'M' || $gender === 'F'));
	}
}
