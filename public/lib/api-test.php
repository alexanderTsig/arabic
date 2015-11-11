<?php

require_once 'Application.class.php';
require_once 'User.class.php';
require_once 'AmemberRest.class.php';

Log::setLogOptions([
	'log.level' => LOG_DEBUG,
	'log.console' => true
]);

function Application_test() {
	$app = new Application();
	print_r( $app->getVideoChapters(1,1,'M') );
}

function User_test() {
	$usr = new User(13);

	#$usr->setPref("favourite_colour", "green");
	#echo $usr->getPref("favourite_colour");

	var_dump( $usr->verifyNonce('e4294ae59d98857989e0d1ee1d237f68994b87f9', '2.28.89.65', 'exam') );
}

function User_auth() {
	if (User::checkLogin('kerin', 'wrongpassword') === true) {
		echo "correct!";
	}
	else {
		echo "not correct!";
	}
}

User_test();
