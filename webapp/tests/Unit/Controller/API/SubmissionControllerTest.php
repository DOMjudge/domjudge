<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\AddMoreDemoUsersFixture;
use App\DataFixtures\Test\EnableKotlinFixture;
use App\DataFixtures\Test\RemoveTeamFromAdminUserFixture;
use App\DataFixtures\Test\RemoveTeamFromDemoUserFixture;
use App\DataFixtures\Test\SampleSubmissionsFixture;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class SubmissionControllerTest extends BaseTest
{
    protected ?string $apiEndpoint = 'submissions';

    protected ?string $apiUser = 'admin';

    protected array $expectedObjects = [
        SampleSubmissionsFixture::class . ':0' => [
            'problem_id'  => '1',
            'language_id' => 'cpp',
            'team_id'     => '1',
            'entry_point' => null,
            'time'        => '2021-01-01T12:34:56.000+00:00',
        ],
        SampleSubmissionsFixture::class . ':1' => [
            'problem_id'  => '3',
            'language_id' => 'java',
            'team_id'     => '2',
            'entry_point' => 'Main',
            'time'        => '2021-03-04T12:00:00.000+00:00',
        ],
    ];

    protected array $entityReferences = [
        'problem_id' => Problem::class,
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    protected static array $fixtures = [
        SampleSubmissionsFixture::class,
        AddMoreDemoUsersFixture::class,
    ];

    /**
     * Test that a non-logged-in user can not add a submission.
     */
    public function testAddSubmissionNoAccess(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $this->verifyApiJsonResponse('POST', "/contests/$contestId/$apiEndpoint", 401);
    }

    /**
     * Test that if invalid data is supplied, the correct message is returned.
     *
     * @dataProvider provideAddInvalidData
     */
    public function testAddInvalidData(string $user, array $dataToSend, string $expectedMessage): void
    {
        if (isset($dataToSend['problem_id'])) {
            $dataToSend['problem_id'] = $this->resolveEntityId(Problem::class, (string)$dataToSend['problem_id']);
        }
        if (isset($dataToSend['user_id'])) {
            $dataToSend['user_id'] = $this->resolveReference($dataToSend['user_id']);
        }
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $method = isset($dataToSend['id']) ? 'PUT' : 'POST';
        $url = "/contests/$contestId/$apiEndpoint";
        if ($method === 'PUT') {
            $url .= '/' . $dataToSend['id'];
        }
        $data = $this->verifyApiJsonResponse($method, $url, 400, $user, $dataToSend);
        static::assertEquals($expectedMessage, $data['message']);
    }

    public function provideAddInvalidData(): Generator
    {
        yield ['demo', [], "One of the arguments 'problem', 'problem_id' is mandatory."];
        yield ['demo', ['problem' => 1], "One of the arguments 'language', 'language_id' is mandatory."];
        yield ['demo', ['problem_id' => 1], "One of the arguments 'language', 'language_id' is mandatory."];
        yield ['demo', ['problem_id' => 4, 'language' => 'cpp'], "Problem '4' not found or not submittable."];
        yield ['demo', ['problem_id' => 1, 'language' => 'cpp'], "No files specified."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'cpp'], "No files specified."];
        yield ['demo', ['problem_id' => 1, 'language' => 'abc'], "Language 'abc' not found or not submittable."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'abc'], "Language 'abc' not found or not submittable."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'abc'], "Language 'abc' not found or not submittable."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'cpp', 'team_id' => '1'], "Can not submit for a different team."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'cpp', 'user_id' => 1], "Can not submit for a different user."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'cpp', 'id' => '123'], "A team can not assign id."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'cpp', 'time' => '2021-01-01T00:00:00'], "A team can not assign time."];
        yield ['demo', ['problem_id' => 1, 'language_id' => 'cpp', 'files' => []], "The 'files' attribute must be an array with a single item, containing an object with a base64 encoded data field."];
        yield [
            'demo',
            ['problem_id' => 1, 'language_id' => 'cpp', 'files' => 'this is not an array'],
            "Unexpected value for parameter \"files\": expecting \"array\", got \"string\"."
        ];
        yield [
            'demo',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'files'       => [
                    // More than one item
                    ['data' => 'aaa'],
                    ['data' => 'aaa'],
                ],
            ],
            "The 'files' attribute must be an array with a single item, containing an object with a base64 encoded data field."
        ];
        yield [
            'demo',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'files'       => [
                    // Not valid base64
                    ['data' => '*&(^&*(^(&*(&*^'],
                ],
            ],
            "The 'files[0].data' attribute is not base64 encoded."
        ];
        yield [
            'demo',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'files'       => [
                    // Valid base64, but not a ZIP file
                    ['data' => 'aaa'],
                ],
            ],
            "No valid zip archive given"
        ];
        yield [
            'demo',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'files'       => [
                    ['data' => 'aaa', 'mime' => 'wrong'],
                ],
            ],
            "The 'files[0].mime' attribute must be application/zip if provided."
        ];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => '1'], "No files specified."];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => '3'], "Team with ID '3' not found in contest or not enabled."];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => '1', 'user_id' => 'doesnotexist'], "User not found."];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => '1', 'user_id' => AddMoreDemoUsersFixture::class . ':seconddemo'], "User not linked to provided team."];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => '1', 'user_id' => AddMoreDemoUsersFixture::class . ':thirddemo'], "User not enabled."];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => '1', 'user_id' => AddMoreDemoUsersFixture::class . ':fourthdemo'], "User not linked to a team."];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => '1', 'time' => 'this is not a time'], "Can not parse time 'this is not a time'."];
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => '1',
                'id'          => '$this is not valid$',
            ],
            "ID '\$this is not valid$' is not valid.",
        ];
    }

    /**
     * Test that passing an ID is not allowed when performing a POST.
     */
    public function testSupplyIdInPost(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $data = $this->verifyApiJsonResponse('POST', "/contests/$contestId/$apiEndpoint", 400, 'admin', ['problem_id' => $this->resolveEntityId(Problem::class, '1'), 'language_id' => 'cpp', 'id' => '123']);
        static::assertEquals('Passing an ID is not supported for POST.', $data['message']);
    }

    /**
     * Test that passing a wrong ID is not allowed when performing a PUT.
     */
    public function testSupplyWrongIdInPut(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $data = $this->verifyApiJsonResponse('PUT', "/contests/$contestId/$apiEndpoint/id1", 400, 'admin', ['problem_id' => $this->resolveEntityId(Problem::class, '1'), 'language_id' => 'cpp', 'id' => '123']);
        static::assertEquals('ID does not match URI.', $data['message']);
    }

    /**
     * Test that when submitting for a language that requires an entry point but not supplying an error is returned.
     */
    public function testMissingEntryPoint(): void
    {
        $this->loadFixture(EnableKotlinFixture::class);

        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $data = $this->verifyApiJsonResponse('POST', "/contests/$contestId/$apiEndpoint", 400, 'demo', ['problem_id' => $this->resolveEntityId(Problem::class, '1'), 'language' => 'kotlin']);

        static::assertEquals('Main class required, but not specified.', $data['message']);
    }

    /**
     * Test that when submitting as a user without an association team an error is returned.
     *
     * @dataProvider provideMissingTeam
     */
    public function testMissingTeam(string $username): void
    {
        $this->loadFixtures([RemoveTeamFromDemoUserFixture::class, RemoveTeamFromAdminUserFixture::class]);

        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $data = $this->verifyApiJsonResponse('POST', "/contests/$contestId/$apiEndpoint", 400, $username, ['problem_id' => $this->resolveEntityId(Problem::class, '1'), 'language' => 'cpp']);

        static::assertEquals('User does not belong to a team.', $data['message']);
    }

    public function provideMissingTeam(): Generator
    {
        yield ['demo'];
        yield ['admin'];
    }

    /**
     * Test that adding submissions works as expected.
     *
     * @dataProvider provideAddSuccess
     */
    public function testAddSuccess(
        string $user,
        array $dataToSend,
        ?array $zipFiles,
        array $filesToSend,
        bool $idIsExternal,
        int $expectedProblemId,
        int $expectedTeamId,
        string $expectedUsername,
        string $expectedLanguageId,
        ?string $expectedSubmissionExternalId, // If known
        ?string $expectedTime, // If known
        array $expectedFiles,
        ?string $expectedEntryPoint = null
    ): void {
        $this->loadFixture(EnableKotlinFixture::class);

        if (isset($dataToSend['problem'])) {
            $dataToSend['problem'] = $this->resolveEntityId(Problem::class, (string)$dataToSend['problem']);
        }
        if (isset($dataToSend['problem_id'])) {
            $dataToSend['problem_id'] = $this->resolveEntityId(Problem::class, (string)$dataToSend['problem_id']);
        }
        if (isset($dataToSend['user_id'])) {
            $dataToSend['user_id'] = $this->resolveReference($dataToSend['user_id']);
        }
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $method = isset($dataToSend['id']) ? 'PUT' : 'POST';
        $url = "/contests/$contestId/$apiEndpoint";
        if ($method === 'PUT') {
            $url .= '/' . $dataToSend['id'];
        }

        if ($zipFiles !== null) {
            if (!isset($dataToSend['files'])) {
                $dataToSend['files'] = [];
            }
            if (!isset($dataToSend['files'][0])) {
                $dataToSend['files'][0] = [];
            }
            $dataToSend['files'][0]['data'] = $this->base64ZipWithFiles($zipFiles);
        }
        $submittedSubmission = $this->verifyApiJsonResponse($method, $url, 200, $user, $dataToSend, $filesToSend);
        static::assertIsArray($submittedSubmission);
        static::assertArrayHasKey('id', $submittedSubmission);

        $submissionId = $submittedSubmission['id'];

        // Now load the submission
        $submissionRepository = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Submission::class);
        if ($idIsExternal) {
            /** @var Submission $submission */
            $submission = $submissionRepository->findOneBy(['externalid' => $submissionId]);
        } else {
            $submission = $submissionRepository->find($submissionId);
        }

        static::assertInstanceOf(Submission::class, $submission);
        static::assertEquals($expectedProblemId, $submission->getProblem()->getProbid(), 'Wrong problem ID');
        static::assertEquals($expectedTeamId, $submission->getTeam()->getTeamid(), 'Wrong team ID');
        static::assertEquals($expectedUsername, $submission->getUser()->getUsername(), 'Wrong user');
        static::assertEquals($expectedLanguageId, $submission->getLanguage()->getLangid(), 'Wrong language ID');
        if ($expectedSubmissionExternalId) {
            static::assertEquals($expectedSubmissionExternalId, $submission->getExternalid(), 'Wrong external submission ID');
        }
        if ($expectedTime) {
            static::assertEquals($expectedTime, $submission->getAbsoluteSubmitTime());
        }
        static::assertEquals($expectedEntryPoint, $submission->getEntryPoint());
        $submissionFiles = [];
        /** @var SubmissionFile $file */
        foreach ($submission->getFiles() as $file) {
            $submissionFiles[$file->getFilename()] = $file->getSourcecode();
        }
        static::assertEquals($expectedFiles, $submissionFiles, 'Wrong files');

        // Also load the submission from the API, to see it now gets returned.
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint/$submissionId", 200, 'admin');
    }

    public function provideAddSuccess(): Generator
    {
        // Submit a single file as a file upload.
        yield [
            'demo',
            [
                'problem'  => 1,
                'language' => 'cpp',
            ],
            null,
            ['code' => new UploadedFile(__FILE__, 'somefile.cpp')],
            false,
            1,
            2,
            'demo',
            'cpp',
            null,
            null,
            ['somefile.cpp' => file_get_contents(__FILE__)],
        ];
        // Submit multiple files as a file upload
        yield [
            'demo',
            [
                'problem'  => 1,
                'language' => 'cpp',
            ],
            null,
            [
                'code' => [
                    new UploadedFile(__FILE__, 'somefile.cpp'),
                    new UploadedFile(__DIR__ . '/BaseTest.php', 'another.cpp'),
                ],
            ],
            false,
            1,
            2,
            'demo',
            'cpp',
            null,
            null,
            [
                'somefile.cpp' => file_get_contents(__FILE__),
                'another.cpp'  => file_get_contents(__DIR__ . '/BaseTest.php'),
            ],
        ];
        // Submit with an entrypoint.
        yield [
            'demo',
            [
                'problem'     => 1,
                'language'    => 'kotlin',
                'entry_point' => 'SomeFileKt',
            ],
            null,
            [
                'code' => [
                    new UploadedFile(__FILE__, 'somefile.kt'),
                ],
            ],
            false,
            1,
            2,
            'demo',
            'kt',
            null,
            null,
            ['somefile.kt' => file_get_contents(__FILE__)],
            'SomeFileKt',
        ];
        // Submit a single file in CLICS format
        yield [
            'demo',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
            ],
            ['main.cpp' => '// No content'],
            [],
            false,
            1,
            2,
            'demo',
            'cpp',
            null,
            null,
            ['main.cpp' => '// No content'],
        ];
        // Submit multiple files in CLICS format, also provide mime.
        yield [
            'demo',
            [
                'problem_id'  => 2,
                'language_id' => 'java',
                'files'       => [
                    ['mime' => 'application/zip'],
                ],
            ],
            [
                'main.java'    => 'Some java file',
                'another.java' => 'A second java file',
            ],
            [],
            false,
            2,
            2,
            'demo',
            'java',
            null,
            null,
            [
                'main.java'    => 'Some java file',
                'another.java' => 'A second java file',
            ],
        ];
        // Submit as admin under a different team ID.
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => '2',
            ],
            ['main.cpp' => '// No content'],
            [],
            false,
            1,
            2,
            'demo',
            'cpp',
            null,
            null,
            ['main.cpp' => '// No content'],
        ];
        // Submit as admin under a different user ID.
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => '2',
                'user_id'     => AddMoreDemoUsersFixture::class . ':seconddemo',
            ],
            ['main.cpp' => '// No content'],
            [],
            false,
            1,
            2,
            'seconddemo',
            'cpp',
            null,
            null,
            ['main.cpp' => '// No content'],
        ];
        // Submit as admin and specify the submission ID.
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => '1',
                'id'          => 'myextid123',
            ],
            ['main.cpp' => '// No content'],
            [],
            true,
            1,
            1,
            'admin',
            'cpp',
            'myextid123',
            null,
            ['main.cpp' => '// No content'],
        ];
        // Submit as admin and specify time.
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => '1',
                'time'        => '2020-01-01T12:34:56',
            ],
            ['main.cpp' => '// No content'],
            [],
            false,
            1,
            1,
            'admin',
            'cpp',
            null,
            '2020-01-01T12:34:56.000+00:00',
            ['main.cpp' => '// No content'],
        ];
    }

    /**
     * Get a base64 encoded ZIP with the files as contents.
     *
     * Note: this method can not be called inside a data provider, since it uses the container.
     *
     * @param array $files Mapping from filename to contents
     *
     * @return string The base64 encoded ZIP file
     */
    protected function base64ZipWithFiles(array $files): string
    {
        $zip = new ZipArchive();
        $tempFilename = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), "api-submissions-test-");

        $zip->open($tempFilename, ZipArchive::OVERWRITE);
        foreach ($files as $file => $content) {
            $zip->addFromString($file, $content);
        }
        $zip->close();

        $zipContent = file_get_contents($tempFilename);
        unlink($tempFilename);
        return base64_encode($zipContent);
    }
}
