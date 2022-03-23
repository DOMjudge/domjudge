<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Problem;
use Doctrine\Persistence\ObjectManager;

class DummyProblemFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $problem = (new Problem())
            ->setName('Dummy problem')
            ->setTimelimit(2);
        $manager->persist($problem);
        $manager->flush();

        $this->addReference(sprintf('%s:%d', static::class, 0), $problem);
    }
}
