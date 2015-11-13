<?php
namespace PTA;

class AmemberRest {
	protected static $api_key = 'JZ6AQFedeLGvolV0Ogfo';
	protected static $url_prefix = 'https://portal.pathtoarabic.com/amember4/api';
	protected static $timeout = 5;

	private static function getUrl($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$timeout);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}

	private static function getJson($url, $query_params = null) {
		$default_params = ['_key' => self::$api_key];
		
		$query_params = ($query_params !== null)
			? array_merge($default_params, $query_params)
			: $default_params;

		$query_params = http_build_query($query_params);
	
		if (substr($url, 0, 1) === '/')
			$url = self::$url_prefix . $url;

		$response = self::getUrl("$url?$query_params");

		if ($response !== null)
			return json_decode($response, true);
	}

	public static function getUsers() {
		return self::getJson('/users');
	}

	public static function checkAccessByLoginPass($username, $password) {
		$json = self::getJson('/check-access/by-login-pass', [
			'login' => $username,
			'pass'  => $password
		]);

		if ($json === null) {
			Log::debug('Got empty response for /check-access/by-login-pass in ' . __METHOD__);
			return null;
		}

		return (array_key_exists('ok', $json) && $json['ok'] == 1);
	}
}
