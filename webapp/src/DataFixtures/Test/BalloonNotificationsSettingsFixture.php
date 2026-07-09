<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\DataFixtures\ExampleData\TeamAffiliationFixture;
use App\Entity\Balloon;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class BalloonNotificationsSettingsFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'beforeUnfreeze']);

        $affiliation = (new TeamAffiliation())
            ->setExternalid('balloontester')
            ->setShortname('BT')
            ->setName('Balloon Tester')
            ->setCountry('NLD');

        $manager->persist($affiliation);

        // Setup two teams, and two problems, one of these problems must be solved before the freeze,
        // while the other is only solved after the freeze.

        // Create three categories, two sharing the same short order.
        $cat1 = (new TeamCategory())
            ->setSortorder(1)
            ->setName('balloon cat1')
            ->setExternalid('balloon cat1');
        $cat2 = (new TeamCategory())
            ->setSortorder(1)
            ->setName('balloon cat2')
            ->setExternalid('balloon cat2');
        $cat3 = (new TeamCategory())
            ->setSortorder(2)
            ->setName('balloon cat3')
            ->setExternalid('balloon cat3');

        $manager->persist($cat1);
        $manager->persist($cat2);
        $manager->persist($cat3);

        $teamA = (new Team())
            ->setExternalid('exteam1')
            ->setIcpcid('exteam1')
            ->setLabel('exteama1')
            ->setName('Balloon team 1')
            ->setAffiliation($affiliation)
            ->addCategory($cat1);

        $teamB = (new Team())
            ->setExternalid('exteam2')
            ->setIcpcid('exteam2')
            ->setLabel('exteama2')
            ->setName('Balloon team 2')
            ->setAffiliation($affiliation)
            ->addCategory($cat2);

        $teamC = (new Team())
            ->setExternalid('exteam3')
            ->setIcpcid('exteam3')
            ->setLabel('exteama3')
            ->setName('Balloon team 3')
            ->setAffiliation($affiliation)
            ->addCategory($cat2);

        // Add one team in entirely different sort-order
        $teamD = (new Team())
            ->setExternalid('exteam4')
            ->setIcpcid('exteam4')
            ->setLabel('exteama4')
            ->setName('Balloon team 4')
            ->setAffiliation($affiliation)
            ->addCategory($cat3);

        $manager->persist($teamA);
        $manager->persist($teamB);
        $manager->persist($teamC);
        $manager->persist($teamD);

        // Create three problems that will be solved pre-freeze and one problem that will only be solved post-freeze, for teams in $cat.
        // The team in $cat2, will solve two problems before and two problems after the freeze.
        $preFreezeA = (new Problem())->setName("BalloonProblemA");
        $cpA = (new ContestProblem())
            ->setShortname('BA')
            ->setProblem($preFreezeA)
            ->setColor("#000000")
            ->setContest($contest);
        $manager->persist($preFreezeA);
        $manager->persist($cpA);

        $preFreezeB = (new Problem())->setName("BalloonProblemB");
        $cpB = (new ContestProblem())
            ->setShortname('BB')
            ->setProblem($preFreezeB)
            ->setColor("#000000")
            ->setContest($contest);
        $manager->persist($preFreezeB);
        $manager->persist($cpB);

        $preFreezeC = (new Problem())->setName("BalloonProblemC");
        $cpC = (new ContestProblem())
            ->setShortname('BC')
            ->setProblem($preFreezeC)
            ->setColor("#000000")
            ->setContest($contest);
        $manager->persist($preFreezeC);
        $manager->persist($cpC);

        $postFreeze = (new Problem())->setName("BalloonProblemD");
        $cpD = (new ContestProblem())
            ->setShortname('BD')
            ->setProblem($postFreeze)
            ->setColor("#000000")
            ->setContest($contest);
        $manager->persist($postFreeze);
        $manager->persist($cpD);

        $language = $manager->getRepository(Language::class)->findByExternalId('cpp');

        $freezeTime = $contest->getFreezetime();
        $preFreezeSubmitTime = $freezeTime - 2;
        $postFreezeSubmitTime = $freezeTime + 1;
        $submissionData = [
            // Team A submits all three 'pre-freeze problems', before the freeze
            // team, submittime,           problem,     contest problem,
            [$teamA, $preFreezeSubmitTime, $preFreezeA, $cpA],
            [$teamA, $preFreezeSubmitTime, $preFreezeB, $cpB],
            [$teamA, $preFreezeSubmitTime, $preFreezeC, $cpC],

            // Team B submits the final problem during the freeze.
            // team, submittime,            problem,     contest problem,
            [$teamB, $postFreezeSubmitTime, $postFreeze, $cpD],

            // All teams end up solving all problems.
            // team, submittime,            problem,     contest problem,
            [$teamA, $postFreezeSubmitTime, $postFreeze, $cpD],

            [$teamB, $postFreezeSubmitTime, $preFreezeA, $cpA],
            [$teamB, $postFreezeSubmitTime, $preFreezeB, $cpB],
            [$teamB, $postFreezeSubmitTime, $preFreezeC, $cpC],

            // Submit some problems at submit time to test boundary condition.
            [$teamC, $postFreezeSubmitTime, $preFreezeA, $cpA],
            [$teamC, $freezeTime, $preFreezeB, $cpB],
            [$teamC, $freezeTime, $preFreezeC, $cpC],
            [$teamC, $postFreezeSubmitTime, $postFreeze, $cpD],

            [$teamD, $preFreezeSubmitTime, $preFreezeA, $cpA],
            [$teamD, $preFreezeSubmitTime, $preFreezeB, $cpB],
            [$teamD, $postFreezeSubmitTime, $preFreezeC, $cpC],
            [$teamD, $postFreezeSubmitTime, $postFreeze, $cpD],
        ];

        foreach ($submissionData as $i => [$team, $submitTime, $problem, $cp]) {
            $submission = (new Submission())
                ->setContest($contest)
                ->setTeam($team)
                ->setContestProblem($cp)
                ->setLanguage($language)
                ->setSubmittime($submitTime);

            $balloon = (new Balloon())
                ->setSubmission($submission)
                ->setDone(false)
                ->setTeam($team)
                ->setContest($contest)
                ->setProblem($problem);

            $manager->persist($submission);
            $manager->persist($balloon);
        }

        $manager->flush();
    }
}
