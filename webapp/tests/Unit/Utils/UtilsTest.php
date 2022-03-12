<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Entity\TeamAffiliation;
use App\Utils\Utils;
use Generator;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    /**
     * Test that the absTime function returns the correct data
     */
    public function testAbsTime(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        self::assertEquals('2009-02-14T05:16:30.000+05:45', Utils::absTime(1234567890));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns the correct data when using a
     * time with millisecond precision
     */
    public function testAbsTimeWithMillis(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        self::assertEquals('2009-02-14T05:16:30.987+05:45', Utils::absTime(1234567890.98765));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns the correct data when using a
     * time with millisecond precision when flooring the result
     */
    public function testAbsTimeWithMillisFloored(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        self::assertEquals('2009-02-14T05:16:30+05:45', Utils::absTime(1234567890.98765, true));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns the correct data when using a
     * time with 0000 milliseconds
     */
    public function testAbsTimeWithMillis9999(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        self::assertEquals('1970-01-01T06:48:31.999+05:30', Utils::absTime(4711.9999));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the absTime function returns null on null epoch
     */
    public function testAbsTimeNull(): void
    {
        self::assertNull(Utils::absTime(null));
    }

    /**
     * Test that the relTime function returns the correct data
     */
    public function testRelTime(): void
    {
        self::assertEquals('1:18:31.000', Utils::relTime(4711));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * time with millisecond precision
     */
    public function testRelTimeWithMillis(): void
    {
        self::assertEquals('1:18:31.081', Utils::relTime(4711.0815));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * time with millisecond precision when flooring the result
     */
    public function testRelTimeWithMillisFloored(): void
    {
        self::assertEquals('1:18:31', Utils::relTime(4711.0815, true));
    }

    /**
     * Test that the relTIme function returns the correct data when using a
     * time with 0000 milliseconds
     */
    public function testRelTimeWithMillis9999(): void
    {
        self::assertEquals('1:18:31.999', Utils::relTime(4711.9999));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * negative value
     */
    public function testNegativeRelTime(): void
    {
        self::assertEquals('-3:25:45.000', Utils::relTime(-12345));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * negative value and a time with millisecond precision
     */
    public function testNegativeRelTimeWithMillis(): void
    {
        self::assertEquals('-3:25:45.678', Utils::relTime(-12345.6789));
    }

    /**
     * Test that the relTime function returns the correct data when using a
     * negative value and a time with millisecond precision when flooring the result
     */
    public function testNegativeRelTimeWithMillisFloored(): void
    {
        self::assertEquals('-3:25:45', Utils::relTime(-12345.6789, true));
    }

    /**
     * Test that the toEpochFloat function works with a leap day
     */
    public function testToEpochFloatLeapday(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
        self::assertEquals(1583017140.000123, Utils::toEpochFloat('2020-02-29T23:59:00.000123+01:00'));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the toEpochFloat function works on a DST change
     */
    public function testToEpochFloatDstChange(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
        self::assertEquals(1572140520.010203, Utils::toEpochFloat('2019-10-27T02:42:00.010203+01:00'));
        self::assertEquals(1572136920.010203, Utils::toEpochFloat('2019-10-27T02:42:00.010203+02:00'));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the toEpochFloat works with random data
     */
    public function testAbsTimeToEpochFloatRandom(): void
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
                $t = (float)sprintf('%d.%03d', $now - $year + rand(0, 2*$year), rand(0, 999));
                $t2 = Utils::toEpochFloat(Utils::absTime($t));
                self::assertEqualsWithDelta($t, $t2, 0.0000001, "comparing random times in TZ=$tz");
            }
        }

        date_default_timezone_set($tz_orig);
    }

    /**
     * Test that printtime returns an empty string when no date is passed
     */
    public function testPrinttimeNotime(): void
    {
        self::assertEquals('', Utils::printTime(null, "H:i"));
    }

    /**
     * Test that the printtime function returns the correct result
     */
    public function testPrinttime(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $timestamp = 1544964581.3604;
        $expected = '2018-12-16 12:49';
        self::assertEquals($expected, Utils::printtime($timestamp, 'Y-m-d H:i'));
        date_default_timezone_set($tz);
    }

    /**
     * Test that the printtimediff function returns the correct result
     */
    public function testPrinttimediff(): void
    {
        // Test the empty end case,
        self::assertEquals("00:00", Utils::printtimediff(microtime(true)));

        $start = $end = 1544964581.3604;

        self::assertEquals("00:00", Utils::printtimediff($start, $end));

        $end += 2;
        self::assertEquals("00:02", Utils::printtimediff($start, $end));

        $end += 123;
        self::assertEquals("02:05", Utils::printtimediff($start, $end));

        $end += 4*60;
        self::assertEquals("06:05", Utils::printtimediff($start, $end));

        $end += 59;
        self::assertEquals("07:04", Utils::printtimediff($start, $end));

        $end += 13*60;
        self::assertEquals("20:04", Utils::printtimediff($start, $end));

        $end += (3*Utils::DAY_IN_SECONDS);
        self::assertEquals("3d 0:20:04", Utils::printtimediff($start, $end));

        $end += (365*Utils::DAY_IN_SECONDS);
        self::assertEquals("368d 0:20:04", Utils::printtimediff($start, $end));
    }

    /**
     * Test the difftime function
     */
    public function testDifftime(): void
    {
        $now = Utils::now();
        $offset = 10;
        $soon = $now + $offset;

        self::assertEquals(0, Utils::difftime($now, $now));
        self::assertEquals($offset, Utils::difftime($soon, $now));
        self::assertEquals(-$offset, Utils::difftime($now, $soon));
    }

    /**
     * Test the results of calculating the difference between two times.
     * Note: the function "assumes" the first is larger than the second
     * according to its specification.
     */
    public function testTimeStringDiff(): void
    {
        self::assertEquals("01:00:00", Utils::timeStringDiff("16:00:00", "15:00:00"));
        self::assertEquals("00:00:03", Utils::timeStringDiff("16:00:00", "15:59:57"));
        self::assertEquals("00:00:00", Utils::timeStringDiff("16:43:12", "16:43:12"));
        self::assertEquals("00:14:55", Utils::timeStringDiff("01:50:50", "01:35:55"));
        self::assertEquals("01:14:55", Utils::timeStringDiff("01:50:50", "00:35:55"));
        self::assertEquals("01:14:55", Utils::timeStringDiff("01:50:50", "35:55"));
    }

    /**
     * Test function that converts colour name to hex notation.
     * If value is already hexadecimal, return it unchanged.
     */
    public function testConvertToHexNoop(): void
    {
        $color = '#aa43c3';
        self::assertEquals($color, Utils::convertToHex($color));
        $color = '#CCA';
        self::assertEquals($color, Utils::convertToHex($color));
    }

    /**
     * Test function that converts colour name to hex notation.
     * Returns correct value for known colour names.
     */
    public function testConvertToHexConvert(): void
    {
        self::assertEquals('#B22222', Utils::convertToHex('firebrick'));
        self::assertEquals('#00BFFF', Utils::convertToHex('deep sky blue'));
        self::assertEquals('#FFD700', Utils::convertToHex('GOLD'));
        self::assertEquals('#B8860B', Utils::convertToHex('darkgoldenrod '));
    }

    public function testParseHexColor(): void
    {
        self::assertEquals([255, 255, 255], Utils::parseHexColor('#ffffff'));
        self::assertEquals([0, 0, 0], Utils::parseHexColor('#000000'));
        self::assertEquals([171, 205, 239], Utils::parseHexColor('#abcdef'));
        self::assertEquals([254, 220, 186], Utils::parseHexColor('#FEDCBA'));
    }

    public function testComponentToHex(): void
    {
        self::assertEquals('00', Utils::componentToHex(0));
        self::assertEquals('ff', Utils::componentToHex(255));
        self::assertEquals('ab', Utils::componentToHex(171));
        self::assertEquals('fe', Utils::componentToHex(254));
    }

    public function testRgbToHex(): void
    {
        self::assertEquals('#ffffff', Utils::rgbToHex([255, 255, 255]));
        self::assertEquals('#000000', Utils::rgbToHex([0, 0, 0]));
        self::assertEquals('#abcdef', Utils::rgbToHex([171, 205, 239]));
        self::assertEquals('#fedcba', Utils::rgbToHex([254, 220, 186]));
    }

    /**
     * Test function that converts colour name to hex notation.
     * Returns null for unknown values.
     */
    public function testConvertToHexUnknown(): void
    {
        self::assertNull(Utils::convertToHex('doesnotexist'));
        self::assertNull(Utils::convertToHex('#aabbccdd'));
        self::assertNull(Utils::convertToHex('#12346h'));
    }

    /**
     * Test function that converts colour hex notation to (nearest) name.
     * If value is not hexadecimal, return it unchanged.
     */
    public function testConvertToColorNoop(): void
    {
        $color = 'doesnotexist';
        self::assertEquals($color, Utils::convertToColor($color));
        $color = 'darkgoldenrod';
        self::assertEquals($color, Utils::convertToColor($color));
        $color = '#aabbccdd';
        self::assertEquals($color, Utils::convertToColor($color));
        $color = '#12346h';
        self::assertEquals($color, Utils::convertToColor($color));
        $color = '#1234';
        self::assertEquals(null, Utils::convertToColor($color));
    }

    /**
     * Test function that converts colour hex notation to (nearest) name.
     * Returns correct value for known colour names.
     */
    public function testConvertToColorConvertExact(): void
    {
        self::assertEquals('firebrick', Utils::convertToColor('#B22222'));
        self::assertEquals('firebrick', Utils::convertToColor('#b22222'));
        self::assertEquals('red', Utils::convertToColor('#F00'));
        self::assertEquals('lightsteelblue', Utils::convertToColor('#ACD'));
    }

    /**
     * Test function that converts colour hex notation to (nearest) name.
     * Returns correct closest value for known colour names.
     */
    public function testConvertToColorConvertClosest(): void
    {
        self::assertEquals('white', Utils::convertToColor('#fffffe'));
        self::assertEquals('black', Utils::convertToColor('#000010'));
    }

    /**
     * Test float rounding function called with null.
     */
    public function testRoundedFloatNull(): void
    {
        self::assertNull(Utils::roundedFloat(null));
    }

    /**
     * Test float rounding function called with a number without decimals.
     */
    public function testRoundedFloatNoDecimals(): void
    {
        self::assertEquals(-5, Utils::roundedFloat(-5));
        self::assertEquals(100, Utils::roundedFloat(100));
    }

    /**
     * Test float rounding function called with a number with decimals to default number of decimals.
     */
    public function testRoundedFloatDecimals(): void
    {
        self::assertEquals(6.01, Utils::roundedFloat(6.01));
        self::assertEquals(6.002, Utils::roundedFloat(6.002));
        self::assertEquals(6.002, Utils::roundedFloat(6.00213));
        self::assertEquals(6.002, Utils::roundedFloat(6.0025123));
    }

    /**
     * Test float rounding function called with a number with decimals to a specified number of decimals.
     */
    public function testRoundedFloatDecimalsSpecifiedLength(): void
    {
        self::assertEquals(6.01, Utils::roundedFloat(6.01, 2));
        self::assertEquals(6.01, Utils::roundedFloat(6.01, 5));
        self::assertEquals(6.0024, Utils::roundedFloat(6.0024, 4));
        self::assertEquals(6, Utils::roundedFloat(6.00213, 0));
        self::assertEquals(6.02, Utils::roundedFloat(6.025123, 2));
    }

    /**
     * Test that penalty time is correctly calculated.
     */
    public function testCalcPenaltyTime(): void
    {
        self::assertEquals(0, Utils::calcPenaltyTime(true, 1, 20, false));
        self::assertEquals(20, Utils::calcPenaltyTime(true, 2, 20, false));
        self::assertEquals(40, Utils::calcPenaltyTime(true, 3, 20, false));
        self::assertEquals(60, Utils::calcPenaltyTime(true, 4, 20, false));
        self::assertEquals(0, Utils::calcPenaltyTime(true, 1, 25, false));
        self::assertEquals(25, Utils::calcPenaltyTime(true, 2, 25, false));
        self::assertEquals(50, Utils::calcPenaltyTime(true, 3, 25, false));
        self::assertEquals(75, Utils::calcPenaltyTime(true, 4, 25, false));
    }

    /**
     * Test that penalty time is correctly calculated in seconds.
     */
    public function testCalcPenaltyTimeSeconds(): void
    {
        self::assertEquals(0, Utils::calcPenaltyTime(true, 1, 20, true));
        self::assertEquals(1200, Utils::calcPenaltyTime(true, 2, 20, true));
        self::assertEquals(2400, Utils::calcPenaltyTime(true, 3, 20, true));
        self::assertEquals(3600, Utils::calcPenaltyTime(true, 4, 20, true));
        self::assertEquals(0, Utils::calcPenaltyTime(true, 1, 50, true));
        self::assertEquals(3000, Utils::calcPenaltyTime(true, 2, 50, true));
        self::assertEquals(6000, Utils::calcPenaltyTime(true, 3, 50, true));
        self::assertEquals(9000, Utils::calcPenaltyTime(true, 4, 50, true));
    }

    /**
     * Test that penalty time is correctly calculated: problem not solved.
     */
    public function testCalcPenaltyTimeNotSolved(): void
    {
        self::assertEquals(0, Utils::calcPenaltyTime(false, 1, 20, false));
        self::assertEquals(0, Utils::calcPenaltyTime(false, 2, 20, false));
        self::assertEquals(0, Utils::calcPenaltyTime(false, 3, 20, false));
        self::assertEquals(0, Utils::calcPenaltyTime(false, 4, 20, false));
        self::assertEquals(0, Utils::calcPenaltyTime(false, 1, 25, false));
        self::assertEquals(0, Utils::calcPenaltyTime(false, 2, 25, false));
        self::assertEquals(0, Utils::calcPenaltyTime(false, 3, 25, true));
        self::assertEquals(0, Utils::calcPenaltyTime(false, 4, 25, true));
    }

    /**
     * Test that the scoreboard time is correctly truncated, time is in seconds
     */
    public function testScoreTimeInSeconds(): void
    {
        self::assertEquals(0, Utils::scoretime(0, true));
        self::assertEquals(0, Utils::scoretime(0.05, true));
        self::assertEquals(10, Utils::scoretime(10.9, true));
    }

    /**
     * Test that the scoreboard time is correctly truncated, time is in minutes
     */
    public function testScoreTimeInMinutes(): void
    {
        self::assertEquals(0, Utils::scoretime(0, false));
        self::assertEquals(0, Utils::scoretime(35, false));
        self::assertEquals(0, Utils::scoretime(59.9, false));
        self::assertEquals(1, Utils::scoretime(60, false));
        self::assertEquals(1, Utils::scoretime(60.2, false));
        self::assertEquals(5, Utils::scoretime(332, false));
    }

    /**
     * Test that printhost truncates a hostname
     */
    public function testPrinthost(): void
    {
        self::assertEquals("my", Utils::printhost("my.example.hostname.example.com"));
        self::assertEquals("hostonly", Utils::printhost("hostonly"));
    }

    /**
     * Test that printhost does not truncate a hostname
     */
    public function testPrinthostFull(): void
    {
        self::assertEquals("my.example.hostname.example.com", Utils::printhost("my.example.hostname.example.com", true));
        self::assertEquals("hostonly", Utils::printhost("hostonly", true));
    }

    /**
     * Test that printhost does not truncate an IP address.
     */
    public function testPrinthostIP(): void
    {
        self::assertEquals("127.0.0.1", Utils::printhost("127.0.0.1"));
        self::assertEquals("2001:610:0:800f:f816:3eff:fe15:c440", Utils::printhost("2001:610:0:800f:f816:3eff:fe15:c440"));
        self::assertEquals("127.0.0.1", Utils::printhost("127.0.0.1", true));
    }

    /**
     * Test that printsize prints some sizes
     */
    public function testPrintsize(): void
    {
        self::assertEquals("0 B", Utils::printsize(0));
        self::assertEquals("1000 B", Utils::printsize(1000));
        self::assertEquals("1024 B", Utils::printsize(1024));
        self::assertEquals("1.0 KB", Utils::printsize(1025));
        self::assertEquals("2 KB", Utils::printsize(2048));
        self::assertEquals("2.5 KB", Utils::printsize(2560));
        self::assertEquals("5 MB", Utils::printsize(5242880));
        self::assertEquals("23 GB", Utils::printsize(24696061952));
    }

    /**
     * Test that printsize prints some sizes with specified number of decimals
     */
    public function testPrintsizeDecimalsSpecified(): void
    {
        self::assertEquals("0 B", Utils::printsize(0, 4));
        self::assertEquals("1.00 KB", Utils::printsize(1025, 2));
        self::assertEquals("3 KB", Utils::printsize(2560, 0));
        self::assertEquals("5 MB", Utils::printsize(5242880, 10));
        self::assertEquals("22.999999254941940 GB", Utils::printsize(24696061152, 15));
    }

    /**
     * Basic testing of the LCSdiff function
     */
    public function testComputeLcsDiff(): void
    {
        $line_a = "DOMjudge is a system for running programming contests,";
        $line_b = "DOMjudge is a very good system for running programming contests,";
        $line_c = "DOMjudge is for running some programming contests,";

        $diff = Utils::computeLcsDiff($line_a, $line_b);
        self::assertTrue($diff[0]);
        self::assertStringContainsString('DOMjudge is a <ins>very</ins> <ins>good</ins> system for running', $diff[1]);

        $diff = Utils::computeLcsDiff($line_b, $line_a);
        self::assertTrue($diff[0]);
        self::assertStringContainsString('DOMjudge is a <del>very</del> <del>good</del> system for running', $diff[1]);

        $diff = Utils::computeLcsDiff($line_a, $line_c);
        self::assertTrue($diff[0]);
        self::assertStringContainsString('DOMjudge is <del>a</del> <del>system</del> for running <ins>some</ins> programming contests', $diff[1]);

        $diff = Utils::computeLcsDiff($line_a, $line_a);
        self::assertFalse($diff[0]);
        self::assertEquals("$line_a\n", $diff[1]);
    }

    /**
     * Testing of the LCSdiff function with long strings
     */
    public function testComputeLcsDiffLonglines(): void
    {
        $line_a = "DOMjudge is a system for running programming contests,";
        $line_b = "This usually means that teams are on-site and have a fixed time period (mostly 5 hours) and one computer to solve a number of problems (mostly 8-12). Problems are solved by writing a program in one of the allowed languages, that reads input according to the problem input specification and writes the correct, corresponding output. The judging is done by submitting the source code of the solution to the jury. There the jury system automatically compiles and runs the program and compares the program output with the expected output. This software can be used to handle the submission and judging during such contests. It also handles feedback to the teams and communication on problems (clarification requests). It has web interfaces for the jury, the teams (their submissions and clarification requests) and the public (scoreboard).";

        $diff = Utils::computeLcsDiff($line_a, $line_b);
        self::assertTrue($diff[0]);
        self::assertStringContainsString('<ins>judging</ins> [cut off rest of line...]', $diff[1]);
    }

    /**
     * Test that the specialchars function returns the correct result
     */
    public function testSpecialchars(): void
    {
        $plain = "Example string to test";
        self::assertEquals($plain, Utils::specialchars($plain));

        $html = 'Example <a href="aap">string</a> to test';
        $htmlenc = 'Example &lt;a href=&quot;aap&quot;&gt;string&lt;/a&gt; to test';
        self::assertEquals($htmlenc, Utils::specialchars($html));

        $validutf = "Test Thĳs ⛪⚖";
        self::assertEquals($validutf, Utils::specialchars($validutf));

        $invalidutf = "Test \xc3\x28 example";
        $replacedutf = "Test �( example";
        self::assertEquals($replacedutf, Utils::specialchars($invalidutf));
    }

    /**
     * Test that string is not cut when shorter or one longer than requested maximum
     */
    public function testCutStringNoop(): void
    {
        $string = 'Example string.';
        self::assertEquals($string, Utils::cutString($string, 70));
        self::assertEquals($string, Utils::cutString($string, 14));
        self::assertEquals($string, Utils::cutString($string, 15));
        self::assertEquals($string, Utils::cutString($string, 16));
    }

    /**
     * Test that string is cut when one longer than requested maximum
     */
    public function testCutStringCut(): void
    {
        $string = 'Example string.';
        self::assertEquals("Examp…", Utils::cutString($string, 5));
    }

    /**
     * Test that string is not cut when not longer than 1 over requested maximum,
     * counting multi-byte characters as one.
     */
    public function testCutStringNoopMB(): void
    {
        $string = '📍📍📍';
        self::assertEquals($string, Utils::cutString($string, 3));
        self::assertEquals($string, Utils::cutString($string, 2));
    }

    /**
     * Test that string is cut when one longer than requested maximum,
     * counting multi-byte characters as one.
     */
    public function testCutStringCutMB(): void
    {
        $string = '📍📍📍📍📍📍';
        self::assertEquals("📍📍📍…", Utils::cutString($string, 3));
        self::assertEquals("📍📍…", Utils::cutString($string, 2));
    }

    /**
     * Test image type png
     */
    public function testGetImageType(): void
    {
        $logo = __DIR__ . '/../../../public/images/teams/domjudge.jpg';
        $image = file_get_contents($logo);
        $error = null;

        $type = Utils::getImageType($image, $error);
        self::assertEquals('jpeg', $type);
        self::assertNull($error);
    }

    /**
     * Test image type with no implemented logic
     */
    public function testGetImageTypeNotImplemented(): void
    {
        $logo = __DIR__ . '/../../../public/images/DOMjudgelogo.svg';
        $image = file_get_contents($logo);
        $error = null;

        $type = Utils::getImageType($image, $error);
        self::assertFalse($type);
        self::assertEquals('Could not determine image information.', $error);
    }

    /**
     * Test image type with invalid image
     */
    public function testGetImageTypeError(): void
    {
        $image = 'Not really an image';
        $error = null;

        $type = Utils::getImageType($image, $error);
        self::assertFalse($type);
        self::assertEquals('Could not determine image information.', $error);
    }

    /**
     * test image thumbnail creation
     * @dataProvider provideImagesToThumb
     */
    public function testGetImageThumb($imageLocation, $mime) : void
    {
        $logo = dirname(__file__) . $imageLocation;
        $image = file_get_contents($logo);
        $error = null;
        $tmp = sys_get_temp_dir();
        $maxsize = 30;

        $thumb = Utils::getImageThumb($image, $maxsize, $tmp, $error);
        self::assertNull($error);

        $data = getimagesizefromstring($thumb);
        self::assertEquals($maxsize, $data[0]);  // resized width
        self::assertEquals($mime, $data['mime']);
    }

    public function provideImagesToThumb() : \Generator
    {
        yield ['/../../../public/images/teams/domjudge.jpg', 'image/jpeg'];
        yield ['/../../../public/js/cross.gif', 'image/gif'];
        yield ['/../../../public/js/hs.png', 'image/png'];
    }

    /**
     * Test image thumb with invalid image
     */
    public function testGetImageThumbError(): void
    {
        $image = 'Not really an image';
        $error = null;
        $tmp = sys_get_temp_dir();
        $maxsize = 30;

        $thumb = Utils::getImageThumb($image, $maxsize, $tmp, $error);
        self::assertFalse($thumb);
        self::assertEquals('Could not determine image information.', $error);
    }

    /**
     * Test getting image size
     *
     * @dataProvider provideTestGetImageSize
     */
    public function testGetImageSize(string $filename, int $expectedWidth, int $expectedHeight): void
    {
        [$width, $height, $ratio] = Utils::getImageSize($filename);
        self::assertEquals($expectedWidth, $width);
        self::assertEquals($expectedHeight, $height);
        self::assertEquals($width / $height, $ratio);
    }

    public function provideTestGetImageSize(): Generator
    {
        yield [__DIR__ . '/../../../public/js/hs.png', 181, 101];
        yield [__DIR__ . '/../../../public/images/teams/domjudge.jpg', 320, 200];
        yield [__DIR__ . '/../../../public/images/DOMjudgelogo.svg', 510, 1122];
    }

    /**
     * Test that the wrapUnquoted function returns the correct result
     */
    public function testWrapUnquotedSingleLineUnquoted(): void
    {
        $text = "This is an example text.";
        self::assertEquals($text, Utils::wrapUnquoted($text));
    }

    /**
     * Test that the specialchars function returns the correct result with a
     * long line
     */
    public function testWrapUnquotedLongLineUnquoted(): void
    {
        $text = "This is an example text.";
        $result = "This is an
example
text.";
        self::assertEquals($result, Utils::wrapUnquoted($text, 10));
    }

    /**
     * Test that the specialchars function returns the correct result with a
     * long quoted line
     */
    public function testWrapUnquotedLongLineWithQuoted(): void
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
        self::assertEquals($result, Utils::wrapUnquoted($text, 10));
    }

    /**
     * Test that the specialchars function returns the correct result with a
     * long quoted line with a custom quote character
     */
    public function testWrapUnquotedLongLineWithQuotedCustomQuoteCharacter(): void
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
        self::assertEquals($result, Utils::wrapUnquoted($text, 10, '#'));
    }

    /**
     * Test that the startsWith function returns the correct result
     */
    public function testStartsWith(): void
    {
        $text = "The quick brown fox jumped over the lazy dog.";
        $start = "The quick";
        self::assertTrue(Utils::startsWith($text, $start));
        self::assertTrue(Utils::startsWith($start, $start));
        self::assertFalse(Utils::startsWith($start, $text));
    }

    /**
     * Test that the endsWith function returns the correct result
     */
    public function testEndsWith(): void
    {
        $text = "The quick brown fox jumped over the lazy dog.";
        $end = "lazy dog.";
        self::assertTrue(Utils::endsWith($text, $end));
        self::assertTrue(Utils::endsWith($end, $end));
        self::assertFalse(Utils::endsWith($end, $text));
    }

    /**
     * Test that the generatePassword function generates a valid password (when
     * using more entropy)
     */
    public function testGeneratePasswordMoreEntropy(): void
    {
        $passes = [];
        $onlyCorrectChars = true;
        for ($i=0; $i < 100; ++$i) {
            $pass = Utils::generatePassword();
            $onlyCorrectChars = $onlyCorrectChars && preg_match('/^[a-zA-Z0-9_-]+$/', $pass);
            $passes[] = $pass;
        }

        self::assertEquals(1, max(array_count_values($passes)));
        self::assertEquals(32, min(array_map('strlen', $passes)));
        self::assertEquals(32, max(array_map('strlen', $passes)));
        self::assertTrue($onlyCorrectChars);
    }

    /**
     * Test that the generatePassword function generates a valid password when
     * using less entropy
     */
    public function testGeneratePasswordWithLessEntropy(): void
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

        self::assertEquals(1, max(array_count_values($passes)));
        self::assertEquals(12, min(array_map('strlen', $passes)));
        self::assertEquals(12, max(array_map('strlen', $passes)));
        self::assertTrue($onlyalnum);
        self::assertFalse($containsforbidden);
    }

    /**
     * Test that PHP ini values for bytes are converted correctly.
     */
    public function testPhpiniToBytes(): void
    {
        self::assertEquals(100, Utils::phpiniToBytes('100'));
        self::assertEquals(100*1024**3, Utils::phpiniToBytes('100g'));
        self::assertEquals(120*1024**2, Utils::phpiniToBytes('120m'));
        self::assertEquals(1*1024, Utils::phpiniToBytes('1k'));
        self::assertEquals(1*1024, Utils::phpiniToBytes('1K'));
        self::assertEquals(20*1024**3, Utils::phpiniToBytes('20G'));
        self::assertEquals(12*1024**2, Utils::phpiniToBytes('12M'));
    }

    /**
     * Test that we get the correct table name for an entity
     */
    public function testTableForEntity(): void
    {
        $entity = new TeamAffiliation();
        self::assertEquals('team_affiliation', Utils::tableForEntity($entity));
    }

    /**
     * Test that returning a binary file sets correct header
     */
    public function testStreamAsBinaryFile(): void
    {
        $content = 'The quick brown fox jumps over the lazy dog.';
        $filename = 'foxdog.txt';
        $length = strlen($content);

        $response = Utils::StreamAsBinaryFile($content, $filename)->__toString();

        self::assertMatchesRegularExpression('#Content-Disposition:\s+attachment; filename="' . str_replace('.', '\.', $filename) . '"#', $response);
        self::assertMatchesRegularExpression("#Content-Type:\s+application/octet-stream#", $response);
        self::assertMatchesRegularExpression("#Content-Length:\s+$length#", $response);
        self::assertMatchesRegularExpression("#Content-Transfer-Encoding:\s+binary#", $response);
    }

    /**
     * Test Tab Separated Value encoding
     */
    public function testToTsvField(): void
    {
        self::assertEquals('team name', Utils::toTsvField('team name'));
        self::assertEquals('Team,,, name', Utils::toTsvField('Team,,, name'));
        self::assertEquals('team\\nname', Utils::toTsvField("team\nname"));
        self::assertEquals('team\\tname\\nexample\\t', Utils::toTsvField("team\tname\nexample\t"));
        self::assertEquals('team\\r\\nname', Utils::toTsvField("team\r\nname"));
        self::assertEquals('tea\\\\mname', Utils::toTsvField("tea\\mname"));
        self::assertEquals('team nåme…', Utils::toTsvField("team nåme…"));
        self::assertEquals('team🎈name', Utils::toTsvField("team🎈name"));
    }

    /**
     * Test Tab Separated Value parsing
     */
    public function testParseTsvLine(): void
    {
        $bs  = "\\";
        $tab = "\t";
        self::assertEquals(["team name", "rank"], Utils::parseTsvLine("team name".$tab."rank"));
        self::assertEquals(["team\tname\t", "rank"], Utils::parseTsvLine("team".$bs."t"."name".$bs."t".$tab."rank"));
        self::assertEquals(["team\nname\r", "rank"], Utils::parseTsvLine("team".$bs."n"."name".$bs."r".$tab."rank"));
        self::assertEquals(["team\\name\\", "rank"], Utils::parseTsvLine("team".$bs.$bs."name".$bs.$bs.$tab."rank"));
        self::assertEquals([$bs], Utils::parseTsvLine($bs.$bs));
        self::assertEquals([$bs."t"], Utils::parseTsvLine($bs.$bs."t"));
        self::assertEquals(["Team,,, name"], Utils::parseTsvLine("Team,,, name\n"));
        self::assertEquals(["Team", "", "", " nm "], Utils::parseTsvLine("Team".$tab.$tab.$tab." nm \r\n"));
        self::assertEquals(["tea\\mname", "rank"], Utils::parseTsvLine("tea".$bs.$bs."mname".$tab."rank"));
        self::assertEquals(["team nåme…", "rank"], Utils::parseTsvLine("team nåme…".$tab."rank"));
        self::assertEquals(["team🎈name", "rank"], Utils::parseTsvLine("team🎈name".$tab."rank"));
    }

    /**
     * Test that reindexing an array works
     */
    public function testReindex(): void
    {
        $input = [1, 2, 3];
        $expectedOutput = [2 => 1, 4 => 2, 6 => 3];
        $doubled = function ($item) {
            return $item * 2;
        };
        self::assertEquals($expectedOutput, Utils::reindex($input, $doubled));
    }
}
