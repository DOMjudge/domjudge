<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ScoreboardService;
use Doctrine\Common\Collections\Order;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ScoreboardServiceTest extends KernelTestCase
{
    public function testScoreKeyConversionInt(): void
    {
        self::assertEquals(
            "00000000000000000000000.000000000",
            ScoreboardService::convertToScoreKeyElement(0)
        );

        self::assertEquals(
            "00000000000000000000001.000000000",
            ScoreboardService::convertToScoreKeyElement(1)
        );

        self::assertEquals(
            "00000000000000000000042.000000000",
            ScoreboardService::convertToScoreKeyElement(42)
        );

        self::assertEquals(
            "00000000000000000000666.000000000",
            ScoreboardService::convertToScoreKeyElement(666)
        );
    }

    public function testScoreKeyConversionIntAscending(): void
    {
        self::assertEquals(
            "99999999999999999999999.000000000",
            ScoreboardService::convertToScoreKeyElement(0, Order::Ascending)
        );

        self::assertEquals(
            "99999999999999999999998.000000000",
            ScoreboardService::convertToScoreKeyElement(1, Order::Ascending)
        );

        self::assertEquals(
            "99999999999999999999957.000000000",
            ScoreboardService::convertToScoreKeyElement(42, Order::Ascending)
        );
    }

    public function testScoreKeyConversionBcmath(): void
    {
        self::assertEquals(
            "00000000000000000000000.000000000",
            ScoreboardService::convertToScoreKeyElement("0")
        );

        self::assertEquals(
            "00000000000000000000000.123000000",
            ScoreboardService::convertToScoreKeyElement("0.123")
        );

        self::assertEquals(
            "00000000000000000000666.123456789",
            ScoreboardService::convertToScoreKeyElement("666.123456789")
        );
    }

    public function testScoreKeyConversionBcmathAscending(): void
    {
        self::assertEquals(
            "99999999999999999999999.000000000",
            ScoreboardService::convertToScoreKeyElement("0", Order::Ascending)
        );

        self::assertEquals(
            "99999999999999999999998.877000000",
            ScoreboardService::convertToScoreKeyElement("0.123", Order::Ascending)
        );

        self::assertEquals(
            "99999999999999999999332.876543211",
            ScoreboardService::convertToScoreKeyElement("666.123456789", Order::Ascending)
        );
    }

    public function testScoreKeyNegativeInt(): void
    {
        $this->expectException(Exception::class);
        ScoreboardService::convertToScoreKeyElement(-1);
    }

    public function testScoreKeyNegativeBcmath(): void
    {
        $this->expectException(Exception::class);
        ScoreboardService::convertToScoreKeyElement("-0.123");
    }

    public function testTooLarge(): void
    {
        $this->expectException(Exception::class);
        ScoreboardService::convertToScoreKeyElement("100000000000000000000000");
    }

    public function testEmptyTeams(): void
    {
        $teamA = ScoreboardService::getICPCScoreKey(0, 0, 0);
        $teamB = ScoreboardService::getICPCScoreKey(0, 0, 0);
        self::assertEquals($teamA, $teamB);
    }

    public function testEqualTeams(): void
    {
        $teamA = ScoreboardService::getICPCScoreKey(7, 666, 420);
        $teamB = ScoreboardService::getICPCScoreKey(7, 666, 420);
        self::assertEquals($teamA, $teamB);
    }

    public function testOneTeamEmpty(): void
    {
        $teamA = ScoreboardService::getICPCScoreKey(0, 0, 0);
        $teamB = ScoreboardService::getICPCScoreKey(7, 666, 420);
        self::assertTrue($teamA < $teamB);
    }

    public function testOneTeamSolvedMore(): void
    {
        $teamA = ScoreboardService::getICPCScoreKey(1, 333, 210);
        $teamB = ScoreboardService::getICPCScoreKey(7, 666, 420);
        self::assertTrue($teamA < $teamB);
    }

    public function testEqualExceptLast(): void
    {
        $teamA = ScoreboardService::getICPCScoreKey(7, 666, 420);
        $teamB = ScoreboardService::getICPCScoreKey(7, 666, 421);
        self::assertTrue($teamA > $teamB);
    }

    public function testEqualExceptSecondLast(): void
    {
        $teamA = ScoreboardService::getICPCScoreKey(7, 666, 420);
        $teamB = ScoreboardService::getICPCScoreKey(7, 667, 420);
        self::assertTrue($teamA > $teamB);
    }

    public function testEqualExceptFirst(): void
    {
        $teamA = ScoreboardService::getICPCScoreKey(7, 666, 420);
        $teamB = ScoreboardService::getICPCScoreKey(8, 666, 420);
        self::assertTrue($teamA < $teamB);
    }
}
