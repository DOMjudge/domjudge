<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\TeamAffiliation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SampleAffiliationsFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $affiliationData = [
            // short name, name,                                                country, ICPC ID
            ['FAU',        'Friedrich-Alexander-Universität Erlangen-Nürnberg', 'DEU',   '1234'],
            ['ABC',        'Affiliation without country',                        null,   'abc-icpc-id'],
        ];

        foreach ($affiliationData as $index => $affiliationItem) {
            $affiliation = (new TeamAffiliation())
                ->setExternalid($affiliationItem[0])
                ->setShortname($affiliationItem[0])
                ->setName($affiliationItem[1])
                ->setCountry($affiliationItem[2])
                ->setIcpcid($affiliationItem[3]);
            $manager->persist($affiliation);
            $manager->flush();
            // Add a reference, since the submission ID changes during testing because of the auto increment
            $this->addReference(sprintf('%s:%d', static::class, $index), $affiliation);
        }
    }
}
