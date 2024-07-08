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
use App\Service\DOMJudgeService;
use App\Service\ImportExportService;
use App\Service\ScoreboardService;
use App\Tests\Unit\BaseTestCase;
use Collator;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        yield [
            ['name' => 'Some name'],
            'Missing fields: one of (start_time, start-time), one of (id, short_name, short-name), duration',
        ];
        yield [
            ['short-name' => 'somename', 'start-time' => '2020-01-01 12:34:56'],
            'Missing fields: one of (name, formal_name), duration',
        ];
        yield [
            [
                'name' => 'Test contest',
                'short-name' => 'test',
                'duration' => '5:00:00',
                'start-time' => 'Invalid start time here',
            ],
            'Can not parse start-time',
        ];
        yield [
            [
                'name' => 'Test contest',
                'id' => 'test',
                'duration' => '5:00:00',
                'start_time' => 'Invalid start time here',
            ],
            'Can not parse start_time',
        ];
        yield [
            [
                'name' => 'Test contest',
                'short-name' => 'test',
                'duration' => '5:00:00',
                'start-time' => '2020-01-01T12:34:56+02:00',
                'scoreboard-freeze-length' => '6:00:00',
            ],
            'Freeze duration is longer than contest length',
        ];
        yield [
            [
                'name' => 'Test contest',
                'id' => 'test',
                'duration' => '5:00:00',
                'start_time' => '2020-01-01T12:34:56+02:00',
                'scoreboard_freeze_duration' => '6:00:00',
            ],
            'Freeze duration is longer than contest length',
        ];
        yield [
            [
                'name' => '',
                'short-name' => '',
                'duration' => '5:00:00',
                'start-time' => '2020-01-01T12:34:56+02:00',
                'scoreboard-freeze-length' => '30:00',
            ],
            "Contest has errors:\n\nname: This value should not be blank.\nshortname: This value should not be blank.",
        ];
    }

    /**
     * @dataProvider provideImportContestDataSuccess
     */
    public function testImportContestDataSuccess(
        mixed $data,
        string $expectedShortName,
        array $expectedProblems = []
    ): void {
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
                'name' => 'Some test contest',
                'short-name' => 'test-contest',
                'duration' => '5:00:00',
                'start-time' => '2020-01-01T12:34:56+02:00',
                'scoreboard-freeze-length' => '1:00:00',
            ],
            'test-contest',
        ];
        // - Freeze length without hours
        // - Set a short name with invalid characters
        // - Use DateTime object for start time
        yield [
            [
                'name' => 'Some test contest',
                'short-name' => 'test-contest $-@ test',
                'duration' => '5:00:00',
                'start-time' => new DateTime('2020-01-01T12:34:56+02:00'),
                'scoreboard-freeze-length' => '30:00',
            ],
            'test-contest__-__test',
        ];
        // Real life example from NWERC 2020 practice session, including problems.
        yield [
            [
                'duration' => '2:00:00',
                'name' => 'NWERC 2020 Practice Session',
                'penalty-time' => '20',
                'scoreboard-freeze-length' => '30:00',
                'short-name' => 'practice',
                'start-time' => '2021-03-27 09:00:00+00:00',
                'public' => true,
                'problems' => [
                    [
                        'color' => '#FE9DAF',
                        'letter' => 'A',
                        'rgb' => '#FE9DAF',
                        'short-name' => 'anothereruption',
                    ],
                    [
                        'color' => '#008100',
                        'letter' => 'B',
                        'rgb' => '#008100',
                        'short-name' => 'brokengears',
                    ],
                    [
                        'color' => '#FF7109',
                        'letter' => 'C',
                        'rgb' => '#FF7109',
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
                'name' => 'Some test contest',
                'id' => 'test-contest',
                'duration' => '5:00:00',
                'start_time' => '2020-01-01T12:34:56+02:00',
                'scoreboard_freeze_duration' => '1:00:00',
                'public' => false,
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
            'name' => 'Some test contest',
            'id' => 'test-contest',
            'duration' => '5:00:00',
            'start_time' => '2020-01-01T12:34:56+02:00',
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
                'name' => $problem->getProblem()->getName(),
                'externalid' => $problem->getProblem()->getExternalid(),
                'timelimit' => $problem->getProblem()->getTimelimit(),
                'color' => $problem->getColor(),
            ];
        }

        self::assertEquals($expectedProblems, $problems);
    }

    public function provideImportProblemsDataSuccess(): Generator
    {
        yield [
            [
                [
                    'color' => '#FE9DAF',
                    'letter' => 'A',
                    'rgb' => '#FE9DAF',
                    'short-name' => 'anothereruption',
                ],
                [
                    'color' => '#008100',
                    'letter' => 'B',
                    'rgb' => '#008100',
                    'short-name' => 'brokengears',
                ],
                [
                    'color' => '#FF7109',
                    'letter' => 'C',
                    'rgb' => '#FF7109',
                    'short-name' => 'cheating',
                ],
            ],
            [
                'A' => [
                    'name' => 'anothereruption',
                    'externalid' => 'anothereruption',
                    'timelimit' => 10,
                    'color' => '#FE9DAF',
                ],
                'B' => [
                    'name' => 'brokengears',
                    'externalid' => 'brokengears',
                    'timelimit' => 10,
                    'color' => '#008100',
                ],
                'C' => [
                    'name' => 'cheating',
                    'externalid' => 'cheating',
                    'timelimit' => 10,
                    'color' => '#FF7109',
                ],
            ],
        ];
        yield [
            [
                [
                    'ordinal' => 0,
                    'id' => 'accesspoints',
                    'label' => 'A',
                    'time_limit' => 2,
                    'name' => 'Access Points',
                    'rgb' => '#FF0000',
                    'color' => 'red',
                ],
                [
                    'ordinal' => 1,
                    'id' => 'brexitnegotiations',
                    'label' => 'B',
                    'time_limit' => 6,
                    'name' => 'Brexit Negotiations',
                    'rgb' => '#0422D8',
                    'color' => 'mediumblue',
                ],
                [
                    'ordinal' => 2,
                    'id' => 'circuitdesign',
                    'label' => 'C',
                    'time_limit' => 6,
                    'name' => 'Circuit Board Design',
                    'rgb' => '#008100',
                    'color' => 'green',
                ],
            ],
            [
                'A' => [
                    'name' => 'Access Points',
                    'externalid' => 'accesspoints',
                    'timelimit' => 2,
                    'color' => '#FF0000',
                ],
                'B' => [
                    'name' => 'Brexit Negotiations',
                    'externalid' => 'brexitnegotiations',
                    'timelimit' => 6,
                    'color' => '#0422D8',
                ],
                'C' => [
                    'name' => 'Circuit Board Design',
                    'externalid' => 'circuitdesign',
                    'timelimit' => 6,
                    'color' => '#008100',
                ],
            ],
        ];
    }

    public function testImportAccountsTsvSuccess(): void
    {
        $this->loadFixtures([
            TeamWithExternalIdEqualsOneFixture::class,
            TeamWithExternalIdEqualsTwoFixture::class,
        ]);

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

        self::assertEquals(0, $importCount);
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
                'roles' => ['admin', 'team'],
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
                'roles' => ['admin', 'team'],
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
            $expectedUsers = [
                ...$expectedUsers,
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
            ];
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
            ],
            [
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
            ],
            [
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
            ],
            [
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

        self::assertMatchesRegularExpression('/name: This value should not be blank./', $message);
        self::assertEquals(0, $importCount);

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

        self::assertMatchesRegularExpression('/name: This value should not be blank./', $message);
        self::assertEquals(0, $importCount);

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
            ],
            [
                'externalid' => '47',
                'name' => 'Participants',
                'visible' => true,
            ],
            [
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
            ],
            [
                'externalid' => '47',
                'name' => 'Participants',
                'visible' => true,
            ],
            [
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

        self::assertMatchesRegularExpression('/name: This value should not be blank/', $message);
        self::assertEquals(0, $importCount);

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
            ],
            [
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
        self::assertEquals(0, $importCount);

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
    public function testGetResultsData(bool $full): void
    {
        // Set up some results we can test with
        // This data is based on the ICPC World Finals 47
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $startTime = new DateTimeImmutable('2023-05-01 08:00:00');

        $contest = (new Contest())
            ->setName('ICPC World Finals 47')
            ->setShortname('wf47')
            ->setStarttimeString($startTime->format(DateTimeInterface::ATOM))
            ->setEndtimeString($startTime->add(new DateInterval('PT5H'))->format(DateTimeInterface::ATOM));
        $em->persist($contest);
        $em->flush();

        $groupsById = [];
        $groupsData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/sample-groups.json'), true);
        foreach ($groupsData as $groupData) {
            $group = (new TeamCategory())
                ->setExternalid($groupData['id'])
                ->setName($groupData['name'])
                ->setSortorder(37);
            $em->persist($group);
            $em->flush();
            $groupsById[$group->getExternalid()] = $group;
        }
        $teamsData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/sample-teams.json'), true);
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

        $problemsData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/sample-problems.json'), true);
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

        $scoreboardData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/sample-scoreboard.json'), true);
        foreach ($scoreboardData['rows'] as $scoreboardRow) {
            $team = $teamsById[$scoreboardRow['team_id']];
            foreach ($scoreboardRow['problems'] as $problemData) {
                if ($problemData['solved']) {
                    $contestProblem = $contestProblemsById[$problemData['problem_id']];
                    // Add fake submission for this problem. First add wrong ones
                    for ($i = 0; $i < $problemData['num_judged'] - 1; $i++) {
                        $submissionInsertQuery->executeQuery([
                            'teamid' => $team->getTeamid(),
                            'cid' => $contest->getCid(),
                            'probid' => $contestProblem->getProbid(),
                            'langid' => $cpp->getLangid(),
                            'submittime' => $startTime
                                ->add(new DateInterval('PT' . $problemData['time'] . 'M'))
                                ->sub(new DateInterval('PT1M'))
                                ->getTimestamp(),
                        ]);
                        $submitId = $em->getConnection()->lastInsertId();
                        $judgingInsertQuery->executeQuery([
                            'uuid' => Uuid::uuid4()->toString(),
                            'submitid' => $submitId,
                            'result' => 'wrong-awnser',
                        ]);
                    }
                    // Add correct submission
                    $submissionInsertQuery->executeQuery([
                        'teamid' => $team->getTeamid(),
                        'cid' => $contest->getCid(),
                        'probid' => $contestProblem->getProbid(),
                        'langid' => $cpp->getLangid(),
                        'submittime' => $startTime
                            ->add(new DateInterval('PT' . $problemData['time'] . 'M'))
                            ->getTimestamp(),
                    ]);
                    $submitId = $em->getConnection()->lastInsertId();
                    $judgingInsertQuery->executeQuery([
                        'uuid' => Uuid::uuid4()->toString(),
                        'submitid' => $submitId,
                        'result' => 'correct',
                    ]);
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

        $results = $importExportService->getResultsData(37, $full);
        $expectedResults = [
            new ResultRow('870679', 1, 'Gold Medal', 9, 995, 216, 'Northern Eurasia'),
            new ResultRow('870257', 2, 'Gold Medal', 9, 1068, 227, 'Asia East'),
            new ResultRow('870678', 3, 'Gold Medal', 9, 1143, 206),
            new ResultRow('873624', 4, 'Gold Medal', 9, 1304, 292, 'Europe'),
            new ResultRow('870259', 5, 'Silver Medal', 9, 1524, 274),
            new ResultRow('870260', 6, 'Silver Medal', 8, 1013, 281),
            new ResultRow('928309', 7, 'Silver Medal', 8, 1102, 230, 'Asia Pacific'),
            new ResultRow('870037', 8, 'Silver Medal', 8, 1120, 268, 'North America'),
            new ResultRow('870583', 9, 'Bronze Medal', 8, 1121, 260),
            new ResultRow('870584', 10, 'Bronze Medal', 8, 1424, 291),
            new ResultRow('870051', 11, 'Bronze Medal', 7, 842, 279),
            new ResultRow('870647', 12, 'Bronze Medal', 7, 940, 259),
            new ResultRow('870670', 13, 'Highest Honors', 7, 955, 291, 'Latin America'),
            new ResultRow('870585', $full ? 14 : 13, 'Highest Honors', 7, 962, 290),
            new ResultRow('870649', $full ? 14 : 13, 'Highest Honors', 7, 962, 290),
            new ResultRow('870271', $full ? 16 : 13, 'Highest Honors', 7, 980, 283),
            new ResultRow('870642', $full ? 17 : 13, 'Highest Honors', 7, 1021, 256),
            new ResultRow('870045', $full ? 18 : 13, 'Highest Honors', 7, 1076, 271),
            new ResultRow('870582', $full ? 19 : 13, 'Highest Honors', 7, 1128, 278),
            new ResultRow('870654', $full ? 20 : 13, 'Highest Honors', 7, 1130, 284),
            new ResultRow('868994', $full ? 21 : 13, 'Highest Honors', 7, 1381, 296),
            new ResultRow('870644', 22, 'High Honors', 6, 510, 187),
            new ResultRow('870646', $full ? 23 : 22, 'High Honors', 6, 642, 216),
            new ResultRow('870680', $full ? 24 : 22, 'High Honors', 6, 645, 218),
            new ResultRow('881825', $full ? 25 : 22, 'High Honors', 6, 680, 237),
            new ResultRow('871349', $full ? 26 : 22, 'High Honors', 6, 683, 246),
            new ResultRow('870692', $full ? 27 : 22, 'High Honors', 6, 708, 243),
            new ResultRow('870041', $full ? 28 : 22, 'High Honors', 6, 718, 260),
            new ResultRow('870268', $full ? 29 : 22, 'High Honors', 6, 765, 292),
            new ResultRow('870681', $full ? 30 : 22, 'High Honors', 6, 932, 287),
            new ResultRow('870040', $full ? 31 : 22, 'High Honors', 6, 968, 238),
            new ResultRow('870044', $full ? 32 : 22, 'High Honors', 6, 1010, 275),
            new ResultRow('870658', $full ? 33 : 22, 'High Honors', 6, 1046, 293),
            new ResultRow('870038', $full ? 34 : 22, 'High Honors', 6, 1103, 282),
            new ResultRow('870696', $full ? 35 : 22, 'High Honors', 6, 1189, 290),
            new ResultRow('870650', 36, 'Honors', 5, 398, 137),
            new ResultRow('870672', $full ? 37 : 36, 'Honors', 5, 489, 158),
            new ResultRow('870656', $full ? 38 : 36, 'Honors', 5, 496, 116),
            new ResultRow('870043', $full ? 39 : 36, 'Honors', 5, 522, 160),
            new ResultRow('870648', $full ? 40 : 36, 'Honors', 5, 573, 168),
            new ResultRow('870652', $full ? 41 : 36, 'Honors', 5, 578, 143),
            new ResultRow('870627', $full ? 42 : 36, 'Honors', 5, 579, 180, 'Asia West'),
            new ResultRow('870639', $full ? 43 : 36, 'Honors', 5, 582, 213),
            new ResultRow('870273', $full ? 44 : 36, 'Honors', 5, 592, 199),
            new ResultRow('870653', $full ? 45 : 36, 'Honors', 5, 630, 292),
            new ResultRow('870659', $full ? 46 : 36, 'Honors', 5, 644, 154),
            new ResultRow('870683', $full ? 47 : 36, 'Honors', 5, 653, 207),
            new ResultRow('870874', $full ? 48 : 36, 'Honors', 5, 660, 221),
            new ResultRow('870052', $full ? 49 : 36, 'Honors', 5, 662, 181),
            new ResultRow('870270', $full ? 50 : 36, 'Honors', 5, 683, 239),
            new ResultRow('870046', $full ? 51 : 36, 'Honors', 5, 737, 227),
            new ResultRow('870050', $full ? 52 : 36, 'Honors', 5, 739, 260),
            new ResultRow('870637', $full ? 53 : 36, 'Honors', 5, 742, 255),
            new ResultRow('870048', $full ? 54 : 36, 'Honors', 5, 743, 271),
            new ResultRow('870630', $full ? 55 : 36, 'Honors', 5, 747, 247),
            new ResultRow('870272', $full ? 56 : 36, 'Honors', 5, 747, 284),
            new ResultRow('870667', $full ? 57 : 36, 'Honors', 5, 770, 216),
            new ResultRow('870686', $full ? 58 : 36, 'Honors', 5, 795, 219),
            new ResultRow('870578', $full ? 59 : 36, 'Honors', 5, 807, 257),
            new ResultRow('870579', $full ? 60 : 36, 'Honors', 5, 822, 205),
            new ResultRow('870267', $full ? 61 : 36, 'Honors', 5, 833, 257),
            new ResultRow('870674', $full ? 62 : 36, 'Honors', 5, 837, 226),
            new ResultRow('870691', $full ? 63 : 36, 'Honors', 5, 839, 243),
            new ResultRow('870264', $full ? 64 : 36, 'Honors', 5, 850, 209),
            new ResultRow('870635', $full ? 65 : 36, 'Honors', 5, 862, 275),
            new ResultRow('870590', $full ? 66 : 36, 'Honors', 5, 867, 245),
            new ResultRow('870269', $full ? 67 : 36, 'Honors', 5, 878, 267),
            new ResultRow('870668', $full ? 68 : 36, 'Honors', 5, 889, 257),
            new ResultRow('870263', $full ? 69 : 36, 'Honors', 5, 891, 220),
            new ResultRow('870065', $full ? 70 : 36, 'Honors', 5, 908, 238, 'Africa and Arab'),
            new ResultRow('870053', $full ? 71 : 36, 'Honors', 5, 968, 260),
            new ResultRow('870042', $full ? 72 : 36, 'Honors', 5, 971, 292),
            new ResultRow('870689', $full ? 73 : 36, 'Honors', 5, 1008, 298),
            new ResultRow('870685', $full ? 74 : 36, 'Honors', 5, 1048, 267),
            new ResultRow('870638', $full ? 75 : 36, 'Honors', 5, 1164, 294),
            new ResultRow('871379', $full ? 76 : 36, 'Honors', 5, 1227, 273),
            new ResultRow('870056', null, 'Honorable', 2, 465, 299),
            new ResultRow('870055', null, 'Honorable', 4, 465, 164),
            new ResultRow('870063', null, 'Honorable', 1, 348, 288),
            new ResultRow('870066', null, 'Honorable', 2, 289, 173),
            new ResultRow('870054', null, 'Honorable', 4, 693, 255),
            new ResultRow('870067', null, 'Honorable', 2, 405, 259),
            new ResultRow('870688', null, 'Honorable', 4, 632, 198),
            new ResultRow('870690', null, 'Honorable', 3, 691, 271),
            new ResultRow('870574', null, 'Honorable', 4, 339, 128),
            new ResultRow('870640', null, 'Honorable', 3, 435, 195),
            new ResultRow('870636', null, 'Honorable', 3, 333, 130),
            new ResultRow('870061', null, 'Honorable', 1, 140, 140),
            new ResultRow('871347', null, 'Honorable', 3, 599, 287),
            new ResultRow('870577', null, 'Honorable', 4, 590, 215),
            new ResultRow('870057', null, 'Honorable', 2, 367, 253),
            new ResultRow('870641', null, 'Honorable', 3, 448, 243),
            new ResultRow('870663', null, 'Honorable', 0, 0, 0),
            new ResultRow('870662', null, 'Honorable', 2, 459, 238),
            new ResultRow('870058', null, 'Honorable', 2, 312, 196),
            new ResultRow('870629', null, 'Honorable', 3, 538, 299),
            new ResultRow('870628', null, 'Honorable', 4, 712, 298),
            new ResultRow('870631', null, 'Honorable', 4, 421, 191),
            new ResultRow('870632', null, 'Honorable', 4, 603, 266),
            new ResultRow('870633', null, 'Honorable', 3, 469, 250),
            new ResultRow('870634', null, 'Honorable', 1, 96, 96),
            new ResultRow('870694', null, 'Honorable', 4, 879, 290),
            new ResultRow('870068', null, 'Honorable', 1, 74, 74),
            new ResultRow('870693', null, 'Honorable', 3, 650, 279),
            new ResultRow('870587', null, 'Honorable', 4, 447, 228),
            new ResultRow('870588', null, 'Honorable', 4, 707, 244),
            new ResultRow('873768', null, 'Honorable', 4, 651, 210),
            new ResultRow('870687', null, 'Honorable', 4, 870, 268),
            new ResultRow('870643', null, 'Honorable', 4, 379, 192),
            new ResultRow('870581', null, 'Honorable', 2, 398, 287),
            new ResultRow('870258', null, 'Honorable', 4, 448, 141),
            new ResultRow('870062', null, 'Honorable', 1, 58, 58),
            new ResultRow('869963', null, 'Honorable', 4, 920, 274),
            new ResultRow('870675', null, 'Honorable', 1, 162, 162),
            new ResultRow('870664', null, 'Honorable', 2, 255, 226),
            new ResultRow('870660', null, 'Honorable', 3, 766, 289),
            new ResultRow('870676', null, 'Honorable', 2, 279, 150),
            new ResultRow('870673', null, 'Honorable', 1, 230, 190),
            new ResultRow('870671', null, 'Honorable', 3, 333, 196),
            new ResultRow('870661', null, 'Honorable', 3, 728, 272),
            new ResultRow('870669', null, 'Honorable', 4, 654, 210),
            new ResultRow('870666', null, 'Honorable', 3, 382, 177),
            new ResultRow('870665', null, 'Honorable', 3, 568, 224),
            new ResultRow('870657', null, 'Honorable', 4, 333, 107),
            new ResultRow('870651', null, 'Honorable', 4, 474, 160),
            new ResultRow('870645', null, 'Honorable', 3, 609, 277),
            new ResultRow('870591', null, 'Honorable', 1, 86, 66),
            new ResultRow('870589', null, 'Honorable', 3, 480, 178),
            new ResultRow('870039', null, 'Honorable', 3, 596, 252),
            new ResultRow('870697', null, 'Honorable', 3, 761, 275),
        ];

        if (!$full) {
            // Sort by rank/name.
            uasort($expectedResults, function (ResultRow $a, ResultRow $b) use ($teamsByIcpcId) {
                if ($a->rank !== $b->rank) {
                    // Honorable mention has no rank.
                    if ($a->rank === null) {
                        return 1;
                    } elseif ($b->rank === null) {
                        return -1;
                    }
                    return $a->rank <=> $b->rank;
                }
                $teamA = $teamsByIcpcId[$a->teamId] ?? null;
                $teamB = $teamsByIcpcId[$b->teamId] ?? null;
                $nameA = $teamA?->getEffectiveName();
                $nameB = $teamB?->getEffectiveName();
                $collator = new Collator('en');
                return $collator->compare($nameA, $nameB);
            });
            $expectedResults = array_values($expectedResults);
        }


        self::assertEquals($expectedResults, $results);
    }

    public function provideGetResultsData(): Generator
    {
        yield [true];
        yield [false];
    }
}
