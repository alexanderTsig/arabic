<?php
namespace PTA;

class Number {

	public static function isInteger($str) {
		return ($str !== null && preg_match('/^\d+$/', $str) != 0);
	}

	public static function toInteger($val) {
		if (gettype($val) === "integer")
			return $val;

		if (self::IsInteger($val)) {
			return intval($val);
		}
		else {
			# FIXME: add error handler here (just in case)
		}
	}
}
