<?php declare(strict_types=1);

namespace App\Tests\Controller\API;

use App\Entity\Language;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

class SubmissionControllerTest extends BaseTest
{
    /**
     * Test that a non logged in user can not add a submission
     */
    public function testAddSubmissionNoAccess()
    {
        $this->verifyApiJsonResponse('POST', '/contests/2/submissions', 401);
    }

    /**
     * Test that if not all data is supplied, the correct message is returned
     *
     * @dataProvider provideAddMissingData
     */
    public function testAddMissingData(string $user, array $dataToSend, string $expectedMessage)
    {
        $data = $this->verifyApiJsonResponse('POST', '/contests/2/submissions', 400, $user, $dataToSend);
        static::assertEquals($expectedMessage, $data['message']);
    }

    public function provideAddMissingData(): Generator
    {
        yield ['dummy', [], "One of the arguments 'problem', 'problem_id' is mandatory"];
        yield ['dummy', ['problem' => 1], "One of the arguments 'language', 'language_id' is mandatory"];
        yield ['dummy', ['problem_id' => 1], "One of the arguments 'language', 'language_id' is mandatory"];
        yield ['dummy', ['problem_id' => 4, 'language' => 'cpp'], "Problem 4 not found or not submittable"];
        yield ['dummy', ['problem_id' => 1, 'language' => 'cpp'], "No files specified."];
        yield ['dummy', ['problem_id' => 1, 'language_id' => 'cpp'], "No files specified."];
        yield ['dummy', ['problem_id' => 1, 'language' => 'abc'], "Language abc not found or not submittable"];
        yield ['dummy', ['problem_id' => 1, 'language_id' => 'abc'], "Language abc not found or not submittable"];
        yield ['dummy', ['problem_id' => 1, 'language_id' => 'abc'], "Language abc not found or not submittable"];
        yield ['dummy', ['problem_id' => 1, 'language_id' => 'cpp', 'team_id' => 1], "Can not submit for a different team"];
        yield ['dummy', ['problem_id' => 1, 'language_id' => 'cpp', 'id' => '123'], "A team can not assign id"];
        yield ['dummy', ['problem_id' => 1, 'language_id' => 'cpp', 'time' => '2021-01-01T00:00:00'], "A team can not assign time"];
        yield ['dummy', ['problem_id' => 1, 'language_id' => 'cpp', 'files' => []], "The 'files' attribute must be an array with a single item, containing an object with a base64 encoded data field"];
        yield [
            'dummy',
            ['problem_id' => 1, 'language_id' => 'cpp', 'files' => 'this is not an array'],
            "The 'files' attribute must be an array with a single item, containing an object with a base64 encoded data field"
        ];
        yield [
            'dummy',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'files'       => [
                    // More than one item
                    ['data' => 'aaa'],
                    ['data' => 'aaa'],
                ],
            ],
            "The 'files' attribute must be an array with a single item, containing an object with a base64 encoded data field"
        ];
        yield [
            'dummy',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'files'       => [
                    // Not valid base64
                    ['data' => '*&(^&*(^(&*(&*^'],
                ],
            ],
            "The 'files[0].data' attribute is not base64 encoded"
        ];
        yield [
            'dummy',
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
            'dummy',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'files'       => [
                    ['data' => 'aaa', 'mime' => 'wrong'],
                ],
            ],
            "The 'files[0].mime' attribute must be application/zip if provided"
        ];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp'], "User does not belong to a team"];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => 1], "No files specified."];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => 3], "Team 3 not found or not enabled"];
        yield ['admin', ['problem_id' => 1, 'language' => 'cpp', 'team_id' => 1, 'time' => 'this is not a time'], "Can not parse time this is not a time"];
    }

    /**
     * Test that when submitting for a language that requires an entry point but not supplying an error is returned
     */
    public function testMissingEntryPoint()
    {
        // First, enable Kotlin as a language
        $em = self::$container->get(EntityManagerInterface::class);
        /** @var Language $kotlin */
        $kotlin = $em->getRepository(Language::class)->find('kt');
        $kotlin->setAllowSubmit(true);
        $em->flush();

        $data = $this->verifyApiJsonResponse('POST', '/contests/2/submissions', 400, 'dummy', ['problem_id' => 1, 'language' => 'kotlin']);

        static::assertEquals('Main class required, but not specified.', $data['message']);
    }

    /**
     * Test that adding submissions works as expected
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
        string $expectedLanguageId,
        ?string $expectedSubmissionExternalId, // If known
        ?string $expectedTime, // If known
        array $expectedFiles,
        ?string $expectedEntryPoint = null
    ) {
        // First, enable Kotlin as a language as this is the only language with an entrypoint
        $em = self::$container->get(EntityManagerInterface::class);
        /** @var Language $kotlin */
        $kotlin = $em->getRepository(Language::class)->find('kt');
        $kotlin->setAllowSubmit(true);
        $em->flush();

        if ($zipFiles !== null) {
            if (!isset($dataToSend['files'])) {
                $dataToSend['files'] = [];
            }
            if (!isset($dataToSend['files'][0])) {
                $dataToSend['files'][0] = [];
            }
            $dataToSend['files'][0]['data'] = $this->base64ZipWithFiles($zipFiles);
        }
        $submissionId = $this->verifyApiJsonResponse('POST', '/contests/2/submissions', 200, $user, $dataToSend, $filesToSend);
        static::assertIsString($submissionId);

        // Now load the submission
        $submissionRepository = static::$container->get(EntityManagerInterface::class)->getRepository(Submission::class);
        if ($idIsExternal) {
            /** @var Submission $submission */
            $submission = $submissionRepository->findOneBy(['externalid' => $submissionId]);
        } else {
            $submission = $submissionRepository->find($submissionId);
        }

        static::assertInstanceOf(Submission::class, $submission);
        static::assertEquals($expectedProblemId, $submission->getProblem()->getProbid(), 'Wrong problem ID');
        static::assertEquals($expectedTeamId, $submission->getTeam()->getTeamid(), 'Wrong team ID');
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
    }

    public function provideAddSuccess(): Generator
    {
        // Submit a single file as a file upload
        yield [
            'dummy',
            [
                'problem'  => 1,
                'language' => 'cpp',
            ],
            null,
            ['code' => new UploadedFile(__FILE__, 'somefile.cpp')],
            false,
            1,
            2,
            'cpp',
            null,
            null,
            ['somefile.cpp' => file_get_contents(__FILE__)],
        ];
        // Submit multiple files as a file upload
        yield [
            'dummy',
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
            'cpp',
            null,
            null,
            [
                'somefile.cpp' => file_get_contents(__FILE__),
                'another.cpp'  => file_get_contents(__DIR__ . '/BaseTest.php'),
            ],
        ];
        // Submit with an entrypoint
        yield [
            'dummy',
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
            'kt',
            null,
            null,
            ['somefile.kt' => file_get_contents(__FILE__)],
            'SomeFileKt',
        ];
        // Submit a single file in CLICS format
        yield [
            'dummy',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
            ],
            ['main.cpp' => '// No content'],
            [],
            false,
            1,
            2,
            'cpp',
            null,
            null,
            ['main.cpp' => '// No content'],
        ];
        // Submit multiple files in CLICS format, also provide mime
        yield [
            'dummy',
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
            'java',
            null,
            null,
            [
                'main.java'    => 'Some java file',
                'another.java' => 'A second java file',
            ],
        ];
        // Submit as admin under a different team ID
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => 1,
            ],
            ['main.cpp' => '// No content'],
            [],
            false,
            1,
            1,
            'cpp',
            null,
            null,
            ['main.cpp' => '// No content'],
        ];
        // Submit as admin and specify the submission ID
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => 1,
                'id'          => 'myextid123',
            ],
            ['main.cpp' => '// No content'],
            [],
            true,
            1,
            1,
            'cpp',
            'myextid123',
            null,
            ['main.cpp' => '// No content'],
        ];
        // Submit as admin and specify time
        yield [
            'admin',
            [
                'problem_id'  => 1,
                'language_id' => 'cpp',
                'team_id'     => 1,
                'time'        => '2020-01-01T12:34:56',
            ],
            ['main.cpp' => '// No content'],
            [],
            false,
            1,
            1,
            'cpp',
            null,
            '2020-01-01T12:34:56.000+00:00',
            ['main.cpp' => '// No content'],
        ];
    }

    /**
     * Get a base64 encoded ZIP with the files as contents.
     *
     * Note: this method can not be called inside a data provider, since it uses the container
     *
     * @param array $files Mapping from filename to contents
     *
     * @return string The base64 encoded ZIP file
     */
    protected function base64ZipWithFiles(array $files): string
    {
        $zip          = new ZipArchive();
        $tempFilename = tempnam(static::$container->get(DOMJudgeService::class)->getDomjudgeTmpDir(), "api-submissions-test-");

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
