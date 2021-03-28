<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\User;
use App\Utils\Utils;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RejudgingStatesFixture extends AbstractTestDataFixture
{
    public function rejudgingStages(): array
    {
        $rejudgingStages = [
            ['Unit',NULL,0],
            ['0Percent_1',NULL,1],
            ['0Percent_2',NULL,2],
            ['Finished',True,0],
            ['Canceled',False,0],
        ];

        return $rejudgingStages;
    }

    public function load(ObjectManager $manager)
    {
        /** @var Contest $contest */
        $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        /** @var User $user */
        $user = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        foreach ($this->rejudgingStages() as $index => $rejudgingStage) {
            $rejudging = (new Rejudging())
                ->setStarttime(Utils::toEpochFloat('2019-01-01 07:07:07'))
                ->setStartUser($user)
                ->setAutoApply(False)
                ->setReason($rejudgingStage[0]);
            if ($rejudgingStage[1] !== NULL) {
                $rejudging->setValid($rejudgingStage[1]);
                $rejudging->setEndtime(Utils::toEpochFloat('2019-01-02 07:07:07'))
                    ->setFinishUser($user);
            }
            $manager->persist($rejudging);
            $manager->flush();
            for ($x = 0; $x < $rejudgingStage[2]; $x++) {
                $submission = (new Submission())
                    ->setSubmittime(Utils::toEpochFloat('2018-01-02 07:07:07'))
                    ->setRejudging($rejudging);
                $judging = (new Judging())
                    ->setContest($contest)
                    ->setSubmission($submission)
                    ->setValid(False)
                    ->setRejudging($rejudging);
                $manager->persist($judging);
                $manager->persist($submission);
                $manager->flush();
            }
        }
    }
}
