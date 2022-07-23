<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class SampleSubmissionsInBucketsFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        // First, set the demo contest to have normal timing. We also set (un)freeze time,
        // but the test will unset them for specific cases
        $demoContest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $demoContest
            ->setStarttimeString('2022-01-01 00:00:00 Europe/Amsterdam')
            ->setFreezetimeString('2022-01-01 04:05:00 Europe/Amsterdam')
            ->setEndtimeString('2022-01-01 05:00:00 Europe/Amsterdam')
            ->setUnfreezetime(sprintf('%d-01-01 05:00:00 Europe/Amsterdam', date('Y') + 1));
        $manager->flush();

        // Now add some submissions:
        // * One correct and one incorrect one before the freeze
        // * One correct and one incorrect one just before the freeze
        // * One correct and one incorrect at the end of the freeze

        $submissionData = [
            ['2022-01-01 02:00:00 Europe/Amsterdam', true],
            ['2022-01-01 03:00:00 Europe/Amsterdam', false],
            ['2022-01-01 04:03:00 Europe/Amsterdam', true],
            ['2022-01-01 04:03:00 Europe/Amsterdam', false],
            ['2022-01-01 04:40:00 Europe/Amsterdam', true],
            ['2022-01-01 04:50:00 Europe/Amsterdam', false],
        ];
        foreach ($submissionData as [$time, $correct]) {
            $problem    = $demoContest->getProblems()->first();
            $submission = (new Submission())
                ->setContest($demoContest)
                ->setTeam($manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']))
                ->setContestProblem($problem)
                ->setLanguage($manager->getRepository(Language::class)->find('cpp'))
                ->setSubmittime(Utils::toEpochFloat($time));
            $judging    = (new Judging())
                ->setContest($demoContest)
                ->setStarttime(Utils::toEpochFloat($time))
                ->setEndtime(Utils::toEpochFloat($time) + 5)
                ->setValid(true)
                ->setSubmission($submission)
                ->setResult($correct ? 'correct' : 'timelimit');
            $submission->addJudging($judging);
            $manager->persist($submission);
            $manager->persist($judging);
            $manager->flush();
        }
    }
}
