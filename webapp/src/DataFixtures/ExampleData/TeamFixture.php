<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TeamFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    final public const TEAM_REFERENCE = 'team';

    public function load(ObjectManager $manager): void
    {
        $team = new Team();
        $team
            ->setExternalid('exteam')
            ->setIcpcid('exteam')
            ->setLabel('exteam')
            ->setName('Example teamname')
            ->setAffiliation($this->getReference(TeamAffiliationFixture::AFFILIATION_REFERENCE, TeamAffiliation::class))
            ->addCategory($this->getReference(TeamCategoryFixture::PARTICIPANTS_REFERENCE, TeamCategory::class));

        $manager->persist($team);
        $manager->flush();

        $this->addReference(self::TEAM_REFERENCE, $team);
    }

    public function getDependencies(): array
    {
        return [TeamAffiliationFixture::class, TeamCategoryFixture::class];
    }
}
