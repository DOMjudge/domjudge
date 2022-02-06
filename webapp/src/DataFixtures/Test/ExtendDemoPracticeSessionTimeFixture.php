<?php declare(strict_types=1);

namespace App\DataFixtures\Test;

use App\Entity\Contest;
use DateTime;
use Doctrine\Persistence\ObjectManager;

class ExtendDemoPracticeSessionTimeFixture extends AbstractTestDataFixture
{
    public function load(ObjectManager $manager): void
    {
        // Make sure the demo practice contest is still running
        /** @var Contest $demoPracticeContest */
        $demoPracticeContest = $manager->getRepository(Contest::class)->findOneBy(['shortname' => 'demoprac']);
        $demoPracticeContest
            ->setEndtimeString((new DateTime('+1 day'))->format('Y-m-d H:i:s'))
            ->setDeactivatetimeString((new DateTime('+2 days'))->format('Y-m-d H:i:s'));

        $manager->flush();
    }
}
