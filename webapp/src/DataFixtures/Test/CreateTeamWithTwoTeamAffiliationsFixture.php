<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\DataFixtures\ExampleData\TeamAffiliationFixture;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

class CreateTeamWithTwoTeamAffiliationsFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $team = new Team();
        $team
            ->setExternalid('teamwithtwogroups')
            ->setIcpcid('teamwithtwogroups')
            ->setLabel('teamwithtwogroups')
            ->setName('Team with two groups')
            ->setAffiliation($manager->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => 'utrecht']))
            ->addCategory($manager->getRepository(TeamCategory::class)->findOneBy(['externalid' => 'participants']))
            ->addCategory($manager->getRepository(TeamCategory::class)->findOneBy(['externalid' => 'observers']));

        $manager->persist($team);
        $manager->flush();
    }
}
