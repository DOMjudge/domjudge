<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Team;
use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

/**
 * Adds the team of the demo user to a second visible scoring category.
 */
class DemoTeamTwoScoringCategoriesFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $category = (new TeamCategory())
            ->setName('Second scoring category')
            ->setExternalid('secondscoring')
            ->setTypes([TeamCategory::TYPE_SCORING])
            ->setVisible(true);
        $manager->persist($category);

        $team = $manager->getRepository(Team::class)->findOneBy(['externalid' => 'exteam']);
        $team->addCategory($category);
        $manager->flush();
    }
}
