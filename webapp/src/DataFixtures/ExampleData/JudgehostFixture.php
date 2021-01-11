<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Judgehost;
use Doctrine\Persistence\ObjectManager;

class JudgehostFixture extends AbstractExampleDataFixture
{
    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $judgehost = new Judgehost();
        $judgehost
            ->setHostname('example-judgehost1')
            ->setActive(false);

        $manager->persist($judgehost);
        $manager->flush();
    }
}
