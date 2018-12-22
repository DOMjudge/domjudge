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

    public function testPrinttimeNotime()
    {
        $this->assertEquals('', Utils::printTime(null, "%H:%M"));
    }

    public function testPrinttime()
    {
        $timestamp = 1544964581.3604;
        $expected = '2018-12-16 12:49';
        $this->assertEquals($expected, Utils::printtime($timestamp, '%Y-%m-%d %H:%M'));
    }

    public function testPrinttimediff()
    {
        $start = $end = 1544964581.3604;

        $this->assertEquals("00:00", Utils::printtimediff($start, $end));

        $end += 1;
        $this->assertEquals("00:01", Utils::printtimediff($start, $end));

        $end += 123;
        $this->assertEquals("02:04", Utils::printtimediff($start, $end));

        $end += 4*60;
        $this->assertEquals("06:04", Utils::printtimediff($start, $end));

        $end += 59;
        $this->assertEquals("07:03", Utils::printtimediff($start, $end));

        $end += 13*60;
        $this->assertEquals("20:03", Utils::printtimediff($start, $end));

        $end += (72*60*60);
        $this->assertEquals("3d 0:20:03", Utils::printtimediff($start, $end));

        $end += (365*24*60*60);
        $this->assertEquals("368d 0:20:03", Utils::printtimediff($start, $end));
    }

    public function testSpecialchars()
    {
        $plain = "Example string to test";
        $this->assertEquals($plain, Utils::specialchars($plain));

        $html = 'Example <a href="aap">string</a> to test';
        $htmlenc = 'Example &lt;a href=&quot;aap&quot;&gt;string&lt;/a&gt; to test';
        $this->assertEquals($htmlenc, Utils::specialchars($html));

        $validutf = "Test Thĳs ⛪⚖";
        $this->assertEquals($validutf, Utils::specialchars($validutf));

        $invalidutf = "Test \xc3\x28 example";
        $replacedutf = "Test �( example";
        $this->assertEquals($replacedutf, Utils::specialchars($invalidutf));
    }
}
