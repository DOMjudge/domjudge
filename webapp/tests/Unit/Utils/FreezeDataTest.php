<?php declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\DataFixtures\Test\ContestTimeFixture;
use App\Entity\Contest;
use App\Tests\Unit\BaseTest as BaseBaseTest;
use App\Utils\FreezeData;
use Generator;

class FreezeDataTest extends BaseBaseTest
{
    protected function getContestData(string $reference): FreezeData
    {
        $this->loadFixture(ContestTimeFixture::class);
        $em = self::getContainer()->get('doctrine')->getManager();
        $contest = $em->getRepository(Contest::class)->findOneBy(['name' => $reference]);
        return new FreezeData($contest);
    }

    /**
     * Testing all functions separate makes little sense as they all need similar data.
     * By keeping the assertions separate it's still easy to see where we get a possible failure.
     *
     * @dataProvider provideContestProgress
     */
    public function testFreezeData(
        string $reference,
        int $progress,
        bool $finalized,
        bool $running,
        bool $started,
        bool $stopped,
        bool $showFrozen,
        bool $showFinal
    ): void {
        $data = $this->getContestData($reference);
        self::assertEquals($progress, $data->getProgress());
        self::assertEquals($finalized, $data->finalized());
        self::assertEquals($running, $data->running());
        self::assertEquals($started, $data->started());
        self::assertEquals($stopped, $data->stopped());
        self::assertEquals($showFrozen, $data->showFrozen());
        self::assertEquals($showFinal, $data->showFinal());
    }

    /**
     * Data setup as:
     * - ContestIdentifierName
     * - Progress of contest
     * - Finalized
     * - Running
     * - Stopped
     * - Started
     * - ShowFrozen
     * - ShowFinal
     */
    public function provideContestProgress(): Generator
    {
        yield ['beforeActivation',              -1, false, false, false, false, false, false];
        yield ['beforeStart',                   -1, false, false, false, false, false, false];
        yield ['beforeFreeze',                   0, false,  true,  true, false, false, false];
        yield ['beforeEnd',                     50, false,  true,  true, false,  true, false];
        yield ['beforeFinalized',              100, false, false,  true,  true,  true, false];
        yield ['beforeUnfreeze',               100,  true, false,  true,  true,  true, false];
        yield ['beforeDeactivation',           100,  true, false,  true,  true, false,  true];
        yield ['afterDeactivation',            100,  true, false,  true,  true, false,  true];
        yield ['beforeUnfreezeNoFinalize',     100, false, false,  true,  true,  true, false];
        yield ['beforeDeactivationNoFinalize', 100, false, false,  true,  true, false,  true];
        yield ['afterDeactivationNoFinalize',  100, false, false,  true,  true, false,  true];
        yield ['beforeStartNFr',                -1, false, false, false, false, false, false];
        yield ['beforeEndNFr',                  50, false,  true,  true, false, false, false];
        yield ['beforeDeactivationNFr',        100, false, false,  true,  true, false,  true];
        yield ['afterDeactivationNFr',         100, false, false,  true,  true, false,  true];
        yield ['noDeactivationNFr',            100, false, false,  true,  true, false,  true];
    }
}
