<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EnableJavaEntrypointFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager)
    {
        $java = $manager->getRepository(Language::class)->find('java');
        $java->setRequireEntryPoint(true);
        $manager->flush();
    }
}
