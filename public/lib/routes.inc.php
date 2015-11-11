<?php

$app->get('/image/:filename/:width(/:height)', function($filename, $width, $height = 0) use ($app) {
    if ($width > 450 || $height > 450) {
		$app->halt(403, 'Request forbidden due to invalid image dimensions.');
	}
    $img = new Imagick("/var/www/portal.pathtoarabic.com/public/img/$filename");
    $img->resizeImage($width, $height, imagick::FILTER_LANCZOS, 0.9);
    $app->contentType('image/' . $img->getImageFormat());
    echo $img;
});

$app->get('/', function() use ($app, $user) {
    $app->redirect('/home');
});

$app->get('/login', function() use ($app, $user) {
    if ($user->isLoggedIn()) {
		$app->redirect('/home');
	}
    # FIXME: nonce handling is broken because user id cannot be known in advance. What's the solution?
    $app->render('login.twig', [
        'loginurl' => \PTA\App::getLoginURL(),
        'nonce'    => $user->createNonce($_SERVER['REMOTE_ADDR'], 'login', 0)
    ]);
});

$app->get('/welcome', function() use ($app, $user) {
    #if ($user->hasCompletedSurvey())
	#	$app->redirect('/home');
	#}
    $app->render('welcome.twig', [
        'pjax' => array_key_exists('X-PJAX', getallheaders()),
        'name' => $user->getName()
    ]);
});

$app->get('/survey', function() use ($app, $user) {
    $productType = $user->getProductType();    
    if ($user->hasCompletedSurvey()) {
		$app->redirect('/home');
	}
    $teachers = \PTA\App::getTeachers();
    $male_teachers = [];
    $female_teachers = [];
    foreach ($teachers as $teacher) {
        if ($teacher['gender'] === 'M') {
            $male_teachers[] = $teacher['name'];
        } else {
            $female_teachers[] = $teacher['name'];
        }
    }
    $male_teacher_names = implode(', ', $male_teachers);
    $female_teacher_names = implode(', ', $female_teachers);
    $app->render('survey.twig', [
        'pjax'            => array_key_exists('X-PJAX', getallheaders()),
        'referral_types'  => \PTA\App::getReferralTypes(),
        'male_teachers'   => $male_teacher_names,
        'female_teachers' => $female_teacher_names,
        'product_type'    => $productType 
    ]);
});

$app->get('/(:tplroot/)home', function($tplroot = "") use ($app, $user) {
	# Force new users to take the survey
	if (!$user->hasCompletedSurvey()) {
		$app->redirect('/welcome');
	}
	$levels = \PTA\App::getLevels();
	foreach ($levels as &$level) {
		$level['progress'] = $user->getProgress($level['id']);
	}
	$start = $user->getStartPosition();
	$renewal_time = null;
/*
	if ($user->getMembershipType() === 'Silver') {
		if ($user->memberShipExpiresIn(1 * \PTA\Time::WEEK))
			$renewal_time = '1 week';
		} else {
			for ($i = 1; $i <= 4; $i++) {
				if ($user->membershipExpiresIn($i * \PTA\Time::WEEK)) {
					switch ($i) {
						case 4:
							$renewal_time = '1 month';
							break;
						case 1:
							$renewal_time = '1 week';
							break;
						default:
							$renewal_time = "$i weeks";
					}
					break;
				}
		}
	}; 
	# Warn of expiring membership during the final week
	if ($user->memberShipExpiresIn(1 * \PTA\Time::WEEK) && ! $user->hasCancelledMembership()) {
		$renewal_time = '1 week';
	};
*/
	$signupDate = $user->getSignupDate();
	$app->render("{$tplroot}/home.twig", [
		'pjax'                 => array_key_exists('X-PJAX', getallheaders()),
		'amembernotifications' => $user->getAmemberNotification(),
		'announcements'        => \PTA\App::getAnnouncements(5),
		'board'                => $user->calculateLeaderboard(5),
		'email'                => $user->getEmail(),
		'id'                   => $user->getUserId(),
		'levels'               => $levels,
		'maxlesson'            => $user->getMaxLesson(),
		'minlesson'            => $user->getMinLesson(),
		'name'                 => $user->getName(),
		'product'              => $user->getMembershipType(),
		'progress'             => $user->getProgress(),
		'renewal_time'         => $renewal_time,
		'startlevel'           => $start['level'],
		'tips'                 => $user->getTips('home'),
		'user_level'           => $user->getLevel()
#		'signupdate'           => strftime('%B %G', strtotime($signupDate['month'] . '/01/' . $signupDate['year']))
	]);
});

$app->get('/leaderboard', function() use ($app, $user) {
    $board = $user->calculateLeaderboard(4);
    $signupDate = $user->getSignupDate();
    $app->render('leaderboard.twig', [
        'pjax'       => array_key_exists('X-PJAX', getallheaders()),
        'user_id'    => $user->getUserId(),
        'signupdate' => strftime('%B %G', strtotime($signupDate['month'] . '/01/' . $signupDate['year'])),
        'board'      => $board
    ]);
});

$app->get('/statistics', function() use ($app, $user) {
    $app->render('statistics.twig', [
        'pjax' => array_key_exists('X-PJAX', getallheaders())
    ]);
});

$app->get('/preferences', function() use ($app, $user) {
    $app->render('preferences.twig', [
        'pjax'           => array_key_exists('X-PJAX', getallheaders()),
        'default_gender' => $user->getPreferredGender(),
        'levels'         => \PTA\App::getLevels()
    ]);
});

#$app->get('/attendance', function() use ($app, $user) {
#	$app->render('attendance.twig', [
#		'pjax' => array_key_exists('X-PJAX', getallheaders())
#	]);
#});

$app->get('/membership', function() use ($app, $user) {
    $app->render('membership.twig', [
        'pjax' => array_key_exists('X-PJAX', getallheaders())
    ]);
});

$app->get('/support', function() use ($app, $user) {
    $app->render('support.twig', [
        'pjax' => array_key_exists('X-PJAX', getallheaders())
    ]);
});

$app->get('/support/faq', function() use ($app, $user) {
    $app->render('support-faq.twig', [
        'pjax' => array_key_exists('X-PJAX', getallheaders()),
        'faq'  => \PTA\App::getFAQ()
    ]);
});

$app->get('/support/teacher', function() use ($app, $user) {
    $app->render('support-teacher.twig', [
        'pjax'  => array_key_exists('X-PJAX', getallheaders()),
        'email' => $user->getEmail(),
        'class' => $user->getClass(),
        'nonce' => $user->createNonce($_SERVER['REMOTE_ADDR'], 'support-contact')
    ]);
});

$app->get('/support/contact', function() use ($app, $user) {
    $app->render('support-contact.twig', [
        'pjax'  => array_key_exists('X-PJAX', getallheaders()),
        'email' => $user->getEmail(),
        'class' => $user->getClass(),
        'nonce' => $user->createNonce($_SERVER['REMOTE_ADDR'], 'support-contact')
		# 'countries' => \PTA\App::getCountryList(),
    ]);
});

$app->get('/support/feedback', function() use ($app, $user) {
    $app->render('support-feedback.twig', [
        'pjax'  => array_key_exists('X-PJAX', getallheaders()),
        'email' => $user->getEmail(),
        'nonce' => $user->createNonce($_SERVER['REMOTE_ADDR'], 'support-contact')
    ]);
});

/* $app->get('/announcement/:id', function($id) use ($app, $user) {
$app->render('announcement.twig', [
'pjax'         => array_key_exists('X-PJAX', getallheaders()),
'announcement' => \PTA\App::getAnnouncement($id)
]);
});

$app->get('/announcements', function() use ($app, $user) {
$app->render('announcements.twig', [
'pjax'          => array_key_exists('X-PJAX', getallheaders()),
'announcements' => \PTA\App::getAnnouncements()
]);
}); */

# Display available lessons for a given level
$app->get('/level/:level', function($level) use ($app, $user) {
    $user_level = $user->getLevel();
    # If the request is for a level that is out of bounds, redirect to the user's current level
    if (! $user->isLevelAvailable($level)) {
		$app->redirect('/level/' . $user_level);
	}
    $app->render('lesson-index.twig', [
        'pjax'       => array_key_exists('X-PJAX', getallheaders()),
        'level'      => $level,
        'user_level' => $user_level,
        'lessons'    => $user->getLessons($level),
        'min_lesson' => $user->getMinLesson($level),
        'max_lesson' => $user->getMaxLesson($level),
        'tips'       => $user->getTips('lesson-index'),
        'progress'   => $user->getProgress(),
        'name'       => $user->getFirstname()
    ]);
});

$app->get('/lessons', function() use ($app, $user) {
    $app->redirect('/level/' . $user->getLevel());
});

# Display video for given level/lesson/gender tuple
$app->get('/level/:level/:lesson/video(/:gender)', function($level, $lesson, $gender = null) use ($app, $user) {
    if (!$user->isLessonAvailable($level, $lesson)) {
		$app->redirect('/level/' . $user->getLevel());
	}
//    $catCodes = $user->getProductCategory();    
//    if(in_array(\PTA\App::$Cat_Academy_Code, $catCodes) == false){
//        if(in_array(\PTA\App::$Cat_Engage_Code, $catCodes) == true){
//            $app->redirect(\PTA\App::$membership_path);
//        }
//    }
    $lessonData = \PTA\App::getLesson($level, $lesson);
    $default_gender = $user->getPreferredGender();        
    if ($gender === null) {
		$gender = $default_gender;
	}
    if (! \PTA\Validate::gender($gender)) {
		$app->redirect("/level/$level/$lesson/video");
	}
    $chapters = \PTA\App::getVideoChapters($level, $lesson, $gender);
    if ($chapters === null) {
		$app->redirect('/level/' . $user->getLevel());
	}
    # Get the preceding and following lessons for back/next style buttons
    $prev_lesson = $user->getPreviousLesson($level, $lesson);
    $next_lesson = $user->getNextLesson($level, $lesson);
    if ($prev_lesson !== null) {
		$prev_lesson = '/level/' . $prev_lesson[0] . '/' . $prev_lesson[1] . '/video/' . $gender;
	}
    if ($next_lesson !== null) {
		$next_lesson = '/level/' . $next_lesson[0] . '/' . $next_lesson[1] . '/video/' . $gender;
	}
    $bgImage = $user->getBackgroundImage();
    $app->render('lesson-video.twig', [
        'pjax'           => array_key_exists('X-PJAX', getallheaders()),
        'bgImage'        => $bgImage,
        'chapters'       => $chapters,
        'default_gender' => $default_gender,
        'gender'         => $gender,
        'lesson'         => $lesson,
        'level'          => $level,
        'neturl'         => \PTA\App::getNetConnectionUrl(),
        'next_lesson'    => $next_lesson,
        'prev_lesson'    => $prev_lesson,
        'teachers'       => \PTA\App::getTeachers($level),
        'tips'           => $user->getTips('lesson-video'),
        'title_ar'       => $lessonData['title_ar'],
        'title_en'       => $lessonData['title_en']
    ]);
});

# Display exercise for given level/lesson tuple
$app->get('/level/:level/:lesson/exercise', function($level, $lesson) use ($app, $user) {
    if (!$user->isLessonAvailable($level, $lesson)) {
		$app->redirect('/level/' . $user->getLevel());
	}
    $lessonData = \PTA\App::getLesson($level, $lesson);
    # Get the preceding and following lessons for back/next style buttons
    $prev_lesson = $user->getPreviousLesson($level, $lesson);
    $next_lesson = $user->getNextLesson($level, $lesson);
    if ($prev_lesson !== null) {
		$prev_lesson = '/level/' . $prev_lesson[0] . '/' . $prev_lesson[1] . '/exercise';
	}
    if ($next_lesson !== null) {
		$next_lesson = '/level/' . $next_lesson[0] . '/' . $next_lesson[1] . '/exercise';
	}
    $bgImage = $user->getBackgroundImage();
    $app->render('lesson-exercise.twig', [
        'pjax'        => array_key_exists('X-PJAX', getallheaders()),
        'bgImage'     => $bgImage,
        'lesson'      => $lesson,
        'level'       => $level,
        'movie'       => "//ptaexercise.s3.amazonaws.com/flash/$level/$lesson/movie.swf",
        'neturl'      => \PTA\App::getNetConnectionUrl(),
        'next_lesson' => $next_lesson,
        'prev_lesson' => $prev_lesson,
        'title_ar'    => $lessonData['title_ar'],
        'title_en'    => $lessonData['title_en'],
        'url'         => \PTA\App::getExerciseURL($level, $lesson),
        'user_id'     => $user->getUserId()
    ]);
});


$app->get('/level/:level/:lesson/review', function($level, $lesson) use ($app, $user) {
    if (! $user->isLessonAvailable($level, $lesson)) {
		$app->redirect('/level/' . $user->getLevel());
	}
    $app->render('lesson-review.twig', [
        'level'  => $level,
        'lesson' => $lesson,
        'movie'  => "//ptaexercise.s3.amazonaws.com/flash/$level/Content_review/$lesson/movie.swf"
    ]);
});

# Display exam for given level/lesson tuple
$app->get('/level/:level/:lesson/exam', function($level, $lesson) use ($app, $user) {
    if (! $user->isLessonAvailable($level, $lesson)) {
		$app->redirect('/level/' . $user->getLevel());
	}
    $lessonData = \PTA\App::getLesson($level, $lesson);
    $bgImage = $user->getBackgroundImage();
    # Get the preceding and following lessons for back/next style buttons
    $prev_lesson = $user->getPreviousLesson($level, $lesson);
    $next_lesson = $user->getNextLesson($level, $lesson);
    if ($prev_lesson !== null) {
		$prev_lesson = '/level/' . $prev_lesson[0] . '/' . $prev_lesson[1] . '/exam';
	}
    if ($next_lesson !== null) {
		$next_lesson = '/level/' . $next_lesson[0] . '/' . $next_lesson[1] . '/exam';
	}
    $app->render('lesson-exam.twig', [
        'pjax'        => array_key_exists('X-PJAX', getallheaders()),
        'bgImage'     => $bgImage,
        'lesson'      => $lesson,
        'level'       => $level,
        'neturl'      => \PTA\App::getNetConnectionUrl(),        
        'next_lesson' => $next_lesson,
        'prev_lesson' => $prev_lesson,
        'title_ar'    => $lessonData['title_ar'],
        'title_en'    => $lessonData['title_en'],
        'url'         => \PTA\App::getExamURL($level, $lesson, $user->getUserId(), $user->createNonce($_SERVER['REMOTE_ADDR'], 'exam')),
        'user_id'     => $user->getUserId()
    ]);
});

$app->get('/level/:level/congratulations', function($level) use ($app, $user) {
    # If the request is for a higher level than is available then redirect to the home screen
    if ($level < 2 || ! $user->isLevelAvailable($level)) {
		$app->redirect('/home');
	}
    $names = preg_split('/\s/', $user->getName());
    $app->render('congratulations.twig', [
        'pjax'      => array_key_exists('X-PJAX', getallheaders()),
        'level'     => $level,
        'firstname' => $names[0]
    ]);
});

$app->get('/apihelp/', function() use ($app, $user) {
    $app->contentType('text/plain');
    print_r(\AM_Lite::getInstance()->getAccess());
    print_r(\AM_Lite::getInstance()->getProducts());
    echo "\n\n# AM_Lite User Array\n\n";
    print_r(\AM_Lite::getInstance()->getUser());
    echo "\n\n# AM_Lite Methods\n\n";
    $class = new ReflectionClass(get_class(\AM_Lite::getInstance()));
    print_r($class->getMethods());
});
