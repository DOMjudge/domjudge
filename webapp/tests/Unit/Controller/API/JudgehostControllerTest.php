<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\ExtraJudgehostFixture;
use App\Entity\JudgingRun;
use App\Entity\Submission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Generator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class JudgehostControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'judgehosts';
    protected ?string $apiUser     = 'admin';

    protected static string $skipMessageCI  = "This is very dependent on the contributor setup, check this in CI.";
    protected static string $skipMessageIDs = "Filtering on IDs not implemented in this endpoint.";

    protected array $expectedObjects = [];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    public function testList(): void
    {
        if (getenv("CI")) {
            parent::testList();
        } else {
            static::markTestSkipped(static::$skipMessageCI);
        }
    }

    public function testListWithIds(): void
    {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithIdsNotArray(): void
    {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithAbsentIds(): void
    {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function provideSingle(): Generator
    {
        if (empty($this->expectedObjects)) {
            yield [0, []];
            return;
        }
        foreach ($this->expectedObjects as $expectedProperties) {
            yield [$expectedProperties['hostname'], $expectedProperties];
        }
    }

    /**
     * Test that the endpoint returns an empty list for objects that don't exist.
     */
    #[DataProvider('provideSingleNotFound')]
    public function testSingleNotFound(string $id): void
    {
        $id = $this->resolveReference($id);
        $url = $this->helperGetEndpointURL($this->apiEndpoint, $id);
        $object = $this->verifyApiJsonResponse('GET', $url, 200, $this->apiUser);
        static::assertEquals([], $object);
    }

    /**
     * The judgehost endpoints take form-encoded parameters rather than JSON, so they can not
     * use the JSON helpers from the base class.
     *
     * @param array<string, string> $parameters
     */
    private function postForm(string $apiUri, array $parameters): Response
    {
        $adminPasswordFile = sprintf(
            '%s/%s',
            static::getContainer()->getParameter('domjudge.etcdir'),
            'initial_admin_password.secret'
        );
        // ROLE_ADMIN includes ROLE_JUDGEHOST through the role hierarchy.
        $this->client->request('POST', '/api' . $apiUri, $parameters, [], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => trim(file_get_contents($adminPasswordFile)),
        ]);

        return $this->client->getResponse();
    }

    /**
     * A judgehost must exist before add-judging-run gets as far as looking at its arguments'
     * types, otherwise the endpoint rejects the caller first and the test would pass for the
     * wrong reason.
     */
    private function registerJudgehost(string $hostname): void
    {
        $response = $this->postForm('/judgehosts', ['hostname' => $hostname]);
        static::assertEquals(200, $response->getStatusCode(), (string)$response->getContent());
    }

    /**
     * Every mandatory argument of add-judging-run must be reported as such.
     *
     * `start_time` and `end_time` used to be missing from the check while being typed as
     * non-nullable strings further down, so leaving them out produced a 500 TypeError instead
     * of a 400.
     */
    #[DataProvider('provideMandatoryJudgingRunArgument')]
    public function testAddJudgingRunRequiresArgument(string $missing): void
    {
        $hostname = 'test-judgehost';
        $this->registerJudgehost($hostname);

        $parameters = [
            'runresult' => 'correct',
            'runtime' => '0.01',
            'start_time' => '1000.0',
            'end_time' => '1001.0',
            'output_run' => '',
            'output_diff' => '',
            'output_error' => '',
            'output_system' => '',
        ];
        unset($parameters[$missing]);

        $response = $this->postForm(
            sprintf('/judgehosts/add-judging-run/%s/1', $hostname),
            $parameters
        );

        static::assertEquals(400, $response->getStatusCode(), (string)$response->getContent());
        static::assertStringContainsString(
            sprintf("Argument '%s' is mandatory", $missing),
            (string)$response->getContent()
        );
    }

    public static function provideMandatoryJudgingRunArgument(): Generator
    {
        yield ['runresult'];
        yield ['runtime'];
        yield ['start_time'];
        yield ['end_time'];
        yield ['output_run'];
        yield ['output_diff'];
        yield ['output_error'];
        yield ['output_system'];
    }

    /**
     * Test that an entry point is only stored when it is a valid filename, since it is detected
     * from the compile output, which is under control of the submitter.
     */
    public function testUpdateJudgingChecksEntryPoint(): void
    {
        $this->loadFixture(ExtraJudgehostFixture::class);

        // Adding a submission also creates the judgetasks and judging runs updated below.
        $contestId = $this->getDemoContestId();
        $submitted = $this->verifyApiJsonResponse(
            'POST', "/contests/$contestId/submissions", 200, 'demo',
            ['problem' => 'hello', 'language' => 'cpp'],
            ['code' => new UploadedFile(__FILE__, 'somefile.cpp')]
        );
        static::assertIsArray($submitted);
        $submissionId = $submitted['id'];

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $submission = $em->getRepository(Submission::class)->find($submissionId);
        static::assertInstanceOf(Submission::class, $submission);
        $judgingRun = $em->getRepository(JudgingRun::class)
            ->findOneBy(['judging' => $submission->getJudgings()->first()]);
        static::assertInstanceOf(JudgingRun::class, $judgingRun);
        $judgeTaskId = $judgingRun->getJudgeTaskId();
        static::assertNotNull($judgeTaskId);

        $this->updateJudging($judgeTaskId, 'Main -u root');
        static::assertNull($this->getEntryPoint($submissionId));

        $this->updateJudging($judgeTaskId, 'Main');
        static::assertEquals('Main', $this->getEntryPoint($submissionId));
    }

    /**
     * Get the entry point stored for the given submission, reading it from the database again.
     */
    private function getEntryPoint(string|int $submissionId): ?string
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $submission = $em->getRepository(Submission::class)->find($submissionId);
        static::assertInstanceOf(Submission::class, $submission);

        return $submission->getEntryPoint();
    }

    /**
     * Report a successful compilation with the given entry point, as a judgehost would.
     */
    private function updateJudging(int $judgeTaskId, string $entryPoint): void
    {
        $adminPasswordFile = sprintf(
            '%s/%s',
            static::getContainer()->getParameter('domjudge.etcdir'),
            'initial_admin_password.secret'
        );
        // The admin has the judgehost role through the role hierarchy.
        $this->client->request(
            'PUT',
            sprintf('/api/judgehosts/update-judging/%s/%d', ExtraJudgehostFixture::HOSTNAME, $judgeTaskId),
            [
                'compile_success'  => 1,
                'output_compile'   => base64_encode('compiler output'),
                'compile_metadata' => base64_encode("entry_point: $entryPoint\n"),
                'entry_point'      => $entryPoint,
            ],
            [],
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => trim(file_get_contents($adminPasswordFile)),
            ]
        );
        static::assertTrue($this->client->getResponse()->isSuccessful(), var_export($this->client->getResponse(), true));
    }
}
