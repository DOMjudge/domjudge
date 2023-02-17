<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Utils\Scoreboard\Filter;
use App\Utils\Utils;
use Collator;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use JsonException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ImportExportService
{
    protected EntityManagerInterface $em;
    protected ScoreboardService $scoreboardService;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;
    protected ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $em,
        ScoreboardService $scoreboardService,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ValidatorInterface $validator
    ) {
        $this->em                = $em;
        $this->scoreboardService = $scoreboardService;
        $this->dj                = $dj;
        $this->config            = $config;
        $this->eventLogService   = $eventLogService;
        $this->validator         = $validator;
    }

    /**
     * Get the YAML data for a given contest.
     * @return array
     */
    public function getContestYamlData(Contest $contest): array
    {
        // We expect contest.yaml and problemset.yaml combined into one file here.

        $data = [
            'name' => $contest->getName(),
            'short-name' => $contest->getShortname(),
            'start-time' => Utils::absTime($contest->getStarttime(), true),
            'duration' => Utils::relTime($contest->getContestTime((float)$contest->getEndtime())),
        ];
        if ($warnMsg = $contest->getWarningMessage()) {
            $data['warning-message'] = $warnMsg;
        }
        if ($contest->getFreezetime() !== null) {
            $data['scoreboard-freeze-duration'] = Utils::relTime(
                $contest->getContestTime((float)$contest->getEndtime()) - $contest->getContestTime((float)$contest->getFreezetime()),
                true);
        }
        $data = array_merge($data, [
            'penalty-time' => $this->config->get('penalty_time'),
            'problems' => [],
        ]);

        /** @var ContestProblem $contestProblem */
        foreach ($contest->getProblems() as $contestProblem) {
            // Our color field can be both an HTML color name and an RGB value.
            // If it is in RGB, we try to find the closest HTML color name.
            $color              = $contestProblem->getColor() === null ? null : Utils::convertToColor($contestProblem->getColor());
            $data['problems'][] = [
                'label' => $contestProblem->getShortname(),
                'letter' => $contestProblem->getShortname(),
                'name' => $contestProblem->getProblem()->getName(),
                'short-name' => $contestProblem->getProblem()->getExternalid(),
                'color' => $color ?? $contestProblem->getColor(),
                'rgb' => $contestProblem->getColor() === null ? null : Utils::convertToHex($contestProblem->getColor()),
            ];
        }

        return $data;
    }

    public function importContestData($data, ?string &$message = null, string &$cid = null): bool
    {
        if (empty($data) || !is_array($data)) {
            $message = 'Error parsing YAML file.';
            return false;
        }

        $requiredFields = [['start_time', 'start-time'], 'name', ['id', 'short-name'], 'duration'];
        $missingFields  = [];
        foreach ($requiredFields as $field) {
            if (is_array($field)) {
                $present = false;
                foreach ($field as $f) {
                    if (array_key_exists($f, $data)) {
                        $present = true;
                        break;
                    }
                }

                if (!$present) {
                    $missingFields[] = sprintf('one of (%s)', implode(', ', $field));
                }
            } elseif (!array_key_exists($field, $data)) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $message = sprintf('Missing fields: %s', implode(', ', $missingFields));
            return false;
        }

        $invalid_regex = str_replace(['/^[', '+$/'], ['/[^', '/'], DOMJudgeService::EXTERNAL_IDENTIFIER_REGEX);

        $starttimeValue = $data['start-time'] ?? $data['start_time'];

        if (is_string($starttimeValue)) {
            $starttime = date_create_from_format(DateTime::ISO8601, $starttimeValue) ?:
                // Make sure ISO 8601 but with the T replaced with a space also works.
                date_create_from_format('Y-m-d H:i:sO', $starttimeValue);
        } else {
            /** @var DateTime $starttime */
            $starttime = $starttimeValue;
        }
        if ($starttime === false) {
            $message = 'Can not parse start time';
            return false;
        }

        $starttime->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $activateTime = new DateTime();
        if ($activateTime > $starttime) {
            $activateTime = $starttime;
        }
        $contest = new Contest();
        $contest
            ->setName($data['name'])
            ->setShortname(preg_replace(
                               $invalid_regex,
                               '_',
                               $data['shortname'] ?? $data['short-name'] ?? $data['id']
                           ))
            ->setExternalid($contest->getShortname())
            ->setWarningMessage($data['warning-message'] ?? null)
            ->setStarttimeString(date_format($starttime, 'Y-m-d H:i:s e'))
            ->setActivatetimeString(date_format($activateTime, 'Y-m-d H:i:s e'))
            ->setEndtimeString(sprintf('+%s', $data['duration']));

        // Get all visible categories. For now, we assume these are the ones getting awards
        $visibleCategories = $this->em->getRepository(TeamCategory::class)->findBy(['visible' => true]);

        if (empty($visibleCategories)) {
            $contest->setMedalsEnabled(false);
        } else {
            foreach ($visibleCategories as $visibleCategory) {
                $contest->addMedalCategory($visibleCategory);
            }
        }

        /** @var string|null $freezeDuration */
        $freezeDuration = $data['scoreboard_freeze_duration'] ?? $data['scoreboard-freeze-duration'] ?? $data['scoreboard-freeze-length'] ?? null;
        /** @var string|null $freezeStart */
        $freezeStart = $data['scoreboard-freeze'] ?? $data['freeze'] ?? null;

        if ($freezeDuration !== null) {
            $freezeDurationDiff = Utils::timeStringDiff($data['duration'], $freezeDuration);
            if (strpos($freezeDurationDiff, '-') === 0) {
                $message = 'Freeze duration is longer than contest length';
                return false;
            }
            $contest->setFreezetimeString(sprintf('+%s', $freezeDurationDiff));
        } elseif ($freezeStart !== null) {
            $contest->setFreezetimeString(sprintf('+%s', $freezeStart));
        }

        $errors = $this->validator->validate($contest);
        if ($errors->count()) {
            $messages = [];
            /** @var ConstraintViolationInterface $error */
            foreach ($errors as $error) {
                $messages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
            }

            $message = sprintf("Contest has errors:\n\n%s", implode("\n", $messages));
            return false;
        }

        $this->em->persist($contest);
        $this->em->flush();

        $penaltyTime = $data['penalty_time'] ?? $data['penalty-time'] ?? $data['penalty'] ?? null;
        if ($penaltyTime !== null) {
            $currentPenaltyTime = $this->config->get('penalty_time');
            if ($penaltyTime != $currentPenaltyTime) {
                $penaltyTimeConfiguration = $this->em->getRepository(Configuration::class)->findOneBy(['name' => 'penalty_time']);
                if (!$penaltyTimeConfiguration) {
                    $penaltyTimeConfiguration = new Configuration();
                    $penaltyTimeConfiguration->setName('penalty_time');
                    $this->em->persist($penaltyTimeConfiguration);
                }

                $penaltyTimeConfiguration->setValue((int)$penaltyTime);
            }
        }

        if (isset($data['problems'])) {
            $this->importProblemsData($contest, $data['problems']);
        }

        $cid = $contest->getApiId($this->eventLogService);

        $this->em->flush();
        return true;
    }

    public function importProblemsData(Contest $contest, $problems, array &$ids = null): bool
    {
        // For problemset.yaml the root key is called `problems`, so handle that case
        if (isset($problems['problems'])) {
            $problems = $problems['problems'];
        }

        foreach ($problems as $problemData) {
            // Deal with obsolete attribute names. Also for name fall back to ID if it is not specified.
            $problemName  = $problemData['name'] ?? $problemData['short-name'] ?? $problemData['id'] ?? null;
            $problemLabel = $problemData['label'] ?? $problemData['letter'] ?? null;

            $problem = new Problem();
            $problem
                ->setName($problemName)
                ->setTimelimit($problemData['time_limit'] ?? 10)
                ->setExternalid($problemData['id'] ?? $problemData['short-name'] ?? $problemLabel ?? null);

            $this->em->persist($problem);
            $this->em->flush();

            $contestProblem = new ContestProblem();
            $contestProblem
                ->setShortname($problemLabel)
                ->setColor($problemData['rgb'] ?? $problemData['color'] ?? null)
                // We need to set both the entities and the IDs because of the composite primary key.
                ->setProblem($problem)
                ->setContest($contest);
            $this->em->persist($contestProblem);

            $ids[] = $problem->getApiId($this->eventLogService);
        }

        $this->em->flush();

        // For now this method will never fail so always return true.
        return true;
    }

    /**
     * Get group data
     */
    public function getGroupData(): array
    {
        /** @var TeamCategory[] $categories */
        $categories = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c')
            ->select('c')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($categories as $category) {
            $data[] = [$category->getApiId($this->eventLogService), $category->getName()];
        }

        return $data;
    }

    /**
     * Get team data
     */
    public function getTeamData(): array
    {
        /** @var Team[] $teams */
        $teams = $this->em->createQueryBuilder()
            ->from(Team::class, 't')
            ->join('t.category', 'c')
            ->select('t')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($teams as $team) {
            $data[] = [
                $team->getApiId($this->eventLogService),
                $team->getIcpcId(),
                $team->getCategory()->getApiId($this->eventLogService),
                $team->getEffectiveName(),
                $team->getAffiliation() ? $team->getAffiliation()->getName() : '',
                $team->getAffiliation() ? $team->getAffiliation()->getShortname() : '',
                $team->getAffiliation() ? $team->getAffiliation()->getCountry() : '',
                $team->getAffiliation() ? $team->getAffiliation()->getExternalid() : '',
            ];
        }

        return $data;
    }

    /**
     * Get results data for the given sortorder.
     */
    public function getResultsData(int $sortOrder): array
    {
        // We'll here assume that the requested file will be of the current contest,
        // as all our scoreboard interfaces do:
        // 1    ICPC ID     24314   string
        // 2    Rank in contest     1   integer
        // 3    Award   Gold Medal  string
        // 4    Number of problems the team has solved  4   integer
        // 5    Total Time  534     integer
        // 6    Time of the last submission     233     integer
        // 7    Group Winner    North American  string

        $contest = $this->dj->getCurrentContest();
        if ($contest === null) {
            throw new BadRequestHttpException('No current contest');
        }

        /** @var TeamCategory[] $categories */
        $categories  = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c', 'c.categoryid')
            ->select('c')
            ->where('c.visible = 1')
            ->getQuery()
            ->getResult();
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = $category->getCategoryid();
        }

        $scoreIsInSeconds = (bool)$this->config->get('score_in_seconds');
        $filter = new Filter();
        $filter->categories = $categoryIds;
        $scoreboard = $this->scoreboardService->getScoreboard($contest, true, $filter);

        /** @var Team[] $teams */
        $teams = $this->em->createQueryBuilder()
            ->from(Team::class, 't', 't.icpcid')
            ->select('t')
            ->where('t.icpcid IS NOT NULL')
            ->orderBy('t.icpcid')
            ->getQuery()
            ->getResult();

        $numberOfTeams = count($scoreboard->getScores());
        // Determine number of problems solved by median team.
        $count  = 0;
        $median = 0;
        foreach ($scoreboard->getScores() as $teamScore) {
            $count++;
            $median = $teamScore->numPoints;
            if ($count > $numberOfTeams / 2) {
                break;
            }
        }

        $ranks        = [];
        $groupWinners = [];
        $data         = [];

        foreach ($scoreboard->getScores() as $teamScore) {
            if ($teamScore->team->getCategory()->getSortorder() !== $sortOrder) {
                continue;
            }
            $maxTime = -1;
            foreach ($scoreboard->getMatrix()[$teamScore->team->getTeamid()] as $matrixItem) {
                $time    = Utils::scoretime($matrixItem->time, $scoreIsInSeconds);
                $maxTime = max($maxTime, $time);
            }

            $rank      = $teamScore->rank;
            $numPoints = $teamScore->numPoints;
            if ($rank <= ($contest->getGoldMedals() ?? 4)) {
                $awardString = 'Gold Medal';
            } elseif ($rank <= ($contest->getGoldMedals() ?? 4) + ($contest->getSilverMedals() ?? 4)) {
                $awardString = 'Silver Medal';
            } elseif ($rank <= ($contest->getGoldMedals() ?? 4) + ($contest->getSilverMedals() ?? 4) + ($contest->getBronzeMedals() ?? 4) + $contest->getB()) {
                $awardString = 'Bronze Medal';
            } elseif ($numPoints >= $median) {
                // Teams with equally solved number of problems get the same rank.
                if (!isset($ranks[$numPoints])) {
                    $ranks[$numPoints] = $rank;
                }
                $rank        = $ranks[$numPoints];
                $awardString = 'Ranked';
            } else {
                $awardString = 'Honorable';
                $rank        = '';
            }

            $groupWinner = "";
            $categoryId  = $teamScore->team->getCategory()->getCategoryid();
            if (!isset($groupWinners[$categoryId])) {
                $groupWinners[$categoryId] = true;
                $groupWinner               = $teamScore->team->getCategory()->getName();
            }

            $data[] = [
                $teamScore->team->getIcpcId(),
                $rank,
                $awardString,
                $teamScore->numPoints,
                $teamScore->totalTime,
                $maxTime,
                $groupWinner
            ];
        }

        // Sort by rank/name.
        uasort($data, function ($a, $b) use ($teams) {
            if ($a[1] != $b[1]) {
                // Honorable mention has no rank.
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
                $nameA = $teamA->getEffectiveName();
            } else {
                $nameA = '';
            }
            if ($teamB) {
                $nameB = $teamB->getEffectiveName();
            } else {
                $nameB = '';
            }
            $collator = new Collator('en');
            return $collator->compare($nameA, $nameB);
        });

        return $data;
    }

    /**
     * Import a TSV file.
     */
    public function importTsv(string $type, UploadedFile $file, ?string &$message = null): int
    {
        $content = file($file->getRealPath());
        // The first line of the tsv is always the format with a version number.
        // Currently, we hardcode version 1 because there are no others.
        $version = rtrim(array_shift($content));
        // Two variants are in use: one where the first token is a static string
        // "File_Version" and the second where it's the type, e.g. "groups".
        $versionMatch = '1';
        if ($type == 'teams') {
            $versionMatch = '[12]';
        }
        $regex = sprintf("/^(File_Version|%s)\t%s$/i", $type, $versionMatch);
        if (!preg_match($regex, $version)) {
            $message = sprintf("TSV has unknown format or version: %s != %s", $version, $versionMatch);
            return -1;
        }

        switch ($type) {
            case 'groups':
                return $this->importGroupsTsv($content, $message);
            case 'teams':
                return $this->importTeamsTsv($content, $message);
            case 'accounts':
                return $this->importAccountsTsv($content, $message);
            default:
                $message = sprintf('Invalid import type %s', $type);
                return -1;
        }
    }

    /**
     * Import a JSON file
     */
    public function importJson(string $type, UploadedFile $file, ?string &$message = null): int
    {
        $content = file_get_contents($file->getRealPath());
        try {
            $data = $this->dj->jsonDecode($content);
        } catch (JsonException $e) {
            // Check if we can parse it as YAML
            try {
                $data = Yaml::parse($content, Yaml::PARSE_DATETIME);
            } catch (ParseException $e) {
                $message = "File contents is not valid JSON or YAML: " . $e->getMessage();
                return -1;
            }
        }

        if (!is_array($data) || !is_int(key($data))) {
            $message = 'File contents does not contain JSON array';
            return -1;
        }

        switch ($type) {
            case 'groups':
                return $this->importGroupsJson($data, $message);
            case 'organizations':
                return $this->importOrganizationsJson($data, $message);
            case 'teams':
                return $this->importTeamsJson($data, $message);
            case 'accounts':
                return $this->importAccountsJson($data, $message);
            default:
                $message = sprintf('Invalid import type %s', $type);
                return -1;
        }
    }

    /**
     * Import groups TSV.
     */
    protected function importGroupsTsv(array $content, ?string &$message = null): int
    {
        $groupData = [];
        $l         = 1;
        foreach ($content as $line) {
            $l++;
            $line = Utils::parseTsvLine(trim($line));
            $groupData[] = [
                'categoryid' => @$line[0],
                'name' => @$line[1]
            ];
        }

        return $this->importGroupData($groupData);
    }

    /**
     * Import groups JSON
     *
     * @param TeamCategory[]|null $saved The saved groups
     */
    public function importGroupsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        $groupData = [];
        foreach ($data as $idx => $group) {
            $groupData[] = [
                'categoryid' => @$group['id'],
                'icpc_id' => @$group['icpc_id'],
                'name' => @$group['name'],
                'visible' => !($group['hidden'] ?? false),
                'sortorder' => @$group['sortorder'],
                'color' => @$group['color'],
            ];
        }

        return $this->importGroupData($groupData, $saved);
    }

    /**
     * Import group data from the given array
     *
     * @param TeamCategory[]|null $saved The saved groups
     *
     * @throws NonUniqueResultException
     */
    protected function importGroupData(array $groupData, ?array &$saved = null): int
    {
        // We want to overwrite the ID so change the ID generator.
        $createdCategories = [];
        $updatedCategories = [];

        foreach ($groupData as $groupItem) {
            if (empty($groupItem['categoryid'])) {
                $categoryId = null;
                $teamCategory = null;
            } else {
                $categoryId = $groupItem['categoryid'];
                $field = $this->eventLogService->apiIdFieldForEntity(TeamCategory::class);
                $teamCategory = $this->em->getRepository(TeamCategory::class)->findOneBy([$field => $categoryId]);
            }
            $added = false;
            if (!$teamCategory) {
                $teamCategory = new TeamCategory();
                if ($categoryId !== null) {
                    $teamCategory->setExternalid($categoryId);
                }
                $this->em->persist($teamCategory);
                $added = true;
            }
            $teamCategory
                ->setName($groupItem['name'])
                ->setVisible($groupItem['visible'] ?? true)
                ->setSortorder($groupItem['sortorder'] ?? 0)
                ->setColor($groupItem['color'] ?? null)
                ->setIcpcid($groupItem['icpc_id'] ?? null);
            $this->em->flush();
            $this->dj->auditlog('team_category', $teamCategory->getCategoryid(), 'replaced',
                                             'imported from tsv / json');
            if ($added) {
                $createdCategories[] = $teamCategory->getCategoryid();
            } else {
                $updatedCategories[] = $teamCategory->getCategoryid();
            }
            if ($saved !== null) {
                $saved[] = $teamCategory;
            }
        }

        if ($contest = $this->dj->getCurrentContest()) {
            if (!empty($createdCategories)) {
                $this->eventLogService->log('team_category', $createdCategories, 'create', $contest->getCid(), null, null, false);
            }
            if (!empty($updatedCategories)) {
                $this->eventLogService->log('team_category', $updatedCategories, 'update', $contest->getCid(), null, null, false);
            }
        }

        return count($groupData);
    }

    /**
     * Import organizations JSON.
     *
     * @param TeamAffiliation[]|null $saved The saved groups
     */
    public function importOrganizationsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        $organizationData = [];
        foreach ($data as $idx => $organization) {
            $organizationData[] = [
                'externalid' => @$organization['id'],
                'shortname' => @$organization['shortname'] ?? @$organization['name'],
                'name' => @$organization['formal_name'] ?? @$organization['name'],
                'country' => @$organization['country'],
                'icpc_id' => $organization['icpc_id'] ?? null,
            ];
        }

        return $this->importOrganizationData($organizationData, $saved);
    }

    /**
     * Import organization data from the given array.
     *
     * @param TeamAffiliation[]|null $saved The saved groups
     *
     * @throws NonUniqueResultException
     */
    protected function importOrganizationData(array $organizationData, ?array &$saved = null): int
    {
        $createdOrganizations = [];
        $updatedOrganizations = [];
        foreach ($organizationData as $organizationItem) {
            $externalId      = $organizationItem['externalid'];
            $teamAffiliation = null;
            $added           = false;
            if ($externalId !== null) {
                $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $externalId]);
            }
            if (!$teamAffiliation) {
                $teamAffiliation = new TeamAffiliation();
                $teamAffiliation->setExternalid($externalId);
                $this->em->persist($teamAffiliation);
                $added = true;
            }
            if (!isset($organizationItem['shortname'])) {
                throw new BadRequestHttpException('Shortname missing.');
            }
            $teamAffiliation
                ->setShortname($organizationItem['shortname'])
                ->setName($organizationItem['name'])
                ->setCountry($organizationItem['country'])
                ->setIcpcid($organizationItem['icpc_id'] ?? null);
            $this->em->flush();
            if ($added) {
                $createdOrganizations[] = $teamAffiliation->getAffilid();
            } else {
                $updatedOrganizations[] = $teamAffiliation->getAffilid();
            }
            $this->dj->auditlog('team_affiliation', $teamAffiliation->getAffilid(), 'replaced',
                                             'imported from tsv / json');
            if ($saved !== null) {
                $saved[] = $teamAffiliation;
            }
        }

        if ($contest = $this->dj->getCurrentContest()) {
            if (!empty($createdOrganizations)) {
                $this->eventLogService->log('team_affiliation', $createdOrganizations, 'create', $contest->getCid(), null, null, false);
            }
            if (!empty($updatedOrganizations)) {
                $this->eventLogService->log('team_affiliation', $updatedOrganizations, 'update', $contest->getCid(), null, null, false);
            }
        }

        return count($organizationData);
    }

    /**
     * Import teams TSV
     * @throws NonUniqueResultException
     */
    protected function importTeamsTsv(array $content, ?string &$message = null): int
    {
        $teamData = [];
        $l        = 1;
        foreach ($content as $line) {
            $l++;
            $line = Utils::parseTsvLine(trim($line));

            // teams.tsv contains data pertaining both to affiliations and teams.
            // Hence, return data for both tables.

            // We may do more integrity/format checking of the data here.

            // Set ICPC IDs to null if they are not given.
            $teamIcpcId = @$line[1];
            if (empty($teamIcpcId)) {
                $teamIcpcId = null;
            }
            $affiliationExternalid = preg_replace('/^INST-(U-)?/', '', @$line[7]);
            if (empty($affiliationExternalid)) {
                // TODO: note that when we set this external ID to NULL, we *will* add team affiliations
                // multiple times, as the App\Entity\TeamAffiliation query below will not find an affiliation.
                // We might want to change that to also search on shortname and/or name?
                $affiliationExternalid = null;
            }

            // Set team ID to ICPC ID if it has the literal value 'null' and the ICPC ID is numeric.
            $teamId = @$line[0];
            if ($teamId === 'null' && is_numeric($teamIcpcId)) {
                $teamId = (int)$teamIcpcId;
            }

            $teamData[] = [
                'team' => [
                    'teamid' => $teamId,
                    'icpcid' => $teamIcpcId,
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
        return $this->importTeamData($teamData, $message);
    }

    /**
     * Import teams JSON.
     *
     * @param Team[]|null $saved The saved teams
     */
    public function importTeamsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        $teamData = [];
        foreach ($data as $idx => $team) {
            $teamData[] = [
                'team' => [
                    'teamid' => $team['id'] ?? null,
                    'icpcid' => $team['icpc_id'] ?? null,
                    'categoryid' => $team['group_ids'][0] ?? null,
                    'name' => @$team['name'],
                    'display_name' => @$team['display_name'],
                    'publicdescription' => @$team['members'],
                    'room' => @$team['room'],
                ],
                'team_affiliation' => [
                    'externalid' => $team['organization_id'] ?? null,
                ]
            ];
        }

        return $this->importTeamData($teamData, $message, $saved);
    }

    /**
     * Import accounts JSON.
     *
     * @param User[]|null $saved The saved users
     */
    public function importAccountsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        $teamRole     = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);
        $juryRole     = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'jury']);
        $adminRole    = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'admin']);
        $balloonRole  = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'balloon']);
        $juryCategory = $this->em->getRepository(TeamCategory::class)->findOneBy(['name' => 'Jury']);
        if (!$juryCategory) {
            $juryCategory = new TeamCategory();
            $juryCategory
                ->setName('Jury')
                ->setSortorder(100)
                ->setVisible(false)
                ->setExternalid('jury');
            $this->em->persist($juryCategory);
            $this->em->flush();
        }
        $accountData = [];
        foreach ($data as $idx => $account) {
            $juryTeam = null;
            $roles    = [];
            switch ($account['type']) {
                case 'admin':
                    $roles[] = $adminRole;
                    // Don't break so we can also add the jury features
                case 'jury': // We don't break to let non existing role jury be interpret as role judge
                case 'judge':
                    if ($account['type'] !== 'admin') {
                        $roles[] = $juryRole;
                    }
                    $roles[]  = $teamRole;
                    $juryTeam = [
                        'name'              => $account['name'] ?? $account['username'],
                        'externalid'        => $account['externalid'] ?? $account['username'],
                        'category'          => $juryCategory,
                        'publicdescription' => $account['name'] ?? $account['username'],
                    ];
                    break;
                case 'team':
                    $roles[] = $teamRole;
                    break;
                case 'balloon':
                    $roles[] = $balloonRole;
                    break;
                case 'analyst':
                case 'staff':
                    // Ignore type analyst and staff for now. We don't have a useful mapping yet.
                    continue 2;
                default:
                    $message = sprintf('unknown role on index %d: %s', $idx, $account['type']);
                    return -1;
            }
            $accountData[] = [
                'user' => [
                    'name'           => $account['name'] ?? null,
                    'externalid'     => $account['id'] ?? $account['username'],
                    'username'       => $account['username'],
                    'plain_password' => $account['password'],
                    'teamid'         => $account['team_id'] ?? null,
                    'user_roles'     => $roles,
                    'ip_address'     => $account['ip'] ?? null,
                ],
                'team' => $juryTeam,
            ];
        }

        return $this->importAccountData($accountData, $saved);
    }

    /**
     * Import team data from the given array.
     *
     * @param Team[]|null $saved The saved teams
     *
     * @throws NonUniqueResultException
     */
    protected function importTeamData(array $teamData, ?string &$message, ?array &$saved = null): int
    {
        $createdAffiliations = [];
        $createdTeams        = [];
        $updatedTeams        = [];
        foreach ($teamData as $teamItem) {
            // It is legitimate that a team has no affiliation. Do not add it then.
            $teamAffiliation = null;
            $teamCategory    = null;
            if (!empty($teamItem['team_affiliation']['shortname'])) {
                // First look up if the affiliation already exists.
                $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['shortname' => $teamItem['team_affiliation']['shortname']]);
                if (!$teamAffiliation) {
                    $teamAffiliation  = new TeamAffiliation();
                    $propertyAccessor = PropertyAccess::createPropertyAccessor();
                    foreach ($teamItem['team_affiliation'] as $field => $value) {
                        $propertyAccessor->setValue($teamAffiliation, $field, $value);
                    }

                    $this->em->persist($teamAffiliation);
                    $this->em->flush();
                    $createdAffiliations[] = $teamAffiliation->getAffilid();
                    $this->dj->auditlog('team_affiliation', $teamAffiliation->getAffilid(),
                                        'added', 'imported from tsv');
                }
            } elseif (!empty($teamItem['team_affiliation']['externalid'])) {
                $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $teamItem['team_affiliation']['externalid']]);
                if (!$teamAffiliation) {
                    $teamAffiliation = new TeamAffiliation();
                    $teamAffiliation
                        ->setExternalid($teamItem['team_affiliation']['externalid'])
                        ->setName($teamItem['team_affiliation']['externalid'] . ' - auto-create during import');
                    $this->em->persist($teamAffiliation);
                    $this->dj->auditlog('team_affiliation',
                        $teamAffiliation->getAffilid(),
                        'added', 'imported from tsv / json');
                }
            }
            $teamItem['team']['affiliation'] = $teamAffiliation;
            unset($teamItem['team']['affilid']);

            if (!empty($teamItem['team']['categoryid'])) {
                $field = $this->eventLogService->apiIdFieldForEntity(TeamCategory::class);
                $teamCategory = $this->em->getRepository(TeamCategory::class)->findOneBy([$field => $teamItem['team']['categoryid']]);
                if (!$teamCategory) {
                    $teamCategory = new TeamCategory();
                    $teamCategory
                        ->setExternalid($teamItem['team']['categoryid'])
                        ->setName($teamItem['team']['categoryid'] . ' - auto-create during import');
                    $this->em->persist($teamCategory);
                    $this->dj->auditlog('team_category', $teamCategory->getCategoryid(),
                                        'added', 'imported from tsv');
                }
            }
            $teamItem['team']['category'] = $teamCategory;
            unset($teamItem['team']['categoryid']);

            $metadata = $this->em->getClassMetaData(Team::class);

            // Determine if we need to set the team ID manually or automatically
            if (empty($teamItem['team']['teamid'])) {
                $team = null;
            } else {
                $field = $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid';
                $team = $this->em->getRepository(Team::class)->findOneBy([$field => $teamItem['team']['teamid']]);
            }
            if (!$team) {
                $team  = new Team();
                $added = true;
            } else {
                $added = false;
            }

            if (empty($teamItem['team']['teamid'])) {
                $message = 'ID for team required';
                return -1;
            }

            if (preg_match('/^([a-zA-Z0-9]{1}([a-zA-Z0-9._-]{0,34}[a-zA-Z0-9])?)$/', $teamItem['team']['teamid']) === 0) {
                $message = 'ID not in CLICS format';
                return -1;
            }
            $team->setExternalid($teamItem['team']['teamid']);
            unset($teamItem['team']['teamid']);

            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            foreach ($teamItem['team'] as $field => $value) {
                $propertyAccessor->setValue($team, $field, $value);
            }

            if ($added) {
                $this->em->persist($team);
            }
            $this->em->flush();

            if ($added) {
                $createdTeams[] = $team->getTeamid();
            } else {
                $updatedTeams[] = $team->getTeamid();
            }

            $this->dj->auditlog('team', $team->getTeamid(), 'replaced', 'imported from tsv');
            if ($saved !== null) {
                $saved[] = $team;
            }
        }

        if ($contest = $this->dj->getCurrentContest()) {
            if (!empty($createdAffiliations)) {
                $this->eventLogService->log('team_affiliation', $createdAffiliations,
                                            'create', $contest->getCid());
            }
            if (!empty($createdTeams)) {
                $this->eventLogService->log('team', $createdTeams, 'create', $contest->getCid(), null, null, false);
            }
            if (!empty($updatedTeams)) {
                $this->eventLogService->log('team', $updatedTeams, 'update', $contest->getCid(), null, null, false);
            }
        }

        return count($teamData);
    }

    /**
     * Import account data from the given array.
     *
     * @param User[]|null $saved The saved users
     *
     * @throws NonUniqueResultException
     */
    protected function importAccountData(array $accountData, ?array &$saved = null): int
    {
        $newTeams     = [];
        foreach ($accountData as $accountItem) {
            if (!empty($accountItem['team'])) {
                $team = $this->em->getRepository(Team::class)->findOneBy([
                    'name' => $accountItem['team']['name'],
                    'category' => $accountItem['team']['category']
                ]);
                if ($team === null) {
                    $team = new Team();
                    $team
                        ->setName($accountItem['team']['name'])
                        ->setCategory($accountItem['team']['category'])
                        ->setExternalid($accountItem['team']['externalid'])
                        ->setPublicDescription($accountItem['team']['publicdescription'] ?? null);
                    $this->em->persist($team);
                    $action = EventLogService::ACTION_CREATE;
                } else {
                    $action = EventLogService::ACTION_UPDATE;
                }
                $this->em->flush();
                $newTeams[] = [
                    'team' => $team,
                    'action' => $action,
                ];
                $this->dj->auditlog('team', $team->getTeamid(), 'replaced',
                    'imported from tsv, autocreated for judge');
                $accountItem['user']['team'] = $team;
                unset($accountItem['user']['teamid']);
            }

            $user = $this->em->getRepository(User::class)->findOneBy(['username' => $accountItem['user']['username']]);
            if (!$user) {
                $user  = new User();
                $added = true;
            } else {
                $added = false;
            }

            if (array_key_exists('teamid', $accountItem['user'])) {
                $teamId = $accountItem['user']['teamid'];
                unset($accountItem['user']['teamid']);
                $team = null;
                if ($teamId !== null) {
                    $field = $this->eventLogService->apiIdFieldForEntity(Team::class);
                    $team  = $this->em->getRepository(Team::class)->findOneBy([$field => $teamId]);
                    if (!$team) {
                        $team = new Team();
                        $team
                            ->setExternalid($teamId)
                            ->setName($teamId . ' - auto-create during import');
                        $this->em->persist($team);
                        $this->dj->auditlog('team', $team->getTeamid(),
                            'added', 'imported from tsv');
                    }
                }
                $accountItem['user']['team'] = $team;
            }

            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            foreach ($accountItem['user'] as $field => $value) {
                $propertyAccessor->setValue($user, $field, $value);
            }

            if ($added) {
                $this->em->persist($user);
            }
            $this->em->flush();

            if ($saved !== null) {
                $saved[] = $user;
            }

            $this->dj->auditlog('user', $user->getUserid(), 'replaced', 'imported from tsv');
        }

        if ($contest = $this->dj->getCurrentContest()) {
            foreach ($newTeams as $newTeam) {
                $team = $newTeam['team'];
                $action = $newTeam['action'];
                $this->eventLogService->log('team', $team->getTeamid(), $action, $contest->getCid());
            }
        }

        return count($accountData);
    }

    /**
     * Import accounts TSV
     */
    protected function importAccountsTsv(array $content, ?string &$message = null): int
    {
        $accountData = [];
        $l           = 1;
        $teamRole    = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);
        $juryRole    = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'jury']);
        $adminRole   = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'admin']);
        $balloonRole  = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'balloon']);

        $juryCategory = $this->em->getRepository(TeamCategory::class)->findOneBy(['name' => 'Jury']);
        if (!$juryCategory) {
            $juryCategory = new TeamCategory();
            $juryCategory
                ->setName('Jury')
                ->setSortorder(100)
                ->setVisible(false)
                ->setExternalid('jury');
            $this->em->persist($juryCategory);
            $this->em->flush();
        }

        foreach ($content as $line) {
            $l++;
            $line = Utils::parseTsvLine(trim($line));

            $team  = $juryTeam = null;
            $roles = [];
            switch ($line[0]) {
                case 'admin':
                    $roles[] = $adminRole;
                    // Don't break so we can also add the jury features
                case 'jury': // We don't break to let non existing role jury be interpret as role judge
                case 'judge':
                    if ($line[0] !== 'admin') {
                        $roles[] = $juryRole;
                    }
                    $roles[]  = $teamRole;
                    $juryTeam = ['name' => $line[1], 'externalid' => $line[2], 'category' => $juryCategory, 'publicdescription' => $line[1]];
                    break;
                case 'team':
                    $roles[] = $teamRole;
                    // For now, we assume we can find the teamid by parsing
                    // the username and taking the number in the middle, i.e. we
                    // allow any username in the form "abc" where a and c are arbitrary
                    // strings that contain no numbers and b only contains numbers. The teamid
                    // id is then "b".
                    // Note that https://ccs-specs.icpc.io/2021-11/ccs_system_requirements#accountstsv
                    // assumes team accounts of the form "team-nnn" where
                    // nnn is a zero-padded team number.
                    $teamId = preg_replace('/^[^0-9]*0*([0-9]+)[^0-9]*$/', '\1', $line[2]);
                    if (!preg_match('/^[0-9]+$/', $teamId)) {
                        $message = sprintf('cannot parse team id on line %d from "%s"', $l,
                                           $line[2]);
                        return -1;
                    }
                    $field = $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid';
                    $team  = $this->em->getRepository(Team::class)->findOneBy([$field => $teamId]);
                    if ($team === null) {
                        $message = sprintf('unknown team id %s on line %d', $teamId, $l);
                        return -1;
                    }
                    break;
                case 'balloon':
                    $roles[] = $balloonRole;
                    break;
                case 'analyst':
                    // Ignore type analyst for now. We don't have a useful mapping yet.
                    continue 2;
                default:
                    $message = sprintf('unknown role on line %d: %s', $l, $line[0]);
                    return -1;
            }

            // accounts.tsv contains data pertaining to users, their roles and
            // teams. Hence, return data for both tables.

            // We may do more integrity/format checking of the data here.
            $accountData[] = [
                'user' => [
                    'name' => $line[1],
                    'externalid' => $line[2],
                    'username' => $line[2],
                    'plain_password' => $line[3],
                    'team' => $team,
                    'user_roles' => $roles,
                    'ip_address' => $line[4] ?? null,
                ],
                'team' => $juryTeam,
            ];
        }

        return $this->importAccountData($accountData);
    }
}
