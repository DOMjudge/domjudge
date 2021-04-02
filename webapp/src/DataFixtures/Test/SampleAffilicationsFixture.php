<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\TeamAffiliation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SampleAffilicationsFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $affiliationData = [
            // short name, name,                                                country
            ['FAU',        'Friedrich-Alexander-Universität Erlangen-Nürnberg', 'DEU'],
            ['ABC',        'Affiliation without country',                        null],
        ];

        foreach ($affiliationData as $index => $affiliationItem) {
            $affiliation = (new TeamAffiliation())
                ->setShortname($affiliationItem[0])
                ->setName($affiliationItem[1])
                ->setCountry($affiliationItem[2]);
            $manager->persist($affiliation);
            $manager->flush();
            // Add a reference, since the submission ID changes during testing because of the auto increment
            $this->addReference(sprintf('%s:%d', static::class, $index), $affiliation);
        }
    }
}
