<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

class SampleTeamsFixture extends AbstractTestDataFixture
{
    final public const FIRST_TEAM_REFERENCE = 'team1';
    final public const SECOND_TEAM_REFERENCE = 'team2';

    public function load(ObjectManager $manager): void
    {
        $affiliation = $manager->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => 'utrecht']);
        $category = $manager->getRepository(TeamCategory::class)->findOneBy(['externalid' => 'participants']);

        $team1 = new Team();
        $team1
            ->setExternalid('team1')
            ->setIcpcid('team1')
            ->setLabel('team1')
            ->setName('Team 1')
            ->setAffiliation($affiliation)
            ->setCategory($category);

        $team2 = new Team();
        $team2
            ->setExternalid('team2')
            ->setIcpcid('team2')
            ->setLabel('team2')
            ->setName('Team 2')
            ->setAffiliation($affiliation)
            ->setCategory($category);

        $manager->persist($team1);
        $manager->persist($team2);
        $manager->flush();

        $this->addReference(self::FIRST_TEAM_REFERENCE, $team1);
        $this->addReference(self::SECOND_TEAM_REFERENCE, $team2);
    }
}
