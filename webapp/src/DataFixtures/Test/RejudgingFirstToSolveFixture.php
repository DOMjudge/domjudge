<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class RejudgingFirstToSolveFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $team1 = $manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $team2 = (new Team())
            ->setName('Another team')
            ->setCategory($team1->getCategory());

        $manager->persist($team2);

        $submissionData = [
            // team, submittime,            result]
            [$team1, '2021-01-01 12:34:56', 'correct'],
            [$team2, '2021-01-01 12:33:56', 'wrong-answer'],
        ];

        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $language = $manager->getRepository(Language::class)->find('cpp');
        $problem = $contest->getProblems()->filter(fn(ContestProblem $problem) => $problem->getShortname() === 'A')->first();

        foreach ($submissionData as $submissionItem) {
            $submission = (new Submission())
                ->setContest($contest)
                ->setTeam($submissionItem[0])
                ->setContestProblem($problem)
                ->setLanguage($language)
                ->setValid(true)
                ->setSubmittime(Utils::toEpochFloat($submissionItem[1]));
            $judging = (new Judging())
                ->setContest($contest)
                ->setStarttime(Utils::toEpochFloat($submissionItem[1]))
                ->setEndtime(Utils::toEpochFloat($submissionItem[1]) + 5)
                ->setValid(true)
                ->setResult($submissionItem[2]);
            $judging->setSubmission($submission);
            $submission->addJudging($judging);
            $manager->persist($submission);
            $manager->persist($judging);
            $manager->flush();
        }
    }
}
