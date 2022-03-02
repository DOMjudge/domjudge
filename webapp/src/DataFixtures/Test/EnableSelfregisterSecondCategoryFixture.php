<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

class EnableSelfregisterSecondCategoryFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $selfRegisterCategory = $manager->getRepository(TeamCategory::class)->findOneBy(['name' => 'Self-Registered']);
        $selfRegisterCategory->setAllowSelfRegistration(true);
        $manager->flush();
    }
}
