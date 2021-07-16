<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\JudgingRunOutput;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class SampleSubmissionsFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $submissionData = [
            // team name,         problem shortname, language, submittime,            entry point, result
            ['DOMjudge',          'hello',           'cpp',    '2021-01-01 12:34:56', null,        'correct'],
            ['Example teamname',  'boolfind',        'java',   '2021-03-04 12:00:00', 'Main',      'wrong-answer'],
            ['Example teamname',  'fltcmp',          'java',   '2021-03-05 11:09:45', 'Main',      'wrong-answer'],
            ['Example teamname',  'fltcmp',          'java',   '2021-03-05 11:12:05', 'Main',      'wrong-answer'],
        ];

        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $judgehost = (new Judgehost)
            ->setHostname('fixture-judgehost');
        $manager->persist($judgehost);
        $manager->flush();
        foreach ($submissionData as $index => $submissionItem) {
            $problem = $contest->getProblems()->filter(function (ContestProblem $problem) use ($submissionItem) {
                return $problem->getShortname() === $submissionItem[1];
            })->first();
            $testCases = $problem->getProblem()->getTestcases();
            $submission = (new Submission())
                ->setContest($contest)
                ->setTeam($manager->getRepository(Team::class)->findOneBy(['name' => $submissionItem[0]]))
                ->setContestProblem($problem)
                ->setLanguage($manager->getRepository(Language::class)->find($submissionItem[2]))
                ->setSubmittime(Utils::toEpochFloat($submissionItem[3]))
                ->setEntryPoint($submissionItem[4]);
            $manager->persist($submission);
            $manager->flush();
            // Partially add the judging on the testcase, the judging_run_output is not filled.
            $judging = (new Judging())
                ->setContest($contest)
                ->setSubmission($submission)
                ->setStarttime(Utils::toEpochFloat($submissionItem[3]))
                ->setEndtime(Utils::toEpochFloat($submissionItem[3]) + count($testCases))
                ->setJudgehost($judgehost)
                ->setValid(true)
                ->setResult($submissionItem[5]);
            $manager->persist($judging);
            $manager->flush();
            foreach($testCases as $testCase) {
                $rank = $testCase->getRank();
                $judgeTask = (new JudgeTask())
                    ->setType('judging_run')
                    ->setTestcaseid($testCase->getTestcaseid())
                    ->setPriority(1)
                    ->setJobid($judging->getJudgingid())
                    ->setSubmitid($submission->getSubmitid());
                if ($submissionItem[5] === 'correct' || $rank === 1) {
                    // Judgetasks are not started when a non-correct result is found
                    $judgeTask = $judgeTask
                        ->setJudgehost($judgehost)
                        ->setValid(true)
                        ->setStarttime(Utils::toEpochFloat($submissionItem[3]) + $rank);
                } else {
                    $judgeTask = $judgeTask
                        ->setValid(false);
                }
                $manager->persist($judgeTask);
                $judgingRun = (new JudgingRun())
                    ->setJudging($judging)
                    ->setJudgeTask($judgeTask)
                    ->setTestcase($testCase);
                if ($submissionItem[5] === 'success' || $rank === 1) {
                    $judgingRun = $judgingRun
                        ->setRuntime(1)
                        ->setRunresult($submissionItem[5])
                        ->setEndtime(Utils::toEpochFloat($submissionItem[3]) + 1 + $testCase->getRank());
                    $judgingrun_out = (new JudgingRunOutput)
                        ->setRun($judgingRun)
                        ->setOutputRun("OUTPUT")
                        ->setOutputDiff("DIFF");
                    $manager->persist($judgingrun_out);
                }
                $manager->persist($judgingRun);
            }
            $manager->flush();
            // Add a reference, since the submission ID changes during testing because of the auto increment
            $this->addReference(sprintf('%s:%d', static::class, $index), $submission);
        }
    }
}
