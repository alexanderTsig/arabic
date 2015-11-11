<?php
namespace PTA;

class Product {
	private static $weight = [
		'Silver'                 => 10,
		'Silver (Special Offer)' => 10,
		'Gold'                   => 20,
		'Platinum'               => 30
	];

	public static function getWeight($product) {
		return array_key_exists($product, self::$weight) ? self::$weight[$product] : null;
	}
}
