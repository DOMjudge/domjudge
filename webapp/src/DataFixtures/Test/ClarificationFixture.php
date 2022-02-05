<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\Clarification;
use App\Entity\Problem;
use App\Entity\Team;
use Doctrine\Persistence\ObjectManager;

class ClarificationFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        /** @var Team $team */
        $team = $manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        /** @var Problem $problem */
        $problem = $manager->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);

        $unhandledClarification = new Clarification();
        $unhandledClarification
            ->setContest($contest)
            ->setSubmittime(1518385738.901348000)
            ->setSender($team)
            ->setProblem($problem)
            ->setBody('Is it necessary to read the problem statement carefully?')
            ->setAnswered(false);

        $juryGeneral = new Clarification();
        $juryGeneral
            ->setContest($contest)
            ->setSubmittime(1518386000)
            ->setJuryMember('admin')
            ->setBody("Lunch is served")
            ->setAnswered(true);

        $juryGeneralToTeam = new Clarification();
        $juryGeneralToTeam
            ->setContest($contest)
            ->setSubmittime(1518385663.689197000)
            ->setRecipient($team)
            ->setJuryMember('admin')
            ->setProblem($problem)
            ->setBody("There was a mistake in judging this problem. Please try again")
            ->setAnswered(true);

        foreach ([$unhandledClarification, $juryGeneral, $juryGeneralToTeam] as $index => $clar) {
            $manager->persist($clar);
            $manager->flush();
            $this->addReference(sprintf('%s:%d', static::class, $index), $clar);
        }
    }
}
