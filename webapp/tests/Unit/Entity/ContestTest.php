<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Contest;
use App\Entity\RemovedInterval;
use PHPUnit\Framework\TestCase;

class ContestTest extends TestCase
{
    public function testGetAbsoluteTime(): void
    {
        $contest = new Contest();
        $contest->setStarttime(42);

        static::assertEquals(null, $contest->getAbsoluteTime(null));

        static::assertEquals(1672585200, $contest->getAbsoluteTime('2023-01-01 16:00:00 Europe/Amsterdam'));
        static::assertEquals(1672624800, $contest->getAbsoluteTime('2023-01-01 16:00:00 Pacific/Honolulu'));

        static::assertEquals(42, $contest->getAbsoluteTime("-00:00"));
        static::assertEquals(42, $contest->getAbsoluteTime("+00:00"));
        static::assertEquals(42+4*3600, $contest->getAbsoluteTime("+4:00"));
        static::assertEquals(42-23*60, $contest->getAbsoluteTime("-0:23"));

        static::assertEquals(42-((34*60) + 56.789), $contest->getAbsoluteTime("-0:34:56.789"));
        static::assertEquals(42+(1*3600)+(47*60)+11, $contest->getAbsoluteTime("+1:47:11"));
        $removedInterval = new RemovedInterval();
        $removedInterval
            ->setStarttime(111)
            ->setEndtime(555);
        $contest->addRemovedInterval($removedInterval);
        static::assertEquals(42+(1*3600)+(47*60)+11 + 444, $contest->getAbsoluteTime("+1:47:11"));
    }
}
