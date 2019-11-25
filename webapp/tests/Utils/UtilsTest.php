<?php declare(strict_types=1);
namespace Tests\Utils;

use App\Utils\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testAbsTime()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30.000+05:45', Utils::absTime(1234567890));
        date_default_timezone_set($tz);
    }

    public function testAbsTimeWithMillis()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30.987+05:45', Utils::absTime(1234567890.98765));
        date_default_timezone_set($tz);
    }

    public function testAbsTimeWithMillisFloored()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30+05:45', Utils::absTime(1234567890.98765, true));
        date_default_timezone_set($tz);
    }

    public function testAbsTimeWithMillis9999()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('1970-01-01T06:48:31.999+05:30', Utils::absTime(4711.9999));
        date_default_timezone_set($tz);
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

    public function test_to_epoch_float_leapday()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
        $this->assertEquals(1583017140.000123, Utils::to_epoch_float('2020-02-29T23:59:00.000123+01:00'));
        date_default_timezone_set($tz);
    }

    public function test_to_epoch_float_dst_change()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
        $this->assertEquals(1572140520.010203, Utils::to_epoch_float('2019-10-27T02:42:00.010203+01:00'));
        $this->assertEquals(1572136920.010203, Utils::to_epoch_float('2019-10-27T02:42:00.010203+02:00'));
        date_default_timezone_set($tz);
    }

    public function test_absTime_to_epoch_float_random()
    {
        $tz_orig = date_default_timezone_get();

        $timezones = [
            'Asia/Kathmandu',
            'Europe/Amsterdam',
            'Europe/London',
            'America/St_Johns',
            'Pacific/Auckland',
            'UTC'
        ];
        foreach ($timezones as $tz) {
            date_default_timezone_set($tz);

            $now = time();
            $year = 365*24*3600;
            for ($i=0; $i<10000; $i++) {
                $t = (float)sprintf('%d.%03d', $now - $year + rand(0,2*$year), rand(0,999));
                $t2 = Utils::to_epoch_float(Utils::absTime($t));
                $this->assertEquals($t, $t2, "comparing random times in TZ=$tz", 0.0000001);
            }
        }

        date_default_timezone_set($tz_orig);
    }

    public function testPrinttimeNotime()
    {
        $this->assertEquals('', Utils::printTime(null, "%H:%M"));
    }

    public function testPrinttime()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $timestamp = 1544964581.3604;
        $expected = '2018-12-16 12:49';
        $this->assertEquals($expected, Utils::printtime($timestamp, '%Y-%m-%d %H:%M'));
        date_default_timezone_set($tz);
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

    public function testWrapUnquotedSingleLineUnquoted()
    {
        $text = "This is an example text.";
        $this->assertEquals($text, Utils::wrap_unquoted($text));
    }

    public function testWrapUnquotedLongLineUnquoted()
    {
        $text = "This is an example text.";
        $result = "This is an
example
text.";
        $this->assertEquals($result, Utils::wrap_unquoted($text, 10));
    }

    public function testWrapUnquotedLongLineWithQuoted()
    {
        $text = "> > This is an example text.
> And another long line appears here.
> Also a shorter line.
> Short.

This is the unquoted part.
> Really?
Yes.

By the way, pi > 3.
Just so you know";
        $result = "> > This is an example text.
> And another long line appears here.
> Also a shorter line.
> Short.

This is
the
unquoted
part.
> Really?
Yes.

By the
way, pi >
3.
Just so
you know";
        $this->assertEquals($result, Utils::wrap_unquoted($text, 10));
    }

    public function testWrapUnquotedLongLineWithQuotedCustomQuoteCharacter()
    {
        $text = "# This is an example text.
# And another long line appears here.

> This is the unquoted part.

";
        $result = "# This is an example text.
# And another long line appears here.

> This is
the
unquoted
part.";
        $this->assertEquals($result, Utils::wrap_unquoted($text, 10, '#'));
    }

    public function testStartsWith()
    {
        $text = "The quick brown fox jumped over the lazy dog.";
        $start = "The quick";
        $this->assertTrue(Utils::startsWith($text, $start));
        $this->assertTrue(Utils::startsWith($start, $start));
        $this->assertFalse(Utils::startsWith($start, $text));
    }

    public function testEndsWith()
    {
        $text = "The quick brown fox jumped over the lazy dog.";
        $end = "lazy dog.";
        $this->assertTrue(Utils::endsWith($text, $end));
        $this->assertTrue(Utils::endsWith($end, $end));
        $this->assertFalse(Utils::endsWith($end, $text));
    }

    public function testGeneratePassword()
    {
        $passes = [];
        $onlyalnum = true;
        $containsforbidden = false;
        for ($i=0; $i < 100; ++$i) {
            $pass = Utils::generatePassword();
            $onlyalnum = $onlyalnum && ctype_alnum($pass);
            $containsforbidden = $containsforbidden || preg_match('/o01l[A-Z]/', $pass);
            $passes[] = $pass;
        }

        $this->assertEquals(1, max(array_count_values($passes)));
        $this->assertEquals(6, min(array_map('strlen', $passes)));
        $this->assertEquals(6, max(array_map('strlen', $passes)));
        $this->assertTrue($onlyalnum);
        $this->assertFalse($containsforbidden);
    }

}
