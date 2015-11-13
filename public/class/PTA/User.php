<?php

namespace PTA;

use DateTime;
use DateTimeZone;
use PDO;
use \Michelf\Markdown;

class User implements \JsonSerializable {

    private static $instance;
    protected $id;
    protected $min_lesson_for = [];
    protected $max_lesson_for = [];
    protected $rank;
    protected $signup_date;

    # The object constructor. aMember handles the user session. If the user is
    # signed in then his or her id will be determined with the support of AM_Lite.

    #
	public function __construct($id = null) {
        if ($id === null) {
            $am_user = \AM_Lite::getInstance()->getUser();
            $this->id = $am_user['user_id'];
        } else {
            $this->id = $id; // This is useful for debugging
        }
    }

    # Embed an instance of the object as a Singleton. The principal advantage of
    # using the Factory pattern to obtain an instance is that we will avoid the
    # overhead incurred by the constructor within the scope of a request.
    # It also means that we can return a Teacher object where appropriate.

    #
	public static function getInstance($id = null) {
        if ($id !== null) {
            self::$instance = new User($id);
        } elseif (self::$instance === null) {
            $am_user = \AM_Lite::getInstance()->getUser();

            if ($am_user !== null && array_key_exists('access_level', $am_user) && $am_user['access_level'] == 1) {
                self::$instance = new Teacher();
            } else {
                self::$instance = new User();
            }
        }

        return self::$instance;
    }

    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'level' => $this->getLevel(),
            'background_image' => $this->getBackgroundImage(),
            'maxLesson' => $this->getMaxLesson(),
            'minLesson' => $this->getMinLesson(),
            'startLevel' => $this->getStartPosition()['level'],
            'startLesson' => $this->getStartPosition()['lesson'],
            'progress' => $this->getProgress(),
            'pref' => [
                'view' => [
                    'oldLessons' => $this->getPref('view:oldLessons'),
                    'arabicText' => $this->getPref('view:arabicText'),
                    'filterByStar' => $this->getPref('view:filterByStar'),
                ],
                'video' => [
                    'pseudoStreaming' => $this->getPref('video:pseudoStreaming'),
                ],
                'defaultGender' => $this->getPreferredGender(),
                'background_image' => $this->getBackgroundImage()
            ]
        ];
    }

    public function switchLevel($level) {
        if (!Validate::level($level, __METHOD__))
            return false;
        $user_id = $this->id;
        if ($level === $this->getLevel())
            Log::debug(__METHOD__ . ": cancelling switch to level $level because this is already the active level for user $user_id");
        $this->wipeTestResults();
        $this->invalidateCache();
        Log::debug(__METHOD__ . ": switch to level $level for user $user_id");
    }

    private function wipeTestResults() {
        $sql = 'DELETE FROM test_results WHERE user_id = :user_id';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return ($stmt->rowCount() > 0);
    }

    public function getPoints($user_id = null) {
        if ($user_id === null)
            $user_id = $this->getUserId();

        $cache = App::getMemcachedInstance();
        $memcache_key = "user-$user_id-points";
        $points = $cache->get($memcache_key);
        if ($points !== false)
            return $points;

        $sql = <<<SQL
			SELECT SUM(points) FROM (
				SELECT level_id, lesson_id, MAX(points) AS points
				FROM test_result
				WHERE user_id = :user_id AND score >= passing_score
				GROUP BY level_id, lesson_id
			) AS t
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0)
            return null;

        $points = $stmt->fetch(PDO::FETCH_COLUMN, 0);
        if ($points === null)
            $points = 0;
        $cache->set($memcache_key, $points);
        return $points;
    }

    public function getVideoPlaylist($level, $lesson, $gender) {
        if (!\PTA\Validate::gender($gender))
            $gender = $this->getPreferredGender();
//		return \PTA\App::getVideoPlayList($level, $lesson, $gender, $this->getPref('video:pseudoStreaming'));
        return \PTA\App::getVideoPlayList($level, $lesson, $gender, true);
    }

    public function getTips($context = null) {
        $sql = <<<SQL
			SELECT id, message
			FROM tip
			WHERE id NOT IN (
				SELECT tip_id AS id
				FROM tip_view
				WHERE user_id = :user_id
					AND (dismissed != 0 OR (dismissed = 0 AND last_ack >= DATE_SUB(NOW(), INTERVAL 24 HOUR)))
			)
SQL;

        if ($context !== null)
            $sql .= 'AND context = :context';

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        if ($context !== null)
            $stmt->bindValue(':context', $context, PDO::PARAM_STR);
        $stmt->execute();

        # Return an empty array if there are no tips to be shown
        if ($stmt->rowCount() === 0)
            return [];

        $tips = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tips as &$tip) {
            $tip['message'] = Markdown::defaultTransform($tip['message']);
        }

        return $tips;
    }

    public function getAmemberNotification() {
        $user_id = $this->id;
        $product_ids = array();
        foreach (\AM_Lite::getInstance()->getAccess() as $access) {
            $expiry_date = DateTime::createFromFormat('Y-m-d H:i:s', $access['expire_date'] . ' 00:00:00', new DateTimeZone('UTC'));
            # We're only interested in access records for the current user that haven't expired
            if ($access['user_id'] != $this->id || $expiry_date->format('U') - time() <= 0)
                continue;
            $product_ids[] = $access['product_id'];
        }
        $categories = array();
        if (count($product_ids) > 0) {
            $sql = "SELECT * FROM `am_product_category` WHERE `product_category_id` in (SELECT `product_category_id` FROM `am_product_product_category` WHERE `product_id` IN (" . implode(',', $product_ids) . "))";
            $categories = \PTA\Amlite::getInstance()->select($sql);
        }
        $category_ids = array();
        if (count($categories) > 0) {
            foreach ($categories as $row) {
                $category_ids[] = $row['product_category_id'];
            }
        }
        array_push($product_ids, '-1'); //to avoide SQL error
        $category_ids = array();
        array_push($category_ids, '-1'); //to avoide SQL error		

        $user_group_ids = array();
        array_push($user_group_ids, '-1'); //to avoide SQL error

        $sql = sprintf("SELECT * FROM ?_notification WHERE
            notification_id IN (
                SELECT notification_id FROM ?_notification_access WHERE
                    ((fn='%s' AND id =  %s) OR
                    (fn='%s' AND id IN (%s)) OR
                    (fn='%s' AND id IN (%s)) OR
                    (fn='%s')) OR
                    (fn='%s' AND id IN (%s))
            )
            AND
                (begin IS NULL OR begin<='%s') AND
                (expire IS NULL OR expire>='%s') AND
                is_disabled=0
            ORDER BY sort_order", 'user_id', $user_id, 'product_id', implode(',', $product_ids), 'product_category_id', implode(',', $category_ids), 'free', 'user_group_id', implode(',', $user_group_ids), date("Y-m-d"), date("Y-m-d")
        );
        $notofications = \PTA\Amlite::getInstance()->select($sql);
        return $notofications;
    }

    function deferTip($tip_id) {
        if (!Number::isInteger($tip_id))
            return false;

        $sql = <<<SQL
			INSERT INTO tip_view (user_id, tip_id, last_ack) VALUES (:user_id, :tip_id, NOW())
			ON DUPLICATE KEY UPDATE last_ack = NOW()
SQL;
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':tip_id', $tip_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    function dismissTip($tip_id) {
        if (!Number::isInteger($tip_id))
            return false;

        $sql = <<<SQL
			INSERT INTO tip_view (user_id, tip_id, last_ack, dismissed) VALUES (:user_id, :tip_id, NOW(), 1)
			ON DUPLICATE KEY UPDATE dismissed = 1
SQL;
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':tip_id', $tip_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getSignupDate() {
        if ($this->signup_date !== null)
            return $this->signup_date;

        $sql = <<<'SQL'
			SELECT YEAR(student.added) AS year, MONTH(student.added) AS month
			FROM amember4.am_user AS student
			WHERE student.user_id = :user_id
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        # Watch out - this can mask errors
        #if ($stmt->rowCount() !== 1)
        #	return null;

        $this->signup_date = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->signup_date;
    }

    public function getSignupDateAsEpoch() {
        $sql = <<<'SQL'
			SELECT UNIX_TIMESTAMP(am_user.added)
			FROM amember4.am_user
			WHERE user_id = :user_id
SQL;
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_COLUMN, 0);
    }

    public function calculateLeaderboard($limit = 5) {
        $limit += ($limit & 1) ? 0 : 1;

        $sql = <<<'SQL'
			SELECT
				am_user.user_id,
				am_user.login

			FROM
				amember4.am_user

			LEFT JOIN amember4.am_data ON (am_user.user_id = am_data.id AND am_data.`key` = 'access_level')

			WHERE
				(am_data.value IS NULL OR am_data.value != '1')
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $board = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $board[] = [
                'user_id' => $row['user_id'],
                'login' => $row['login'],
                'points' => $this->getPoints($row['user_id'])
            ];
        }

        usort($board, function($a, $b) {
            return $a['points'] < $b['points'];
        });

        $rank = 1;
        foreach ($board as &$entry) {
            $entry['rank'] = $rank;
            $rank++;
        }

        $user_entry = array_filter($board, function($item) {
            return ($item['user_id'] == $this->getUserId());
        });

        if (count($user_entry) == 1) {
            $array_pos = key($user_entry);
            array_splice($board, 0, $array_pos - (($limit - 1) / 2));
            //array_splice($board, $limit - 1);
            array_splice($board, $limit );
        } else {
            array_splice($board, $limit);
        }


        return $board;
    }

    public function calculateLeaderboardBroken($limit = 5) {
        $sql = <<<'SQL'
			SELECT
				student.user_id,
				student.login,
				student.country,
				SUM(score.points) AS total_points

			FROM
			(
				amember4.am_user         AS student,
				pathtoarabic.test_result AS score,
				#amember4.am_data         AS data,
				(
					SELECT student.added AS user_added
					FROM amember4.am_user AS student
				) AS filter
			)
				#(
				#	SELECT
				#		MONTH(student.added) AS join_month,
				#		YEAR(student.added) AS join_year
				#	FROM amember4.am_user AS student
				#	WHERE student.user_id = :user_id
				#) AS filter

			LEFT JOIN amember4.am_data AS data ON (student.user_id = data.id AND data.`key` = 'access_level')
			WHERE student.user_id = score.user_id
				AND data.value != '1'
				AND student.added >= filter.user_added - interval 47 week
				AND student.added <= filter.user_added + interval 47 week
			#	AND MONTH(student.added) = filter.join_month
			#	AND YEAR(student.added)  = filter.join_year

			GROUP BY student.user_id
			ORDER BY total_points DESC
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $board = [];
        $count = 0;
        $user_rank = null;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['rank'] = $count + 1;

            if ($row['user_id'] == $this->id) {
                $user_rank = $row['rank'];
                $board[] = $row;
            } else if ($count < $limit) {
                $board[] = $row;
            }

            $count++;

            if ($user_rank !== null && $count >= $limit) {
                $this->rank = $user_rank;
                break;
            }
        }

        if ($count > $limit)
            array_splice($board, -2, 1);

        return $board;
    }

    public function getRank() {
        if ($this->rank === null) {
            $this->calculateLeaderboard(1);
        }
        return $this->rank;
    }

    public function invalidateCache() {
        $cache = App::getMemcachedInstance();

        $user_id = 'user-' . $this->getUserId();
        $cache->delete("$user_id-tests-passed");

        for ($level = 1; $level <= count(App::getLevelIdentifiers()); $level++) {
            $cache->delete("$user_id-test-results-$level");
            $cache->delete("$user_id-tests-passed-$level");
            $cache->delete("$user_id-points");
        }

        $this->max_lesson_for = [];

        Log::debug('Invalidated cache for user ' . $this->getUserId());
    }

    # FIXME: Use memcache?

    #
	public function getLevel() {
        $all_levels = App::getLevelIdentifiers();
        $start_level = $this->getStartPosition()['level'];

        foreach (range($start_level, end($all_levels)) as $level) {
            $lesson_count = count(App::getLessons($level));
            $tests_passed = $this->getTestsPassed($level);
            if ($tests_passed < $lesson_count)
                return $level;
        }

        return end($all_levels);
    }

    public function getBackgroundImage() {
        $sql = 'SELECT background_image FROM user WHERE user.id = :user_id';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return ($stmt->rowCount() > 0 ? $stmt->fetchColumn(0) : '');
    }

    # null then all levels are taken into consideration.

    #
	public function calculateTestsPassed($level = null, $min_lesson = 1) {
        if (!Validate::level($level, __METHOD__))
            return null;

        $cache = App::getMemcachedInstance();
        $memcache_key = 'user-' . $this->getUserId() . '-tests-passed' . ($level !== null ? "-$level" : '');
        $tests_passed = $cache->get($memcache_key);

        if ($tests_passed !== false)
            return count($tests_passed);

        $where_clause = ($level === null) ? 'user_id = :user_id AND score >= passing_score' : 'user_id = :user_id AND level_id = :level AND score >= passing_score';

        $sql = <<<"SQL"
			SELECT lesson_id
			FROM test_result
			NATURAL JOIN (
				SELECT lesson_id, MAX(score) AS score
				FROM test_result
				WHERE $where_clause
				GROUP BY lesson_id
			) AS t
			WHERE user_id = :user_id
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        if ($level !== null)
            $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->execute();

        $tests_passed = [];
        while (($lesson = $stmt->fetch(PDO::FETCH_COLUMN)) !== false) {
            if ($lesson >= $min_lesson)
                $tests_passed[$lesson] = true;
        }

        $cache->set($memcache_key, $tests_passed);
        return count($tests_passed);
    }

    # Calculate the overall progress of the user as a percentage. This is done by
    # dividing the number of lessons by the number of tests passed, either for a
    # given level or the entire course.

    #
	private function calculateProgress($level = null) {
        if ($level !== null && !Validate::level($level, __METHOD__))
            return null;

        return round($this->getTestsPassed($level) / count(App::getLessons($level)) * 100);
    }

    public static function checkLogin($user, $password) {
        return AmemberRest::checkAccessByLoginPass($user, $password);
    }

    # Determine whether the user has a valid (non-expired) membership

    #
	public function isMembershipValid() {
        return true;
        // return \Am_Lite::getInstance()->haveSubscriptions(\Am_Lite::ANY);
        return \AM_Lite::getInstance()->isUserActive();
    }

    public function hasCancelledMembership() {
        # This query returns a date if the following two conditions hold true:
        #
		# 1) The user has a valid membership covered by a paid invoice
        # 2) The user has cancelled his or her membership
        #
		# This has particular uses such as refraining from nagging the user to renew.
        #
		$sql = <<<"SQL"
			SELECT MAX(expire_date) AS expire_date
			FROM amember4.am_access AS a, amember4.am_invoice AS i
			WHERE
				a.invoice_id = i.invoice_id AND
				i.status = 3 AND
				a.user_id = :user_id
			HAVING MAX(expire_date) > NOW()
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        return ($stmt->rowCount() ? true : false);
    }

    public function getMembershipStatus() {
        # The constants are defined here: amember4/application/default/models/User.php
        switch (\Am_Lite::getInstance()->getUser()['status']) {
            case 0: return 'pending';
            case 1: return 'active';
            case 2: return 'expired';
        }
        return null;
    }

    # Return the membership type. We ascertain that by walking through access
    # records for the user and determining the best product to which they still
    # have access.

    #
    public function getMembershipType() {
        $products = \AM_Lite::getInstance()->getProducts();
        $best_product = null;
        $weight = 0;
        $b = \AM_Lite::getInstance()->getCategories();

        foreach (\AM_Lite::getInstance()->getAccess() as $access) {
            $expiry_date = DateTime::createFromFormat('Y-m-d H:i:s', $access['expire_date'] . ' 00:00:00', new DateTimeZone('UTC'));

            # We're only interested in access records for the current user that haven't expired
            if ($access['user_id'] != $this->id || $expiry_date->format('U') - time() <= 0)
                continue;

            # Check whether the product_id refers to a product better than any seen in a previous iteration
            $prospective_weight = Product::getWeight($products[$access['product_id']]);

            if ($prospective_weight > $weight) {
                $weight = $prospective_weight;
                $best_product = $products[$access['product_id']];
            }
        }

        return $best_product;
    }

    public function getProductCategory() {
//            $categories =\PTA\Amlite::getInstance()->select("SELECT * FROM ?_product_category ORDER BY parent_id, 0+sort_order");
        $productIds = array();
        foreach (\AM_Lite::getInstance()->getAccess() as $access) {
            $expiry_date = DateTime::createFromFormat('Y-m-d H:i:s', $access['expire_date'] . ' 00:00:00', new DateTimeZone('UTC'));
            # We're only interested in access records for the current user that haven't expired
            if ($access['user_id'] != $this->id || $expiry_date->format('U') - time() <= 0)
                continue;
            $productIds[] = $access['product_id'];
        }
        $categories = array();
        if (count($productIds) > 0) {
            $sql = "SELECT * FROM `am_product_category` WHERE `product_category_id` in (SELECT `product_category_id` FROM `am_product_product_category` WHERE `product_id` IN (" . implode(',', $productIds) . "))";
            $categories = \PTA\Amlite::getInstance()->select($sql);
        }
        $categoryCodes = array();
        if (count($categories) > 0) {
            foreach ($categories as $row) {
                $categoryCodes[] = $row['code'];
            }
        }
        return $categoryCodes;
    }

    public function getProductType() {
        $ProductTypes = $this->getProductCategory();
        //Full Access - Product 1 - Academy or Membership
        if (in_array(\PTA\App::$Cat_membership_Code, $ProductTypes) == true || in_array(\PTA\App::$Cat_Academy_Code, $ProductTypes) == true) {
            return "academy";
        }
        // Product 2 - Engage
        if (in_array(\PTA\App::$Cat_Engage_Code, $ProductTypes) == true) {
            return "engage";
        }
        // Product 3 - Digital Download        
        if (in_array(\PTA\App::$Cat_Engage_Code, $ProductTypes) == true) {
            return "digital download";
        }
        return "";
    }

    # If the user's membership is chronologically scoped then return the expiry date as a DateTime object.

    #
	public function getExpiryDate() {
        $date_string = \Am_Lite::getInstance()->getExpire(\Am_Lite::ANY);

        if ($date_string === null)
            return null;

        # FIXME: check whether aMember returns dates differently depending on a user's timezone
        $date = DateTime::createFromFormat('Y-m-d H:i:s', "$date_string 00:00:00", new DateTimeZone('UTC'));

        if ($date === false) {
            Log::error("Unable to convert expiry date to DateTime object: $date_string");
            return null;
        } else {
            return $date;
        }
    }

    # Test whether a membership expires within a given period of time, in seconds.

    #
	public function membershipExpiresIn($seconds) {
        $expiry_date = $this->getExpiryDate();
        return ($expiry_date !== null && $expiry_date->format('U') - time() <= $seconds);
    }

    # Determine whether the user is currently signed in

    #
	public function isLoggedIn() {
        return \Am_Lite::getInstance()->isLoggedIn();
    }

    public function getUserId() {
        # The object constructor. aMember handles the user session. If the user is
        # signed in then his or her id will be determined with the support of AM_Lite.
        #if ($this->id === null) {
        #	$am_user = AM_Lite::getInstance()->getUser();
        #	$this->id = $am_user['user_id'];
        #}

        return $this->id;
    }

    public function getClass() {
        $class = get_class(self::$instance);

        if (!$class)
            return null;

        return (preg_match('/Teacher$/', $class) ? 'teacher' : 'student');
    }

    # Return the user's name from aMemeber

    #
	public function getName() {
        return \AM_Lite::getInstance()->getName();
    }

    public function getFirstname() {
        preg_match('/^(\S+)/', $this->getName(), $matches);
        return $matches[1];
    }

    # Return the username from aMember

    #
	public function getUsername() {
        return \AM_Lite::getInstance()->getUsername();
    }

    # Return the user's email address from aMember

    #
	public function getEmail() {
        $email = \AM_Lite::getInstance()->getEmail();
        return strtolower(trim($email));
    }

    # Return the user's phone number from aMember

    #
	public function getPhone() {
        $am_user = AM_lite::getInstance()->getUser();
        return $am_user['phone'];
    }

    # FIXME: Use memcache

    #
	public function getTestsPassed($level = null) {
        $levels = ($level === null) ? App::getLevelIdentifiers() : [ $level];
        $start = $this->getStartPosition();
        $tests_passed = 0;

        foreach ($levels as $level) {
            $min_lesson = 1;

            # If the level is lower than the user's start level then count all possible tests as having been passed
            if ($level < $start['level']) {
                $tests_passed += count(App::getLessons($level));
                continue;
            }
            # If the level is equal to the user's start level then count all possible tests up to the start lesson as having been passed (if applicable)
            elseif ($level == $start['level'] && $start['lesson'] > 1) {
                $min_lesson = $start['lesson'];
                $tests_passed += $min_lesson - 1;
            }

            $tests_passed += $this->calculateTestsPassed($level, $min_lesson);
        }

        return $tests_passed;
    }

    # FIXME: Use memcache

    #
	public function getProgress($level = null) {
        return $this->calculateProgress($level);
    }

    # Determine the user's preferred teacher gender

    #
	public function getPreferredGender($null_value = 'M') {
        $sql = 'SELECT default_gender FROM user WHERE user.id = :user_id';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return ($stmt->rowCount() > 0 ? $stmt->fetchColumn(0) : $null_value); # FIXME: breaks video viewing if null is returned. why?
    }

    public function setPreferredGender($gender) {
        $sql = 'INSERT INTO user (id, default_gender) VALUES (:user_id, :gender) ON DUPLICATE KEY UPDATE default_gender = :gender';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
        return ($stmt->execute() === true) ? 0 : 1;
    }

    public function setPreferredBackGroundImage($imageUrl) {
        $sql = 'INSERT INTO user (id, background_image) VALUES (:user_id, :background_image) ON DUPLICATE KEY UPDATE background_image = :background_image';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':background_image', $imageUrl, PDO::PARAM_STR);
        return ($stmt->execute() === true) ? 0 : 1;
    }

    public function getReferralTypes() {
        $sql = 'SELECT r.name FROM referral_type AS r, user_referral AS ur WHERE ur.user_id = :user_id AND ur.referral_type_id = r.id';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_NUM);
    }

    public function addReferralTypes($types) {
        $sql = 'INSERT IGNORE INTO user_referral (user_id, referral_type_id) VALUES (:user_id, :referral_type_id)';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);

        foreach ($types as $type_id) {
            $stmt->bindValue(':referral_type_id', $type_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        return 0; # FIXME: errors should be propagated
    }

    # For a given level/lesson tuple, return the tuple for the preceding lesson,
    # provided that the user is allowed to access it (otherwise it returns null).
    # This is particularly useful for navigation buttons.

    #
	public function getPreviousLesson($level, $lesson) {
        # Beware! The Slim framework always passes parameters as strings.
        $level = Number::toInteger($level);
        $lesson = Number::toInteger($lesson);

        if ($level > 1 && $lesson === 1) {
            $level--;
            $lesson = count(App::getLessons($level));
        } else if ($lesson > 1) {
            $lesson--;
        } else {
            return null;
        }

        if (!$this->isLessonAvailable($level, $lesson))
            return null;

        return [$level, $lesson];
    }

    # For a given level/lesson tuple, return the tuple for the following lesson,
    # provided that the user is allowed to access it (otherwise it returns null).
    # This is particularly useful for navigation buttons.

    #
	public function getNextLesson($level, $lesson) {
        # The Slim framework always passes parameters as strings
        $level = Number::toInteger($level);
        $lesson = Number::toInteger($lesson);

        if ($lesson < count(App::getLessons($level))) {
            $lesson++;

            if (!$this->isLessonAvailable($level, $lesson))
                return null;
        }
        else if ($level < count(App::getLevels())) {
            $level++;
            $lesson = 1;

            if (!$this->isLevelAvailable($level))
                return null;
        }
        else {
            return null;
        }

        return [$level, $lesson];
    }

    public function isLevelAvailable($level) {
        if (!Validate::level($level, __METHOD__))
            return null;

        return ($level >= $this->getStartPosition()['level'] && $level <= $this->getLevel());
    }

    public function isLessonAvailable($level, $lesson) {
        if (!Validate::lesson($level, $lesson, __METHOD__))
            return null;

        return ($lesson >= $this->getMinLesson($level) && $lesson <= $this->getMaxLesson($level));
    }

    # For a given level, return the highest lesson available to the user

    #
	public function getMaxLesson($level = null) {
        if ($level !== null && array_key_exists($level, $this->max_lesson_for))
            return $this->max_lesson_for[$level];

        $start = $this->getStartPosition();

        if ($level === null) {
            $level = $this->getLevel();
        } else {
            // There can be no legitimate answer if the requested level is outside the user's reach
            if (!$this->isLevelAvailable($level))
                return null;
        }

        $max_lesson = $this->getTestsPassed($level);
        $max_lesson = ($max_lesson === count(App::getLessons($level))) ? $max_lesson : $max_lesson + 1;

        if ($level == $start['level'] && $max_lesson < $start['lesson']) {
            $max_lesson = $start['lesson'];
        }

        $this->max_lesson_for[$level] = $max_lesson;
        return $max_lesson;
    }

    public function getMinLesson($level = null) {
        if ($level !== null && array_key_exists($level, $this->min_lesson_for))
            return $this->min_lesson_for[$level];

        if ($level === null) {
            $level = $this->getLevel();
        } else {
            // There can be no legitimate answer if the requested level is outside the user's reach
            if (!$this->isLevelAvailable($level))
                return null;
        }

        $start = $this->getStartPosition();
        $min_lesson = ($level === $start['level']) ? $start['lesson'] : 1;
        $this->min_lesson_for[$level] = $min_lesson;
        return $min_lesson;
    }

    # Return a list of lessons for a given level. If the level is not defined then
    # it defaults to the user's current level. This method is different to that in
    # the application class because it includes the user's test results.

    #
	public function getLessons($level = null) {
        if ($level === null)
            $level = $this->getLevel();

        $lessons = App::getLessons($level);

        // FIXME: is this still valid? memcache returns false for missing key
        if ($lessons === false)
            return false;

        $cache = App::getMemcachedInstance();
        $memcache_key = 'user-' . $this->getUserId() . "-test-results-$level";
        $test_results = $cache->get($memcache_key);

        if ($test_results === false) {
            $sql = <<<"SQL"
				SELECT lesson_id, score, passing_score, timestamp
				FROM test_result
				NATURAL JOIN (
					SELECT lesson_id, MAX(score) AS score
					FROM test_result
					WHERE user_id = :user_id AND level_id = :level
					GROUP BY lesson_id
				) AS t
				WHERE user_id = :user_id
				ORDER BY lesson_id ASC
SQL;
            $stmt = PdoFactory::getInstance()->prepare($sql);
            $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
            $stmt->bindValue(':level', $level);
            $stmt->execute();
            $test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cache->set($memcache_key, $test_results);
        }

        foreach ($test_results as $row) {
            $i = $row['lesson_id'];
            $lessons[$i]['score'] = $row['score'];
            $lessons[$i]['passing_score'] = $row['passing_score'];
            $lessons[$i]['timestamp'] = $row['timestamp'];
        }

        foreach ($lessons as &$lesson) {
            if (array_key_exists('score', $lesson)) {
                $lesson['test_status'] = ($lesson['score'] >= $lesson['passing_score']) ? "passed" : "failed";
            } else {
                if ($lesson['lesson'] < $this->getMaxLesson($level)) {
                    $lesson['test_status'] = ($lesson['level'] == $this->getStartPosition()['level'] && $lesson['lesson'] < $this->getStartPosition()['lesson']) ? "skipped" : "missed";
                }
            }

            $lesson['locked'] = !$this->isLessonAvailable($lesson['level'], $lesson['lesson']);
        }

        $sql = 'SELECT lesson FROM star WHERE user_id = :user_id AND level = :level';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':level', $level);
        $stmt->execute();

        while ($i = $stmt->fetch(PDO::FETCH_COLUMN, 0)) {
            $lessons[$i]['starred'] = true;
        }

        return $lessons;
    }

    public function getStartPosition($null_value = ['level' => 1, 'lesson' => 1]) {
        $cache = App::getMemcachedInstance();
        $memcache_key = 'user-' . $this->getUserId() . '-startposition';
        $position = $cache->get($memcache_key);
        if ($position !== false)
            return $position;

        $sql = 'SELECT start_level, start_lesson FROM user WHERE user.id = :user_id';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0)
            return $null_value;

        $row = $stmt->fetch(PDO::FETCH_NUM);

        $position = [
            'level' => $row[0],
            'lesson' => $row[1]
        ];

        $cache->set($memcache_key, $position);
        return $position;
    }

    public function setStartPosition($position) {
        $sql = 'INSERT INTO user (id, start_level, start_lesson) VALUES (:user_id, :level, :lesson) ON DUPLICATE KEY UPDATE start_level = :level, start_lesson = :lesson';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':level', $position['level'], PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $position['lesson'], PDO::PARAM_INT);
        return ($stmt->execute() === true) ? 0 : 1;
    }

    public function setTestResults($args) {
        Log::debug("Processing test results for User " . $this->getUserId() . " for " . $args['level'] . "/" . $args['lesson']);

        $defaults = [
            "max_score" => 100,
            "min_score" => 0,
            "passing_score" => 70
        ];

        $bad_arg = false;

        foreach (['passing_score', 'min_score', 'max_score', 'points', 'max_points'] as $key) {
            if (!(array_key_exists($key, $args) && Number::isInteger($args[$key]))) {
                Log::error(__METHOD__ . " given invalid argument; $key is either undefined or not a valid integer");
                $bad_arg = true;
            }
        }

        if ($bad_arg === true) {
            $debug = str_replace("\n", '\n', print_r($args, true));
            Log::debug("Arguments: $debug");
            Log::debug("Cancelling collection of test results due to bad arguments :(");
            return null;
        }

        $args = array_merge($defaults, $args);

        $sql = <<<"SQL"
			INSERT INTO test_result (user_id, level_id, lesson_id, score, passing_score, min_score, max_score, points, max_points)
			VALUES (:user_id, :level_id, :lesson_id, :score, :passing_score, :min_score, :max_score, :points, :max_points);
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);

        Log::debug("User " . $this->getUserId() . ' passed exam ' . $args['level'] . '/' . $args['lesson'] . ' with a score of ' . $args['score']);

        $rc = $stmt->execute([
            ':user_id' => $this->getUserId(),
            ':level_id' => $args['level'],
            ':lesson_id' => $args['lesson'],
            ':score' => $args['score'],
            ':passing_score' => $args['passing_score'],
            ':min_score' => $args['min_score'],
            ':max_score' => $args['max_score'],
            ':points' => $args['points'],
            ':max_points' => $args['max_points']
        ]);

        if ($rc === false) {
            Log::debug("Test result insertion failed for user " . $this->getUserId());
            return null;
        }

        Log::debug("Test result insertion succeeded for user " . $this->getUserId() . ". Invalidating user cache.");
        $this->invalidateCache();

        return ($args['score'] >= $args['passing_score']);
    }

    public function getNote($level, $lesson) {
        $sql = 'SELECT text FROM note WHERE user_id = :user_id AND level = :level AND lesson = :lesson';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->getUserId(), PDO::PARAM_INT);
        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0)
            return null;

        return $stmt->fetch(PDO::FETCH_COLUMN, 0);
    }

    public function setNote($level, $lesson, $text) {
        $sql = <<<"SQL"
			INSERT INTO note (user_id, level, lesson, text) VALUES (:user_id, :level, :lesson, :text)
			ON DUPLICATE KEY UPDATE text = :text
SQL;
        $stmt = PdoFactory::getInstance()->prepare($sql);

        return $stmt->execute([
                    ':user_id' => $this->getUserId(),
                    ':level' => $level,
                    ':lesson' => $lesson,
                    ':text' => $text
        ]);
    }

    # Store a user preference as a key/value pair in volatile storage

    #
	public function setPref($pref, $value) {
        if ($value === "true") {
            $value = true;
        } elseif ($value === "false") {
            $value = false;
        }

        $cache = App::getMemcachedInstance();
        $key_name = 'uid_' . $this->getUserId() . '_pref_' . $pref;
        $cache->delete($key_name);
        $cache->set($key_name, $value);
    }

    # Retrive a user preference by its key from volatile storage

    #
	public function getPref($pref) {
        $key_name = 'uid_' . $this->getUserId() . '_pref_' . $pref;
        return App::getMemcachedInstance()->get($key_name);
    }

    # Request a nonce. This is currently used to prevent the forgery of submitted exam results.

    #
	public function createNonce($ip, $class = 'undefined', $user_id = null) {
        $uuid = App::createUUID();
        if (!$uuid) {
            $uuid = md5(uniqid(mt_rand(), true));
        }
        if ($user_id === null)
            $user_id = $this->getUserId();

        $sql = <<<"SQL"
			INSERT INTO nonce (user_id, ip, class, value, created_on)
			VALUES (:user_id, :ip, :class, :value, NOW())
			ON DUPLICATE KEY UPDATE value = :value, created_on = NOW()
SQL;
        $stmt = PdoFactory::getInstance()->prepare($sql);

        $rc = $stmt->execute([
            ':user_id' => $user_id,
            ':ip' => $ip,
            ':class' => $class,
            ':value' => $uuid
        ]);
        return ($rc === true) ? $uuid : null;
    }

    # Verify a nonce. This is currently used to prevent the forgery of submitted exam results.
    # Note that nonces more than one hour old will not be considered valid.

    #
	public function verifyNonce($value, $ip, $class = 'undefined', $user_id = null) {
        if ($user_id === null)
            $user_id = $this->getUserId();

        $sql = <<<"SQL"
			SELECT COUNT(*) FROM nonce
			WHERE user_id = :user_id AND ip = :ip AND class = :class AND value = :value AND created_on >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
SQL;
        $stmt = PdoFactory::getInstance()->prepare($sql);

        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':class', $class, PDO::PARAM_STR);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);

        $stmt->execute();
        return $stmt->fetchColumn(0) == 1 ? true : false;
    }

    # A static version of the above method. It exists so that a nonce can be verified without calling upon aMember to
    # infer the user id. Currently, this is used by the downlevel exam results submission code to protect submissions
    # from being lost due to the aMember session having expired.

    #
	public static function validateNonce($user_id, $value, $ip, $class = 'undefined') {
        # Given an explicit user id, the constructor will not call upon aMember
        $user = new User($user_id);
        return $user->verifyNonce($value, $ip, $class, $user_id);
    }

    public function getAverageScores($level, $offset = 0) {
        if (!Validate::level($level, __METHOD__))
            return null;

        $sql = <<<"SQL"
			SELECT lesson_id, AVG(score) AS mean_score
			FROM test_result
			WHERE user_id = :user_id AND level_id = :level
			GROUP BY level_id, lesson_id
SQL;

        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->execute();

        $resultset = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function logLogin($ip) {
        $sql = "INSERT INTO user_login (user_id, authenticated_on, ip) VALUES (:user_id, NOW(), :ip)";
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $rc = $stmt->execute([
            'user_id' => $this->getUserId(),
            'ip' => $ip
        ]);
        return $rc;
    }

    public function isFirstLogin() {
        $sql = "SELECT COUNT(*) FROM user_login WHERE user_id = :user_id LIMIT 1";
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();

        # Note! At least one entry will have been written to the user_login table
        return $stmt->fetchColumn(0) < 2 ? true : false;
    }

    public function hasCompletedSurvey() {
        return ($this->getPreferredGender(null) !== null && $this->getStartPosition(null) !== null && count($this->getReferralTypes()) > 0);
    }

    public function addStar($level, $lesson) {
        $sql = 'INSERT IGNORE INTO star (user_id, level, lesson) VALUES (:user_id, :level, :lesson)';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
        $stmt->execute();
        return 0; # FIXME: errors should be propagated
    }

    public function removeStar($level, $lesson) {
        $sql = 'DELETE FROM star WHERE user_id = :user_id AND level = :level AND lesson = :lesson';
        $stmt = PdoFactory::getInstance()->prepare($sql);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':level', $level, PDO::PARAM_INT);
        $stmt->bindValue(':lesson', $lesson, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    private static function recurseObject($obj, $callback) {
        if (!is_object($obj))
            return false;

        foreach ($obj as $prop => $value) {
            if (gettype($value) === "object") {
                self::recurseObject($value, $callback);
            } else {
                $callback($prop, $value);
            }
        }
    }

    public function setPreferences($user_data) {
        $dispatch_table = [
            'defaultGender' => function($value) {
                $this->setPreferredGender($value);
            },
            'background_image' => function($value) {
                $this->setPreferredBackGroundImage($value);
            },
            'arabicText' => function($value) {
                $this->setPref('view:arabicText', $value);
            },
            'filterByStar' => function($value) {
                $this->setPref('view:filterByStar', $value);
            },
            'oldLessons' => function($value) {
                $this->setPref('view:oldLessons', $value);
            },
            'pseudoStreaming' => function($value) {
                $this->setPref('video:pseudoStreaming', $value);
            }
        ];

        self::recurseObject($user_data, function($pref, $value) use ($dispatch_table) {
            if (array_key_exists($pref, $dispatch_table))
                $dispatch_table[$pref]($value);
        });

        return true;
    }

}
