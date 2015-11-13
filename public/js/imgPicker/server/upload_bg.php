<?php

/*
require_once '../vendor/autoload.php';
require_once 'class/autoload.php';
require_once 'amember4/library/Am/Lite.php';
 */
// HTTP access control
// header('Access-Control-Allow-Origin: yourwebsite.com');
// header('Access-Control-Allow-Origin: www.yourwebsite.com');


require_once dirname(__FILE__) . '/ImgPicker.php';

$user_id = 455; // Ahmed

// $image->name     (string)
// $image->type     (string)
// $image->size     (integer; available only in upload_start)
// $image->path     (string)
// $image->url      (string)
// $image->width    (integer)
// $image->height   (integer)
// $image->versions (array; available only in upload_complete and crop_complete)

$options = [
    //'upload_dir'    => '/var/cache/avatar/',
	//'upload_url'    => '/img/avatar/',
    

    'upload_dir' => dirname(__FILE__) . '/../files/',
    // Upload directory url:
    'upload_url' => '/js/imgPicker/files/',


	'mkdir_mode'    => 0755,
    'versions' => [
        'bg' => [
			'max_width'  => 1920,
			'max_height' => 1080
        ],
	],
	'load' => function($instance) {
		// FIXME: How does autload work exactly?
		// return 'avatar.jpg';
	},
    'delete' => function($filename, $instance) {
		return false;
	},
	'upload_start' => function($image, $instance) use ($user_id) {
		$fn = sprintf('%s.%s', "incoming_$user_id", $image->type);  
		$image->name = $fn;
		syslog(LOG_DEBUG, 'upload_start');
		syslog(LOG_DEBUG, "path = " . $image->path);
		syslog(LOG_DEBUG, "name = " . $image->name);
		syslog(LOG_DEBUG, "size = " . $image->size);
		if ($image->size > 7 * pow(2, 20)) {
			syslog(LOG_DEBUG, "Image size is too large");
		}
	},
	'upload_complete' => function($image, $instance) {
		syslog(LOG_DEBUG, 'upload_complete');
		syslog(LOG_DEBUG, "path = " . $image->path);
		syslog(LOG_DEBUG, "name = " . $image->name);
	},
	'crop_start' => function($image, $instance) use ($user_id) {
		$fn = sprintf('%s.%s', $user_id, $image->type);  
		$image->name = $fn;
		syslog(LOG_DEBUG, 'crop_start');
		syslog(LOG_DEBUG, "path = " . $image->path);
		syslog(LOG_DEBUG, "name = " . $image->name);
	},
	'crop_complete' => function($image, $instance) use ($user_id) {
		syslog(LOG_DEBUG, 'crop_complete');
		syslog(LOG_DEBUG, "path = " . $image->path);
		syslog(LOG_DEBUG, "name = " . $image->name);
		$upload_dir = $instance->getUploadPath();
		foreach (glob(sprintf('%s/%s.*', $upload_dir, $user_id)) as $path) {
			if (! is_file($path)) {
				continue;
			}
			if (basename($path) !== $image->name) {
				syslog(LOG_DEBUG, "Unlinking $path");
				unlink($path);
			}
		}
	}
];

new ImgPicker($options);
