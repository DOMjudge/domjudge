<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends AbstractExampleDataFixture implements DependentFixtureInterface
{
    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $user = new User();
        $user
            ->setUsername('demo')
            ->setName('demo user for example team')
            ->setPlainPassword('demo')
            ->setTeam($this->getReference(TeamFixture::TEAM_REFERENCE))
            ->addUserRole($manager->getRepository(Role::class)->findOneBy(['dj_role' => 'team']));

        $manager->persist($user);
        $manager->flush();
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [TeamFixture::class];
    }
}
