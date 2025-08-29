<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\AddProblemAttachmentFixture;
use App\DataFixtures\Test\DummyProblemFixture;
use App\Entity\Problem;

class ProblemControllerTest extends BaseTestCase
{
    /* This tests with the anonymous user;
       for tests with the admin user see ProblemControllerAdminTest.
     */

    protected ?string $apiEndpoint = 'problems';
    protected ?string $entityClass = Problem::class;

    protected array $expectedObjects = [
        'boolfind' => [
            "ordinal"    => 2,
            "id"         => "boolfind",
            "short_name" => "C",
            "label"      => "C",
            "time_limit" => 5,
            "name"       => "Boolean switch search",
            "rgb"        => "#9B630C",
            "color"      => "saddlebrown",
            "statement"  => [
                [
                    'href'     => 'contests/demo/problems/boolfind/statement',
                    'mime'     => 'application/pdf',
                    'filename' => 'C.pdf',
                ],
            ],
            'attachments' => [
                [
                    'href'     => 'contests/demo/problems/boolfind/attachment/interactor',
                    'mime'     => 'text/x-script.python',
                    'filename' => 'interactor',
                ],
            ],
        ],
        'fltcmp' => [
            "ordinal"    => 1,
            "id"         => "fltcmp",
            "short_name" => "B",
            "label"      => "B",
            "time_limit" => 5,
            "name"       => "Float special compare test",
            "rgb"        => "#E93603",
            "color"      => "orangered",
            "statement"  => [
                [
                    'href'     => 'contests/demo/problems/fltcmp/statement',
                    'mime'     => 'application/pdf',
                    'filename' => 'B.pdf',
                ],
            ],
            'attachments' => [],
        ],
        'hello' => [
            "ordinal"    => 0,
            "id"         => "hello",
            "short_name" => "A",
            "label"      => "A",
            "time_limit" => 5,
            "name"       => "Hello World",
            "rgb"        => "#9486EA",
            "color"      => "mediumpurple",
            "statement"  => [
                [
                    'href'     => 'contests/demo/problems/hello/statement',
                    'mime'     => 'application/pdf',
                    'filename' => 'A.pdf',
                ],
            ],
            'attachments' => [],
        ],
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixture(AddProblemAttachmentFixture::class);
    }

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
        $url       = $this->helperGetEndpointURL($this->apiEndpoint) . '/' . $problemId;
        $this->verifyApiJsonResponse('DELETE', $url, 401, $this->apiUser, ['label' => 'dummy']);

        // Check that we still have three problems left
        $indexUrl = $this->helperGetEndpointURL($this->apiEndpoint);
        $problems = $this->verifyApiJsonResponse('GET', $indexUrl, 200, $this->apiUser);
        self::assertCount(3, $problems);
    }

    /**
     * Test that the statement endpoint returns a PDF for objects that exist.
     *
     * @dataProvider provideSingle
     */
    public function testStatement(int|string $id): void
    {
        $id = $this->resolveReference($id);
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        $url = $this->helperGetEndpointURL($apiEndpoint, (string)$id) . '/statement';

        // Use output buffering since this is a streamed response
        $this->client->request('GET', '/api' . $url);
        $response = $this->client->getInternalResponse();
        self::assertEquals(200, $response->getStatusCode(), 'Statement found');

        // We can't easily check if the contents are actually a PDF, so assume it is
    }

    /**
     * Test that the statement endpoint does not return anything for objects that don't exist.
     *
     * @dataProvider provideSingleNotFound
     */
    public function testStatementNotFound(string $id): void
    {
        $id = $this->resolveReference($id);
        if (($apiEndpoint = $this->apiEndpoint) === null) {
            static::markTestSkipped('No endpoint defined.');
        }
        $url = $this->helperGetEndpointURL($apiEndpoint, $id) . '/statement';
        $this->verifyApiJsonResponse('GET', $url, 404, $this->apiUser);
    }
}
