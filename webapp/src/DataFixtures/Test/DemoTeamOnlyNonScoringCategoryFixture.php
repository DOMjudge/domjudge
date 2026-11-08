<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Team;
use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

/**
 * Puts the team of the demo user only in a visible category that is not a
 * scoring category, so it is not on the scoreboard but can still submit.
 */
class DemoTeamOnlyNonScoringCategoryFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $category = (new TeamCategory())
            ->setName('Badge only')
            ->setExternalid('badgeonly')
            ->setTypes([TeamCategory::TYPE_BADGE_TOP])
            ->setVisible(true);
        $manager->persist($category);

        $team = $manager->getRepository(Team::class)->findOneBy(['externalid' => 'exteam']);
        foreach ($team->getCategories() as $oldCategory) {
            $team->removeCategory($oldCategory);
        }
        $team->addCategory($category);
        $manager->flush();
    }
}
