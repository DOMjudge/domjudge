<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\User;
use Doctrine\Persistence\ObjectManager;

class RemoveTeamFromAdminUserFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $user = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $user->setTeam();
        $manager->flush();
    }
}
