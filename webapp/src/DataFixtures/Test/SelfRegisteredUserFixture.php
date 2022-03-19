<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\Team;
use Doctrine\Persistence\ObjectManager;

class SelfRegisteredUserFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user
            ->setUsername('selfregister')
            ->setName('selfregistered user for example team')
            ->setEmail('electronic@mail.tld')
            ->setPlainPassword('demo')
            ->setTeam($manager->getRepository(Team::class)->findOneBy(['name' => 'exteam']))
            ->addUserRole($manager->getRepository(Role::class)->findOneBy(['dj_role' => 'team']));

        $manager->persist($user);
        $manager->flush();
    }
}
