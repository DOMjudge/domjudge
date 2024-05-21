<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Team;
use Doctrine\Persistence\ObjectManager;

class TeamWithExternalIdEqualsOneFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $team = $manager->getRepository(Team::class)->findOneBy(['name' => 'DOMjudge']);
        $team->setExternalid('1');
        $manager->flush();
    }
}
