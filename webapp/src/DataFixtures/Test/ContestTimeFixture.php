<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class ContestTimeFixture extends AbstractTestDataFixture
{
                     //Name,                         Activation, Start, Freeze, End, Finalized, Unfreeze, Deactivation
    const VALUES = [['beforeActivation',               5,         10,     15,    20,   25,        30,       35],
                    ['beforeStart',                    0,          5,     10,    15,   20,        25,       30],
                    ['beforeFreeze',                  -5,          0,      5,    10,   15,        20,       25],
                    ['beforeEnd',                    -10,         -5,      0,     5,   10,        15,       20],
                    ['beforeFinalized',              -15,        -10,     -5,     0,    5,        10,       15],
                    ['beforeUnfreeze',               -20,        -15,    -10,    -5,    0,         5,       10],
                    ['beforeDeactivation',           -25,        -20,    -15,   -10,   -5,         0,        5],
                    ['afterDeactivation',            -35,        -30,    -25,   -20,  -15,       -10,       -5],
                    ['beforeUnfreezeNoFinalize',     -20,        -15,    -10,    -5, null,         5,       10],
                    ['beforeDeactivationNoFinalize', -25,        -20,    -15,   -10, null,         0,        5],
                    ['afterDeactivationNoFinalize',  -35,        -30,    -25,   -20, null,       -10,       -5],
                    ['beforeStartNFr',                 0,          5,   null,    15, null,      null,       30],
                    ['beforeEndNFr',                 -10,         -5,   null,     5, null,      null,       20],
                    ['beforeDeactivationNFr',        -25,        -20,   null,   -10, null,      null,        5],
                    ['afterDeactivationNFr',         -35,        -30,   null,   -20, null,      null,       -5],
                    ['noDeactivationNFr',            -35,        -30,   null,   -20, null,      null,     null]];

    protected function getTime(?int $multiplier): ?string
    {
        return is_null($multiplier) ? null : Utils::absTime(Utils::now()+$multiplier*60);
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::VALUES as $row) {
            $name = $row[0];
            $timeActivate = $this->getTime($row[1]);
            $timeStart = $this->getTime($row[2]);
            $timeFreeze = $this->getTime($row[3]);
            $timeEnd = $this->getTime($row[4]);
            $timeFinalized = is_null($row[5]) ? null : Utils::now()+60*1000*$row[5];
            $timeUnfrozen = $this->getTime($row[6]);
            $timeDeactivated = $this->getTime($row[7]);
            $contest = new Contest();
            $contest->setName($name)
                    ->setExternalid($name)
                    ->setShortname($name)
                    ->setActivatetimeString($timeActivate)
                    ->setStartTimeString($timeStart)
                    ->setFreezeTimeString($timeFreeze)
                    ->setEndTimeString($timeEnd)
                    ->setFinalizeTime($timeFinalized)
                    ->setUnfreezeTimeString($timeUnfrozen)
                    ->setDeactivateTimeString($timeDeactivated);
            $manager->persist($contest);
            $manager->flush();
            $this->addReference(static::class . $name, $contest);
        }
    }
}
