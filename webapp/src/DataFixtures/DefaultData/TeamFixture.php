<?php declare(strict_types=1);

namespace App\DataFixtures\DefaultData;

use App\Entity\Team;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TeamFixture extends AbstractDefaultDataFixture implements DependentFixtureInterface
{
    public const DOMJUDGE_REFERENCE = 'domjudge';

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        if (!($team = $manager->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']))) {
            $team = (new Team())
                ->setName('DOMjudge')
                ->setCategory($this->getReference(TeamCategoryFixture::SYSTEM_REFERENCE));
            $manager->persist($team);
        }
        $manager->flush();

        $this->addReference(self::DOMJUDGE_REFERENCE, $team);
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [TeamCategoryFixture::class];
    }
}
