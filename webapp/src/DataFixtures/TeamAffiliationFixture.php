<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TeamAffiliation;
use Doctrine\Persistence\ObjectManager;

/**
 * Class TeamAffiliationFixture
 * @package App\DataFixtures
 */
class TeamAffiliationFixture extends AbstractExampleDataFixture
{
    public const AFFILIATION_REFERENCE = 'affiliation';

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
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
