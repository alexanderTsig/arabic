<?php

/* # NOTE: Used by preview avavar upload mechanism
$app->post('/api/user/avatar', function() use ($app, $user) {
    $base64 = explode(',', $app->request()->getBody())[1];
    $image = base64_decode($base64);
    $ok = false;
    if ($image) {
		$ok = (file_put_contents("/var/cache/avatar/" . $user->getUserId() . ".png", $image) !== false);
	}
    $app->contentType('application/json');
    echo json_encode([ 'ok' => $ok]);
}); */

// BEGIN JUNK ROUTES

$app->get('/api/level/:level', function($level) use ($app, $user) {
    $user_level = $user->getLevel();
    if ($user_level < $level) {
		$app->redirect('/level/' . $user_level);
	}
    $lessons = $user->getLessons($level);
    $min_lesson = $user->getMinLesson($level);
    $max_lesson = $user->getMaxLesson($level);
    echo "<pre>";
    echo "Min lesson: $min_lesson\n\n";
    echo "Max lesson: $max_lesson\n\n";
    echo print_r($lessons);
    echo "</pre>";
});

$app->post('/api/user/avatarimg', function() use ($app, $user) {
    $request = $app->request();
    $filepath = $request->post('path');
    $ok = false;    
    if ($filepath){
		$ok = @copy($_SERVER["DOCUMENT_ROOT"]."/".$filepath, \PTA\App::getAvatarPath(). $user->getUserId() . ".png");
		# @unlink($_SERVER["DOCUMENT_ROOT"]."/".$filepath);
    }
	# $ok = (file_put_contents("/var/cache/avatar/" . $user->getUserId() . ".png", $image) !== false);
    $app->contentType('application/json');
    echo json_encode([ 'ok' => $ok]);
});

// END JUNK ROUTES

$app->get('/api/user/avatar/:user_id', function($user_id = null) use ($app, $user) {
	$basedir = PTA\App::getAvatarPath();
	if ($user_id === null) {
		$user_id = $user->getUserId();
	}
	$paths = array_filter(glob("$basedir/$user_id.{png,jpg,gif}", GLOB_NOSORT | GLOB_BRACE), function($path) {
		return is_file($path);
	});
	if (count($paths) === 0) {
		$app->contentType("image/jpeg");
		readfile("$basedir/default.jpg");
	} else {
		usort($paths, function($a, $b) {
			return filemtime($a) < filemtime($b);
		});
		$info = new SplFileInfo($paths[0]);
		$ext = $info->getExtension();
		$mimetype = 'image/' . ($ext === 'jpg') ? 'jpeg' : $ext;
		$app->contentType($mimetype);
		readfile($paths[0]);
	}
});





$app->get('/api/level/:level/:lesson/questions/reviewable', function($level, $lesson) use ($app) {
    $app->contentType('application/json');
    echo json_encode(PTA\App::getQuestionsHavingReview($level, $lesson));
});

$app->get('/api/user/membership', function() use ($app, $user) {
    $app->contentType('application/json');
    $expires_on = $user->getExpiryDate();
    echo json_encode([
        'status' => $user->getMembershipStatus(),
        'isValid' => $user->isMembershipValid(),
        'hasCancelled' => $user->hasCancelledMembership(),
        'expiresOn' => ($expires_on !== null) ? $expires_on->format('U') : null
    ]);
});

$app->get('/api/tip/:tip_id/dismiss', function($tip_id) use ($app, $user) {
    $app->contentType('application/json');
    echo json_encode([
        'ok' => $user->dismissTip($tip_id)
    ]);
});

$app->get('/api/tip/:tip_id/defer', function($tip_id) use ($app, $user) {
    $app->contentType('application/json');
    echo json_encode([
        'ok' => $user->deferTip($tip_id)
    ]);
});

$app->get('/api/user/data', function() use ($app, $user) {
    if ($app->request()->get('nocache') == 1) {
		$user->invalidateCache();
	}
    $app->contentType('application/json');
    echo json_encode($user);
});

$app->post('/api/user/data', function() use ($app, $user) {
    $app->contentType('application/json');
    $data = json_decode($app->request()->getBody());
    if (isset($data->rating) && $data->rating) {
		$position = [
			'level'  => intval($data->rating->level),
			'lesson' => intval($data->rating->lesson)
		];
        $user->setStartPosition($position);
    }
    echo json_encode([
        'ok' => ($data !== null && $user->setPreferences($data))
    ]);
});

# Generate JSON data which is used to initialize the playlist in Flowplayer
# FIXME - change this to use the /api namespace ...
$app->get('/video/:level/:lesson/(:gender/)playlist', function($level, $lesson, $gender = null) use ($app, $user) {
    $playlist = $user->getVideoPlaylist($level, $lesson, $gender);
    $app->contentType('application/json');
    echo json_encode($playlist);
});

$app->post('/level/:level/:lesson/exam', function($level, $lesson) use ($app) {
    $app->contentType('application/json');
    $request = $app->request();
	$user_id = $request->post('id');
    if (\PTA\User::validateNonce($user_id, $request->post('nonce'), $_SERVER['REMOTE_ADDR'], 'exam')) {
        $user = \PTA\User::getInstance($user_id);
        $oldlevel = $user->getLevel();
        $result = $user->setTestResults([
            'level'         => $level,
            'lesson'        => $lesson,
            'id'            => $user_id,
            'score'         => $request->post('score'),
            'passing_score' => $request->post('passing_score'),
            'min_score'     => $request->post('min_score'),
            'max_score'     => $request->post('max_score'),
            'points'        => $request->post('points'),
            'max_points'    => $request->post('max_points')
        ]);
        echo json_encode([
            'code'       => ($result !== null) ? 0 : 1,
            'pass'       => $result,
            'startlevel' => $user->getStartPosition()['level'],
            'oldlevel'   => $oldlevel,
            'level'      => $user->getLevel(),
            'lesson'     => $user->getMaxLesson()
        ]);
    } else {
        \PTA\Log::error('nonce verification failed for $ip while submitting exam results');
        echo json_encode(['code' => 1]);
    }
});

$app->post('/level/:level/:lesson/results', function($level, $lesson) use ($app, $user) {
    $app->contentType('application/json');
    $request = $app->request();
    $oldlevel = $user->getLevel();
    if ($user->verifyNonce($request->post('nonce'), $_SERVER['REMOTE_ADDR'], 'exam')) {
        $result = $user->setTestResults([
            'level'         => $level,
            'lesson'        => $lesson,
            'score'         => $request->post('score'),
            'passing_score' => $request->post('passing_score'),
            'min_score'     => $request->post('min_score'),
            'max_score'     => $request->post('max_score'),
            'points'        => $request->post('points'),
            'max_points'    => $request->post('max_points')
        ]);
        echo json_encode([
            'code'       => 0,
            'pass'       => $result,
            'startlevel' => $user->getStartPosition()['level'],
            'oldlevel'   => $oldlevel,
            'level'      => $user->getLevel(),
            'lesson'     => $user->getMaxLesson()
        ]);
    } else {
        \PTA\Log::error('nonce verification failed for $ip while submitting exam results');
        echo json_encode(['code' => 1]);
    }
});

$app->post('/api/support/contact', function() use ($app, $user) {
    $app->contentType('application/json');
    $request = $app->request();
    if ($user->verifyNonce($request->post('nonce'), $_SERVER['REMOTE_ADDR'], 'support-contact')) {
        $from = $request->post('email');
        $subject = $user->getName() . ' submitted ';
        $alias = 'support';
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $from\r\n";
        $headers .= 'X-Mailer: PHP/' . phpversion();
        #$comment = wordwrap($request->post('comment'), 72, "\r\n");
        $message = '<html style="background:#ccc">';
        $message .= '<body style="height:100%;width:75%;background:#fff;border-right:3px solid #bbb;padding:0.5em 1.5em;margin:0 !important" leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">' . "\r\n";
        $message .= '<h3 style="font-size:x-large;font-family:Calibri,Verdana,sans;border-bottom:2px solid #000;">User Details' . "</h3>\r\n";
        $message .= "<pre>\r\n";
        $message .= '     Name: ' . $user->getName() . "\r\n";
        $message .= '    Email: ' . $from . "\r\n";
        $message .= 'Telephone: ' . $request->post('phone') . "\r\n";
        $message .= ' Category: ' . $request->post('verbose_category') . "\r\n";
        $message .= "</pre>\r\n";
        $comment = $request->post('comment');
        if ($comment) {
            $message .= '<h3 style="font-size:x-large;font-family:Calibri,Verdana,sans;border-bottom:2px solid #000;">Comment' . "</h3>\r\n";
            $message .= "$comment\r\n";
        }
        $sample = $request->post('arabic');
        if ($sample) {
            $message .= '<h3 style="font-size:x-large;font-family:Calibri,Verdana,sans;border-bottom:2px solid #000;">Arabic Sample' . "</h3>\r\n";
            $message .= '<p dir="rtl" style="font-size:xx-large">' . $sample . "</p>\r\n";
        }
        $message .= "</body>\r\n";
        $message .= "</html>\r\n";
        \PTA\Log::debug("support category: " . $request->post('category'));
        switch ($request->post('category')) {
            case 'billing':
                $subject .= 'a billing enquiry';
                $alias = 'billing';
                break;
            case 'feedback':
                $subject .= 'feedback';
                $alias = 'feedback';
                break;
            case 'teacher':
                $subject .= 'a request for teacher support';
                $alias = 'teacher';
                break;
            default:
                $subject .= 'a support request';
                #$alias = ($user->getClass() === 'student') ? 'support' : 'teacher';
                $alias = 'support';
                break;
        }
        $to = "$alias@pathtoarabic.com";
        echo json_encode([
            'status' => (mail($to, $subject, $message, $headers)) ? '0' : '1'
        ]);
    } else {
        \PTA\Log::error('nonce verification failed for $ip while submitting support/contact form data');
        echo json_encode(['status' => '1']);
    }
});

$app->get('/api/get/note/:level/:lesson', function($level, $lesson) use ($app, $user) {
    $note = $user->getNote($level, $lesson);
    $app->contentType('text/plain');
    echo ($note !== null) ? $note : '';
});

$app->post('/api/post/note/:level/:lesson', function($level, $lesson) use ($app, $user) {
    $result = $user->setNote($level, $lesson, $app->request()->post('text'));
    $app->contentType('application/json');
    echo json_encode(['success' => $result]);
});

$app->get('/api/getlevel', function() use ($app, $user) {
    $app->contentType('application/json');
    echo json_encode([
        'level'      => $user->getLevel(),
        'startlevel' => $user->getStartPosition()['level']
    ]);
});

$app->get('/api/getpref/:pref', function($pref) use ($app, $user) {
    $app->contentType('application/json');
    echo json_encode([
        $pref => $user->getPref($pref)
    ]);
});

$app->get('/api/setpref/:pref/:value', function($pref, $value) use ($app, $user) {
    $app->contentType('application/json');
    $user->setPref($pref, $value);
    echo json_encode([
        $pref => $value
    ]);
});

$app->get('/api/graph/level/:level', function($level) use ($app, $user) {
    $user_scores = $user->getAverageScores($level, -1);
    $lessons = [];
    for ($i = 1; $i <= count($user_scores); $i++) {
        array_push($lessons, $i);
    }
    $global_scores = array_slice(\PTA\App::getAverageScores($level), 0, count($user_scores));
    $chartdata = ['empty' => true];
    if (count($user_scores) > 0 || count($global_scores) > 0) {
        $chartdata = [
			'chart' => [
				'renderTo' => 'chart'
			],
			'title' => [
				'text' => 'Performance'
			],
            'xAxis' => [
                'title' => [
                    'text' => 'Lessons'
                ],
                'categories' => $lessons
            ],
            'yAxis' => [
                'min' => 0,
                'max' => 100,
                'title' => [
                    'text' => 'Average Score / Tries'
                ]
            ],
            'series' => [
                [
                    'name' => 'You',
                    'data' => $user_scores,
                    'type' => 'line'
                ],
                [
                    'name' => 'Other Users',
                    'data' => $global_scores,
                    'type' => 'line'
                ]
            ]
        ];
    }
    $app->contentType('application/json');
    echo json_encode($chartdata);
});

$app->post('/api/login/hook', function() use ($app, $user) {
    $request = $app->request();
    $ip = $_SERVER['REMOTE_ADDR'];
    $logged = true;
    #if ($user->isLoggedIn() && $user->verifyNonce($request->post('nonce'), $ip, 'login-' . $user->getUsername(), 0)) {
    	$user->logLogin($ip);
    #}
    $app->contentType('application/json');
    echo json_encode([
        'status' => ($logged) ? '0' : '1'
    ]);
});

$app->post('/api/affiliate/search', function() use ($app, $user) {
    $app->contentType('application/json');
    $searchterm = $app->request()->post('searchterm');
    $affiliates = \PTA\App::findAffiliate($searchterm);
    echo json_encode($affiliates);
});

$app->get('/api/teachers', function() use ($app, $user) {
    $app->contentType('application/json');
    echo json_encode(\PTA\App::getTeachers());
});

$app->post('/api/survey', function() use ($app, $user) {
    $app->contentType('application/json');
    $data = json_decode($app->request()->getBody());
    $rc = 0;
    $rc |= $user->setPreferredGender($data->gender);
    $position = ['level' => 1, 'lesson' => 1];
    switch ($data->rating) {
        case 2:
            $position = ['level' => 1, 'lesson' => 5];
            break;
        case 3:
            $position = ['level' => 2, 'lesson' => 1];
            break;
        case 4:
            $position = ['level' => 3, 'lesson' => 1];
            break;
        case 5:
            $position = ['level' => 4, 'lesson' => 1];
    }
    $rc |= $user->setStartPosition($position);
    $rc |= $user->addReferralTypes($data->referred);
    echo json_encode([
        'status' => $rc
    ]);
});

$app->post('/api/star', function() use ($app, $user) {
    $app->contentType('application/json');
	$request = $app->request();
	$rc = $user->addStar($request->post('level'), $request->post('lesson'));
    echo json_encode([
		'status' => $rc
    ]);
});

$app->post('/api/unstar', function() use ($app, $user) {
    $app->contentType('application/json');
	$request = $app->request();
	$rc = $user->removeStar($request->post('level'), $request->post('lesson'));
    echo json_encode([
        'status' => ($rc !== 0)
    ]);
});



/**
 * Engage tincan APi endpoint
 */
$app->post('/:level/:lesson/result-exercisestatements', function($level, $lesson) use ($app) {
    die();
});

$app->post('/:level/:lesson/result-exercisestatements/activities/state', function($level, $lesson) use ($app) {
    die();
});

$app->post('/:level/:lesson/result-exercisestatements/statements', function($level, $lesson) use ($app) {
    die();
});


/**
 * Unlock tincan APi endpoint
 */

$app->post('/level/:level/:lesson/result-exam/activities/state', function($level, $lesson) use ($app) {
    die();
});
$app->post('/level/:level/:lesson/result-exam/statements/', function($level, $lesson) use ($app) {
    $request_body = file_get_contents('php://input');
    parse_str(urldecode($request_body), $data);
    if (isset($data['content'])) {
        $result = json_decode($data['content']);
    }else{
        die();
    }
    $verb = $result->verb->id;
    $verb = str_replace('http://adlnet.gov/expapi/verbs/', '', $verb);
    $user_id = $data['user'];
    $authKey = $data['Authorization'];    
    if(in_array($verb, array('completed','passed','failed'))){
        if (\PTA\User::validateNonce($user_id, $authKey, $_SERVER['REMOTE_ADDR'], 'exam')) {
        $user = \PTA\User::getInstance($user_id);
        $oldlevel = $user->getLevel();
        if(isset($result->result->completion) == true){
            die();
        }
        $score = $result->result->score->scaled*100;
        $passingScore = 70;
        if($verb == "failed"){
            if($score > $passingScore){
                $passingScore = $score+10;
            }
        }else if($verb == "passed"){
            if($score < $passingScore){
                $passingScore = $score+10;
            }
        }
        $status = $user->setTestResults([
            'level' => $level,
            'lesson' => $lesson,
            'id' => $user_id,
            'score' => $score,
            'passing_score' => $passingScore,
            'min_score' => 0,
            'max_score' => 100,
            'points' => $result->result->score->raw,
            'max_points' => $result->result->score->max
        ]);
        echo json_encode([
            'code' => ($status !== null) ? 0 : 1,
            'pass' => $status,
            'startlevel' => $user->getStartPosition()['level'],
            'oldlevel' => $oldlevel,
            'level' => $user->getLevel(),
            'lesson' => $user->getMaxLesson()
        ]);
       }   
    }   
});