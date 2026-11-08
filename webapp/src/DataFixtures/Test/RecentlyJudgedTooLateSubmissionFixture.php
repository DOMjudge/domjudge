<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\Team;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

/**
 * Two submissions of the demo team that were judged just now: one made just before
 * the end of the demo contest and one made just after it.
 */
class RecentlyJudgedTooLateSubmissionFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $manager->getRepository(Team::class)->findOneBy(['externalid' => 'exteam']);
        $contestProblem = $contest->getProblems()->first();
        $language = $manager->getRepository(Language::class)->findByExternalId('cpp');

        foreach ([-60, 60] as $index => $offset) {
            $submission = (new Submission())
                ->setContest($contest)
                ->setTeam($team)
                ->setContestProblem($contestProblem)
                ->setLanguage($language)
                ->setSubmittime((float)$contest->getEndtime() + $offset);
            $judging = (new Judging())
                ->setContest($contest)
                ->setStarttime(Utils::now() - 1)
                ->setEndtime(Utils::now())
                ->setValid(true)
                ->setSubmission($submission)
                ->setResult('wrong-answer');
            $submission->addJudging($judging);
            $manager->persist($submission);
            $manager->persist($judging);
            $this->addReference(sprintf('%s:%d', static::class, $index), $submission);
        }
        $manager->flush();
    }
}
