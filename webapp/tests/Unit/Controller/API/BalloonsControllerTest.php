<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use Generator;

class BalloonsControllerTest extends BaseTest
{
    /**
     * In the default test setup there are no judgings yet, so no balloons,
     */
    public function testBalloonsNoJudgings(): void
    {
        $contestId = $this->getDemoContestId();
        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/balloons", 200, 'admin');
        static::assertEquals([], $response);
    }

    public function testBalloonsNoJudgingsToDo(): void
    {
        $contestId = $this->getDemoContestId();
        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/balloons?todo=1", 200, 'admin');
        static::assertEquals([], $response);
    }

    /**
     * @dataProvider provideUnprivilegedUsers
     */
    public function testBalloonsAccessForPrivilegedUsersOnly(?string $user, int $result): void
    {
        $contestId = $this->getDemoContestId();
        $this->verifyApiJsonResponse('GET', "/contests/$contestId/balloons", $result, $user);
    }

    public function provideUnprivilegedUsers(): Generator
    {
        yield [null, 401];
        yield ['demo', 403];
    }
}
