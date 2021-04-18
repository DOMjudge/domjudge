<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EnableKotlinFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager)
    {
        $kotlin = $manager->getRepository(Language::class)->find('kt');
        $kotlin->setAllowSubmit(true);
        $manager->flush();
    }
}
