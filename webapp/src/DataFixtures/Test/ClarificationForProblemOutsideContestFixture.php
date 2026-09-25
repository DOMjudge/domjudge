<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\Problem;
use Doctrine\Persistence\ObjectManager;

/**
 * A clarification about a problem that is not part of its own contest, as can
 * happen when the problem is removed from the contest after the fact.
 */
class ClarificationForProblemOutsideContestFixture extends AbstractTestDataFixture
{
    public const BODY = 'This problem is not part of this contest';

    public function load(ObjectManager $manager): void
    {
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);

        // Deliberately not linked to the contest through a ContestProblem.
        $problem = (new Problem())
            ->setExternalid('unlinked')
            ->setName('Problem outside the contest')
            ->setTimelimit(2);
        $manager->persist($problem);

        $manager->persist((new Clarification())
            ->setContest($contest)
            ->setSubmittime(1518385738.901348000)
            ->setProblem($problem)
            ->setBody(static::BODY)
            ->setAnswered(true));
        $manager->flush();
    }
}
