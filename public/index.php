<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

# This application uses the Slim micro-framework and the Twig template engine.
# Composer was used to construct the initial application skeleton, as
# described here:
#
# http://www.slimframework.com/read/skeleton-application)


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT");
header("Access-Control-Allow-Headers: Content-Type");
// Access-Control headers are received during OPTIONS requests

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers:  {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}


require_once '../vendor/autoload.php';
require_once 'class/autoload.php';
require_once 'amember4/library/Am/Lite.php';

# Initialize the Slim framework
$app = new \Slim\Slim([
	'view' => new \Slim\Extras\Views\Twig(),
	'templates.path' => '../templates',
	'log.level'      => 4,
	'log.enabled'    => true,
	'log.writer' => new \Slim\Extras\Log\DateTimeFileWriter([
		'path'        => '../logs',
		'name_format' => 'y-m-d'
	])
]);

# Enforce conditions for route parameters
\Slim\Route::setDefaultConditions([
	'level'  => '\d+',
	'lesson' => '\d+',
	'gender' => '(M|F)',
#	'image'  => '.+\.(png|jpg)',
	'width'  => '\d+',
	'height' => '\d+'
]);

# Implement a hook to redirect users who haven't signed in, or those with an expired membership
$user = \PTA\User::getInstance();
$app->hook('slim.before', function() use ($app, $user) {
	$request_path = $app->request()->getPathInfo();
	# Do not allow for exam result submissions to be thwarted by an expired aMember session
	if ($app->request()->isPost() && (preg_match('/^\/level\/\d+\/\d+\/exam$/', $request_path) != 0 || preg_match('/^\/level\/\d+\/\d+\/result/', $request_path) != 0))
		return;
	if ($user->isLoggedIn() && !$user->isMembershipValid()) {
		# Redirect the user to the signup page unless dealing with an XHR request for user data
		if (!($app->request()->isGet() && substr($request_path, 0, 9) === '/api/user')) {
			$app->redirect('/amember4/signup');
		}
	} else if (!$user->isLoggedIn() && $request_path !== '/login') {
		$app->redirect('/login');
	}

	# FIXME - This needs reviewing	
    /**
     *  Access Level 
     */
    if($user->isLoggedIn()) {
        $ProductTypes = $user->getProductCategory();
        //Full Access - Product 1 - Academy or Membership
        if(in_array(\PTA\App::$Cat_membership_Code ,$ProductTypes) == true || in_array(\PTA\App::$Cat_Academy_Code ,$ProductTypes) == true) {
            return true;
        }
        // Product 2 - Engage
        if(in_array(\PTA\App::$Cat_Engage_Code ,$ProductTypes) == true) {
            if(preg_match('/^\/level\/\d+\/\d+\/video/', $request_path) != 0){
                $app->redirect(\PTA\App::$membership_path);
                return true;
            }
            return true;
        }
        // Product 3 - Digital Download        
        if(in_array(\PTA\App::$Cat_Engage_Code ,$ProductTypes) == true) {
            if(preg_match('/^\/home/', $request_path) != 0 || preg_match('/^\/support/', $request_path) != 0 || preg_match('/^\/amember/', $request_path) != 0){
                return true;
            }else{                
                $app->redirect('/amember4/member');
            }
        }        
        $app->redirect('/amember4/signup');
    }
});

$app->hook('slim.after.router', function() use ($app) {
    # Disable caching for all content types other than images
    $response = $app->response();
    if (substr($response['Content-Type'], 0, 6) === 'image/') {
        $response['Cache-Control'] = 'public, max-age=86400';
        $response['Expires'] = gmdate('D, d M Y H:i:s GMT', strtotime('+1 day'));
    } else {
        $response['Pragma'] = 'nocache';
        $response['Cache-Control'] = 'no-cache, no-store, max-age=0, must-revalidate';
        $response['Expires'] = 'Fri, 01 Jan 1990 00:00:00 GMT';
    }
});

# Set options for the Twig template engine
\Slim\Extras\Views\Twig::$twigOptions = [
	'auto_reload'      => true,
	'autoescape'       => true,
	'charset'          => 'utf-8',
	'strict_variables' => false, # only enable this for debugging!
#	'cache'            => realpath('../templates/cache')
];
$twig = $app->view()->getEnvironment();
$twig->addGlobal('remote_ip', $_SERVER['REMOTE_ADDR']);

# Import routes from separate include files
require 'lib/routes.inc.php';
require 'lib/routes-api.inc.php';

# Execute Slim to handle the request
$app->run();
