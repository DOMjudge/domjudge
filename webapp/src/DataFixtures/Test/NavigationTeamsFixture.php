<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Team;
use App\Entity\TeamCategory;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use App\DataFixtures\DefaultData\TeamCategoryFixture;

class NavigationTeamsFixture extends AbstractTestDataFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $category = $manager->getRepository(TeamCategory::class)->findOneBy(['name' => 'Participants']);

        foreach (['nav-alpha', 'nav-beta', 'nav-gamma'] as $id) {
            $team = new Team();
            $team
                ->setExternalid($id)
                ->setName(ucfirst($id) . ' Team')
                ->addCategory($category);
            $manager->persist($team);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TeamCategoryFixture::class];
    }
}
