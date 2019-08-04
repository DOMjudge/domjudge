<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Team;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class TeamFixture
 * @package App\DataFixtures
 */
class TeamFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    public const TEAM_REFERENCE = 'team';

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $team = new Team();
        $team
            ->setExternalid('exteam')
            ->setName('Example teamname')
            ->setAffiliation($this->getReference(TeamAffiliationFixture::AFFILIATION_REFERENCE))
            ->setCategory($this->getReference(TeamCategoryFixture::PARTICIPANTS_REFERENCE));

        $manager->persist($team);
        $manager->flush();

        $this->addReference(self::TEAM_REFERENCE, $team);
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [TeamAffiliationFixture::class, TeamCategoryFixture::class];
    }
}
