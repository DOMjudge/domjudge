<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\DataFixtures\Test\DemoPreStartContestFixture;
use App\Tests\Unit\BaseTestCase;

class PublicClarificationVisibilityTest extends BaseTestCase
{
    public function testByProblemShowsProblem(): void
    {
        $this->verifyPageResponse('GET', '/public/clarifications/by-problem/hello', 200);
    }

    /**
     * Before the contest starts, the problems are not public, so their names must not leak.
     */
    public function testByProblemBeforeStartIsNotFound(): void
    {
        $this->loadFixture(DemoPreStartContestFixture::class);
        $this->verifyPageResponse('GET', '/public/clarifications/by-problem/hello', 404);
    }
}
