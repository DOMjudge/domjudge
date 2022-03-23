<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\DummyProblemFixture;

class ProblemControllerTest extends BaseTest
{
    /* This tests with the anonymous user;
       for tests with the admin user see ProblemControllerAdminTest.
     */

    protected ?string $apiEndpoint = 'problems';

    protected array $expectedObjects = [
      3 => [
        "ordinal" => 0,
        "id" => "3",
        "short_name" => "boolfind",
        "label" => "boolfind",
        "time_limit" => 5,
        "externalid" => "boolfind",
        "name" => "Boolean switch search",
        "rgb" => "#008000",
        "color" => "green",
      ],
      2 => [
        "ordinal" => 1,
        "id" => "2",
        "short_name" => "fltcmp",
        "label" => "fltcmp",
        "time_limit" => 5,
        "externalid" => "fltcmp",
        "name" => "Float special compare test",
        "rgb" => "#CD5C5C",
        "color" => "indianred",
      ],
      1 => [
        "ordinal" => 2,
        "id" => "1",
        "short_name" => "hello",
        "label" => "hello",
        "time_limit" => 5,
        "externalid" => "hello",
        "name" => "Hello World",
        "rgb" => "#87CEEB",
        "color" => "skyblue",
      ],
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    public function testDeleteNotAllowed(): void
    {
        if ($this->apiUser === 'admin') {
            $this->markTestSkipped('Only run for non-admins.');
        }

        // Check that we can not delete the problem
        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/2';
        $this->verifyApiJsonResponse('DELETE', $url, 401, $this->apiUser);

        // Check that we still have three problems left
        $indexUrl = $this->helperGetEndpointURL($this->apiEndpoint);
        $problems = $this->verifyApiJsonResponse('GET', $indexUrl, 200, $this->apiUser);
        self::assertCount(3, $problems);
    }

    public function testAddNotAllowed(): void
    {
        if ($this->apiUser === 'admin') {
            $this->markTestSkipped('Only run for non-admins.');
        }

        $this->loadFixture(DummyProblemFixture::class);

        // Check that we can not add a problem
        $problemId = $this->resolveReference(DummyProblemFixture::class . ':0');
        $url = $this->helperGetEndpointURL($this->apiEndpoint) . '/' . $problemId;
        $this->verifyApiJsonResponse('DELETE', $url, 401, $this->apiUser, ['label' => 'dummy']);

        // Check that we still have three problems left
        $indexUrl = $this->helperGetEndpointURL($this->apiEndpoint);
        $problems = $this->verifyApiJsonResponse('GET', $indexUrl, 200, $this->apiUser);
        self::assertCount(3, $problems);
    }
}
