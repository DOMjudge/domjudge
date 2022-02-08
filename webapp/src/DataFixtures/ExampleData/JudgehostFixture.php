<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Judgehost;
use Doctrine\Persistence\ObjectManager;

class JudgehostFixture extends AbstractExampleDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $judgehost = new Judgehost();
        $judgehost
            ->setHostname('example-judgehost1')
            ->setEnabled(false);

        $manager->persist($judgehost);
        $manager->flush();
    }
}
