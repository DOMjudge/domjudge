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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ImportExportServiceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    /**
     * @dataProvider provideImportContestDataErrors
     */
    public function testImportContestDataErrors($data, string $expectedMessage): void
    {
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        self::assertFalse($importExportService->importContestData($data, $message, $cid));
        self::assertEquals($expectedMessage, $message);
        self::assertNull($cid);
    }

    public function provideImportContestDataErrors(): Generator
    {
        yield [[], 'Error parsing YAML file.'];
        yield [['name' => 'Some name'], 'Missing fields: one of (start_time, start-time), one of (id, short-name), duration'];
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
                'name'       => 'Test contest',
                'id'         => 'test',
                'duration'   => '5:00:00',
                'start_time' => 'Invalid start time here',
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
                'name'                       => 'Test contest',
                'id'                         => 'test',
                'duration'                   => '5:00:00',
                'start_time'                 => '2020-01-01T12:34:56+02:00',
                'scoreboard_freeze_duration' => '6:00:00',
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
     * @dataProvider provideImportContestDataSuccess
     */
    public function testImportContestDataSuccess($data, string $expectedShortName, array $expectedProblems = []): void
    {
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        self::assertTrue($importExportService->importContestData($data, $message, $cid), 'Importing failed: ' . $message);
        self::assertNull($message);
        self::assertIsString($cid);

        $contest = $this->getContest($cid);

        self::assertEquals($data['name'], $contest->getName());
        self::assertEquals($expectedShortName, $contest->getShortname());

        $problems = [];
        /** @var ContestProblem $problem */
        foreach ($contest->getProblems() as $problem) {
            $problems[$problem->getShortname()] = $problem->getProblem()->getExternalid();
        }

        self::assertEquals($expectedProblems, $problems);
    }

    public function provideImportContestDataSuccess(): Generator
    {
        // YAML format:

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
        // Real life example from NWERC 2020 practice session, including problems.
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

        // JSON (API) format:
        yield [
            [
                'name'                       => 'Some test contest',
                'id'                         => 'test-contest',
                'duration'                   => '5:00:00',
                'start_time'                 => '2020-01-01T12:34:56+02:00',
                'scoreboard_freeze_duration' => '1:00:00',
            ],
            'test-contest',
        ];
    }

    /**
     * @dataProvider provideImportProblemsDataSuccess
     */
    public function testImportProblemsDataSuccess($data, array $expectedProblems): void
    {
        // First create a new contest by import it
        $contestData = [
            'name'                       => 'Some test contest',
            'id'                         => 'test-contest',
            'duration'                   => '5:00:00',
            'start_time'                 => '2020-01-01T12:34:56+02:00',
            'scoreboard_freeze_duration' => '1:00:00',
        ];
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importExportService->importContestData($contestData, $message, $cid);

        $contest = $this->getContest($cid);
        self::assertTrue($importExportService->importProblemsData($contest, $data, $ids));
        self::assertNotNull($ids);
        self::assertCount(count($expectedProblems), $ids);

        $contest = $this->getContest($cid);

        $problems = [];
        /** @var ContestProblem $problem */
        foreach ($contest->getProblems() as $problem) {
            $problems[$problem->getShortname()] = [
                'name'       => $problem->getProblem()->getName(),
                'externalid' => $problem->getProblem()->getExternalid(),
                'timelimit'  => $problem->getProblem()->getTimelimit(),
                'color'      => $problem->getColor(),
            ];
        }

        self::assertEquals($expectedProblems, $problems);
    }

    public function provideImportProblemsDataSuccess(): Generator
    {
        yield [
            [
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
            [
                'A' => [
                    'name'       => 'anothereruption',
                    'externalid' => 'anothereruption',
                    'timelimit'  => 10,
                    'color'      => '#FE9DAF',
                ],
                'B' => [
                    'name'       => 'brokengears',
                    'externalid' => 'brokengears',
                    'timelimit'  => 10,
                    'color'      => '#008100',
                ],
                'C' => [
                    'name'       => 'cheating',
                    'externalid' => 'cheating',
                    'timelimit'  => 10,
                    'color'      => '#FF7109',
                ],
            ],
        ];
        yield [
            [
                [
                    'ordinal'    => 0,
                    'id'         => 'accesspoints',
                    'label'      => 'A',
                    'time_limit' => 2,
                    'name'       => 'Access Points',
                    'rgb'        => '#FF0000',
                    'color'      => 'red'
                ],
                [
                    'ordinal'    => 1,
                    'id'         => 'brexitnegotiations',
                    'label'      => 'B',
                    'time_limit' => 6,
                    'name'       => 'Brexit Negotiations',
                    'rgb'        => '#0422D8',
                    'color'      => 'mediumblue'
                ],
                [
                    'ordinal'    => 2,
                    'id'         => 'circuitdesign',
                    'label'      => 'C',
                    'time_limit' => 6,
                    'name'       => 'Circuit Board Design',
                    'rgb'        => '#008100',
                    'color'      => 'green'
                ],
            ],
            [
                'A' => [
                    'name'       => 'Access Points',
                    'externalid' => 'accesspoints',
                    'timelimit'  => 2,
                    'color'      => '#FF0000',
                ],
                'B' => [
                    'name'       => 'Brexit Negotiations',
                    'externalid' => 'brexitnegotiations',
                    'timelimit'  => 6,
                    'color'      => '#0422D8',
                ],
                'C' => [
                    'name'       => 'Circuit Board Design',
                    'externalid' => 'circuitdesign',
                    'timelimit'  => 6,
                    'color'      => '#008100',
                ],
            ],
        ];
    }

    public function testImportAccountsTsvSuccess(): void
    {
        // We test all account types twice:
        // - Team without postfix
        // - Team with postfix
        // - Judge
        // - Admin
        // - Analyst (will be ignored)
        // We also set the IP address for some accounts.
        $accounts = <<<EOF
accounts	1
team	Team 1	team001	password1
team	Team 2	team2	password2	1.2.3.4
team	Team 2 user a	team02a	password3	5.6.7.8
team	Team 2 user b	team02b	password4
judge	Judge member 1	judge1	password5
judge	Another judge member	judge2	password6	9.10.11.12
jury	Wrongly named judge member	judge3	password6	9.10.11.12
admin	Some admin	adminx	password7
admin	Another admin	adminy	password8
analyst	Analyst number 1	analyst1	password9	13.14.15.16
analyst	Analyst two	analyst2	password10
balloon	Balloon station	balloon1	password11
balloon	Backup balloon station	balloon2	password12
EOF;

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'accounts-tsv');
        file_put_contents($fileName, $accounts);
        $file = new UploadedFile($fileName, 'accounts.tsv');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importTsv('accounts', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        $this->testImportAccounts($importCount, $message, true);
    }

    public function testImportAccountsJsonSuccess(): void
    {
        // We test all account types twice:
        // - Team
        // - Judge
        // - Admin
        // - Analyst (will be ignored)
        // We also set the IP address for some accounts.
        $accounts = <<<EOF
- id: team001
  username: team001
  name: Team 1
  password: password1
  type: team
  team_id: 1
- id: team2
  username: team2
  name: Team 2
  password: password2
  type: team
  team_id: 2
  ip: 1.2.3.4
- id: judge1
  username: judge1
  name: Judge member 1
  password: password5
  type: judge
- id: judge2
  username: judge2
  name: Another judge member
  password: password6
  type: judge
  ip: 9.10.11.12
- id: judge3
  username: judge3
  name: Wrongly named judge member
  password: password6
  type: jury
  ip: 9.10.11.12
- id: adminx
  username: adminx
  name: Some admin
  password: password7
  type: admin
- id: adminy
  username: adminy
  name: Another admin
  password: password8
  type: admin
- id: analyst1
  username: analyst1
  name: Analyst number 1
  password: password9
  type: analyst
  ip: 13.14.15.16
- id: analyst2
  username: analyst2
  name: Analyst two
  password: password10
  type: analyst
- id: balloon1
  username: balloon1
  name: Balloon station
  password: password11
  type: balloon
- id: balloon2
  username: balloon2
  name: Backup balloon station
  password: password12
  type: balloon
EOF;

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'accounts-yaml');
        file_put_contents($fileName, $accounts);
        $file = new UploadedFile($fileName, 'accounts.yaml');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('accounts', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        $this->testImportAccounts($importCount, $message, false);
    }

    protected function testImportAccounts(int $importCount, ?string $message, bool $forTsv): void
    {
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
                'roles' => ['jury', 'team'],
                'name' => 'Wrongly named judge member',
                'username' => 'judge3',
                'password' => 'password6',
                'ip' => '9.10.11.12',
                'team' => [
                    'name' => 'Wrongly named judge member',
                    'category' => 'Jury',
                    'members' => 'Wrongly named judge member',
                ],
            ],
            [
                'roles' => ['admin','team'],
                'name' => 'Some admin',
                'username' => 'adminx',
                'password' => 'password7',
                'team' => [
                    'name' => 'Some admin',
                    'category' => 'Jury',
                    'members' => 'Some admin',
                ],
            ],
            [
                'roles' => ['admin','team'],
                'name' => 'Another admin',
                'username' => 'adminy',
                'password' => 'password8',
                'team' => [
                    'name' => 'Another admin',
                    'category' => 'Jury',
                    'members' => 'Another admin',
                ],
            ],
            [
                'roles' => ['balloon'],
                'name' => 'Balloon station',
                'username' => 'balloon1',
                'password' => 'password11',
            ],
            [
                'roles' => ['balloon'],
                'name' => 'Backup balloon station',
                'username' => 'balloon2',
                'password' => 'password12',
            ],
        ];
        if ($forTsv) {
            $expectedUsers = array_merge($expectedUsers, [
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
            ]);
        }
        $unexpectedUsers = ['analyst1', 'analyst2'];

        self::assertEquals(count($expectedUsers), $importCount);
        self::assertNull($message);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $userPasswordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Check for all expected users.
        foreach ($expectedUsers as $data) {
            $user = $em->getRepository(User::class)->findOneBy(['username' => $data['username']]);
            self::assertNotNull($user, "User $data[username] does not exist");
            self::assertEquals($data['name'], $user->getName());
            self::assertTrue($userPasswordHasher->isPasswordValid($user, $data['password']));
            // To verify roles we need to sort them.
            $roles = $user->getRoleList();
            sort($roles);
            $dataRoles = $data['roles'];
            sort($dataRoles);
            self::assertEquals($dataRoles, $roles);

            // Verify the team.
            if (isset($data['team'])) {
                self::assertNotNull($user->getTeam(), $data['username']);
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
                    self::assertEquals($data['team']['members'], $team->getPublicDescription());
                }
                if (isset($data['team']['description'])) {
                    self::assertEquals($data['team']['description'], $team->getPublicDescription());
                }
            } else {
                self::assertNull($user->getTeam());
            }
        }

        // Check all unexpected users are not present.
        foreach ($unexpectedUsers as $username) {
            $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);
            self::assertNull($user, "User $username should not exist");
        }
    }

    protected function getContest($cid): Contest
    {
        // First clear the entity manager to have all data.
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $config = static::getContainer()->get(ConfigurationService::class);
        $dataSource = $config->get('data_source');
        if ($dataSource === DOMJudgeService::DATA_SOURCE_LOCAL) {
            return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Contest::class)->find($cid);
        } else {
            return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Contest::class)->findOneBy(['externalid' => $cid]);
        }
    }
}
