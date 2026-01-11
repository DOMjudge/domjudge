<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\DataFixtures\Test\NavigationTeamsFixture;
use App\Entity\Team;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpKernel\KernelInterface;

class BaseControllerTest extends BaseTestCase
{
    protected static array $fixtures = [NavigationTeamsFixture::class];

    private TestableBaseController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $container = static::getContainer();

        $this->controller = new TestableBaseController(
            $container->get(EntityManagerInterface::class),
            $container->get(EventLogService::class),
            $container->get(DOMJudgeService::class),
            $container->get(KernelInterface::class),
        );
    }

    /**
     * @dataProvider provideNavigationCases
     */
    public function testGetPreviousAndNextObjectIds(
        string $currentId,
        array $orderBy,
        ?string $expectedPrevious,
        ?string $expectedNext
    ): void {
        $result = $this->controller->exposedGetPreviousAndNextObjectIds(
            Team::class,
            $currentId,
            'externalid',
            $orderBy
        );

        self::assertEquals($expectedPrevious, $result['previous']);
        self::assertEquals($expectedNext, $result['next']);
    }

    public function provideNavigationCases(): Generator
    {
        yield 'first element ASC' => ['domjudge', ['e.externalid' => 'ASC'], null, 'exteam'];
        yield 'second element ASC' => ['exteam', ['e.externalid' => 'ASC'], 'domjudge', 'nav-alpha'];
        yield 'third element ASC' => ['nav-beta', ['e.externalid' => 'ASC'], 'nav-alpha', 'nav-gamma'];
        yield 'last element ASC' => ['nav-gamma', ['e.externalid' => 'ASC'], 'nav-beta', null];

        yield 'first element DESC' => ['nav-gamma', ['e.externalid' => 'DESC'], null, 'nav-beta'];
        yield 'second element DESC' => ['nav-alpha', ['e.externalid' => 'DESC'], 'nav-beta', 'exteam'];
        yield 'third element DESC' => ['exteam', ['e.externalid' => 'DESC'], 'nav-alpha', 'domjudge'];
        yield 'last element DESC' => ['domjudge', ['e.externalid' => 'DESC'], 'exteam', null];
    }

    public function testNonExistentEntityReturnsNulls(): void
    {
        $result = $this->controller->exposedGetPreviousAndNextObjectIds(
            Team::class,
            'non-existent-id'
        );

        self::assertNull($result['previous']);
        self::assertNull($result['next']);
    }
}
