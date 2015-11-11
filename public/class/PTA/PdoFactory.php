<?php
namespace PTA;

use PDO;

class PdoFactory {
	private static $pdo;
	/*  private static $password_for = [
		'pathtoarabic' => 'Yop2qLpr'
	]; */

	public static function getInstance(/* $db = 'pathtoarabic' */) {
		# FIXME: Singletons are considered bad form: http://www.slideshare.net/go_oh/singletons-in-php-why-they-are-bad-and-how-you-can-eliminate-them-from-your-applications
		# Consider making the object invocant responsible for maintaining one instance, rather than enforcing it here.

		if (self::$pdo === null) {
			self::$pdo = new PDO('mysql:host=localhost;dbname=pathtoarabic', 'pathtoarabic', 'Yop2qLpr');
			self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}

		return self::$pdo;
	}

	public static function execStatement($stmt) {
		// TODO: this method should handle statement execution for centralized error handling
	}

}
