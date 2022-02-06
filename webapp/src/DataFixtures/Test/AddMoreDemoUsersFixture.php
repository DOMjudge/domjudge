<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;

class AddMoreDemoUsersFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user
            ->setUsername('seconddemo')
            ->setName('second demo user for example team')
            ->setPlainPassword('seconddemo')
            ->setTeam($manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']))
            ->addUserRole($manager->getRepository(Role::class)->findOneBy(['dj_role' => 'team']));

        $manager->persist($user);
        $manager->flush();

        $this->addReference(static::class . ':seconddemo', $user);

        $user = new User();
        $user
            ->setUsername('thirddemo')
            ->setName('third demo user which is disabled')
            ->setPlainPassword('thirddemo')
            ->setEnabled(false);

        $manager->persist($user);
        $manager->flush();

        $this->addReference(static::class . ':thirddemo', $user);

        $user = new User();
        $user
            ->setUsername('fourthdemo')
            ->setName('fourth demo user not linked to any team')
            ->setPlainPassword('fourthdemo')
            ->addUserRole($manager->getRepository(Role::class)->findOneBy(['dj_role' => 'team']));

        $manager->persist($user);
        $manager->flush();

        $this->addReference(static::class . ':fourthdemo', $user);
    }
}
