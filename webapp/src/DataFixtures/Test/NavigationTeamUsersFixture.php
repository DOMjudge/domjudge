<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class NavigationTeamUsersFixture extends AbstractTestDataFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        foreach (['nav-alpha', 'nav-beta', 'nav-gamma'] as $id) {
            $team = $manager->getRepository(Team::class)->findOneBy(['externalid' => $id]);
            $role = $manager->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);
            $user = new User();
            $user
                ->setExternalid($id . '-user')
                ->setUsername($id . '-user')
                ->setPlainPassword($id . '-user')
                ->setName(ucfirst($id) . ' User')
                ->setTeam($team)
                ->addUserRole($role);
            $manager->persist($user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [NavigationTeamsFixture::class];
    }
}
