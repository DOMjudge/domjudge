<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Rejudging;
use App\Entity\User;
use App\Utils\Utils;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RejudgingStatesFixture extends Fixture
{
    public function rejudgingStages(): array
    {
        $rejudgingStages = [
            ['Unit',NULL],
            ['Finished',True],
            ['Canceled',False],
        ];

        return $rejudgingStages;
    }

    public function load(ObjectManager $manager)
    {
        /** @var User $user */
        $user = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        foreach ($this->rejudgingStages() as $index => $rejudgingStage) {
            $rejudging = (new Rejudging())
                ->setStarttime(Utils::toEpochFloat('2019-01-01 07:07:07'))
                ->setStartUser($user)
                ->setReason($rejudgingStage[0]);
            if ($rejudgingStage[1] !== NULL) {
                $rejudging->setValid($rejudgingStage[1]);
                $rejudging->setEndtime(Utils::toEpochFloat('2019-01-02 07:07:07'))
                    ->setFinishUser($user);
            }
            $manager->persist($rejudging);
            $manager->flush();
        }
    }
}
