<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils\Scoreboard;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataFixtures\Test\ContestTimeFixture;
use App\Entity\Contest;
use App\Tests\Unit\BaseTestCase as BaseBaseTestCase;
use App\Tests\Unit\Utils\FreezeDataTest;
use App\Utils\FreezeData;
use App\Utils\Scoreboard\Scoreboard;
use Generator;

class ScoreboardTest extends BaseBaseTestCase
{
    #[DataProvider('provideFreezeDataProgress')]
    public function testScoreboardProgress(
        string $reference,
        int $progress,
        bool $_1,
        bool $_2,
        bool $_3,
        bool $_4,
        bool $_5,
        bool $_6
    ): void {
        $this->loadFixture(ContestTimeFixture::class);
        $em = self::getContainer()->get('doctrine')->getManager();
        $contest = $em->getRepository(Contest::class)->findOneBy(['name' => $reference]);
        $freezeData = new FreezeData($contest);
        $scoreBoard = new Scoreboard($contest, [], [], [], [], [], $freezeData, false, true);
        self::assertEquals($scoreBoard->getProgress(), $progress);
    }

    public static function provideFreezeDataProgress(): Generator
    {
        $class = new FreezeDataTest('provideContestProgress');
        return $class->provideContestProgress();
    }
}
