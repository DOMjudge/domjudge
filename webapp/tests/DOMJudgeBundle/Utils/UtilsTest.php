<?php
namespace Tests\DOMJudgeBundle\Utils;

use DOMJudgeBundle\Utils\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testAbsTime()
    {
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30.000+05:45', Utils::absTime(1234567890));
    }

    public function testAbsTimeWithMillis()
    {
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30.987+05:45', Utils::absTime(1234567890.98765));
    }

    public function testAbsTimeWithMillisFloored()
    {
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30+05:45', Utils::absTime(1234567890.98765, true));
    }

    public function testAbsTimeWithMillis9999()
    {
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('1970-01-01T06:48:31.999+05:30', Utils::absTime(4711.9999));
    }

    public function testRelTime()
    {
        $this->assertEquals('1:18:31.000', Utils::relTime(4711));
    }

    public function testRelTimeWithMillis()
    {
        $this->assertEquals('1:18:31.081', Utils::relTime(4711.0815));
    }

    public function testRelTimeWithMillis9999()
    {
        $this->assertEquals('1:18:31.999', Utils::relTime(4711.9999));
    }

    public function testRelTimeWithMillisFloored()
    {
        $this->assertEquals('1:18:31', Utils::relTime(4711.0815, true));
    }

    public function testNegativeRelTime()
    {
        $this->assertEquals('-3:25:45.000', Utils::relTime(-12345));
    }

    public function testNegativeRelTimeWithMillis()
    {
        $this->assertEquals('-3:25:45.678', Utils::relTime(-12345.6789));
    }

    public function testNegativeRelTimeWithMillisFloored()
    {
        $this->assertEquals('-3:25:45', Utils::relTime(-12345.6789, true));
    }
}
