<?php declare(strict_types=1);

namespace App\DataFixtures\ExampleData;

use App\Entity\TeamAffiliation;
use Doctrine\Persistence\ObjectManager;

class TeamAffiliationFixture extends AbstractExampleDataFixture
{
    public const AFFILIATION_REFERENCE = 'affiliation';

    public function load(ObjectManager $manager): void
    {
        $affiliation = new TeamAffiliation();
        $affiliation
            ->setExternalid('utrecht')
            ->setShortname('UU')
            ->setName('Utrecht University')
            ->setCountry('NLD');

        $manager->persist($affiliation);
        $manager->flush();

        $this->addReference(self::AFFILIATION_REFERENCE, $affiliation);
    }
}
