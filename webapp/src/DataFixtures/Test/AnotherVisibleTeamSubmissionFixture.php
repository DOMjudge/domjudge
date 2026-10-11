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

/**
 * Creates a second visible team with a judged submission, intended for tests that need to
 * exercise cross-team visibility rules (e.g. judgement_type_id redaction).
 */
class AnotherVisibleTeamSubmissionFixture extends AbstractTestDataFixture
{
    final public const SUBMISSION_REFERENCE = 'another-visible-team-submission';
    final public const TEAM_REFERENCE = 'another-visible-team';

    public function load(ObjectManager $manager): void
    {
        $exampleTeam = $manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $otherTeam = (new Team())
            ->setName('Another visible team')
            ->setExternalid('othervisibleteam');
        foreach ($exampleTeam->getCategories() as $category) {
            $otherTeam->addCategory($category);
        }
        $manager->persist($otherTeam);

        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $problem = $contest->getProblems()
            ->filter(fn(ContestProblem $cp) => $cp->getShortname() === 'B')
            ->first();
        $language = $manager->getRepository(Language::class)->findByExternalId('cpp');

        $submittime = Utils::toEpochFloat('2021-02-15 10:00:00');
        $submission = (new Submission())
            ->setContest($contest)
            ->setTeam($otherTeam)
            ->setContestProblem($problem)
            ->setLanguage($language)
            ->setValid(true)
            ->setSubmittime($submittime);
        $judging = (new Judging())
            ->setContest($contest)
            ->setSubmission($submission)
            ->setStarttime($submittime)
            ->setEndtime($submittime + 5)
            ->setValid(true)
            ->setResult('wrong-answer');
        $submission->addJudging($judging);
        $manager->persist($submission);
        $manager->persist($judging);
        $manager->flush();

        $this->addReference(self::TEAM_REFERENCE, $otherTeam);
        $this->addReference(self::SUBMISSION_REFERENCE, $submission);
    }
}
