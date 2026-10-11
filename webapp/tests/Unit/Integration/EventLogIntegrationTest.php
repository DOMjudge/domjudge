<?php declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\Team;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventLogIntegrationTest extends KernelTestCase
{
    public function testLoggingEventDoesNotDetachManagedEntities(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var EventLogService $eventLogService */
        $eventLogService = $container->get(EventLogService::class);

        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $team = $em->getRepository(Team::class)->findOneBy(['name' => 'Example teamname']);
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);

        self::assertNotNull($contest);
        self::assertNotNull($team);
        self::assertNotNull($problem);

        self::assertTrue($em->contains($contest), 'Contest should be managed before logging');
        self::assertTrue($em->contains($team), 'Team should be managed before logging');
        self::assertTrue($em->contains($problem), 'Problem should be managed before logging');

        // Log an update event for the team
        $eventLogService->log(
            'teams',
            $team->getTeamid(),
            EventLogService::ACTION_UPDATE,
            $contest->getCid()
        );

        // Before Phase 1, internalApiRequest called $em->clear(), which detached all entities.
        // With HINT_REFRESH, the entities MUST remain managed in Doctrine's identity map.
        self::assertTrue($em->contains($contest), 'Contest should remain managed after logging');
        self::assertTrue($em->contains($team), 'Team should remain managed after logging');
        self::assertTrue($em->contains($problem), 'Problem should remain managed after logging');
    }
}
