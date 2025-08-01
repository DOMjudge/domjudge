<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

class NonSortOrderTeamCategoryFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $category = (new TeamCategory())
            ->setName('Category for color')
            ->setExternalid('colorcat')
            ->setTypes([TeamCategory::TYPE_BACKGROUND])
            ->setColor('#123123');
        $manager->persist($category);
        $manager->flush();

        $this->addReference(sprintf('%s:%d', static::class, 0), $category);
    }
}
