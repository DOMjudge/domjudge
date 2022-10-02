<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Rejudging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\User;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class RejudgingStatesFixture extends AbstractTestDataFixture
{
    public static function rejudgingStages(): array
    {
        $rejudgingStages = [];
        $rejudgingStages[] = ['Unit',null,0,1,['demo']];
        $rejudgingStages[] = ['0Percent_1',null,1,0,['demo']];
        $rejudgingStages[] = ['0Percent_2',null,2,0,['demo']];
        $rejudgingStages[] = ['Finished',true,1,0,['demo']];
        $rejudgingStages[] = ['Canceled',false,2,0,['demo']];
        return $rejudgingStages;
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User $user */
        $user = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        foreach ($this->rejudgingStages() as $index => $rejudgingStage) {
            $rejudging = (new Rejudging())
                ->setStarttime(Utils::toEpochFloat('2019-01-01 07:07:07'))
                ->setStartUser($user)
                ->setAutoApply(false)
                ->setReason($rejudgingStage[0]);
            // Rejudgings can already be finished
            if ($rejudgingStage[1] !== null) {
                $rejudging->setValid($rejudgingStage[1]);
                $rejudging->setEndtime(Utils::toEpochFloat('2019-01-02 07:07:07'))
                    ->setFinishUser($user);
            }
            $manager->persist($rejudging);
            $manager->flush();
            // One rejudging can consist of submissions of multiple contests
            foreach ($rejudgingStage[4] as $contestName) {
                /** @var Contest $contest */
                $contest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => $contestName]);
                /** @var Team $team */
                $team = $manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
                /** @var Language $language */
                $language = $manager->getRepository(Language::class)->find('java');
                // A rejudging has both judgings todo and finished
                for ($b = 0; $b<=1; $b++) {
                    for ($x = 0; $x < $rejudgingStage[2+$b]; $x++) {
                        $submission = (new Submission())
                            ->setSubmittime(Utils::toEpochFloat('2018-01-02 07:07:07'))
                            ->setRejudging($rejudging)
                            ->setContest($contest)
                            ->setTeam($team)
                            ->setLanguage($language);
                        if ($contest !== null) {
                            $submission->setContestProblem($contest->getProblems()->first());
                        }
                        $manager->persist($submission);
                        $manager->flush();
                        $judging = (new Judging())
                            ->setSubmission($submission)
                            ->setValid(false)
                            ->setContest($contest)
                            ->setRejudging($rejudging);
                        // Finished judging
                        if ($b === 1) {
                            $judging = $judging->setEndtime(Utils::toEpochFloat('2018-01-02 07:07:07'))
                                ->setResult("wrong-answer")
                                ->setStarttime(Utils::toEpochFloat('2018-01-02 07:07:07'));
                        }
                        $manager->persist($judging);
                        $manager->flush();
                    }
                }
            }
        }
    }
}
