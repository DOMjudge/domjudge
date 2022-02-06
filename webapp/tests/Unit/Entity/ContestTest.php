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

        $this->assertEquals(null, $contest->getAbsoluteTime(null));

        $this->assertEquals(1672585200, $contest->getAbsoluteTime('2023-01-01 16:00:00 Europe/Amsterdam'));
        $this->assertEquals(1672624800, $contest->getAbsoluteTime('2023-01-01 16:00:00 Pacific/Honolulu'));

        $this->assertEquals(42, $contest->getAbsoluteTime("-00:00"));
        $this->assertEquals(42, $contest->getAbsoluteTime("+00:00"));
        $this->assertEquals(42+4*3600, $contest->getAbsoluteTime("+4:00"));
        $this->assertEquals(42-23*60, $contest->getAbsoluteTime("-0:23"));

        $this->assertEquals(42-((34*60) + 56.789), $contest->getAbsoluteTime("-0:34:56.789"));
        $this->assertEquals(42+(1*3600)+(47*60)+11, $contest->getAbsoluteTime("+1:47:11"));
        $removedInterval = new RemovedInterval();
        $removedInterval
            ->setStarttime(111)
            ->setEndtime(555);
        $contest->addRemovedInterval($removedInterval);
        $this->assertEquals(42+(1*3600)+(47*60)+11 + 444, $contest->getAbsoluteTime("+1:47:11"));
    }
}
