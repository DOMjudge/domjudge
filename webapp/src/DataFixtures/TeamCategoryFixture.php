<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TeamCategory;
use Doctrine\Persistence\ObjectManager;

class TeamCategoryFixture extends AbstractExampleDataFixture
{
    public const PARTICIPANTS_REFERENCE = 'participants';

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $participants = new TeamCategory();
        $participants->setName('Participants');

        $observers = new TeamCategory();
        $observers
            ->setName('Observers')
            ->setSortorder(1)
            ->setColor('#ffcc33');

        $organisation = new TeamCategory();
        $organisation
            ->setName('Organisation')
            ->setSortorder(1)
            ->setColor('#ff99cc')
            ->setVisible(false);

        $manager->persist($participants);
        $manager->persist($observers);
        $manager->persist($organisation);
        $manager->flush();

        $this->addReference(self::PARTICIPANTS_REFERENCE, $participants);
    }
}
