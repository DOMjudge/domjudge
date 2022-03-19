<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use Doctrine\Persistence\ObjectManager;

class DemoPreEndContestFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $demoContest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $demoContest->setStarttimeString('2020-01-01 09:00:00 Europe/Amsterdam')
        ->setFreezetimeString('2021-01-03 12:34:56 Europe/Amsterdam')
        ->setEndtimeString(
            sprintf(
                '%s-01-01 09:00:00 Europe/Amsterdam',
                date('Y') + 1
            )
        ); // Set the time explicit to guard against changes in the Default fixture.
        $manager->persist($demoContest);
        $manager->flush();
    }
}
