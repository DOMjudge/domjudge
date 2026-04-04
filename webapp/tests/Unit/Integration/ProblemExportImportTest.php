<?php declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Entity\Contest;
use App\Entity\Problem;
use App\Service\ImportProblemService;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use ZipArchive;

/**
 * Test that exporting a problem and reimporting it produces an ~identical export.
 *
 * This verifies that the export format adheres to the problem archive format
 * and that no data is lost during an export-import-export roundtrip.
 */
class ProblemExportImportTest extends BaseTestCase
{
    protected array $roles = ['admin'];

    #[DataProvider('provideProblems')]
    public function testExportImportRoundtrip(string $problemExternalId): void
    {
        // Select the demo contest.
        $this->client->request('GET', '/jury/change-contest/demo');

        // Export the original problem.
        $firstZipContent = $this->exportProblem($problemExternalId);
        self::assertNotEmpty($firstZipContent, "Export of '$problemExternalId' should not be empty");

        // Import the just exported zip as a new problem.
        $importedProblem = $this->importZip($firstZipContent);
        self::assertNotNull($importedProblem, "Re-import of '$problemExternalId' should succeed");

        // Re-export the re-imported problem.
        $secondZipContent = $this->exportProblem($importedProblem->getExternalid());
        self::assertNotEmpty($secondZipContent, "Re-export of re-imported '$problemExternalId' should not be empty");

        // Unzip, normalize and compare file lists, then contents.
        $firstContents = $this->unzipString($firstZipContent);
        $secondContents = $this->unzipString($secondZipContent);

        $firstContents = $this->normalizeZipContents($firstContents);
        $secondContents = $this->normalizeZipContents($secondContents);

        $firstFiles = array_keys($firstContents);
        $secondFiles = array_keys($secondContents);
        sort($firstFiles);
        sort($secondFiles);
        self::assertEquals(
            $firstFiles,
            $secondFiles,
            "File lists should match for '$problemExternalId'"
        );

        foreach ($firstContents as $filename => $content) {
            self::assertEquals(
                $content,
                $secondContents[$filename],
                "Content of '$filename' should match for '$problemExternalId'"
            );
        }
    }

    public static function provideProblems(): \Generator
    {
        yield 'hello' => ['hello'];
        yield 'fltcmp' => ['fltcmp'];
        yield 'boolfind' => ['boolfind'];
    }

    private function exportProblem(string $problemExternalId): string
    {
        $this->client->request('GET', '/jury/problems/' . $problemExternalId . '/export');
        $response = $this->client->getInternalResponse();
        self::assertEquals(200, $response->getStatusCode(),
            "Export request for '$problemExternalId' should succeed");

        return $response->getContent();
    }

    private function importZip(string $zipContent): ?Problem
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'domjudge-test-') . '.zip';
        file_put_contents($tmpFile, $zipContent);

        $zip = new ZipArchive();
        $zip->open($tmpFile);

        /** @var ImportProblemService $importService */
        $importService = static::getContainer()->get(ImportProblemService::class);
        $messages = ['info' => [], 'warning' => [], 'danger' => []];

        $contest = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Contest::class)
            ->findOneBy(['shortname' => 'demo']);

        $problem = $importService->importZippedProblem(
            $zip,
            'roundtrip-test.zip',
            null,
            $contest,
            $messages
        );

        $zip->close();
        unlink($tmpFile);

        self::assertEmpty(
            $messages['danger'],
            'Import should not produce errors: ' . implode(', ', $messages['danger'])
        );

        return $problem;
    }

    /**
     * Normalize zip contents by removing variable parts:
     *   - timestamps
     *   - submissions
     *   - IDs
     *   - contest specific properties
     *
     * This should ensure that we can do a meaningful semantic comparison.
     *
     * @param array<string, string> $contents
     * @return array<string, string>
     */
    private function normalizeZipContents(array $contents): array
    {
        $normalized = [];

        foreach ($contents as $filename => $content) {
            // Ignore submissions.
            if (str_starts_with($filename, 'submissions/')) {
                continue;
            }

            // Normalize {answer,input,output}_validators/ directory names: on re-import the
            // executable ID is derived from the zip filename, so the
            // subdirectory would change. Normalize to a hardcoded placeholder.
            foreach (['answer', 'input', 'output'] as $validatorType) {
                $filename = preg_replace(
                    '#^' . $validatorType . '_validators/[^/]+/#',
                    $validatorType . '_validators/_normalized_/',
                    $filename
                );
            }

            $normalized[$filename] = $content;
        }

        if (isset($normalized['problem.yaml'])) {
            // Remove timestamp comment lines.
            $normalized['problem.yaml'] = preg_replace(
                '/^# Problem exported by DOMjudge on .+\n/m',
                '',
                $normalized['problem.yaml']
            );
        }

        if (isset($normalized['domjudge-problem.ini'])) {
            // The color is a contest-problem property and may not roundtrip
            // since the reimported problem gets a new contest-problem association.
            $normalized['domjudge-problem.ini'] = preg_replace(
                '/^color=.*\n/m',
                '',
                $normalized['domjudge-problem.ini']
            );

            // The special_run and special_compare executable IDs are derived
            // from the zip filename on import, so normalize them.
            $normalized['domjudge-problem.ini'] = preg_replace(
                "/^(special_run|special_compare)='[^']+'/m",
                "$1='_normalized_'",
                $normalized['domjudge-problem.ini']
            );
        }

        return $normalized;
    }
}
