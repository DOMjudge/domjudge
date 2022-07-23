<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use Doctrine\Persistence\ObjectManager;

class LockedContestFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        // Load and lock demo contest.
        $demoContest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $demoContest->setIsLocked(true);
        $manager->flush();
    }
}
