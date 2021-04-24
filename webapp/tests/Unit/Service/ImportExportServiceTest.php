<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Service\ImportExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImportExportServiceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    /**
     * @dataProvider provideImportContestYamlErrors
     */
    public function testImportContestYamlErrors($data, string $expectedMessage)
    {
        self::assertFalse(static::$container->get(ImportExportService::class)->importContestYaml($data, $message, $cid));
        self::assertEquals($expectedMessage, $message);
        self::assertNull($cid);
    }

    public function provideImportContestYamlErrors(): Generator
    {
        yield [[], 'Error parsing YAML file.'];
        yield [['name' => 'Some name'], 'Missing fields: start-time, short-name, duration'];
        yield [['short-name' => 'somename', 'start-time' => '2020-01-01 12:34:56'], 'Missing fields: name, duration'];
        yield [
            [
                'name'       => 'Test contest',
                'short-name' => 'test',
                'duration'   => '5:00:00',
                'start-time' => 'Invalid start time here',
            ],
            'Can not parse start time'
        ];
        yield [
            [
                'name'                     => 'Test contest',
                'short-name'               => 'test',
                'duration'                 => '5:00:00',
                'start-time'               => '2020-01-01T12:34:56+02:00',
                'scoreboard-freeze-length' => '6:00:00',
            ],
            'Freeze duration is longer than contest length'
        ];
        yield [
            [
                'name'                     => '',
                'short-name'               => '',
                'duration'                 => '5:00:00',
                'start-time'               => '2020-01-01T12:34:56+02:00',
                'scoreboard-freeze-length' => '30:00',
            ],
            "Contest has errors:\n\nname: This value should not be blank.\nshortname: This value should not be blank."
        ];
    }

    /**
     * @dataProvider provideImportContestYamlSuccess
     */
    public function testImportContestYamlSuccess($data, string $expectedShortName, array $expectedProblems = [])
    {
        self::assertTrue(static::$container->get(ImportExportService::class)->importContestYaml($data, $message, $cid));
        self::assertNull($message);
        self::assertIsString($cid);

        // Load the contest, but first clear the entity manager to have all data
        static::$container->get(EntityManagerInterface::class)->clear();
        /** @var Contest $contest */
        $contest = static::$container->get(EntityManagerInterface::class)->getRepository(Contest::class)->find($cid);

        self::assertEquals($data['name'], $contest->getName());
        self::assertEquals($expectedShortName, $contest->getShortname());

        $problems = [];
        /** @var ContestProblem $problem */
        foreach ($contest->getProblems() as $problem) {
            $problems[$problem->getShortname()] = $problem->getProblem()->getExternalid();
        }

        self::assertEquals($expectedProblems, $problems);
    }

    public function provideImportContestYamlSuccess(): Generator
    {
        // Simple case
        yield [
            [
                'name'                     => 'Some test contest',
                'short-name'               => 'test-contest',
                'duration'                 => '5:00:00',
                'start-time'               => '2020-01-01T12:34:56+02:00',
                'scoreboard-freeze-length' => '1:00:00',
            ],
            'test-contest',
        ];
        // - Freeze length without hours
        // - Set a short name with invalid characters
        // - Use DateTime object for start time
        yield [
            [
                'name'                     => 'Some test contest',
                'short-name'               => 'test-contest $-@ test',
                'duration'                 => '5:00:00',
                'start-time'               => new DateTime('2020-01-01T12:34:56+02:00'),
                'scoreboard-freeze-length' => '30:00',
            ],
            'test-contest__-__test',
        ];
        // Real life example from NWERC 2020 practice session, including problems
        yield [
            [
                'duration'                 => '2:00:00',
                'name'                     => 'NWERC 2020 Practice Session',
                'penalty-time'             => '20',
                'scoreboard-freeze-length' => '30:00',
                'short-name'               => 'practice',
                'start-time'               => '2021-03-27 09:00:00+00:00',
                'problems'                 => [
                    [
                        'color'      => '#FE9DAF',
                        'letter'     => 'A',
                        'rgb'        => '#FE9DAF',
                        'short-name' => 'anothereruption',
                    ],
                    [
                        'color'      => '#008100',
                        'letter'     => 'B',
                        'rgb'        => '#008100',
                        'short-name' => 'brokengears',
                    ],
                    [
                        'color'      => '#FF7109',
                        'letter'     => 'C',
                        'rgb'        => '#FF7109',
                        'short-name' => 'cheating',
                    ],
                ],
            ],
            'practice',
            ['A' => 'anothereruption', 'B' => 'brokengears', 'C' => 'cheating'],
        ];
    }
}
