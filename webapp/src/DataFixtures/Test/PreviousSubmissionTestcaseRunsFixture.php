<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class PreviousSubmissionTestcaseRunsFixture extends AbstractTestDataFixture
{
    public const CURRENT_SUBMISSION_REFERENCE = self::class . ':current';

    public function load(ObjectManager $manager): void
    {
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        /** @var Team $team */
        $team = $manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        /** @var Language $language */
        $language = $manager->getRepository(Language::class)->findByExternalId('cpp');

        /** @var ContestProblem $contestProblem */
        $contestProblem = $contest->getProblems()
            ->filter(fn (ContestProblem $problem) => $problem->getShortname() === 'A')
            ->first();
        /** @var Testcase $testcase */
        $testcase = $contestProblem->getProblem()->getTestcases()->first();

        $this->createSubmissionWithRun(
            $manager,
            $contest,
            $contestProblem,
            $team,
            $language,
            $testcase,
            'issue3627-previous',
            '2030-01-10 12:00:00',
            'wrong-answer',
        );
        $currentSubmission = $this->createSubmissionWithRun(
            $manager,
            $contest,
            $contestProblem,
            $team,
            $language,
            $testcase,
            'issue3627-current',
            '2030-01-10 12:05:00',
            Judging::RESULT_CORRECT,
        );

        $manager->flush();
        $this->addReference(self::CURRENT_SUBMISSION_REFERENCE, $currentSubmission);
    }

    private function createSubmissionWithRun(
        ObjectManager $manager,
        Contest $contest,
        ContestProblem $contestProblem,
        Team $team,
        Language $language,
        Testcase $testcase,
        string $externalId,
        string $submittime,
        string $result,
    ): Submission {
        $submission = (new Submission())
            ->setExternalid($externalId)
            ->setContest($contest)
            ->setTeam($team)
            ->setContestProblem($contestProblem)
            ->setLanguage($language)
            ->setSubmittime(Utils::toEpochFloat($submittime));
        $judging = (new Judging())
            ->setContest($contest)
            ->setStarttime(Utils::toEpochFloat($submittime))
            ->setEndtime(Utils::toEpochFloat($submittime) + 5)
            ->setValid(true)
            ->setSubmission($submission)
            ->setResult($result);
        $judgingRun = (new JudgingRun())
            ->setJudging($judging)
            ->setTestcase($testcase)
            ->setStarttime(Utils::toEpochFloat($submittime))
            ->setEndtime(Utils::toEpochFloat($submittime) + 1)
            ->setRunresult($result)
            ->setRuntime(0.01);

        $submission->addJudging($judging);
        $judging->addRun($judgingRun);
        $testcase->addJudgingRun($judgingRun);
        $manager->persist($submission);
        $manager->persist($judging);
        $manager->persist($judgingRun);

        return $submission;
    }
}
