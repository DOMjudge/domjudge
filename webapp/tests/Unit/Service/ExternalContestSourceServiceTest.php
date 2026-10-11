<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Contest;
use App\Entity\ExternalContestSourceType;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Service\ExternalContestSourceService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

class ExternalContestSourceServiceTest extends BaseTestCase
{
    private string $packageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->packageDir = sys_get_temp_dir() . '/shadow-package-' . uniqid();
        mkdir($this->packageDir);
        file_put_contents(
            $this->packageDir . '/contest.json',
            '{"id": "demo", "name": "Demo contest", "duration": "5:00:00", "penalty_time": 20}'
        );
        touch($this->packageDir . '/event-feed.ndjson');
    }

    protected function tearDown(): void
    {
        unlink($this->packageDir . '/contest.json');
        unlink($this->packageDir . '/event-feed.ndjson');
        rmdir($this->packageDir);
        parent::tearDown();
    }

    #[DataProvider('provideOpenToAllTeams')]
    public function testShadowTeamForAccount(bool $openToAllTeams): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var Contest $contest */
        $contest = $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo']);
        $contest
            ->setOpenToAllTeams($openToAllTeams)
            ->setExternalSourceEnabled(true)
            ->setExternalSourceType(ExternalContestSourceType::CONTEST_PACKAGE)
            ->setExternalSourceSource($this->packageDir);
        $em->flush();

        $service = static::getContainer()->get(ExternalContestSourceService::class);
        $service->setSourceContest($contest);
        $getShadowTeam = new ReflectionMethod($service, 'getShadowTeamForAccount');

        /** @var Team $team */
        $team = $getShadowTeam->invoke($service, 'jury1');
        self::assertSame('shadow_team_jury1', $team->getExternalid());
        self::assertCount(1, $team->getCategories());
        $category = $team->getCategories()->first();
        self::assertSame('shadowing', $category->getExternalid());
        self::assertSame(255, $category->getSortorder());
        self::assertFalse($category->getVisible());
        self::assertTrue($category->hasType(TeamCategory::TYPE_SCORING));

        self::assertSame(!$openToAllTeams, $contest->getTeams()->contains($team));
        // Nothing may clear the entity manager, since the submission import continues with its entities.
        self::assertTrue($em->contains($contest));

        // The team and category are reused for later submissions.
        self::assertSame($team->getTeamid(), $getShadowTeam->invoke($service, 'jury1')->getTeamid());
        $otherTeam = $getShadowTeam->invoke($service, 'jury2');
        self::assertNotSame($team->getTeamid(), $otherTeam->getTeamid());
        self::assertSame($category->getCategoryid(), $otherTeam->getCategories()->first()->getCategoryid());
    }

    public static function provideOpenToAllTeams(): Generator
    {
        yield 'open to all teams' => [true];
        yield 'only selected teams' => [false];
    }
}
