<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Team;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TeamFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    public const TEAM_REFERENCE = 'team';

    public function load(ObjectManager $manager): void
    {
        $team = new Team();
        $team
            ->setExternalid('exteam')
            ->setIcpcid('exteam')
            ->setName('Example teamname')
            ->setAffiliation($this->getReference(TeamAffiliationFixture::AFFILIATION_REFERENCE))
            ->setCategory($this->getReference(TeamCategoryFixture::PARTICIPANTS_REFERENCE));

        $manager->persist($team);
        $manager->flush();

        $this->addReference(self::TEAM_REFERENCE, $team);
    }

    public function getDependencies(): array
    {
        return [TeamAffiliationFixture::class, TeamCategoryFixture::class];
    }
}
