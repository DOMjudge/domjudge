<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use App\Entity\Event;
use App\Utils\Utils;
use Doctrine\Persistence\ObjectManager;

class SampleEventsFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        $contests = $manager->getRepository(Contest::class)->findAll();
        foreach ($contests as $contest) {
            $event = (new Event())
                ->setContest($contest)
                ->setEndpointid((string)$contest->getCid())
                ->setEndpointtype('contest')
                ->setAction('create')
                ->setEventtime(Utils::now())
                // Note: we do not care about the actual contents, as long as we have some event
                ->setContent([]);

            $manager->persist($event);
        }
        $manager->flush();
    }
}
