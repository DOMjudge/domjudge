<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use Doctrine\Persistence\ObjectManager;

class DemoPreActivationContestFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $demoContest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $demoContest->setActivatetimeString(
            sprintf(
                '%d-01-01 09:00:00 Europe/Amsterdam',
                (int)date('Y') + 1
            )
        )->setStarttimeString(
            sprintf(
                '%d-01-01 09:00:00 Europe/Amsterdam',
                (int)date('Y') + 2
            )
        );
        $manager->persist($demoContest);
        $manager->flush();
    }
}
