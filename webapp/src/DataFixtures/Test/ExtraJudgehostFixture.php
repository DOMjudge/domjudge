<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Judgehost;
use Doctrine\Persistence\ObjectManager;

class ExtraJudgehostFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $judgehost = new Judgehost();
        $judgehost
            ->setHostname('example-judgehost2')
            ->setEnabled(false);

        $manager->persist($judgehost);
        $manager->flush();
    }
}
