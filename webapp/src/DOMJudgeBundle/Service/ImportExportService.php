<?php declare(strict_types=1);

namespace DOMJudgeBundle\Service;

use Collator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use DOMJudgeBundle\Entity\Role;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Entity\TeamCategory;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Utils\Scoreboard\Filter;
use DOMJudgeBundle\Utils\Scoreboard\ScoreboardMatrixItem;
use DOMJudgeBundle\Utils\Utils;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ImportExportService
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ScoreboardService $scoreboardService,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager     = $entityManager;
        $this->scoreboardService = $scoreboardService;
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->eventLogService   = $eventLogService;
    }

    /**
     * Get group data
     * @return array
     */
    public function getGroupData(): array
    {
        /** @var TeamCategory[] $categories */
        $categories = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TeamCategory', 'c')
            ->select('c')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($categories as $category) {
            $data[] = [$category->getCategoryid(), $category->getName()];
        }

        return $data;
    }

    /**
     * Get team data
     * @return array
     */
    public function getTeamData(): array
    {
        /** @var Team[] $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Team', 't')
            ->join('t.category', 'c')
            ->select('t')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($teams as $team) {
            $data[] = [
                $team->getTeamid(),
                $team->getExternalid(),
                $team->getCategoryid(),
                $team->getName(),
                $team->getAffiliation()->getName(),
                $team->getAffiliation()->getShortname(),
                $team->getAffiliation()->getCountry(),
                $team->getAffiliation()->getExternalid(),
            ];
        }

        return $data;
    }

    /**
     * Get scoreboard data
     * @return array
     * @throws \Exception
     */
    public function getScoreboardData(): array
    {
        // We'll here assume that the requested file will be of the current contest,
        // as all our scoreboard interfaces do. Row format explanation:
        // Row	Description	Example content	Type
        // 1	Institution name	University of Virginia	string
        // 2	External ID	24314	integer
        // 3	Position in contest	1	integer
        // 4	Number of problems the team has solved	4	integer
        // 5	Total Time	534	integer
        // 6	Time of the last accepted submission	233	integer   -1 if none
        // 6+2i-1	Number of submissions for problem i	2	integer
        // 6+2i	Time when problem i was solved	233	integer   -1 if not solved

        $contest = $this->DOMJudgeService->getCurrentContest();
        if ($contest === null) {
            throw new BadRequestHttpException('No current contest');
        }
        $scoreIsInSeconds = (bool)$this->DOMJudgeService->dbconfig_get('score_in_seconds', false);
        $scoreboard       = $this->scoreboardService->getScoreboard($contest, true);

        $data = [];
        foreach ($scoreboard->getScores() as $teamScore) {
            $maxtime = -1;
            $drow    = [];
            /** @var ScoreboardMatrixItem $matrixItem */
            foreach ($scoreboard->getMatrix()[$teamScore->getTeam()->getTeamid()] as $matrixItem) {
                $time    = Utils::scoretime($matrixItem->getTime(), $scoreIsInSeconds);
                $drow[]  = $matrixItem->getNumberOfSubmissions();
                $drow[]  = $matrixItem->isCorrect() ? $time : -1;
                $maxtime = max($maxtime, $time);
            }

            $data[] = array_merge(
                [
                    $teamScore->getTeam()->getAffiliation() ? $teamScore->getTeam()->getAffiliation()->getName() : '',
                    $teamScore->getTeam()->getExternalid(),
                    $teamScore->getRank(),
                    $teamScore->getNumberOfPoints(),
                    $teamScore->getTotalTime(),
                    $maxtime,
                ],
                $drow
            );
        }

        return $data;
    }

    /**
     * Get results data
     * @return array
     * @throws \Exception
     */
    public function getResultsData()
    {
        // we'll here assume that the requested file will be of the current contest,
        // as all our scoreboard interfaces do
        // 1 	External ID 	24314 	integer
        // 2 	Rank in contest 	1 	integer
        // 3 	Award 	Gold Medal 	string
        // 4 	Number of problems the team has solved 	4 	integer
        // 5 	Total Time 	534 	integer
        // 6 	Time of the last submission 	233 	integer
        // 7 	Group Winner 	North American 	string

        $contest = $this->DOMJudgeService->getCurrentContest();
        if ($contest === null) {
            throw new BadRequestHttpException('No current contest');
        }

        /** @var TeamCategory[] $categories */
        $categories  = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TeamCategory', 'c', 'c.categoryid')
            ->select('c')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = $category->getCategoryid();
        }

        $scoreIsInSeconds = (bool)$this->DOMJudgeService->dbconfig_get('score_in_seconds', false);
        $filter           = new Filter();
        $filter->setCategories($categoryIds);
        $scoreboard = $this->scoreboardService->getScoreboard($contest, true, $filter);

        /** @var Team[] $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Team', 't', 't.externalid')
            ->select('t')
            ->where('t.externalid IS NOT NULL')
            ->orderBy('t.externalid')
            ->getQuery()
            ->getResult();

        $numberOfTeams = count($scoreboard->getScores());
        // determine number of problems solved by median team
        $count  = 0;
        $median = 0;
        foreach ($scoreboard->getScores() as $teamScore) {
            $count++;
            $median = $teamScore->getNumberOfPoints();
            if ($count > $numberOfTeams / 2) {
                break;
            }
        }

        $ranks        = [];
        $groupWinners = [];
        $data         = [];

        foreach ($scoreboard->getScores() as $teamScore) {
            $maxTime = -1;
            /** @var ScoreboardMatrixItem $matrixItem */
            foreach ($scoreboard->getMatrix()[$teamScore->getTeam()->getTeamid()] as $matrixItem) {
                $time    = Utils::scoretime($matrixItem->getTime(), $scoreIsInSeconds);
                $maxTime = max($maxTime, $time);
            }

            $rank           = $teamScore->getRank();
            $numberOfPoints = $teamScore->getNumberOfPoints();
            if ($rank <= 4) {
                $awardString = 'Gold Medal';
            } elseif ($rank <= 8) {
                $awardString = 'Silver Medal';
            } elseif ($rank <= 12 + $contest->getB()) {
                $awardString = 'Bronze Medal';
            } elseif ($numberOfPoints >= $median) {
                // teams with equally solved number of problems get the same rank
                if (!isset($ranks[$numberOfPoints])) {
                    $ranks[$numberOfPoints] = $rank;
                }
                $rank        = $ranks[$numberOfPoints];
                $awardString = 'Ranked';
            } else {
                $awardString = 'Honorable';
                $rank        = '';
            }

            $groupWinner = "";
            $categoryId  = $teamScore->getTeam()->getCategoryid();
            if (!isset($groupWinners[$categoryId])) {
                $groupWinners[$categoryId] = true;
                $groupWinner               = $teamScore->getTeam()->getCategory()->getName();
            }

            $data[] = [
                $teamScore->getTeam()->getExternalid(),
                $rank,
                $awardString,
                $teamScore->getNumberOfPoints(),
                $teamScore->getTotalTime(),
                $maxTime,
                $groupWinner
            ];
        }

        // sort by rank/name
        uasort($data, function ($a, $b) use ($teams) {
            if ($a[1] != $b[1]) {
                // Honorable mention has no rank
                if ($a[1] === '') {
                    return 1;
                } elseif ($b[1] === '') {
                    return -11;
                }
                return $a[1] - $b[1];
            }
            $teamA = $teams[$a[0]] ?? null;
            $teamB = $teams[$b[0]] ?? null;
            if ($teamA) {
                $nameA = $teamA->getName();
            } else {
                $nameA = '';
            }
            if ($teamB) {
                $nameB = $teamB->getName();
            } else {
                $nameB = '';
            }
            $collator = new Collator('en');
            return $collator->compare($nameA, $nameB);
        });

        return $data;
    }

    /**
     * Import a TSV file
     * @param string       $type
     * @param UploadedFile $file
     * @return int
     * @throws \Exception
     */
    public function importTsv(string $type, UploadedFile $file): int
    {
        $content = file($file->getRealPath());
        // The first line of the tsv is always the format with a version number.
        // currently we hardcode version 1 because there are no others
        $version = rtrim(array_shift($content));
        // Two variants are in use: one where the first token is a static string
        // "File_Version" and the second where it's the type, e.g. "groups".
        $versionMatch = '1';
        if ($type == 'teams') {
            $versionMatch = '[12]';
        }
        $regex = sprintf("/^(File_Version|%s)\t%s$/i", $type, $versionMatch);
        if (!preg_match($regex, $version)) {
            throw new BadRequestHttpException(sprintf("Unknown format or version: %s != %s", $version, $versionMatch));
        }

        switch ($type) {
            case 'groups':
                return $this->importGroupsTsv($content);
            case 'teams':
                return $this->importTeamsTsv($content);
            case 'accounts':
                return $this->importAccountsTsv($content);
            default:
                throw new BadRequestHttpException(sprintf('Invalid TSV type %s', $type));
        }
    }

    /**
     * Import groups TSV
     * @param array $content
     * @return int
     * @throws \Exception
     */
    protected function importGroupsTsv(array $content): int
    {
        $groupData = [];
        $l         = 1;
        foreach ($content as $line) {
            $l++;
            $line = explode("\t", trim($line));
            if (!is_numeric($line[0])) {
                throw new BadRequestHttpException(sprintf('Invalid id format on line %d', $l));
            }
            $groupData[] = [
                'categoryid' => @$line[0],
                'name' => @$line[1]
            ];
        }

        // We want to overwrite the ID so change the ID generator
        $metadata = $this->entityManager->getClassMetaData(TeamCategory::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());

        foreach ($groupData as $groupItem) {
            $teamCategory = $this->entityManager->getRepository(TeamCategory::class)->find($groupItem['categoryid']);
            if (!$teamCategory) {
                $teamCategory = new TeamCategory();
                $teamCategory->setCategoryid($groupItem['categoryid']);
                $this->entityManager->persist($teamCategory);
                $action = EventLogService::ACTION_CREATE;
            } else {
                $action = EventLogService::ACTION_UPDATE;
            }
            $teamCategory->setName($groupItem['name']);
            $this->entityManager->flush();
            if ($contest = $this->DOMJudgeService->getCurrentContest()) {
                $this->eventLogService->log('team_category', $teamCategory->getCategoryid(), $action,
                                            $contest->getCid());
            }
            $this->DOMJudgeService->auditlog('team_category', $teamCategory->getCategoryid(), 'replaced',
                                             'imported from tsv');
        }

        return count($groupData);
    }

    /**
     * Import teams TSV
     * @param array $content
     * @return int
     * @throws \Exception
     */
    protected function importTeamsTsv(array $content): int
    {
        $teamData = [];
        $l        = 1;
        foreach ($content as $line) {
            $l++;
            $line = explode("\t", trim($line));

            // teams.tsv contains data pertaining both to affiliations and teams.
            // hence return data for both tables.

            // we may do more integrity/format checking of the data here.

            // Set external ID's to null if they are not given
            $teamExternalId = @$line[1];
            if (empty($teamExternalId)) {
                $teamExternalId = null;
            }
            $affiliationExternalid = preg_replace('/^INST-(U-)?/', '', @$line[7]);
            if (empty($affiliationExternalid)) {
                // TODO: note that when we set this external ID to NULL, we *will* add team affiliations
                // multiple times, as the DOMJudgeBundle:TeamAffiliation query below will not find an affiliation.
                // We might want to change that to also search on shortname and/or name?
                $affiliationExternalid = null;
            }

            // Set team ID to external ID if it has the literal value 'null' and the external ID is numeric
            $teamId = @$line[0];
            if ($teamId === 'null' && is_numeric($teamExternalId)) {
                $teamId = (int)$teamExternalId;
            }

            $teamData[] = [
                'team' => [
                    'teamid' => $teamId,
                    'externalid' => $teamExternalId,
                    'categoryid' => @$line[2],
                    'name' => @$line[3],
                ],
                'team_affiliation' => [
                    'shortname' => !empty(@$line[5]) ? @$line[5] : $affiliationExternalid,
                    'name' => @$line[4],
                    'country' => @$line[6],
                    'externalid' => $affiliationExternalid,
                ]
            ];
        }

        // We want to overwrite the ID so change the ID generator
        $metadata = $this->entityManager->getClassMetaData(TeamCategory::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());

        $metadata = $this->entityManager->getClassMetaData(Team::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());

        $createdAffiliations = [];
        $createdTeams        = [];
        $updatedTeams        = [];
        foreach ($teamData as $teamItem) {
            // it is legitimate that a team has no affiliation. Do not add it then.
            $teamAffiliation = null;
            $teamCategory    = null;
            if (!empty($teamItem['team_affiliation']['shortname'])) {
                // First look up if the affiliation already exists.
                /** @var TeamAffiliation $teamAffiliation */
                $teamAffiliation = $this->entityManager->createQueryBuilder()
                    ->from('DOMJudgeBundle:TeamAffiliation', 'a')
                    ->select('a')
                    ->andWhere('a.externalid = :externalid')
                    ->setParameter(':externalid', $teamItem['team_affiliation']['externalid'])
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($teamAffiliation === null) {
                    $teamAffiliation  = new TeamAffiliation();
                    $propertyAccessor = PropertyAccess::createPropertyAccessor();
                    foreach ($teamItem['team_affiliation'] as $field => $value) {
                        $propertyAccessor->setValue($teamAffiliation, $field, $value);
                    }

                    $this->entityManager->persist($teamAffiliation);
                    $this->entityManager->flush();
                    $createdAffiliations[] = $teamAffiliation->getAffilid();
                    $this->DOMJudgeService->auditlog('team_affiliation', $teamAffiliation->getAffilid(), 'added',
                                                     'imported from tsv');
                }
            }
            $teamItem['team']['affiliation'] = $teamAffiliation;
            unset($teamItem['team']['affilid']);

            if (!empty($teamItem['team']['categoryid'])) {
                $teamCategory = $this->entityManager->getRepository(TeamCategory::class)->find($teamItem['team']['categoryid']);
                if (!$teamCategory) {
                    $teamCategory = new TeamCategory();
                    $teamCategory
                        ->setCategoryid($teamItem['categoryid'])
                        ->setName($teamItem['categoryid'] . ' - auto-create during import');
                    $this->entityManager->persist($teamCategory);
                    $this->DOMJudgeService->auditlog('team_category', $teamCategory->getCategoryid(), 'added',
                                                     'imported from tsv');
                }
            }
            $teamItem['team']['category'] = $teamCategory;
            unset($teamItem['team']['categoryid']);

            $team = $this->entityManager->getRepository(Team::class)->find($teamItem['team']['teamid']);
            if (!$team) {
                $team  = new Team();
                $added = true;
            } else {
                $added = false;
            }

            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            foreach ($teamItem['team'] as $field => $value) {
                $propertyAccessor->setValue($team, $field, $value);
            }

            if ($added) {
                $this->entityManager->persist($team);
            }
            $this->entityManager->flush();

            if ($added) {
                $createdTeams[] = $team->getTeamid();
            } else {
                $updatedTeams[] = $team->getTeamid();
            }

            $this->DOMJudgeService->auditlog('team', $team->getTeamid(), 'replaced', 'imported from tsv');
        }

        if ($contest = $this->DOMJudgeService->getCurrentContest()) {
            if (!empty($createdAffiliations)) {
                $this->eventLogService->log('team_affiliation', $createdAffiliations, 'create', $contest->getCid());
            }
            if (!empty($createdTeams)) {
                $this->eventLogService->log('team', $createdTeams, 'create', $contest->getCid());
            }
            if (!empty($updatedTeams)) {
                $this->eventLogService->log('team', $updatedTeams, 'update', $contest->getCid());
            }
        }

        return count($teamData);
    }

    /**
     * Import accounts TSV
     * @param array $content
     * @return int
     * @throws \Exception
     */
    protected function importAccountsTsv(array $content): int
    {
        $accountData = [];
        $l           = 1;
        $teamRole    = $this->entityManager->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);
        $juryRole    = $this->entityManager->getRepository(Role::class)->findOneBy(['dj_role' => 'jury']);
        $adminRole   = $this->entityManager->getRepository(Role::class)->findOneBy(['dj_role' => 'admin']);

        $juryCategory = $this->entityManager->getRepository(TeamCategory::class)->findOneBy(['name' => 'Jury']);
        if (!$juryCategory) {
            $juryCategory = new TeamCategory();
            $juryCategory
                ->setName('Jury')
                ->setSortorder(100)
                ->setVisible(false);
            $this->entityManager->persist($juryCategory);
            $this->entityManager->flush();
        }

        foreach ($content as $line) {
            $l++;
            $line = explode("\t", trim($line));

            $team  = $juryTeam = null;
            $roles = [];
            switch ($line[0]) {
                case 'admin':
                    $roles[] = $adminRole;
                    break;
                case 'judge':
                    $roles[]  = $juryRole;
                    $roles[]  = $teamRole;
                    $juryTeam = ['name' => $line[1], 'category' => $juryCategory, 'members' => $line[1]];
                    break;
                case 'team':
                    $roles[] = $teamRole;
                    // For now we assume we can find the teamid by parsing
                    // the username and taking the largest suffix number.
                    // Note that https://clics.ecs.baylor.edu/index.php/Contest_Control_System_Requirements#accounts.tsv
                    // assumes team accounts of the form "team-nnn" where
                    // nnn is a zero-padded team number.
                    $teamId = preg_replace('/^[^0-9]*0*([0-9]+)$/', '\1', $line[2]);
                    if (!preg_match('/^[0-9]+$/', $teamId)) {
                        throw new BadRequestHttpException(sprintf('cannot parse team id on line %d from "%s"', $l,
                                                                  $line[2]));
                    }
                    $team = $this->entityManager->getRepository(Team::class)->find($teamId);
                    if ($team === null) {
                        throw new BadRequestHttpException(sprintf('unknown team id %s on line %d', $teamId, $l));
                    }
                    break;
                case 'analyst':
                    // Ignore type analyst for now. We don't have a useful mapping yet.
                    continue 2;
                default:
                    throw new BadRequestHttpException(sprintf('unknown role on line %d: %s', $l, $line[0]));
            }

            // accounts.tsv contains data pertaining to users, their roles and teams. Hence return data for both tables.

            // We may do more integrity/format checking of the data here.
            $accountData[] = [
                'user' => [
                    'name' => $line[1],
                    'username' => $line[2],
                    'plain_password' => $line[3],
                    'team' => $team,
                    'roles' => $roles,
                ],
                'team' => $juryTeam,
            ];
        }

        foreach ($accountData as $accountItem) {
            if (!empty($accountItem['team'])) {
                $team = $this->entityManager->getRepository(Team::class)->findOneBy([
                                                                                        'name' => $accountItem['team']['name'],
                                                                                        'category' => $accountItem['team']['category']
                                                                                    ]);
                if ($team === null) {
                    $team = new Team();
                    $team
                        ->setName($accountItem['team']['name'])
                        ->setCategory($accountItem['team']['category']);
                    $this->entityManager->persist($team);
                    $action = EventLogService::ACTION_CREATE;
                } else {
                    $action = EventLogService::ACTION_UPDATE;
                }
                $this->entityManager->flush();
                $this->eventLogService->log('team', $team->getTeamid(), $action);
                // Reload team as eventlog will have cleared it
                $team = $this->entityManager->getRepository(Team::class)->find($team->getTeamid());
                $this->DOMJudgeService->auditlog('team', $team->getTeamid(), 'replaced',
                                                 'imported from tsv, autocreated for judge');
                $accountItem['user']['team'] = $team;
            }

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $accountItem['user']['username']]);
            if (!$user) {
                $user  = new User();
                $added = true;
            } else {
                $added = false;
            }

            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            foreach ($accountItem['user'] as $field => $value) {
                $propertyAccessor->setValue($user, $field, $value);
            }

            if ($added) {
                $this->entityManager->persist($user);
            }
            $this->entityManager->flush();

            $this->DOMJudgeService->auditlog('user', $user->getUserid(), 'replaced', 'imported from tsv');
        }

        return count($accountData);
    }
}
