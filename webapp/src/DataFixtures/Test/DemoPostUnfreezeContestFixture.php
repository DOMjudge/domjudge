<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use Doctrine\Persistence\ObjectManager;

class DemoPostUnfreezeContestFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $demoContest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $demoContest
            ->setStarttimeString('2021-01-03 12:34:56 Europe/Amsterdam')
            ->setEndtimeString('2021-01-04 12:34:56 Europe/Amsterdam')
            ->setFreezetimeString('2021-01-05 12:34:56 Europe/Amsterdam')
            ->setUnfreezetimeString('2021-01-06 12:34:56 Europe/Amsterdam');
        $manager->persist($demoContest);
        $manager->flush();
    }
}
