<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use Doctrine\Persistence\ObjectManager;

class DemoPreDeactivateContestFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $demoContest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $demoContest
            ->setStarttimeString(
                sprintf(
                    '%d-06-06 00:00:00 Europe/Amsterdam',
                    (int)date('Y') - 1
                )
            )
            ->setEndtimeString(
                sprintf(
                    '%d-06-06 09:00:00 Europe/Amsterdam',
                    (int)date('Y') - 1
                )
            )->setUnfreezetimeString(
                sprintf(
                    '%s-01-01 09:00:00 Europe/Amsterdam',
                    date('Y')
                )
            )->setDeactivatetimeString(
                sprintf(
                    '%d-01-01 09:00:00 Europe/Amsterdam',
                    (int)date('Y') + 2
                )
            ); // Set the time explicit to guard against changes in the Default fixture.
        $manager->persist($demoContest);
        $manager->flush();
    }
}
