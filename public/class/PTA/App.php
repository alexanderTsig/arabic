<?php

namespace PTA;

use \PDO;
use \Michelf\Markdown;

class App {

    protected static $exam_path = 'http://ptaexam.s3.amazonaws.com/';
    protected static $exercise_path = "http://ptaexercise.s3.amazonaws.com/";    

    const MAX_CLIPS = 2; // Allow up to two video clips per lesson/level tuple

    protected static $intro_videos = [
        '20c4e8d9333e8bf605006b5888d3a058ef77d5e1',
        '7426776508f0d239f3f7ab9868d82cd1ce53deca',
        '4968c11e62c32644dc010a77a61a3c951ad5dc55',
        '2f5ab6ebdab84c883509bd7f7395426b01759254',
        'cf8653a03c20e4b73e84492da6321e630a14b09e',
        '0f876e1354beab4ffec33e6ea875a67b8bfa710c',
        '9bbeee044ec6358ac30731224b2997adf7c1616b',
        'a386a29f1c86087a16e0998243e92827b3d68ac1',
        'fd5cfc7c57cb4f932420c806060a87541e3fe04b',
        '8e1d3c74c87c126bdfc43566aa4b79c1289b0117',
        '9f4b5547ea3b2a60387b0652fe00031fff41e13f',
        '389a67bdb5fd33b195c11c2a23aecd2afc9b096a',
        'bd332bdaa73925fa074e718b28a798d5b8acddaf',
        '0aeaa61973474644f79288fbf14821fe130a83a5',
        '658ab5ee85ad2e07ceeea895ff281a6ff4e26911',
        '573cc09f24400a511928342be8d551e38d46440a',
        'a564206fdffd162fce85dd0f914f7abb7713e8f9',
        '440f480a85b0b10e3dfd9bd4be72d2a687a7a476',
        'f0a9436af02ddb806a5b8b6c52bfa18c015495fa',
        'b89b4cba88d3fa22ae928055cc348724d0ad4ec2',
        '9c67749506bf08f1f8cc7365b9883b9bf33ab27c',
        '6d407d7f4a57ecb07835701c5fb133c3d22aae57',
        '6b0115f55410dc2b0f6cb53ca9f32f2412729a9f',
        '8420c591e5daba95b914bea711c9d789b8534621',
        '65bc5042a81f6c77c4b52f73ffa145f82f1c5f7f',
        'bd038e566a8f03180267f3228ca23dfe52e1f8c0'
    ];
    
    
    public static $Cat_membership_Code = "K2u8nrJ6Pc";
    public static $Cat_Academy_Code = "tiRuO84qkl";
    public static $Cat_Engage_Code = "KZzSFTAjc4";
    public static $Cat_Download_Code = "bZQvjKdFdE";
    public static $membership_path = "http://pathtoarabic.co.uk/acad-upgrade/";
    
    public static function getAvatarPath(){
        return $_SERVER["DOCUMENT_ROOT"]."/img/avatar/";
		//return '/var/cache/avatar/';
    }

    public static function getQuestionsHavingReview($level, $lesson) {
        if ($lesson !== null && !Validate::lesson($level, $lesson, __METHOD__))
            return [];

        $stmt = PdoFactory::getInstance()->prepare('SELECT question FROM review WHERE level = :level AND lesson = :lesson ORDER BY question ASC');
        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public static function getLevelIdentifiers() {
        $cache = self::getMemcachedInstance();
        $identifiers = $cache->get('level-identifiers');

        if (!$identifiers) {
            $stmt = PdoFactory::getInstance()->prepare('SELECT id FROM level ORDER by id ASC');
            $stmt->execute();
            $identifiers = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $cache->set('level-identifiers', $identifiers);
        }
        return $identifiers;
    }

    public static function getLevels() {
        $cache = self::getMemcachedInstance();
        $levels = $cache->get('levels');

        if (!$levels) {
            $stmt = PdoFactory::getInstance()->prepare('SELECT id, title, description FROM level ORDER by id ASC');
            $stmt->execute();
            $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cache->set('levels', $levels);
        }
        return $levels;
    }

    public static function getLesson($level = null, $lessonNumber = null) {
        $cache = self::getMemcachedInstance();
        $memcache_key = ('lessonbyid' . (($level !== null) ? "-$level" : '') . (($lessonNumber !== null) ? "-$lessonNumber" : ''));
        $lessons = $cache->get($memcache_key);
        if ($lessons) {
            return $lessons;
        }
        $stmt = PdoFactory::getInstance()->prepare('SELECT level, lesson, title_en, title_ar, description, desc_ar FROM lesson WHERE level = :level AND lesson = :lesson ORDER BY level ASC, lesson ASC');
        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $lessonNumber, PDO::PARAM_INT);
        $stmt->execute();
        $lesson = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lesson = [
                'level' => $row['level'],
                'lesson' => $row['lesson'],
                'title_en' =>$row['title_en'],
                'title_ar' =>$row['title_ar'],
                'description' => $row['description'] ? Markdown::defaultTransform($row['description']) : null,
                'description_ar' => $row['desc_ar'] ? Markdown::defaultTransform($row['desc_ar']) : null
            ];
            break;
        }
        $cache->set($memcache_key, $lesson);
        return $lesson;
    }

    public static function getLessons($level = null) {
        if ($level !== null && !Validate::level($level, __METHOD__))
            return null;

        $cache = self::getMemcachedInstance();
        $memcache_key = ('lessons' . ($level !== null) ? "-$level" : '');

        $lessons = $cache->get($memcache_key);
        if ($lessons)
            return $lessons;

        if ($level === null) {
            $stmt = PdoFactory::getInstance()->prepare('SELECT level, lesson, title_en, title_ar, description, desc_ar FROM lesson ORDER BY level ASC, lesson ASC');
        } else{
            $stmt = PdoFactory::getInstance()->prepare('SELECT level, lesson, title_en, title_ar, description, desc_ar FROM lesson WHERE level = :level ORDER BY level ASC, lesson ASC');
            $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        }
        $stmt->execute();

        $lessons = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lesson = [
                'level' => $row['level'],
                'lesson' => $row['lesson'],
                'title_en' => 'Lesson ' . $row['lesson'] . ': ' . $row['title_en'],
                'title_ar' => 'الدّرس ' . Convert::numberToArabic($row['lesson']) . ': ' . $row['title_ar'],
                'description' => $row['description'] ? Markdown::defaultTransform($row['description']) : null,
                'description_ar' => $row['desc_ar'] ? Markdown::defaultTransform($row['desc_ar']) : null
            ];

            # FIXME: Consider using consistent data structure here ...
            if ($level === null) {
                $lessons[] = $lesson;
            } else {
                # If a level was specified then have the array indices equate with the lesson number.
                # This is convenient for User::getLessons() because it needs to merge additional data with the array.
                $lessons[$row['lesson']] = $lesson;
            }
        }

        $cache->set($memcache_key, $lessons);
        return $lessons;
    }

    public static function getTeachers($level = null) {
        if ($level !== null && !Validate::level($level, __METHOD__))
            return null;

        $cache = self::getMemcachedInstance();
        $memcache_key = 'teachers' . ($level !== null ? "-$level" : '');
        $teachers = $cache->get($memcache_key);

        if ($teachers)
            return $teachers;

        $sql = null;

        if ($level === null) {
            $sql = 'SELECT id, name, gender FROM teacher ORDER BY teacher.name';
        } else {
            $sql = <<<'SQL'
				SELECT teacher.id, teacher.name, teacher.gender
				FROM teacher, teacher_level
				WHERE teacher.id = teacher_level.teacher_id AND teacher_level.level = :level
				ORDER by teacher.name
SQL;
        }

        $stmt = PdoFactory::getInstance()->prepare($sql);
        if ($level !== null)
            $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->execute();
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cache->set($memcache_key, $teachers);

        return $teachers;
    }

    public static function getNetConnectionURL() {
        return 'rtmp://s1ztnteh16mttb.cloudfront.net/cfx/st';
    }

    public static function getVideoCookie($level, $lesson, $gender) {
        if (!Validate::lesson($level, $lesson, __METHOD__))
            return null;

        $sql = <<<'SQL'
			SELECT video.cookie
			FROM video
			LEFT JOIN teacher
			ON video.teacher_id = teacher.id
			WHERE level = :level AND lesson = :lesson AND (teacher.gender = :gender OR video.teacher_id IS NULL)
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);

        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
        $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);

        $stmt->execute();

        if ($stmt->rowCount() === 0)
            return null;

        $count = 1;
        $cookies = [];

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $cookie) {
            #if ($count > self::MAX_CLIPS) {
            #	Log::warning("Too many clips in video table where level = $level and lesson = $lesson. Limiting to " . self::MAX_CLIPS . " result(s).");
            #	return $cookies;
            #}

            $cookies[] = "$cookie";
            $count++;
        }

        return $cookies;
    }

    public static function getVideoChapters($level, $lesson, $gender) {
        if (!Validate::lesson($level, $lesson, __METHOD__))
            return null;

        $sql = <<<'SQL'
			SELECT video.cookie, video_chapter.title, video_chapter.start
			FROM video_chapter, video
			LEFT JOIN teacher ON video.teacher_id = teacher.id
			WHERE video.level = :level AND video.lesson = :lesson
				AND video_chapter.video_id = video.id
				AND (teacher.gender = :gender OR video.teacher_id IS NULL)
			ORDER BY video.clip ASC, video_chapter.start ASC
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);

        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
        $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);

        $stmt->execute();

        if ($stmt->rowCount() === 0)
            return null;

        $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
          # If there is no chapter defined for the very beginning of the clip then insert one here
          if ($chapters[0]['start'] !== '00:00:00:00' && $chapters[0]['start'] !== '00:00:00.00') {
          array_unshift($chapters, [
          'cookie' => $chapters[0]['cookie'],
          'title'  => 'Introduction',
          'start'  => '00:00:00.00'
          ]);
          } */

        # Ignore any chapters defined in the database that align with the very beginning of the video
        /* if ($chapters[0]['start'] == '00:00:00:00' || $chapters[0]['start'] == '00:00:00.00')
          array_shift($chapters); */

        # Insert the introductory clip (with animated logo and lesson number)
        array_unshift($chapters, [
            'cookie' => self::$intro_videos[$lesson - 1],
            'title' => 'Intro',
            'start' => '00:00:00.00'
        ]);

        return $chapters;
    }

    # Converts the output of getVideoChapters() to a format suitable for initializing flowplayer.
    # Downstream is expected to convert to JSON as necessary.

    #
    public static function getVideoPlaylist($level, $lesson, $gender, $pseudostreaming = false) {
        $chapters = self::getVideoChapters($level, $lesson, $gender);

        if ($chapters === null)
            return null;

        $clips = [];
        $last_cookie = null;
        $cuepoints = [];

        for ($i = 0; $i < count($chapters); $i++) {
            $chapter = $chapters[$i];
            $cookie = $chapter['cookie'];
            $cursor = $chapter['start'];

            if ($i === 0 || $cookie === $last_cookie) {
                # Collect the cuepoint
                $cuepoints[] = self::cursorToSeconds($cursor);// * 1000;
            } else {
                # We're iterating over a new clip. Stash the preceding clip along with its cuepoints.
                $clips[] = [
                    'url' => ($pseudostreaming === false) ? "mp4:$last_cookie" : "https://ptavideo.s3.amazonaws.com/$last_cookie.mp4",
                    'cuepoints' => $cuepoints,
                    'autoPlay' => (count($clips) === 0) ? false : true # don't autoplay the first clip
                ];
                # Don't forget to set the first cuepoint for the new clip.
//                $cuepoints = [ self::cursorToSeconds($cursor) * 1000];
                $cuepoints = [ self::cursorToSeconds($cursor)];
            }

            if ($i === (count($chapters) - 1)) {
                # This is the final cuepoint. Stash the current clip along with its cuepoints before returning.
                $clips[] = [
                    'url' => ($pseudostreaming === false) ? "mp4:$cookie" : "https://ptavideo.s3.amazonaws.com/$cookie.mp4",
                    'cuepoints' => $cuepoints,
                    'autoPlay' => (count($clips) === 0) ? false : true # don't autoplay the first clip
                ];

                # Prepend a splash image to the playlist which contains the logo and lesson number in English and Arabic
                array_unshift($clips, [
                    'url' => 'https://ptaimg.s3.amazonaws.com/videosplash.png',
                    'scaling' => 'orig',
                    'autoPlay' => true
                ]);

                return $clips;
            }

            $last_cookie = $cookie;
        }
    }

    public static function cursorToSeconds($cursor) {
        // FIXME: some values have semicolon as last separator instead of a colon
        $part = preg_split("/[:\.]/", $cursor, 4);

        $hours = $part[0];
        $minutes = $part[1];
        $seconds = $part[2];
        $frames = $part[3];

        return round($hours * 3600 + $minutes * 60 + $seconds + ($frames * 0.04));
    }
    
    public static function getHostAddr(){
        return "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s://" : "://") . $_SERVER['HTTP_HOST'];        
    }

    public static function getExerciseURL($level, $lesson) {
        if (!Validate::lesson($level, $lesson, __METHOD__))
            return null;
        $host = self::getHostAddr();
        $tincanBackend  = $host. "/${level}/${lesson}/result-exercise";
        $url = self::$exercise_path . "v2/${level}/${lesson}/story.html?endpoint=".$tincanBackend;
        return $url;
    }

    public static function getExamURL($level, $lesson, $userId, $authkey) {
        if (!Validate::lesson($level, $lesson, __METHOD__))
            return null;
        $host = self::getHostAddr();
        $tincanBackend  = $host. "/level/${level}/${lesson}/result-exam/";
        $actor = $userId;
        $url = self::$exam_path . "v2.1/${level}/${lesson}/story.html?endpoint=".$tincanBackend."&user=".$actor."&auth=".$authkey."&registration=760e3480-ba55-4991-94b0-01820dbd23a2";
        return $url;
    }

    public static function getLoginURL() {
        return \Am_Lite::getInstance()->getLoginURL();
    }

    public static function renderLoginForm() {
        return \Am_Lite::getInstance()->renderLoginForm();
    }

    public static function getAnnouncements($max_results = 10, $date_format = 'l, M Y') {
        if (!Number::isInteger($max_results))
            $max_results = 10;

        $sql = 'SELECT id,title,text,UNIX_TIMESTAMP(created_on) as epoch FROM announcements ORDER BY created_on DESC LIMIT :max_results';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':max_results', $max_results, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0)
            return null;

        $results = [];
        $i = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$i] = $row;
            $results[$i]['date'] = date($date_format, $row['epoch']); # insert friendly date
            $i++;
        }

        return $results;
    }

    public static function getAnnouncement($id) {
        if (!Number::isInteger($id))
            return false;

        $sql = 'SELECT id,title,text,UNIX_TIMESTAMP(created_on) as epoch FROM announcements WHERE id = :id';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0)
            return null;

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getMemcachedInstance() {
        $cache = new \Memcached();
        $cache->addServer('localhost', 11211);
        return $cache;
    }

    public static function createUUID() {
        return chop(shell_exec('/usr/bin/openssl rand 20 -hex'));
    }

    public static function getCountryList() {
        $cache = self::getMemcachedInstance();
        $countries = $cache->get('countries');

        if (!$countries) {
            $stmt = PdoFactory::getInstance()->prepare('SELECT code, name FROM country ORDER by name ASC');
            $stmt->execute();
            $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cache->set('countries', $countries);
        }
        return $countries;
    }

    public static function getFAQ($public = false) {
        $public = ($public === true) ? 1 : 0;

        $sql = <<<'SQL'
			SELECT faq_category.text AS category, faq_category.anchor AS category_anchor, faq.anchor AS question_anchor, faq.question AS question, faq.answer AS answer
			FROM faq_category, faq
			WHERE faq_category.id = faq.category_id AND is_public = :is_public
			ORDER BY faq_category.num
SQL;
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':is_public', $public, PDO::PARAM_INT);
        $stmt->execute();

        $faq = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $field) {
            $answer = [
                'anchor' => $field['question_anchor'],
                'text' => Markdown::defaultTransform($field['answer'])
            ];

            $faq[$field['category']][$field['question']] = $answer;

            // Store the anchor (this is used for navigating quickly between FAQ categories)
            if (!array_key_exists('anchor', $faq[$field['category']]))
                $faq[$field['category']]['anchor'] = $field['category_anchor'];
        }

        return $faq;
    }

    /* private static function markupFaqAnswer($text) {
      # Markup email addresses (this regex isn't RFC compliant but it doesn't need to be)
      preg_match_all('/(?:\s|^)([[:alpha:]]+?@pathtoarabic\.com)(?:\s|$|\.)/iD', $text, $matches, PREG_SET_ORDER);
      foreach ($matches as $match) {
      $address = strtolower($match[1]);
      $text = str_replace($match[1], "<a href=\"mailto:$address\">$address</a>", $text);
      }

      # Markup URLs
      $text = preg_replace('|(http://[^$\s]+[[:alpha:]])|D', '<a href="\1">\1</a>', $text);

      # Convert line feeds to <br> tags
      $text = str_replace("\n", "\n<br>", $text);

      # Format bullet points
      $text = preg_replace('/^(?:<br>)?\*\s+(.+)/m', '<li>\1</li>', $text);
      $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>\1<ul>', $text);

      return $text;
      } */

    public static function getAverageScores($level, $offset = 0) {
        if ($level !== null && !Validate::level($level, __METHOD__))
            return null;

        $cache = self::getMemcachedInstance();
        $resultset = $cache->get("scores-$level");

        if (!$resultset) {
            $sql = <<<"SQL"
				SELECT lesson_id, AVG(score) AS mean_score
				FROM test_result
				WHERE level_id = :level
				GROUP BY level_id, lesson_id
SQL;

            $stmt = PdoFactory::getInstance()->prepare($sql);
            $stmt->bindValue(':level', $level, PDO::PARAM_INT);
            $stmt->execute();

            $resultset = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cache->set("scores-$level", $resultset, time() + 3600);
        }

        $scores = [];
        $next_index = 1;

        foreach ($resultset as $row) {
            $index = $row['lesson_id'];

            for ($i = $next_index; $i <= $index; $i++) {
                $scores[$i + $offset] = null;
            }
            $next_index = $index + 1;

            $scores[$index + $offset] = (float) sprintf('%0.2f', $row['mean_score']);
        }

        return $scores;
    }

    public static function getReferralTypes() {
        $sql = 'SELECT * FROM referral_type ORDER BY name';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findAffiliate($searchterm = null) {
        $users = AmemberRest::getUsers();

        $match_fields = ['fullname', 'login', 'email'];
        $affiliates = [];

        foreach ($users as $user) {
            if (gettype($user) !== "array")
                continue;

            $user['fullname'] = ($user['name_l']) ? $user['name_f'] . ' ' . $user['name_l'] : $user['name_f'];

            if (array_key_exists('is_affiliate', $user) && ($user['is_affiliate'] == 1 || $user['is_affiliate'] == 2)) {
                $record = [
                    'user_id' => $user['user_id'],
                    'affiliate_id' => $user['aff_id'],
                    'login' => $user['login'],
                    'fullname' => $user['fullname'],
                    'email' => $user['email']
                ];

                if ($searchterm !== null) {
                    foreach ($match_fields as $fieldname) {
                        if (array_key_exists($fieldname, $user) && stripos($user[$fieldname], $searchterm) !== false) {
                            $record['matched_field'] = $fieldname;
                            $affiliates[] = $record;
                            break;
                        }
                    }
                } else {
                    $affiliates[] = $record;
                }
            }
        }

        return $affiliates;
    }

    public static function calculateLeaderboard($limit = 5, $join_year = null, $join_month = null) {
        $where_clause = '';

        if ($join_year !== null)
            $where_clause .= ' AND YEAR(student.added) = :year';

        if ($join_month !== null)
            $where_clause .= ' AND MONTH(student.added) = :month';

        $sql = <<<"SQL"
			SELECT
				student.user_id,
				student.name_f,
				student.name_l,
				student.country,
				SUM(score.points) AS total_points
			FROM
				amember4.am_user         AS student,
				pathtoarabic.test_result AS score
			WHERE student.user_id = score.user_id $where_clause
			GROUP BY student.user_id
			ORDER BY total_points DESC
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if ($join_year !== null)
            $stmt->bindValue(':year', $join_year, PDO::PARAM_INT);

        if ($join_month !== null)
            $stmt->bindValue(':month', $join_month, PDO::PARAM_INT);

        $stmt->execute();

        for ($i = 0; $i < count($board); $i++) {
            $board[$i]['rank'] = $i + 1;
        }

        return $board;
    }

}
