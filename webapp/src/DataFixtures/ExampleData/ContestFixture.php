<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Contest;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ContestFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    public const PRACTICE_REFERENCE = 'practice';
    public const DEMO_REFERENCE     = 'demo';

    public function load(ObjectManager $manager): void
    {
        $demoPracticeContest = new Contest();
        $demoPracticeContest
            ->setExternalid('demoprac')
            ->setName('Demo practice session')
            ->setShortname('demoprac')
            ->setStarttimeString(
                sprintf(
                    '%s-01-01 09:00:00 Europe/Amsterdam',
                    date('Y') - 1
                )
            )
            ->setActivatetimeString('-01:00')
            ->setEndtimeString('+02:00')
            ->setDeactivatetimeString('+06:00')
            ->setPublic(false)
            ->setOpenToAllTeams(false)
            ->addTeam($this->getReference(TeamFixture::TEAM_REFERENCE))
            ->addMedalCategory($this->getReference(TeamCategoryFixture::PARTICIPANTS_REFERENCE));

        $demoContest = new Contest();
        $demoContest
            ->setExternalid('demo')
            ->setName('Demo contest')
            ->setShortname('demo')
            ->setStarttimeString(
                sprintf(
                    '%s-01-01 12:00:00 Europe/Amsterdam',
                    date('Y') -1
                )
            )
            ->setActivatetimeString('-00:30:00.123')
            ->setFreezetimeString(
                sprintf(
                    '%s-01-01 16:00:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            )
            ->setEndtimeString(
                sprintf(
                    '%s-01-01 17:00:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            )
            ->setUnfreezetimeString(
                sprintf(
                    '%s-01-01 17:30:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            )
            ->setDeactivatetimeString(
                sprintf(
                    '%s-01-01 18:30:00 Europe/Amsterdam',
                    date('Y') + 2
                )
            )
            ->addMedalCategory($this->getReference(TeamCategoryFixture::PARTICIPANTS_REFERENCE));

        $manager->persist($demoPracticeContest);
        $manager->persist($demoContest);
        $manager->flush();

        $this->addReference(self::PRACTICE_REFERENCE, $demoPracticeContest);
        $this->addReference(self::DEMO_REFERENCE, $demoContest);
    }

    public function getDependencies(): array
    {
        return [TeamFixture::class, TeamCategoryFixture::class];
    }
}
