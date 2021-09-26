<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ImportExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

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
        $config = static::$container->get(ConfigurationService::class);
        $dataSource = $config->get('data_source');
        if ($dataSource === DOMJudgeService::DATA_SOURCE_LOCAL) {
            /** @var Contest $contest */
            $contest = static::$container->get(EntityManagerInterface::class)->getRepository(Contest::class)->find($cid);
        } else {
            /** @var Contest $contest */
            $contest = static::$container->get(EntityManagerInterface::class)->getRepository(Contest::class)->findOneBy(['externalid' => $cid]);
        }

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

    public function testImportAccountsTsvSuccess()
    {
        // We test all account types twice:
        // - Team without postfix
        // - Team with postfix
        // - Judge
        // - Admin
        // - Analyst (will be ignored)
        // We also set the IP address for some of the accounts
        $accounts = <<<EOF
accounts	1
team	Team 1	team001	password1
team	Team 2	team2	password2	1.2.3.4
team	Team 2 user a	team02a	password3	5.6.7.8
team	Team 2 user b	team02b	password4
judge	Judge member 1	judge1	password5
judge	Another judge member	judge2	password6	9.10.11.12
admin	Some admin	adminx	password7
admin	Another admin	adminy	password8
analyst	Analyst number 1	analyst1	password9	13.14.15.16
analyst	Analyst two	analyst2	password10
EOF;

        $expectedUsers = [
            [
                'roles' => ['team'],
                'name' => 'Team 1',
                'username' => 'team001',
                'password' => 'password1',
                'team' => [
                    'id' => 1,
                ],
            ],
            [
                'roles' => ['team'],
                'name' => 'Team 2',
                'username' => 'team2',
                'password' => 'password2',
                'ip' => '1.2.3.4',
                'team' => [
                    'id' => 2,
                ],
            ],
            [
                'roles' => ['team'],
                'name' => 'Team 2 user a',
                'username' => 'team02a',
                'password' => 'password3',
                'ip' => '5.6.7.8',
                'team' => [
                    'id' => 2,
                ],
            ],
            [
                'roles' => ['team'],
                'name' => 'Team 2 user b',
                'username' => 'team02b',
                'password' => 'password4',
                'team' => [
                    'id' => 2,
                ],
            ],
            [
                'roles' => ['jury', 'team'],
                'name' => 'Judge member 1',
                'username' => 'judge1',
                'password' => 'password5',
                'team' => [
                    'name' => 'Judge member 1',
                    'category' => 'Jury',
                    'members' => 'Judge member 1',
                ],
            ],
            [
                'roles' => ['jury', 'team'],
                'name' => 'Another judge member',
                'username' => 'judge2',
                'password' => 'password6',
                'ip' => '9.10.11.12',
                'team' => [
                    'name' => 'Another judge member',
                    'category' => 'Jury',
                    'members' => 'Another judge member',
                ],
            ],
            [
                'roles' => ['admin'],
                'name' => 'Some admin',
                'username' => 'adminx',
                'password' => 'password7',
            ],
            [
                'roles' => ['admin'],
                'name' => 'Another admin',
                'username' => 'adminy',
                'password' => 'password8',
            ],
        ];
        $unexpectedUsers = ['analyst1', 'analyst2'];

        $fileName = tempnam(static::$container->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'accounts-tsv');
        file_put_contents($fileName, $accounts);
        $file = new UploadedFile($fileName, 'accounts.tsv');
        $importCount = static::$container->get(ImportExportService::class)->importTsv('accounts', $file, $message);
        // Remove the file, we don't need it anymore
        unlink($fileName);
        // We expect 8 accounts to be created: 4 team accounts, 2 judge accounts and 2 admin accounts. No analyst accounts
        self::assertEquals(8, $importCount);
        self::assertNull($message);

        /** @var EntityManagerInterface $em */
        $em = static::$container->get(EntityManagerInterface::class);

        $passwordEncoder = static::$container->get(UserPasswordEncoderInterface::class);

        // Check for all expected users
        foreach ($expectedUsers as $data) {
            $user = $em->getRepository(User::class)->findOneBy(['username' => $data['username']]);
            self::assertNotNull($user, "User $data[username] does not exist");
            self::assertEquals($data['name'], $user->getName());
            self::assertTrue($passwordEncoder->isPasswordValid($user, $data['password']));
            // To verify roles we need to sort them
            $roles = $user->getRoleList();
            sort($roles);
            $dataRoles = $data['roles'];
            sort($dataRoles);
            self::assertEquals($dataRoles, $roles);

            // Verify the team
            if (isset($data['team'])) {
                self::assertNotNull($user->getTeam());
                $team = $user->getTeam();
                if (isset($data['team']['id'])) {
                    self::assertEquals($data['team']['id'], $team->getTeamid());
                }
                if (isset($data['team']['name'])) {
                    self::assertEquals($data['team']['name'], $team->getName());
                }
                if (isset($data['team']['category'])) {
                    self::assertNotNull($team->getCategory());
                    self::assertEquals($data['team']['category'], $team->getCategory()->getName());
                }
                if (isset($data['team']['members'])) {
                    self::assertEquals($data['team']['members'], $team->getMembers());
                }
            } else {
                self::assertNull($user->getTeam());
            }
        }

        // Check all unexpected users are not present
        foreach ($unexpectedUsers as $username) {
            $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);
            self::assertNull($user, "User $username should not exist");
        }
    }
}
