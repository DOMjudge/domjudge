<?php declare(strict_types=1);

namespace App\Tests\Utils;

use App\Utils\Utils;
use PHPUnit\Framework\TestCase;
use App\Entity\TeamAffiliation;

class UtilsTest extends TestCase
{
    /**
     * Test that the absTime function returns the correct data
     */
    public function testAbsTime()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30.000+05:45', Utils::absTime(1234567890));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns the correct data when using a
     * time with millisecond precision
     */
    public function testAbsTimeWithMillis()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30.987+05:45', Utils::absTime(1234567890.98765));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns the correct data when using a
     * time with millisecond precision when flooring the result
     */
    public function testAbsTimeWithMillisFloored()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('2009-02-14T05:16:30+05:45', Utils::absTime(1234567890.98765, true));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns the correct data when using a
     * time with 0000 milliseconds
     */
    public function testAbsTimeWithMillis9999()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $this->assertEquals('1970-01-01T06:48:31.999+05:30', Utils::absTime(4711.9999));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns null on null epoch
     */
    public function testAbsTimeNull()
    {
        $this->assertNull(Utils::absTime(null));
    }

    /**
     * Test that the relTime function returns the correct data
     */
    public function testRelTime()
    {
        $this->assertEquals('1:18:31.000', Utils::relTime(4711));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * time with millisecond precision
     */
    public function testRelTimeWithMillis()
    {
        $this->assertEquals('1:18:31.081', Utils::relTime(4711.0815));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * time with millisecond precision when flooring the result
     */
    public function testRelTimeWithMillisFloored()
    {
        $this->assertEquals('1:18:31', Utils::relTime(4711.0815, true));
    }

    /**
     * Test that the relTIme function returns the correct data when using a
     * time with 0000 milliseconds
     */
    public function testRelTimeWithMillis9999()
    {
        $this->assertEquals('1:18:31.999', Utils::relTime(4711.9999));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * negative value
     */
    public function testNegativeRelTime()
    {
        $this->assertEquals('-3:25:45.000', Utils::relTime(-12345));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * negative value and a time with millisecond precision
     */
    public function testNegativeRelTimeWithMillis()
    {
        $this->assertEquals('-3:25:45.678', Utils::relTime(-12345.6789));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * negative value and a time with millisecond precision when flooring the result
     */
    public function testNegativeRelTimeWithMillisFloored()
    {
        $this->assertEquals('-3:25:45', Utils::relTime(-12345.6789, true));
    }

    /**
     * Test that the toEpochFloat function works with a leap day
     */
    public function testToEpochFloatLeapday()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
        $this->assertEquals(1583017140.000123, Utils::toEpochFloat('2020-02-29T23:59:00.000123+01:00'));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the toEpochFloat function works on a DST change
     */
    public function testToEpochFloatDstChange()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
        $this->assertEquals(1572140520.010203, Utils::toEpochFloat('2019-10-27T02:42:00.010203+01:00'));
        $this->assertEquals(1572136920.010203, Utils::toEpochFloat('2019-10-27T02:42:00.010203+02:00'));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the toEpochFloat works with random data
     */
    public function testAbsTimeToEpochFloatRandom()
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
                $t2 = Utils::toEpochFloat(Utils::absTime($t));
                $this->assertEquals($t, $t2, "comparing random times in TZ=$tz", 0.0000001);
            }
        }

        date_default_timezone_set($tz_orig);
    }

    /**
     * Test that printtime returns an empty string when no date is passed
     */
    public function testPrinttimeNotime()
    {
        $this->assertEquals('', Utils::printTime(null, "%H:%M"));
    }

    /**
     * Test that the printtime function returns the correct result
     */
    public function testPrinttime()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $timestamp = 1544964581.3604;
        $expected = '2018-12-16 12:49';
        $this->assertEquals($expected, Utils::printtime($timestamp, '%Y-%m-%d %H:%M'));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the printtimediff function returns the correct result
     */
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

    /**
     * Test the difftime function
     */
    public function testDifftime()
    {
        $now = Utils::now();
        $offset = 10;
        $soon = $now + $offset;

        $this->assertEquals(0, Utils::difftime($now, $now));
        $this->assertEquals($offset, Utils::difftime($soon, $now));
        $this->assertEquals(-$offset, Utils::difftime($now, $soon));
    }

    /**
     * Test the results of calculating the difference between two times.
     * Note: the function "assumes" the first is larger than the second
     * according to its specification.
     */
    public function testTimeStringDiff()
    {
        $this->assertEquals("01:00:00", Utils::timeStringDiff("16:00:00", "15:00:00"));
        $this->assertEquals("00:00:03", Utils::timeStringDiff("16:00:00", "15:59:57"));
        $this->assertEquals("00:00:00", Utils::timeStringDiff("16:43:12", "16:43:12"));
        $this->assertEquals("00:14:55", Utils::timeStringDiff("01:50:50", "01:35:55"));
    }

    /**
     * Test function that converts colour name to hex notation.
     * If value is already hexadecimal, return it unchanged.
     */
    public function testConvertToHexNoop()
    {
        $color = '#aa43c3';
        $this->assertEquals($color, Utils::convertToHex($color));
        $color = '#CCA';
        $this->assertEquals($color, Utils::convertToHex($color));
    }

    /**
     * Test function that converts colour name to hex notation.
     * Returns correct value for known colour names.
     */
    public function testConvertToHexConvert()
    {
        $this->assertEquals('#B22222', Utils::convertToHex('firebrick'));
        $this->assertEquals('#00BFFF', Utils::convertToHex('deep sky blue'));
        $this->assertEquals('#FFD700', Utils::convertToHex('GOLD'));
        $this->assertEquals('#B8860B', Utils::convertToHex('darkgoldenrod '));
    }

    /**
     * Test function that converts colour name to hex notation.
     * Returns null for unknown values.
     */
    public function testConvertToHexUnknown()
    {
        $this->assertNull(Utils::convertToHex('doesnotexist'));
        $this->assertNull(Utils::convertToHex('#aabbccdd'));
        $this->assertNull(Utils::convertToHex('#12346h'));
    }

    /**
     * Test function that converts colour hex notation to (nearest) name.
     * If value is not hexadecimal, return it unchanged.
     */
    public function testConvertToColorNoop()
    {
        $color = 'doesnotexist';
        $this->assertEquals($color, Utils::convertToColor($color));
        $color = 'darkgoldenrod';
        $this->assertEquals($color, Utils::convertToColor($color));
        $color = '#aabbccdd';
        $this->assertEquals($color, Utils::convertToColor($color));
        $color = '#12346h';
        $this->assertEquals($color, Utils::convertToColor($color));
    }

    /**
     * Test function that converts colour hex notation to (nearest) name.
     * Returns correct value for known colour names.
     */
    public function testConvertToColorConvertExact()
    {
        $this->assertEquals('firebrick', Utils::convertToColor('#B22222'));
        $this->assertEquals('firebrick', Utils::convertToColor('#b22222'));
        $this->assertEquals('red', Utils::convertToColor('#F00'));
        $this->assertEquals('lightsteelblue', Utils::convertToColor('#ACD'));
    }

    /**
     * Test function that converts colour hex notation to (nearest) name.
     * Returns correct closest value for known colour names.
     */
    public function testConvertToColorConvertClosest()
    {
        $this->assertEquals('white', Utils::convertToColor('#fffffe'));
        $this->assertEquals('black', Utils::convertToColor('#000010'));
    }

    /**
     * Test float rounding function called with null.
     */
    public function testRoundedFloatNull()
    {
        $this->assertNull(Utils::roundedFloat(null));
    }

    /**
     * Test float rounding function called with a number without decimals.
     */
    public function testRoundedFloatNoDecimals()
    {
        $this->assertEquals(-5, Utils::roundedFloat(-5));
        $this->assertEquals(100, Utils::roundedFloat(100));
    }

    /**
     * Test float rounding function called with a number with decimals to default number of decimals.
     */
    public function testRoundedFloatDecimals()
    {
        $this->assertEquals(6.01, Utils::roundedFloat(6.01));
        $this->assertEquals(6.002, Utils::roundedFloat(6.002));
        $this->assertEquals(6.002, Utils::roundedFloat(6.00213));
        $this->assertEquals(6.002, Utils::roundedFloat(6.0025123));
    }

    /**
     * Test float rounding function called with a number with decimals to a specified number of decimals.
     */
    public function testRoundedFloatDecimalsSpecifiedLength()
    {
        $this->assertEquals(6.01, Utils::roundedFloat(6.01, 2));
        $this->assertEquals(6.01, Utils::roundedFloat(6.01, 5));
        $this->assertEquals(6.0024, Utils::roundedFloat(6.0024, 4));
        $this->assertEquals(6, Utils::roundedFloat(6.00213, 0));
        $this->assertEquals(6.02, Utils::roundedFloat(6.025123, 2));
    }

    /**
     * Test that penalty time is correctly calculated.
     */
    public function testCalcPenaltyTime()
    {
        $this->assertEquals(0,  Utils::calcPenaltyTime(true, 1, 20, false));
        $this->assertEquals(20, Utils::calcPenaltyTime(true, 2, 20, false));
        $this->assertEquals(40, Utils::calcPenaltyTime(true, 3, 20, false));
        $this->assertEquals(60, Utils::calcPenaltyTime(true, 4, 20, false));
        $this->assertEquals(0,  Utils::calcPenaltyTime(true, 1, 25, false));
        $this->assertEquals(25, Utils::calcPenaltyTime(true, 2, 25, false));
        $this->assertEquals(50, Utils::calcPenaltyTime(true, 3, 25, false));
        $this->assertEquals(75, Utils::calcPenaltyTime(true, 4, 25, false));
    }

    /**
     * Test that penalty time is correctly calculated in seconds.
     */
    public function testCalcPenaltyTimeSeconds()
    {
        $this->assertEquals(0,    Utils::calcPenaltyTime(true, 1, 20, true));
        $this->assertEquals(1200, Utils::calcPenaltyTime(true, 2, 20, true));
        $this->assertEquals(2400, Utils::calcPenaltyTime(true, 3, 20, true));
        $this->assertEquals(3600, Utils::calcPenaltyTime(true, 4, 20, true));
        $this->assertEquals(0,    Utils::calcPenaltyTime(true, 1, 50, true));
        $this->assertEquals(3000, Utils::calcPenaltyTime(true, 2, 50, true));
        $this->assertEquals(6000, Utils::calcPenaltyTime(true, 3, 50, true));
        $this->assertEquals(9000, Utils::calcPenaltyTime(true, 4, 50, true));
    }

    /**
     * Test that penalty time is correctly calculated: problem not solved.
     */
    public function testCalcPenaltyTimeNotSolved()
    {
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 1, 20, false));
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 2, 20, false));
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 3, 20, false));
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 4, 20, false));
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 1, 25, false));
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 2, 25, false));
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 3, 25, true));
        $this->assertEquals(0, Utils::calcPenaltyTime(false, 4, 25, true));
    }

    /**
     * Test that the scoreboard time is correctly truncated, time is in seconds
     */
    public function testScoreTimeInSeconds()
    {
        $this->assertEquals(0, Utils::scoretime(0, true));
        $this->assertEquals(0, Utils::scoretime(0.05, true));
        $this->assertEquals(10, Utils::scoretime(10.9, true));
    }

    /**
     * Test that the scoreboard time is correctly truncated, time is in minutes
     */
    public function testScoreTimeInMinutes()
    {
        $this->assertEquals(0, Utils::scoretime(0, false));
        $this->assertEquals(0, Utils::scoretime(35, false));
        $this->assertEquals(0, Utils::scoretime(59.9, false));
        $this->assertEquals(1, Utils::scoretime(60, false));
        $this->assertEquals(1, Utils::scoretime(60.2, false));
        $this->assertEquals(5, Utils::scoretime(332, false));
    }

    /**
     * Test that printhost truncates a hostname
     */
    public function testPrinthost()
    {
        $this->assertEquals("my", Utils::printhost("my.example.hostname.example.com"));
        $this->assertEquals("hostonly", Utils::printhost("hostonly"));
    }

    /**
     * Test that printhost does not truncate a hostname
     */
    public function testPrinthostFull()
    {
        $this->assertEquals("my.example.hostname.example.com", Utils::printhost("my.example.hostname.example.com", true));
        $this->assertEquals("hostonly", Utils::printhost("hostonly", true));
    }

    /**
     * Test that printhost does not truncates an IP address
     */
    public function testPrinthostIP()
    {
        $this->assertEquals("127.0.0.1", Utils::printhost("127.0.0.1"));
        $this->assertEquals("2001:610:0:800f:f816:3eff:fe15:c440", Utils::printhost("2001:610:0:800f:f816:3eff:fe15:c440"));
        $this->assertEquals("127.0.0.1", Utils::printhost("127.0.0.1", true));
    }

    /**
     * Test that printsize prints some sizes
     */
    public function testPrintsize()
    {
        $this->assertEquals("0 B", Utils::printsize(0));
        $this->assertEquals("1000 B", Utils::printsize(1000));
        $this->assertEquals("1024 B", Utils::printsize(1024));
        $this->assertEquals("1.0 KB", Utils::printsize(1025));
        $this->assertEquals("2 KB", Utils::printsize(2048));
        $this->assertEquals("2.5 KB", Utils::printsize(2560));
        $this->assertEquals("5 MB", Utils::printsize(5242880));
        $this->assertEquals("23 GB", Utils::printsize(24696061952));
    }

    /**
     * Test that printsize prints some sizes with specified number of decimals
     */
    public function testPrintsizeDecimalsSpecified()
    {
        $this->assertEquals("0 B", Utils::printsize(0, 4));
        $this->assertEquals("1.00 KB", Utils::printsize(1025, 2));
        $this->assertEquals("3 KB", Utils::printsize(2560, 0));
        $this->assertEquals("5 MB", Utils::printsize(5242880, 10));
        $this->assertEquals("22.999999254941940 GB", Utils::printsize(24696061152, 15));
    }

    /**
     * Basic testing of the LCSdiff function
     */
    public function testComputeLcsDiff()
    {
        $line_a = "DOMjudge is a system for running programming contests,";
        $line_b = "DOMjudge is a very good system for running programming contests,";
        $line_c = "DOMjudge is for running some programming contests,";

        $diff = Utils::computeLcsDiff($line_a, $line_b);
        $this->assertTrue($diff[0]);
        $this->assertContains('DOMjudge is a <ins>very</ins> <ins>good</ins> system for running', $diff[1]);

        $diff = Utils::computeLcsDiff($line_b, $line_a);
        $this->assertTrue($diff[0]);
        $this->assertContains('DOMjudge is a <del>very</del> <del>good</del> system for running', $diff[1]);

        $diff = Utils::computeLcsDiff($line_a, $line_c);
        $this->assertTrue($diff[0]);
        $this->assertContains('DOMjudge is <del>a</del> <del>system</del> for running <ins>some</ins> programming contests', $diff[1]);

        $diff = Utils::computeLcsDiff($line_a, $line_a);
        $this->assertFalse($diff[0]);
        $this->assertEquals("$line_a\n", $diff[1]);
    }

    /**
     * Testing of the LCSdiff function with long strings
     */
    public function testComputeLcsDiffLonglines()
    {
        $line_a = "DOMjudge is a system for running programming contests,";
        $line_b = "This usually means that teams are on-site and have a fixed time period (mostly 5 hours) and one computer to solve a number of problems (mostly 8-12). Problems are solved by writing a program in one of the allowed languages, that reads input according to the problem input specification and writes the correct, corresponding output. The judging is done by submitting the source code of the solution to the jury. There the jury system automatically compiles and runs the program and compares the program output with the expected output. This software can be used to handle the submission and judging during such contests. It also handles feedback to the teams and communication on problems (clarification requests). It has web interfaces for the jury, the teams (their submissions and clarification requests) and the public (scoreboard).";

        $diff = Utils::computeLcsDiff($line_a, $line_b);
        $this->assertTrue($diff[0]);
        $this->assertContains('<ins>judging</ins> [cut off rest of line...]', $diff[1]);
    }

    /**
     * Test that the specialchars function returns the correct result
     */
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

    /**
     * Test that string is not cut when shorter or one longer than requested maximum
     */
    public function testCutStringNoop()
    {
        $string = 'Example string.';
        $this->assertEquals($string, Utils::cutString($string, 70));
        $this->assertEquals($string, Utils::cutString($string, 14));
        $this->assertEquals($string, Utils::cutString($string, 15));
        $this->assertEquals($string, Utils::cutString($string, 16));
    }

    /**
     * Test that string is cut when one longer than requested maximum
     */
    public function testCutStringCut()
    {
        $string = 'Example string.';
        $this->assertEquals("Examp…", Utils::cutString($string, 5));
    }

    /**
     * Test that string is not cut when not longer than 1 over requested maximum,
     * counting multi byte characters as one.
     */
    public function testCutStringNoopMB()
    {
        $string = '📍📍📍';
        $this->assertEquals($string, Utils::cutString($string, 3));
        $this->assertEquals($string, Utils::cutString($string, 2));
    }

    /**
     * Test that string is cut when one longer than requested maximum,
     * counting multi byte characters as one.
     */
    public function testCutStringCutMB()
    {
        $string = '📍📍📍📍📍📍';
        $this->assertEquals("📍📍📍…", Utils::cutString($string, 3));
        $this->assertEquals("📍📍…", Utils::cutString($string, 2));
    }

    /**
     * Test image type png
     */
    public function testGetImageType()
    {
        $logo = dirname(__FILE__) . '/../../public/images/DOMjudgelogo.png';
        $image = file_get_contents($logo);
        $error = null;

        $type = Utils::getImageType($image, $error);
        $this->assertEquals('png', $type);
        $this->assertNull($error);
    }

    /**
     * Test image type with invalid image
     */
    public function testGetImageTypeError()
    {
        $image = 'Not really an image';
        $error = null;

        $type = Utils::getImageType($image, $error);
        $this->assertFalse($type);
        $this->assertEquals('Could not determine image information.', $error);
    }

    /**
     * test image thumbnail creation
     */
    public function testGetImageThumb()
    {
        $logo = dirname(__file__) . '/../../public/images/DOMjudgelogo.png';
        $image = file_get_contents($logo);
        $error = null;
        $tmp = sys_get_temp_dir();
        $maxsize = 30;

        $thumb = Utils::getImageThumb($image, $maxsize, $tmp, $error);
        $this->assertNull($error);

        $data = getimagesizefromstring($thumb);
        $this->assertEquals($maxsize, $data[1]);  // resized height
        $this->assertEquals('image/png', $data['mime']);
    }

    /**
     * Test image thumb with invalid image
     */
    public function testGetImageThumbError()
    {
        $image = 'Not really an image';
        $error = null;
        $tmp = sys_get_temp_dir();
        $maxsize = 30;

        $thumb = Utils::getImageThumb($image, $maxsize, $tmp, $error);
        $this->assertFalse($thumb);
        $this->assertEquals('Could not determine image information.', $error);
    }

    /**
     * Test that the wrapUnquoted function returns the correct result
     */
    public function testWrapUnquotedSingleLineUnquoted()
    {
        $text = "This is an example text.";
        $this->assertEquals($text, Utils::wrapUnquoted($text));
    }

    /**
     * Test that the specialchars function returns the correct result with a
     * long line
     */
    public function testWrapUnquotedLongLineUnquoted()
    {
        $text = "This is an example text.";
        $result = "This is an
example
text.";
        $this->assertEquals($result, Utils::wrapUnquoted($text, 10));
    }

    /**
     * Test that the specialchars function returns the correct result with a
     * long quoted line
     */
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
        $this->assertEquals($result, Utils::wrapUnquoted($text, 10));
    }

    /**
     * Test that the specialchars function returns the correct result with a
     * long quoted line with a custom quote character
     */
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
        $this->assertEquals($result, Utils::wrapUnquoted($text, 10, '#'));
    }

    /**
     * Test that the startsWith function returns the correct result
     */
    public function testStartsWith()
    {
        $text = "The quick brown fox jumped over the lazy dog.";
        $start = "The quick";
        $this->assertTrue(Utils::startsWith($text, $start));
        $this->assertTrue(Utils::startsWith($start, $start));
        $this->assertFalse(Utils::startsWith($start, $text));
    }

    /**
     * Test that the endsWith function returns the correct result
     */
    public function testEndsWith()
    {
        $text = "The quick brown fox jumped over the lazy dog.";
        $end = "lazy dog.";
        $this->assertTrue(Utils::endsWith($text, $end));
        $this->assertTrue(Utils::endsWith($end, $end));
        $this->assertFalse(Utils::endsWith($end, $text));
    }

    /**
     * Test that the generatePassword function generates a valid password (when
     * using more entropy)
     */
    public function testGeneratePasswordMoreEntropy()
    {
        $passes = [];
        $onlyCorrectChars = true;
        for ($i=0; $i < 100; ++$i) {
            $pass = Utils::generatePassword();
            $onlyCorrectChars = $onlyCorrectChars && preg_match('/^[a-zA-Z0-9_-]+$/', $pass);
            $passes[] = $pass;
        }

        $this->assertEquals(1, max(array_count_values($passes)));
        $this->assertEquals(16, min(array_map('strlen', $passes)));
        $this->assertEquals(16, max(array_map('strlen', $passes)));
        $this->assertTrue($onlyCorrectChars);
    }

    /**
     * Test that the generatePassword function generates a valid password when
     * using less entropy
     */
    public function testGeneratePasswordWithLessEntropy()
    {
        $passes = [];
        $onlyalnum = true;
        $containsforbidden = false;
        for ($i=0; $i < 100; ++$i) {
            $pass = Utils::generatePassword(false);
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

    /**
     * Test balloon symbol is returned with specified colour.
     */
    public function testBalloonSym()
    {
        $color = 'GREEN&BLUE';
        $sym = Utils::balloonSym($color);
        $this->assertEquals('<i style="color: GREEN&amp;BLUE" class="fas fa-golf-ball"></i>', $sym);
    }

    /**
     * Test that PHP ini values for bytes are converted correctly.
     */
    public function testPhpiniToBytes()
    {
        $this->assertEquals(100, Utils::phpiniToBytes('100'));
        $this->assertEquals(100*1024**3, Utils::phpiniToBytes('100g'));
        $this->assertEquals(120*1024**2, Utils::phpiniToBytes('120m'));
        $this->assertEquals(1*1024, Utils::phpiniToBytes('1k'));
        $this->assertEquals(1*1024, Utils::phpiniToBytes('1K'));
        $this->assertEquals(20*1024**3, Utils::phpiniToBytes('20G'));
        $this->assertEquals(12*1024**2, Utils::phpiniToBytes('12M'));
    }

    /**
     * Test that we get the correct table name for an entity
     */
    public function testTableForEntity()
    {
        $entity = new TeamAffiliation();
        $this->assertEquals('team_affiliation', Utils::tableForEntity($entity));
    }

    /**
     * Test that returning a binary file sets correct header
     */
    public function testStreamAsBinaryFile()
    {
        $content = 'The quick brown fox jumps over the lazy dog.';
        $filename = 'foxdog.txt';
        $length = strlen($content);

        $response = Utils::StreamAsBinaryFile($content, $filename)->__toString();

        $this->assertRegExp('#Content-Disposition:\s+attachment; filename="' . str_replace('.','\.', $filename) . '"#', $response);
        $this->assertRegExp("#Content-Type:\s+application/octet-stream#", $response);
        $this->assertRegExp("#Content-Length:\s+$length#", $response);
        $this->assertRegExp("#Content-Transfer-Encoding:\s+binary#", $response);
    }

    /**
     * Test Tab Separated Value encoding
     */
    public function testToTsvField()
    {
        $this->assertEquals('team name',    Utils::toTsvField('team name'));
        $this->assertEquals('Team,,, name', Utils::toTsvField('Team,,, name'));
        $this->assertEquals('team\\nname',  Utils::toTsvField("team\nname"));
        $this->assertEquals('team\\tname\\nexample\\t', Utils::toTsvField("team\tname\nexample\t"));
        $this->assertEquals('team\\r\\nname', Utils::toTsvField("team\r\nname"));
        $this->assertEquals('tea\\\\mname', Utils::toTsvField("tea\\mname"));
        $this->assertEquals('team nåme…',   Utils::toTsvField("team nåme…"));
        $this->assertEquals('team🎈name',   Utils::toTsvField("team🎈name"));
    }

    /**
     * Test Tab Separated Value parsing
     */
    public function testParseTsvLine()
    {
        $bs  = "\\";
        $tab = "\t";
        $this->assertEquals(["team name", "rank"],    Utils::parseTsvLine("team name".$tab."rank"));
        $this->assertEquals(["team\tname\t", "rank"], Utils::parseTsvLine("team".$bs."t"."name".$bs."t".$tab."rank"));
        $this->assertEquals(["team\nname\r", "rank"], Utils::parseTsvLine("team".$bs."n"."name".$bs."r".$tab."rank"));
        $this->assertEquals(["team\\name\\", "rank"], Utils::parseTsvLine("team".$bs.$bs."name".$bs.$bs.$tab."rank"));
        $this->assertEquals([$bs],                    Utils::parseTsvLine($bs.$bs));
        $this->assertEquals([$bs."t"],                Utils::parseTsvLine($bs.$bs."t"));
        $this->assertEquals(["Team,,, name"],         Utils::parseTsvLine("Team,,, name\n"));
        $this->assertEquals(["Team", "", "", " nm "], Utils::parseTsvLine("Team".$tab.$tab.$tab." nm \r\n"));
        $this->assertEquals(["tea\\mname", "rank"],   Utils::parseTsvLine("tea".$bs.$bs."mname".$tab."rank"));
        $this->assertEquals(["team nåme…", "rank"],   Utils::parseTsvLine("team nåme…".$tab."rank"));
        $this->assertEquals(["team🎈name", "rank"],   Utils::parseTsvLine("team🎈name".$tab."rank"));
    }
}
