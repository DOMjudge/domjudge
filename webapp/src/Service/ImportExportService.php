<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Utils\Scoreboard\Filter;
use App\Utils\Scoreboard\ScoreboardMatrixItem;
use App\Utils\Utils;
use Collator;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;

class ImportExportService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

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
     * Get the YAML data for a given contest
     * @param Contest $contest
     * @return array
     * @throws Exception
     */
    public function getContestYamlData(Contest $contest)
    {
        // TODO: it seems we dump contest.yaml and system.yaml and problemset.yaml in one here?

        $data = [
            'name' => $contest->getName(),
            'short-name' => $contest->getShortname(),
            'start-time' => Utils::absTime($contest->getStarttime(), true),
            'duration' => Utils::relTime($contest->getContestTime((float)$contest->getEndtime())),
        ];
        if ($contest->getFreezetime() !== null) {
            $data['scoreboard-freeze-duration'] = Utils::relTime(
                $contest->getContestTime((float)$contest->getEndtime()) - $contest->getContestTime((float)$contest->getFreezetime()),
                true);
        }
        $data = array_merge($data, [
            'penalty-time' => $this->config->get('penalty_time'),
            'default-clars' => $this->config->get('clar_answers'),
            'clar-categories' => array_values($this->config->get('clar_categories')),
            'languages' => [],
            'problems' => [],
        ]);

        /** @var Language[] $languages */
        $languages = $this->em->getRepository(Language::class)->findAll();
        foreach ($languages as $language) {
            // TODO: compiler, -flags, runner, -flags?
            $data['languages'][] = [
                'name' => $language->getName(),
            ];
        }

        /** @var ContestProblem $contestProblem */
        foreach ($contest->getProblems() as $contestProblem) {
            // Our color field can be both a HTML color name and an RGB value.
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
        if (empty($data)) {
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
                // make sure ISO 8601 but with the T replaced with a space also works
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
        $contest = new Contest();
        $contest
            ->setName($data['name'])
            ->setShortname(preg_replace(
                               $invalid_regex,
                               '_',
                               $data['shortname'] ?? $data['short-name'] ?? $data['id']
                           ))
            ->setExternalid($contest->getShortname())
            ->setStarttimeString(date_format($starttime, 'Y-m-d H:i:s e'))
            ->setActivatetimeString('-24:00')
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

        if (isset($data['default-clars'])) {
            $currentClarificationAnswersConfiguration = $this->config->get('clar_answers');
            if ($currentClarificationAnswersConfiguration != $data['default-clars']) {
                $clarificationAnswersConfiguration = $this->em->getRepository(Configuration::class)->findOneBy(['name' => 'clar_answers']);
                if (!$clarificationAnswersConfiguration) {
                    $clarificationAnswersConfiguration = new Configuration();
                    $clarificationAnswersConfiguration->setName('clar_answers');
                    $this->em->persist($clarificationAnswersConfiguration);
                }
                $clarificationAnswersConfiguration->setValue($data['default-clars']);
            }
        }

        if (is_array($data['clar-categories'] ?? null)) {
            $currentClarificationCategoriesConfiguration = $this->config->get('clar_categories');
            if ($currentClarificationCategoriesConfiguration != $data['clar-categories']) {
                $clarificationCategoriesConfiguration = $this->em->getRepository(Configuration::class)->findOneBy(['name' => 'clar_categories']);
                if (!$clarificationCategoriesConfiguration) {
                    $clarificationCategoriesConfiguration = new Configuration();
                    $clarificationCategoriesConfiguration->setName('clar_categories');
                    $this->em->persist($clarificationCategoriesConfiguration);
                }
                $categories                           = [];
                foreach ($data['clar-categories'] as $category) {
                    $categoryKey              = substr(
                        str_replace(
                            [' ', ',', '.'],
                            '-',
                            strtolower($category)
                        ),
                        0,
                        9
                    );
                    $categories[$categoryKey] = $category;
                }
                $clarificationCategoriesConfiguration->setValue($categories);
            }
        }

        // We do not import language details, as there's very little to actually import

        if (isset($data['problems'])) {
            $this->importProblemsData($contest, $data['problems']);
        }

        $cid = (string)$contest->getApiId($this->eventLogService);

        $this->em->flush();
        return true;
    }

    public function importProblemsData(Contest $contest, $problems, array &$ids = null): bool
    {
        foreach ($problems as $problemData) {
            // Deal with obsolete attribute names:
            $problemName  = $problemData['name'] ?? $problemData['short-name'] ?? null;
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
                // We need to set both the entities as well as the ID's because of the composite primary key
                ->setProblem($problem)
                ->setContest($contest);
            $this->em->persist($contestProblem);

            $ids[] = (string)$problem->getApiId($this->eventLogService);
        }

        $this->em->flush();

        // For now this method will never fail so always return true
        return true;
    }

    /**
     * Get group data
     * @return array
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
                $team->getTeamid(),
                $team->getIcpcid(),
                $team->getCategory()->getCategoryid(),
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
     * Get results data for the given sortorder
     * @param int $sortOrder
     * @return array
     * @throws Exception
     */
    public function getResultsData(int $sortOrder)
    {
        // we'll here assume that the requested file will be of the current contest,
        // as all our scoreboard interfaces do
        // 1    External ID     24314   integer
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
        // determine number of problems solved by median team
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
            /** @var ScoreboardMatrixItem $matrixItem */
            foreach ($scoreboard->getMatrix()[$teamScore->team->getTeamid()] as $matrixItem) {
                $time    = Utils::scoretime($matrixItem->time, $scoreIsInSeconds);
                $maxTime = max($maxTime, $time);
            }

            $rank      = $teamScore->rank;
            $numPoints = $teamScore->numPoints;
            if ($rank <= 4) {
                $awardString = 'Gold Medal';
            } elseif ($rank <= 8) {
                $awardString = 'Silver Medal';
            } elseif ($rank <= 12 + $contest->getB()) {
                $awardString = 'Bronze Medal';
            } elseif ($numPoints >= $median) {
                // teams with equally solved number of problems get the same rank
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
                $teamScore->team->getApiId($this->eventLogService),
                $rank,
                $awardString,
                $teamScore->numPoints,
                $teamScore->totalTime,
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
     * Import a TSV file
     * @param string       $type
     * @param UploadedFile $file
     * @param string|null  $message
     * @return int
     * @throws Exception
     */
    public function importTsv(string $type, UploadedFile $file, string &$message = null): int
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
            $message = sprintf("Unknown format or version: %s != %s", $version, $versionMatch);
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
     * @param string       $type
     * @param UploadedFile $file
     * @param string|null  $message
     * @return int
     * @throws Exception
     */
    public function importJson(string $type, UploadedFile $file, string &$message = null): int
    {
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $message = 'File contents is not valid JSON: ' . json_last_error_msg();
            return -1;
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
            default:
                $message = sprintf('Invalid import type %s', $type);
                return -1;
        }
    }

    /**
     * Import groups TSV
     * @param array       $content
     * @param string|null $message
     * @return int
     * @throws Exception
     */
    protected function importGroupsTsv(array $content, string &$message = null): int
    {
        $groupData = [];
        $l         = 1;
        foreach ($content as $line) {
            $l++;
            $line = Utils::parseTsvLine(trim($line));
            if (!is_numeric($line[0])) {
                $message = sprintf('Invalid id format on line %d', $l);
                return -1;
            }
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
     * @param array               $data
     * @param string|null         $message
     * @param TeamCategory[]|null $saved The saved groups
     *
     * @return int
     * @throws Exception
     */
    public function importGroupsJson(array $data, string &$message = null, array &$saved = null): int
    {
        $groupData = [];
        foreach ($data as $idx => $group) {
            if (isset($group['id']) && !is_numeric($group['id'])) {
                $message = sprintf('Invalid id format for object at index %d', $idx);
                return -1;
            }
            $groupData[] = [
                'categoryid' => @$group['id'],
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
     * @param array $groupData
     * @param TeamCategory[]|null $saved The saved groups
     *
     * @return int
     *
     * @throws NonUniqueResultException
     */
    protected function importGroupData(array $groupData, array &$saved = null): int
    {
        // We want to overwrite the ID so change the ID generator
        $metadata = $this->em->getClassMetaData(TeamCategory::class);

        foreach ($groupData as $groupItem) {
            if (empty($groupItem['categoryid'])) {
                $categoryId = null;
                $teamCategory = null;
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
                $metadata->setIdGenerator(new IdentityGenerator());
            } else {
                $categoryId = (int)$groupItem['categoryid'];
                $teamCategory = $this->em->getRepository(TeamCategory::class)->find($categoryId);
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
                $metadata->setIdGenerator(new AssignedGenerator());
            }
            if (!$teamCategory) {
                $teamCategory = new TeamCategory();
                if ($categoryId !== null) {
                    $teamCategory->setCategoryid($categoryId);
                }
                $this->em->persist($teamCategory);
                $action = EventLogService::ACTION_CREATE;
            } else {
                $action = EventLogService::ACTION_UPDATE;
            }
            $teamCategory
                ->setName($groupItem['name'])
                ->setVisible($groupItem['visible'] ?? true)
                ->setSortorder($groupItem['sortorder'] ?? 0)
                ->setColor($groupItem['color'] ?? null);
            $this->em->flush();
            if ($contest = $this->dj->getCurrentContest()) {
                $this->eventLogService->log('team_category', $teamCategory->getCategoryid(), $action,
                                            $contest->getCid());
            }
            $this->dj->auditlog('team_category', $teamCategory->getCategoryid(), 'replaced',
                                             'imported from tsv / json');
            if ($saved !== null) {
                $saved[] = $teamCategory;
            }
        }

        return count($groupData);
    }

    /**
     * Import organizations JSON
     *
     * @param array                  $data
     * @param string|null            $message
     * @param TeamAffiliation[]|null $saved The saved groups
     *
     * @return int
     * @throws Exception
     */
    public function importOrganizationsJson(array $data, string &$message = null, array &$saved = null): int
    {
        $organizationData = [];
        foreach ($data as $idx => $organization) {
            $organizationData[] = [
                'externalid' => @$organization['id'],
                'shortname' => @$organization['name'],
                'name' => @$organization['formal_name'],
                'country' => @$organization['country'],
            ];
        }

        return $this->importOrganizationData($organizationData, $saved);
    }

    /**
     * Import organization data from the given array
     *
     * @param array                  $organizationData
     * @param TeamAffiliation[]|null $saved The saved groups
     *
     * @return int
     *
     * @throws NonUniqueResultException
     */
    protected function importOrganizationData(array $organizationData, array &$saved = null): int
    {
        foreach ($organizationData as $organizationItem) {
            $externalId      = $organizationItem['externalid'];
            $teamAffiliation = null;
            if ($externalId !== null) {
                $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $externalId]);
            }
            if (!$teamAffiliation) {
                $teamAffiliation = new TeamAffiliation();
                $teamAffiliation->setExternalid($externalId);
                $this->em->persist($teamAffiliation);
                $action = EventLogService::ACTION_CREATE;
            } else {
                $action = EventLogService::ACTION_UPDATE;
            }
            $teamAffiliation
                ->setShortname($organizationItem['shortname'])
                ->setName($organizationItem['name'])
                ->setCountry($organizationItem['country']);
            $this->em->flush();
            if ($contest = $this->dj->getCurrentContest()) {
                $this->eventLogService->log('team_affiliation', $teamAffiliation->getAffilid(), $action,
                                            $contest->getCid());
            }
            $this->dj->auditlog('team_affiliation', $teamAffiliation->getAffilid(), 'replaced',
                                             'imported from tsv / json');
            if ($saved !== null) {
                $saved[] = $teamAffiliation;
            }
        }

        return count($organizationData);
    }

    /**
     * Import teams TSV
     * @param array       $content
     * @param string|null $message
     * @return int
     * @throws NonUniqueResultException
     */
    protected function importTeamsTsv(array $content, string &$message = null): int
    {
        $teamData = [];
        $l        = 1;
        foreach ($content as $line) {
            $l++;
            $line = Utils::parseTsvLine(trim($line));

            // teams.tsv contains data pertaining both to affiliations and teams.
            // hence return data for both tables.

            // we may do more integrity/format checking of the data here.

            // Set ICPC  ID's to null if they are not given
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

            // Set team ID to ICPC ID if it has the literal value 'null' and the ICPC ID is numeric
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
        return $this->importTeamData($teamData);
    }

    /**
     * Import teams JSON
     *
     * @param array       $data
     * @param string|null $message
     * @param Team[]|null $saved The saved teams
     *
     * @return int
     * @throws Exception
     */
    public function importTeamsJson(array $data, string &$message = null, array &$saved = null): int
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
                    'members' => @$team['members'],
                ],
                'team_affiliation' => [
                    'externalid' => $team['organization_id'] ?? null,
                ]
            ];
        }

        return $this->importTeamData($teamData, $saved);
    }

    /**
     * Import team data from the given array
     *
     * @param array       $teamData
     * @param Team[]|null $saved The saved teams
     *
     * @return int
     *
     * @throws NonUniqueResultException
     */
    protected function importTeamData(array $teamData, array &$saved = null): int
    {
        // We want to overwrite the ID so change the ID generator
        $metadata = $this->em->getClassMetaData(TeamCategory::class);
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
                $teamCategory = $this->em->getRepository(TeamCategory::class)->find($teamItem['team']['categoryid']);
                if (!$teamCategory) {
                    $teamCategory = new TeamCategory();
                    $teamCategory
                        ->setCategoryid((int)$teamItem['team']['categoryid'])
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
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
                $metadata->setIdGenerator(new IdentityGenerator());
            } else {
                $team = $this->em->getRepository(Team::class)->find($teamItem['team']['teamid']);
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
                $metadata->setIdGenerator(new AssignedGenerator());
            }
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
     * @param array       $content
     * @param string|null $message
     * @return int
     * @throws Exception
     */
    protected function importAccountsTsv(array $content, string &$message = null): int
    {
        $accountData = [];
        $l           = 1;
        $teamRole    = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'team']);
        $juryRole    = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'jury']);
        $adminRole   = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => 'admin']);

        $juryCategory = $this->em->getRepository(TeamCategory::class)->findOneBy(['name' => 'Jury']);
        if (!$juryCategory) {
            $juryCategory = new TeamCategory();
            $juryCategory
                ->setName('Jury')
                ->setSortorder(100)
                ->setVisible(false);
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
                    break;
                case 'judge':
                    $roles[]  = $juryRole;
                    $roles[]  = $teamRole;
                    $juryTeam = ['name' => $line[1], 'category' => $juryCategory, 'members' => $line[1]];
                    break;
                case 'team':
                    $roles[] = $teamRole;
                    // For now we assume we can find the teamid by parsing
                    // the username and taking the number in the middle, i.e. we
                    // allow any username in the form "abc" where a and c are arbitrary
                    // strings that contain no numbers and b only contains numbers. The teamid
                    // id is then "b".
                    // Note that https://ccs-specs.icpc.io/ccs_system_requirements#accountstsv
                    // assumes team accounts of the form "team-nnn" where
                    // nnn is a zero-padded team number.
                    $teamId = preg_replace('/^[^0-9]*0*([0-9]+)[^0-9]*$/', '\1', $line[2]);
                    if (!preg_match('/^[0-9]+$/', $teamId)) {
                        $message = sprintf('cannot parse team id on line %d from "%s"', $l,
                                           $line[2]);
                        return -1;
                    }
                    $team = $this->em->getRepository(Team::class)->find($teamId);
                    if ($team === null) {
                        $message = sprintf('unknown team id %s on line %d', $teamId, $l);
                        return -1;
                    }
                    break;
                case 'analyst':
                    // Ignore type analyst for now. We don't have a useful mapping yet.
                    continue 2;
                default:
                    $message = sprintf('unknown role on line %d: %s', $l, $line[0]);
                    return -1;
            }

            // accounts.tsv contains data pertaining to users, their roles and
            // teams. Hence return data for both tables.

            // We may do more integrity/format checking of the data here.
            $accountData[] = [
                'user' => [
                    'name' => $line[1],
                    'username' => $line[2],
                    'plain_password' => $line[3],
                    'team' => $team,
                    'user_roles' => $roles,
                    'ip_address' => $line[4] ?? null,
                ],
                'team' => $juryTeam,
            ];
        }

        $newTeams = [];
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
                        ->setMembers($accountItem['team']['members'] ?? null);
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
            }

            $user = $this->em->getRepository(User::class)->findOneBy(['username' => $accountItem['user']['username']]);
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
                $this->em->persist($user);
            }
            $this->em->flush();

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
}
