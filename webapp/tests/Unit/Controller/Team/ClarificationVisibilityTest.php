<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\DataFixtures\Test\ClarificationWithFutureReplyFixture;
use App\DataFixtures\Test\DemoPreStartContestFixture;
use App\DataFixtures\Test\HelloProblemNotSubmittableFixture;
use App\Entity\Clarification;
use App\Tests\Unit\BaseTestCase;

class ClarificationVisibilityTest extends BaseTestCase
{
    protected array $roles = ['team'];

    public function testByProblemShowsProblem(): void
    {
        $this->verifyPageResponse('GET', '/team/clarifications/by-problem/hello', 200);
    }

    /**
     * Before the contest starts, the problems are not public, so their names must not leak.
     */
    public function testByProblemBeforeStartIsNotFound(): void
    {
        $this->loadFixture(DemoPreStartContestFixture::class);
        $this->verifyPageResponse('GET', '/team/clarifications/by-problem/hello', 404);
    }

    public function testByProblemForNonSubmittableProblemIsNotFound(): void
    {
        $this->loadFixture(HelloProblemNotSubmittableFixture::class);
        $this->verifyPageResponse('GET', '/team/clarifications/by-problem/hello', 404);
    }

    public function testFutureReplyIsNotShown(): void
    {
        $this->loadFixture(ClarificationWithFutureReplyFixture::class);
        $clarId = $this->resolveReference(ClarificationWithFutureReplyFixture::class . ':0', Clarification::class, true);

        $this->verifyPageResponse('GET', "/team/clarifications/$clarId", 200);
        $content = $this->client->getResponse()->getContent();
        static::assertStringContainsString('Reply sent already', $content);
        static::assertStringNotContainsString('Reply from the future', $content);
    }
}
