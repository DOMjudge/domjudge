<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use Doctrine\Persistence\ObjectManager;

/**
 * Keeps the 'hello' problem in the demo contest, but does not allow submitting to it.
 */
class HelloProblemNotSubmittableFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        foreach ($contest->getProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getProblem()->getExternalid() === 'hello') {
                $contestProblem->setAllowSubmit(false);
            }
        }
        $manager->flush();
    }
}
