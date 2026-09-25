<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

/**
 * Creates a single submission without any judging, so it shows up as queued in status counters.
 */
class PendingSubmissionFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $problem = $contest->getProblems()
            ->filter(fn(ContestProblem $cp) => $cp->getShortname() === 'A')
            ->first();
        $language = $manager->getRepository(Language::class)->findByExternalId('cpp');

        $submission = (new Submission())
            ->setContest($contest)
            ->setTeam($team)
            ->setContestProblem($problem)
            ->setLanguage($language)
            ->setValid(true)
            ->setSubmittime(Utils::toEpochFloat('2021-04-01 09:00:00'));
        $manager->persist($submission);
        $manager->flush();
    }
}
