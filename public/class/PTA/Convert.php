<?php
namespace PTA;

class Convert {
	private static $numerals = [
		'indo-arabic' => ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"],
		'arabic'      => ["٠", "١", "٢", "٣", "٤", "٥", "٦", "٧", "٨", "٩"]
	];

	public static function numberToArabic($number) {
		return str_replace(self::$numerals['indo-arabic'], self::$numerals['arabic'], (string) $number);
	}
}
