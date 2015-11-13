<?php
namespace PTA;

class Log {
	protected static $option = [
		'log.console' => false,
		'log.level' => LOG_DEBUG
	];

	public static function setLogOptions($new_option) {
			self::$option = $new_option;
	}

	public static function error($msg) {
		if (self::$option['log.level'] < LOG_ERR)
			return;

		if (self::$option['log.console'] === true)
			echo "ERROR: $msg\n";

		syslog(LOG_ERR, $msg);
	}

	public static function warning($msg) {
		if (self::$option['log.level'] < LOG_WARNING)
			return;

		if (self::$option['log.console'] === true)
			echo "WARNING: $msg\n";

		syslog(LOG_WARNING, $msg);
	}

	public static function debug($msg) {
		if (self::$option['log.level'] < LOG_DEBUG)
			return;

		if (self::$option['log.console'] === true)
			echo "DEBUG: $msg\n";

		syslog(LOG_DEBUG, $msg);
	}
}
