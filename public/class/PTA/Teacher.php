<?php
namespace PTA;

class Teacher extends User {
	# The teacher always has a valid membership (for now)
	public function isMemberShipValid() {
		return true;
	}

	# The teacher's effective level is the highest possible
	public function getLevel() {
		$levels = App::getLevelIdentifiers();
		return end($levels);
	}

	# The teacher will appear to have passed any and all tests
	public function getTestsPassed($level = null) {
		return count(App::getLessons($level));
	}

	# The teacher's progress level is always 100%
	public function getProgress($level = null) {
		return 100;
	}

	public function isLevelAvailable($level) {
		return true;
	}

	public function isLessonAvailable($level, $lesson) {
		return true;
	}
}
