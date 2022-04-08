<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user
            ->setExternalid('demo')
            ->setUsername('demo')
            ->setName('demo user for example team')
            ->setPlainPassword('demo')
            ->setTeam($this->getReference(TeamFixture::TEAM_REFERENCE))
            ->addUserRole($manager->getRepository(Role::class)->findOneBy(['dj_role' => 'team']));

        $manager->persist($user);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TeamFixture::class];
    }
}
