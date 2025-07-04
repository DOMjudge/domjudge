<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\Test\TeamWithExternalIdEqualsOneFixture;
use App\DataFixtures\Test\TeamWithExternalIdEqualsTwoFixture;
use App\DataTransferObject\ResultRow;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ImportExportService;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use Collator;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\SerializerInterface;

class ImportExportServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    /**
     * @dataProvider provideImportContestDataErrors
     */
    public function testImportContestDataErrors(mixed $data, string $expectedMessage): void
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
        yield [['name' => 'Some name'], 'Missing fields: one of (start_time, start-time), one of (id, short_name, short-name), duration'];
        yield [['short-name' => 'somename', 'start-time' => '2020-01-01 12:34:56'], 'Missing fields: one of (name, formal_name), duration'];
        yield [
            [
                'name'       => 'Test contest',
                'short-name' => 'test',
                'duration'   => '5:00:00',
                'start-time' => 'Invalid start time here',
            ],
            'Can not parse start-time'
        ];
        yield [
            [
                'name'       => 'Test contest',
                'id'         => 'test',
                'duration'   => '5:00:00',
                'start_time' => 'Invalid start time here',
            ],
            'Can not parse start_time'
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
            "Contest has errors:\n\n  • `name`: This value should not be blank.\n  • `shortname`: This value should not be blank."
        ];
    }

    /**
     * @dataProvider provideImportContestDataSuccess
     */
    public function testImportContestDataSuccess(mixed $data, string $expectedShortName, array $expectedProblems = []): void
    {
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        self::assertTrue($importExportService->importContestData($data, $message, $cid), 'Importing failed: ' . $message);
        self::assertNull($message);
        self::assertIsString($cid);

        $contest = $this->getContest($cid);

        self::assertEquals($data['name'], $contest->getName());
        self::assertEquals($data['public'] ?? true, $contest->getPublic());
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
                'public'                   => true,
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
                'public'                     => false,
            ],
            'test-contest',
        ];
    }

    /**
     * @dataProvider provideImportProblemsDataSuccess
     */
    public function testImportProblemsDataSuccess(mixed $data, array $expectedProblems): void
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
        $this->loadFixtures([TeamWithExternalIdEqualsOneFixture::class, TeamWithExternalIdEqualsTwoFixture::class]);

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
  team_id: domjudge
- id: team2
  username: team2
  name: Team 2
  password: password2
  type: team
  team_id: exteam
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

    public function testImportAccountsJsonError(): void
    {
        $accounts = <<<EOF
- id: team001
  username: team2//
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
EOF;

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $preCount = $em->getRepository(User::class)->count([]);

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'accounts-yaml');
        file_put_contents($fileName, $accounts);
        $file = new UploadedFile($fileName, 'accounts.yaml');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('accounts', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertEquals(-1, $importCount);
        self::assertMatchesRegularExpression('/Only alphanumeric characters and _-@. are allowed/', $message);

        $postCount = $em->getRepository(User::class)->count([]);
        self::assertEquals($preCount, $postCount);
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
            $expectedUsers = [...$expectedUsers, [
                'roles' => ['team'],
                'name' => 'Team 2 user a',
                'username' => 'team02a',
                'password' => 'password3',
                'ip' => '5.6.7.8',
                'team' => [
                    'id' => 2,
                ],
            ], [
                'roles' => ['team'],
                'name' => 'Team 2 user b',
                'username' => 'team02b',
                'password' => 'password4',
                'team' => [
                    'id' => 2,
                ],
            ]];
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

    public function testImportTeamsTsv(): void
    {
        // Example from the manual, but we have changed the ID's to not mix them with fixtures
        $teamsData = <<<EOF
File_Version	2
11	447047	24	¡i¡i¡	Lund University	LU	SWE	INST-42
12	447837	25	Pleading not FAUlty	Friedrich-Alexander-University Erlangen-Nuremberg	FAU	DEU	INST-43
13	447057	24	Another team from Lund	Lund University	LU	SWE	INST-42
EOF;

        $expectedTeams = [
            [
                'externalid' => '11',
                'icpcid' => '447047',
                'label' => null,
                'name' => '¡i¡i¡',
                'category' => [
                    'externalid' => '24',
                ],
                'affiliation' => [
                    'externalid' => '42',
                    'shortname' => 'LU',
                    'name' => 'Lund University',
                    'country' => 'SWE',
                ],
            ], [
                'externalid' => '12',
                'icpcid' => '447837',
                'label' => null,
                'name' => 'Pleading not FAUlty',
                'category' => [
                    'externalid' => '25',
                ],
                'affiliation' => [
                    'externalid' => '43',
                    'shortname' => 'FAU',
                    'name' => 'Friedrich-Alexander-University Erlangen-Nuremberg',
                    'country' => 'DEU',
                ],
            ], [
                'externalid' => '13',
                'icpcid' => '447057',
                'label' => null,
                'name' => 'Another team from Lund',
                'category' => [
                    'externalid' => '24',
                ],
                'affiliation' => [
                    'externalid' => '42',
                    'shortname' => 'LU',
                    'name' => 'Lund University',
                    'country' => 'SWE',
                ],
            ],
        ];

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'teams-tsv');
        file_put_contents($fileName, $teamsData);
        $file = new UploadedFile($fileName, 'teams.tsv');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importTsv('teams', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertNull($message);
        self::assertEquals(count($expectedTeams), $importCount);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        foreach ($expectedTeams as $data) {
            $team = $em->getRepository(Team::class)->findOneBy(['externalid' => $data['externalid']]);
            self::assertNotNull($team, "Team $data[name] does not exist");
            self::assertEquals($data['icpcid'], $team->getIcpcId());
            self::assertEquals($data['label'], $team->getLabel());
            self::assertEquals($data['name'], $team->getName());
            self::assertNull($team->getLocation());
            self::assertEquals($data['category']['externalid'], $team->getCategory()->getExternalid());
            self::assertEquals($data['affiliation']['externalid'], $team->getAffiliation()->getExternalid());
            self::assertEquals($data['affiliation']['shortname'], $team->getAffiliation()->getShortname());
            self::assertEquals($data['affiliation']['name'], $team->getAffiliation()->getName());
            self::assertEquals($data['affiliation']['country'], $team->getAffiliation()->getCountry());
        }
    }

    public function testImportTeamsJson(): void
    {
        // Example from the manual, but we have changed the ID's to not mix them with fixtures and
        // we explicitly use a different label for the first team and no label for the second
        // Also we explicitly test for the label '0', since that is a special case
        $teamsData = <<<EOF
[{
    "id": "11",
    "icpc_id": "447047",
    "label": "team1",
    "group_ids": ["24"],
    "name": "¡i¡i¡",
    "organization_id": "INST-42",
    "location": {"description": "AUD 10"}
}, {
    "id": "12",
    "icpc_id": "447837",
    "group_ids": ["25"],
    "name": "Pleading not FAUlty",
    "organization_id": "INST-43"
}, {
    "id": "13",
    "icpc_id": "123456",
    "label": "0",
    "group_ids": ["26"],
    "name": "Team with label 0",
    "organization_id": "INST-44"
}]
EOF;

        $expectedTeams = [
            [
                'externalid' => '11',
                'icpcid' => '447047',
                'label' => 'team1',
                'name' => '¡i¡i¡',
                'location' => 'AUD 10',
                'category' => [
                    'externalid' => '24',
                ],
                'affiliation' => [
                    'externalid' => 'INST-42',
                ],
            ], [
                'externalid' => '12',
                'icpcid' => '447837',
                'label' => null,
                'name' => 'Pleading not FAUlty',
                'location' => null,
                'category' => [
                    'externalid' => '25',
                ],
                'affiliation' => [
                    'externalid' => 'INST-43',
                ],
            ], [
                'externalid' => '13',
                'icpcid' => '123456',
                'label' => '0',
                'name' => 'Team with label 0',
                'location' => null,
                'category' => [
                    'externalid' => '26',
                ],
                'affiliation' => [
                    'externalid' => 'INST-44',
                ],
            ],
        ];

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'teams-json');
        file_put_contents($fileName, $teamsData);
        $file = new UploadedFile($fileName, 'teams.json');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('teams', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertNull($message);
        self::assertEquals(count($expectedTeams), $importCount);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        foreach ($expectedTeams as $data) {
            $team = $em->getRepository(Team::class)->findOneBy(['externalid' => $data['externalid']]);
            self::assertNotNull($team, "Team $data[name] does not exist");
            self::assertEquals($data['icpcid'], $team->getIcpcId());
            self::assertEquals($data['label'], $team->getLabel());
            self::assertEquals($data['location'], $team->getLocation());
            self::assertEquals($data['name'], $team->getName());
            self::assertEquals($data['category']['externalid'], $team->getCategory()->getExternalid());
            self::assertEquals($data['affiliation']['externalid'], $team->getAffiliation()->getExternalid());
        }
    }

    public function testImportTeamsJsonError(): void
    {
        $teamsData = <<<EOF
[{
    "id": "11",
    "icpc_id": "447047",
    "label": "team1",
    "group_ids": ["24"],
    "organization_id": "INST-42",
    "location": {"description": "AUD 10"}
}, {
    "id": "12",
    "icpc_id": "447837",
    "group_ids": ["25"],
    "name": "Pleading not FAUlty",
    "organization_id": "INST-43"
}]
EOF;
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $preCount = $em->getRepository(Team::class)->count([]);

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'teams-json');
        file_put_contents($fileName, $teamsData);
        $file = new UploadedFile($fileName, 'teams.json');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('teams', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertMatchesRegularExpression('/.*`name`: This value should not be blank.*/', $message);
        self::assertEquals(-1, $importCount);

        $postCount = $em->getRepository(Team::class)->count([]);
        self::assertEquals($preCount, $postCount);
    }

    public function testImportTeamsJsonErrorEmptyString(): void
    {
        $teamsData = <<<EOF
[{
    "id": "11",
    "icpc_id": "447047",
    "label": "team1",
    "name": "",
    "group_ids": ["24"],
    "organization_id": "INST-42",
    "location": {"description": "AUD 10"}
}, {
    "id": "12",
    "icpc_id": "447837",
    "group_ids": ["25"],
    "name": "Pleading not FAUlty",
    "organization_id": "INST-43"
}]
EOF;
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $preCount = $em->getRepository(Team::class)->count([]);

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'teams-json');
        file_put_contents($fileName, $teamsData);
        $file = new UploadedFile($fileName, 'teams.json');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('teams', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertMatchesRegularExpression('/.*`name`: This value should not be blank.*/', $message);
        self::assertEquals(-1, $importCount);

        $postCount = $em->getRepository(Team::class)->count([]);
        self::assertEquals($preCount, $postCount);
    }

    public function testImportGroupsTsv(): void
    {
        // Example from the manual
        $groupsData = <<<EOF
File_Version	1
13337	Companies
47	Participants
23	Spectators
EOF;

        $expectedGroups = [
            [
                'externalid' => '13337',
                'name' => 'Companies',
                'visible' => true,
            ], [
                'externalid' => '47',
                'name' => 'Participants',
                'visible' => true,
            ], [
                'externalid' => '23',
                'name' => 'Spectators',
                'visible' => true,
            ],
        ];

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'groups-tsv');
        file_put_contents($fileName, $groupsData);
        $file = new UploadedFile($fileName, 'groups.tsv');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importTsv('groups', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertNull($message);
        self::assertEquals(count($expectedGroups), $importCount);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        foreach ($expectedGroups as $data) {
            $category = $em->getRepository(TeamCategory::class)->findOneBy(['externalid' => $data['externalid']]);
            self::assertNotNull($category, "Team category $data[name] does not exist");
            self::assertEquals($data['name'], $category->getName());
            self::assertEquals($data['visible'], $category->getVisible());
        }
    }

    public function testImportGroupsJson(): void
    {
        // Example from the manual
        $groupsData = <<<EOF
[{
    "id": "13337",
    "icpc_id": "123",
    "name": "Companies",
    "hidden": true
}, {
    "id": "47",
    "name": "Participants"
}, {
    "id": "23",
    "name": "Spectators"
}]
EOF;

        $expectedGroups = [
            [
                'externalid' => '13337',
                'name' => 'Companies',
                'icpcid' => '123',
                'visible' => false,
            ], [
                'externalid' => '47',
                'name' => 'Participants',
                'visible' => true,
            ], [
                'externalid' => '23',
                'name' => 'Spectators',
                'visible' => true,
            ],
        ];

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'groups-json');
        file_put_contents($fileName, $groupsData);
        $file = new UploadedFile($fileName, 'groups.json');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('groups', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertNull($message);
        self::assertEquals(count($expectedGroups), $importCount);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        foreach ($expectedGroups as $data) {
            $category = $em->getRepository(TeamCategory::class)->findOneBy(['externalid' => $data['externalid']]);
            self::assertNotNull($category, "Team cagegory $data[name] does not exist");
            self::assertEquals($data['icpcid'] ?? null, $category->getIcpcId());
            self::assertEquals($data['name'], $category->getName());
            self::assertEquals($data['visible'], $category->getVisible());
        }
    }

    public function testImportGroupsJsonError(): void
    {
        // Example from the manual
        $groupsData = <<<EOF
[{
    "id": "13337",
    "icpc_id": "123",
    "hidden": true
}, {
    "id": "47",
    "name": "Participants"
}]
EOF;

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $preCount = $em->getRepository(TeamCategory::class)->count([]);

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'groups-json');
        file_put_contents($fileName, $groupsData);
        $file = new UploadedFile($fileName, 'groups.json');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('groups', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertMatchesRegularExpression('/.*`name`: This value should not be blank.*/', $message);
        self::assertEquals(-1, $importCount);

        $postCount = $em->getRepository(TeamCategory::class)->count([]);
        self::assertEquals($preCount, $postCount);
    }


    public function testImportOrganizationsJson(): void
    {
        // Example from the manual
        $organizationsData = <<<EOF
[{
    "id": "INST-42",
    "icpc_id": "42",
    "name": "LU",
    "formal_name": "Lund University",
    "country": "SWE"
}, {
    "id": "INST-43",
    "icpc_id": "43",
    "name": "FAU",
    "formal_name": "Friedrich-Alexander-University Erlangen-Nuremberg",
    "country": "DEU"
}]
EOF;

        $expectedOrganizations = [
            [
                'externalid' => 'INST-42',
                'icpcid' => '42',
                'shortname' => 'LU',
                'name' => 'Lund University',
                'country' => 'SWE',
            ], [
                'externalid' => 'INST-43',
                'icpcid' => '43',
                'shortname' => 'FAU',
                'name' => 'Friedrich-Alexander-University Erlangen-Nuremberg',
                'country' => 'DEU',
            ],
        ];

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'organizations-json');
        file_put_contents($fileName, $organizationsData);
        $file = new UploadedFile($fileName, 'organizations.json');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('organizations', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertNull($message);
        self::assertEquals(count($expectedOrganizations), $importCount);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        foreach ($expectedOrganizations as $data) {
            $affiliation = $em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $data['externalid']]);
            self::assertNotNull($affiliation, "Team affiliation $data[name] does not exist");
            self::assertEquals($data['icpcid'], $affiliation->getIcpcId());
            self::assertEquals($data['shortname'], $affiliation->getShortname());
            self::assertEquals($data['name'], $affiliation->getName());
            self::assertEquals($data['country'], $affiliation->getCountry());
        }
    }

    public function testImportOrganizationsErrorJson(): void
    {
        // Example from the manual
        $organizationsData = <<<EOF
[{
    "id": "INST-42",
    "icpc_id": "42",
    "name": "LU",
    "formal_name": "Lund University",
    "country": "XXX"
}, {
    "id": "INST-43",
    "icpc_id": "43",
    "name": "FAU",
    "formal_name": "Friedrich-Alexander-University Erlangen-Nuremberg",
    "country": "DEU"
}]
EOF;

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $preCount = $em->getRepository(TeamAffiliation::class)->count([]);

        $fileName = tempnam(static::getContainer()->get(DOMJudgeService::class)->getDomjudgeTmpDir(), 'organizations-json');
        file_put_contents($fileName, $organizationsData);
        $file = new UploadedFile($fileName, 'organizations.json');
        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);
        $importCount = $importExportService->importJson('organizations', $file, $message);
        // Remove the file, we don't need it anymore.
        unlink($fileName);

        self::assertMatchesRegularExpression('/ISO3166-1 alpha-3 values are allowed/', $message);
        self::assertEquals(-1, $importCount);

        $postCount = $em->getRepository(TeamAffiliation::class)->count([]);
        self::assertEquals($preCount, $postCount);
    }


    protected function getContest(int|string $cid): Contest
    {
        // First clear the entity manager to have all data.
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Contest::class)->findOneBy(['externalid' => $cid]);
    }

    /**
     * @dataProvider provideGetResultsData
     */
    public function testGetResultsData(bool $full, bool $honors, string $dataSet, string $expectedResultsFile): void
    {
        // Set up some results we can test with
        // This data is based on the ICPC World Finals 47
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $startTime = new DateTimeImmutable('2023-05-01 08:00:00');

        $medalData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/' . $dataSet . '/sample-medals.json'), true);

        $contest = (new Contest())
            ->setName('ICPC World Finals 47')
            ->setShortname('wf47')
            ->setStarttimeString($startTime->format(DateTimeInterface::ATOM))
            ->setEndtimeString($startTime->add(new DateInterval('PT5H'))->format(DateTimeInterface::ATOM))
            ->setMedalsEnabled(true)
            ->setGoldMedals($medalData['medals']['gold'])
            ->SetSilverMedals($medalData['medals']['silver'])
            ->setBronzeMedals($medalData['medals']['bronze']);

        $groupsById = [];
        $groupsData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/' . $dataSet . '/sample-groups.json'), true);
        foreach ($groupsData as $groupData) {
            $group = (new TeamCategory())
                ->setExternalid($groupData['id'])
                ->setName($groupData['name'])
                ->setSortorder(37);
            $em->persist($group);
            $em->flush();
            $groupsById[$group->getExternalid()] = $group;
            if (in_array($group->getExternalid(), $medalData['medal_categories'], true)) {
                $contest->addMedalCategory($group);
            }
        }

        $em->persist($contest);
        $em->flush();

        $teamsData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/'. $dataSet . '/sample-teams.json'), true);
        /** @var array<string,Team> $teamsById */
        $teamsById = [];
        /** @var array<string,Team> $teamsByIcpcId */
        $teamsByIcpcId = [];
        foreach ($teamsData as $teamData) {
            $team = (new Team())
                ->setExternalid($teamData['id'])
                ->setIcpcid($teamData['icpc_id'])
                ->setName($teamData['name'])
                ->setDisplayName($teamData['display_name'])
                ->setCategory($groupsById[$teamData['group_ids'][0]]);
            $em->persist($team);
            $em->flush();
            $teamsById[$team->getExternalid()] = $team;
            $teamsByIcpcId[$team->getIcpcId()] = $team;
        }

        $problemsData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/'. $dataSet . '/sample-problems.json'), true);
        $contestProblemsById = [];
        foreach ($problemsData as $problemData) {
            $problem = (new Problem())
                ->setExternalid($problemData['id'])
                ->setName($problemData['name']);
            $contestProblem = (new ContestProblem())
                ->setProblem($problem)
                ->setContest($contest)
                ->setColor($problemData['rgb'])
                ->setShortname($problemData['label']);
            $em->persist($problem);
            $em->persist($contestProblem);
            $em->flush();
            $contestProblemsById[$contestProblem->getExternalid()] = $contestProblem;
        }

        $cpp = $em->getRepository(Language::class)->find('cpp');

        // We use direct queries here to speed this up
        $submissionInsertQuery = $em->getConnection()->prepare('INSERT INTO submission (teamid, cid, probid, langid, submittime) VALUES (:teamid, :cid, :probid, :langid, :submittime)');
        $judgingInsertQuery = $em->getConnection()->prepare('INSERT INTO judging (uuid, submitid, result) VALUES (:uuid, :submitid, :result)');

        $submissionInsertQuery->bindValue('cid', $contest->getCid());
        $submissionInsertQuery->bindValue('langid', $cpp->getLangid());

        $scoreboardData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/'. $dataSet . '/sample-scoreboard.json'), true);
        foreach ($scoreboardData['rows'] as $scoreboardRow) {
            $team = $teamsById[$scoreboardRow['team_id']];
            $submissionInsertQuery->bindValue('teamid', $team->getTeamid());
            foreach ($scoreboardRow['problems'] as $problemData) {
                if ($problemData['solved']) {
                    $contestProblem = $contestProblemsById[$problemData['problem_id']];
                    // Add fake submission for this problem. First add wrong ones
                    for ($i = 0; $i < $problemData['num_judged'] - 1; $i++) {
                        $submissionInsertQuery->bindValue('probid', $contestProblem->getProbid());
                        $submissionInsertQuery->bindValue('submittime', $startTime
                            ->add(new DateInterval('PT' . $problemData['time'] . 'M'))
                            ->sub(new DateInterval('PT1M'))
                            ->getTimestamp());
                        $submissionInsertQuery->executeQuery();
                        $submitId = $em->getConnection()->lastInsertId();
                        $judgingInsertQuery->bindValue('uuid', Uuid::uuid4()->toString());
                        $judgingInsertQuery->bindValue('submitid', $submitId);
                        $judgingInsertQuery->bindValue('result', 'wrong-awnser');
                        $judgingInsertQuery->executeQuery();
                    }
                    // Add correct submission
                    $submissionInsertQuery->bindValue('probid', $contestProblem->getProbid());
                    $submissionInsertQuery->bindValue('submittime', $startTime
                        ->add(new DateInterval('PT' . $problemData['time'] . 'M'))
                        ->getTimestamp());
                    $submissionInsertQuery->executeQuery();
                    $submitId = $em->getConnection()->lastInsertId();
                    $judgingInsertQuery->bindValue('uuid', Uuid::uuid4()->toString());
                    $judgingInsertQuery->bindValue('submitid', $submitId);
                    $judgingInsertQuery->bindValue('result', 'correct');
                    $judgingInsertQuery->executeQuery();
                }
            }
        }

        /** @var ScoreboardService $scoreboardService */
        $scoreboardService = static::getContainer()->get(ScoreboardService::class);
        $scoreboardService->refreshCache($contest);

        /** @var ImportExportService $importExportService */
        $importExportService = static::getContainer()->get(ImportExportService::class);

        /** @var RequestStack $requestStack */
        $requestStack = static::getContainer()->get(RequestStack::class);
        $request = new Request();
        $request->cookies->set('domjudge_cid', (string)$contest->getCid());
        $requestStack->push($request);

        $results = $importExportService->getResultsData(37, $full, $honors);

        $resultsContents = file_get_contents(__DIR__ . '/../Fixtures/' . $dataSet . '/' . $expectedResultsFile);
        $resultsContents = substr($resultsContents, strpos($resultsContents, "\n") + 1);
        // Prefix file with a fake header, so we can deserialize them
        $resultsContents = "team_id\trank\taward\tnum_solved\ttotal_time\ttime_of_last_submission\tgroup_winner\n" . $resultsContents;

        $serializer = static::getContainer()->get(SerializerInterface::class);

        $expectedResults = $serializer->deserialize($resultsContents, ResultRow::class . '[]', 'csv', [
            CsvEncoder::DELIMITER_KEY => "\t",
        ]);

        self::assertEquals($expectedResults, $results);
    }

    public function provideGetResultsData(): Generator
    {
        yield [true, true, 'wf', 'results-full-honors.tsv'];
        yield [false, true, 'wf', 'results-wf-honors.tsv'];
        yield [true, false, 'wf', 'results-full-ranked.tsv'];
        yield [false, false, 'wf', 'results-wf-ranked.tsv'];
        yield [true, true, 'sample', 'results-full-honors.tsv'];
        yield [false, true, 'sample', 'results-wf-honors.tsv'];
        yield [true, false, 'sample', 'results-full-ranked.tsv'];
        yield [false, true, 'sample', 'results-wf-honors.tsv'];
    }
}
