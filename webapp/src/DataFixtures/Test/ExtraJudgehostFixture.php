<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Judgehost;
use Doctrine\Persistence\ObjectManager;

class ExtraJudgehostFixture extends AbstractTestDataFixture
{
    final public const HOSTNAME = 'example-judgehost2';

    public function load(ObjectManager $manager): void
    {
        $judgehost = new Judgehost();
        $judgehost
            ->setHostname(self::HOSTNAME)
            ->setEnabled(false);

        $manager->persist($judgehost);
        $manager->flush();
    }
}
