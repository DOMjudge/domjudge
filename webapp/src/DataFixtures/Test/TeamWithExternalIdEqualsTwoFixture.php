<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Team;
use Doctrine\Persistence\ObjectManager;

class TeamWithExternalIdEqualsTwoFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $team = $manager->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $team->setExternalid('2');
        $manager->flush();
    }
}
