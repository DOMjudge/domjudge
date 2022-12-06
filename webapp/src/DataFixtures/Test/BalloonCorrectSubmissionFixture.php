<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Balloon;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class BalloonCorrectSubmissionFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $submissionData = [
            // team name,         language, submittime,            entry point, result
            ['Example teamname',  'cpp',    '2021-01-01 12:34:56', null,        'timelimit'],
            ['Example teamname',  'java',   '2021-01-02 12:00:00', 'Main',      'wrong-answer'],
            ['Example teamname',  'c',      '2021-01-04 12:34:56', null,        'correct'],
        ];

        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'beforeFreeze']);
        
        /** @var Problem $problemA */
        $problemA = new Problem();
        $problemA->setName('U');
        /** @var ContestProblem $cp */
        $cp = new ContestProblem();
        $cp->setShortname('U')
           ->setProblem($problemA)
           ->setColor("#000000")
           ->setContest($contest);
        $manager->persist($problemA);
        $manager->persist($cp);
        foreach ($submissionData as $index => $submissionItem) {
            $submission = (new Submission())
                ->setContest($contest)
                ->setTeam($manager->getRepository(Team::class)->findOneBy(['name' => $submissionItem[0]]))
                ->setContestProblem($cp)
                ->setLanguage($manager->getRepository(Language::class)->find($submissionItem[1]))
                ->setSubmittime(Utils::now()-2)
                ->setEntryPoint($submissionItem[3]);
            $judging = (new Judging())
                ->setContest($contest)
                ->setStarttime(Utils::now()-1)
                ->setEndtime(Utils::now())
                ->setValid(true)
                ->setSubmission($submission)
                ->setResult($submissionItem[4]);
            if ($submissionItem[4] === 'correct') {
                /** @var Balloon $balloon */
                $balloon = new Balloon();
                $balloon->setSubmission($submission)
                        ->setDone(false);
                $manager->persist($balloon);
            }
            $submission->addJudging($judging);
            $manager->persist($submission);
            $manager->persist($judging);
            $manager->flush();
            // Add a reference, since the submission ID changes during testing because of the auto increment
            $this->addReference(sprintf('%s:%d', static::class, $index), $submission);
        }
    }
}
