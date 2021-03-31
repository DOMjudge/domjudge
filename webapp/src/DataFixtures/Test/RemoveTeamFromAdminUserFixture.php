<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RemoveTeamFromAdminUserFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $user = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $user->setTeam();
        $manager->flush();
    }
}
