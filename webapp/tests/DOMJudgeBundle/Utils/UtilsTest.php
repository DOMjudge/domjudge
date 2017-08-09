<?php
namespace Tests\DOMJudgeBundle\Utils;

use DOMJudgeBundle\Utils\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase {
	public function testAbsTime() {
		date_default_timezone_set('Asia/Kathmandu');
		$this->assertEquals('2009-02-14T05:16:30.000+05:45', Utils::absTime(1234567890));
	}

	public function testAbsTimeWithMillis() {
		date_default_timezone_set('Asia/Kathmandu');
		$this->assertEquals('2009-02-14T05:16:30.988+05:45', Utils::absTime(1234567890.98765));
	}

	public function testAbsTimeWithMillisFloored() {
		date_default_timezone_set('Asia/Kathmandu');
		$this->assertEquals('2009-02-14T05:16:30+05:45', Utils::absTime(1234567890.98765, TRUE));
	}

	public function testRelTime() {
		$this->assertEquals('1:18:31.000', Utils::relTime(4711));
	}

	public function testRelTimeWithMillis() {
		$this->assertEquals('1:18:31.082', Utils::relTime(4711.0815));
	}

	public function testRelTimeWithMillisFloored() {
		$this->assertEquals('1:18:31', Utils::relTime(4711.0815, TRUE));
	}

	public function testNegativeRelTime() {
		$this->assertEquals('-3:25:45.000', Utils::relTime(-12345));
	}

	public function testNegativeRelTimeWithMillis() {
		$this->assertEquals('-3:25:45.679', Utils::relTime(-12345.6789));
	}

	public function testNegativeRelTimeWithMillisFloored() {
		$this->assertEquals('-3:25:45', Utils::relTime(-12345.6789, TRUE));
	}
}
