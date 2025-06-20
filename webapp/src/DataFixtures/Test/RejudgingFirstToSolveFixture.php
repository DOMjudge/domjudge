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
            ->setName('Another team');
        foreach ($team1->getCategories() as $category) {
            $team2->addCategory($category);
        }

        $manager->persist($team2);

        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        // Two submissions, one for each team, the incorrect one before the correct one.
        // Later, in the test, we will flip the 'wrong-answer' to correct in
        // order to produce a new first to solve.
        $submissionData = [
            // team, submittime,                     result]
            [$team2, $contest->getStarttime() + 300, 'wrong-answer'],
            [$team1, $contest->getStarttime() + 400, 'correct'],
        ];

        $language = $manager->getRepository(Language::class)->find('cpp');
        $problem = $contest->getProblems()->filter(fn(ContestProblem $problem) => $problem->getShortname() === 'A')->first();

        foreach ($submissionData as $submissionItem) {
            $submission = (new Submission())
                ->setContest($contest)
                ->setTeam($submissionItem[0])
                ->setContestProblem($problem)
                ->setLanguage($language)
                ->setValid(true)
                ->setSubmittime($submissionItem[1]);
            $judging = (new Judging())
                ->setContest($contest)
                ->setStarttime($submissionItem[1])
                ->setEndtime($submissionItem[1] + 5)
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
