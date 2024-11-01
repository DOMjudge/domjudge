<?php declare(strict_types=1);

namespace App\Service;

use App\DataTransferObject\ResultRow;
use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalContestSource;
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
use DateTimeImmutable;
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
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService,
        protected readonly ValidatorInterface $validator
    ) {}

    /**
     * Get the YAML data for a given contest.
     *
     * @return array<string, int|string|array<array{id: string, label: string, letter: string,
     *                                              name: string, color: string, rgb: string}>> $contest
     */
    public function getContestYamlData(Contest $contest, bool $includeProblems = true): array
    {
        // We expect contest.yaml and problemset.yaml combined into one file here.

        $data = [
            'id' => $contest->getExternalid(),
            'formal_name' => $contest->getName(),
            'name' => $contest->getShortname(),
            'start_time' => Utils::absTime($contest->getStarttime(), true),
            'end_time' => Utils::absTime($contest->getEndtime(), true),
            'duration' => Utils::relTime($contest->getContestTime((float)$contest->getEndtime())),
            'penalty_time' => $this->config->get('penalty_time'),
            'activate_time' => Utils::absTime($contest->getActivatetime(), true),
        ];
        if ($warnMsg = $contest->getWarningMessage()) {
            $data['warning_message'] = $warnMsg;
        }

        foreach (['gold', 'silver', 'bronze'] as $medal) {
            $medalCount = $contest->{'get' . ucfirst($medal) . 'Medals'}();
            if ($medalCount) {
                $data['medals'][$medal] = $medalCount;
            }
        }

        if ($contest->getFreezetime() !== null) {
            $data['scoreboard_freeze_time'] = Utils::absTime($contest->getFreezetime(), true);
            $data['scoreboard_freeze_duration'] = Utils::relTime(
                $contest->getContestTime((float)$contest->getEndtime()) - $contest->getContestTime((float)$contest->getFreezetime()),
                true,
            );
            if ($contest->getUnfreezetime() !== null) {
                $data['scoreboard_thaw_time'] = Utils::absTime($contest->getUnfreezetime(), true);
            }
        }
        if ($contest->getFinalizetime() !== null) {
            $data['finalize_time'] = Utils::absTime($contest->getFinalizetime(), true);
        }

        if ($contest->getDeactivatetime() !== null) {
            $data['deactivate_time'] = Utils::absTime($contest->getDeactivatetime(), true);
        }

        if ($includeProblems) {
            $data['problems'] = [];
            /** @var ContestProblem $contestProblem */
            foreach ($contest->getProblems() as $contestProblem) {
                // Our color field can be both an HTML color name and an RGB value.
                // If it is in RGB, we try to find the closest HTML color name.
                $color              = $contestProblem->getColor() === null ? null : Utils::convertToColor($contestProblem->getColor());
                $data['problems'][] = [
                    'id' => $contestProblem->getProblem()->getExternalid(),
                    'label' => $contestProblem->getShortname(),
                    'letter' => $contestProblem->getShortname(),
                    'name' => $contestProblem->getProblem()->getName(),
                    'color' => $color ?? $contestProblem->getColor(),
                    'rgb' => $contestProblem->getColor() === null ? null : Utils::convertToHex($contestProblem->getColor()),
                ];
            }
        }

        return $data;
    }

    /**
     * Finds the first set field from $fields in $data and parse it as a date.
     *
     * To verify that everything works as expected the $errorMessage needs to be checked
     * for parsing errors.
     *
     * @param array<string> $fields
     * @param array<string, string|DateTime|DateTimeImmutable> $data
     */
    protected function convertImportedTime(array $fields, array $data, ?string &$errorMessage = null): ?DateTimeImmutable
    {
        $timeValue = null;
        $usedField = null;
        foreach ($fields as $field) {
            $timeValue = $data[$field] ?? null;
            $usedField = $field;
            // We need to check as the value for the key can be null
            if ($timeValue) {
                break;
            }
        }

        if (is_string($timeValue)) {
            $time = date_create_from_format(DateTime::ISO8601, $timeValue) ?:
                // Make sure ISO 8601 but with the T replaced with a space also works.
                date_create_from_format('Y-m-d H:i:sO', $timeValue);
        } else {
            /** @var DateTime|DateTimeImmutable $time */
            $time = $timeValue;
        }
        // If/When parsing fails we get a false instead of a null
        if ($time === false) {
            $errorMessage = 'Can not parse '.$usedField;
            return null;
        } elseif ($time) {
            $time = $time->setTimezone(new DateTimeZone(date_default_timezone_get()));
        }
        return $time instanceof DateTime ? DateTimeImmutable::createFromMutable($time) : $time;
    }

    public function importContestData(mixed $data, ?string &$errorMessage = null, string &$cid = null): bool
    {
        if (empty($data) || !is_array($data)) {
            $errorMessage = 'Error parsing YAML file.';
            return false;
        }

        $activateTimeFields = ['activate_time', 'activation_time', 'activate-time', 'activation-time'];
        $deactivateTimeFields = preg_filter('/^/', 'de', $activateTimeFields);
        $startTimeFields = ['start_time', 'start-time'];
        $requiredFields = [$startTimeFields, ['name', 'formal_name'], ['id', 'short_name', 'short-name'], 'duration'];
        $missingFields = [];
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
            $errorMessage = sprintf('Missing fields: %s', implode(', ', $missingFields));
            return false;
        }

        $invalid_regex = str_replace(['/^[', '+$/'], ['/[^', '/'], DOMJudgeService::EXTERNAL_IDENTIFIER_REGEX);

        $startTime = $this->convertImportedTime($startTimeFields, $data, $errorMessage);
        if ($errorMessage) {
            return false;
        }

        // Activate time is special, it can return non empty message for parsing error or null if no field was provided
        $activateTime = $this->convertImportedTime($activateTimeFields, $data, $errorMessage);
        if ($errorMessage) {
            return false;
        } elseif (!$activateTime) {
            $activateTime = new DateTime();
            if ($activateTime > $startTime) {
                $activateTime = $startTime;
            }
        }

        $deactivateTime = $this->convertImportedTime($deactivateTimeFields, $data, $errorMessage);
        if ($errorMessage) {
            return false;
        }

        $contest = new Contest();
        $contest
            ->setName($data['name'] ?? $data['formal_name'] )
            ->setShortname(preg_replace(
                               $invalid_regex,
                               '_',
                               $data['short_name'] ?? $data['shortname'] ?? $data['short-name'] ?? $data['id']
                           ))
            ->setExternalid($contest->getShortname())
            ->setWarningMessage($data['warning_message'] ?? $data['warning-message'] ?? null)
            ->setStarttimeString(date_format($startTime, 'Y-m-d H:i:s e'))
            ->setActivatetimeString(date_format($activateTime, 'Y-m-d H:i:s e'))
            ->setEndtimeString(sprintf('+%s', $data['duration']))
            ->setPublic($data['public'] ?? true);
        if ($deactivateTime) {
            $contest->setDeactivatetimeString(date_format($deactivateTime, 'Y-m-d H:i:s e'));
        }

        // Get all visible categories. For now, we assume these are the ones getting awards
        $visibleCategories = $this->em->getRepository(TeamCategory::class)->findBy(['visible' => true]);

        if (empty($visibleCategories)) {
            $contest->setMedalsEnabled(false);
        } else {
            foreach ($visibleCategories as $visibleCategory) {
                $contest
                    ->setMedalsEnabled(true)
                    ->addMedalCategory($visibleCategory);
            }

            foreach (['gold', 'silver', 'bronze'] as $medal) {
                if (isset($data['medals'][$medal])) {
                    $setter = 'set' . ucfirst($medal) . 'Medals';
                    $contest->$setter($data['medals'][$medal]);
                }
            }
        }

        /** @var string|null $freezeDuration */
        $freezeDuration = $data['scoreboard_freeze_duration'] ?? $data['scoreboard-freeze-duration'] ?? $data['scoreboard-freeze-length'] ?? null;
        /** @var string|null $freezeStart */
        $freezeStart = $data['scoreboard-freeze'] ?? $data['freeze'] ?? null;

        if ($freezeDuration !== null) {
            $freezeDurationDiff = Utils::timeStringDiff($data['duration'], $freezeDuration);
            if (str_starts_with($freezeDurationDiff, '-')) {
                $errorMessage = 'Freeze duration is longer than contest length';
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
                $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
            }

            $errorMessage = sprintf("Contest has errors:\n\n%s", implode("\n", $messages));
            return false;
        }

        $this->em->persist($contest);

        $shadow = $data['shadow'] ?? null;
        if ($shadow) {
            $externalSource = $this->em->getRepository(ExternalContestSource::class)->findOneBy(['contest' => $contest]) ?: new ExternalContestSource();
            $externalSource->setContest($contest);
            foreach ($shadow as $field => $value) {
                // Overwrite the existing value if the property is defined in the data: $externalSource-setSource($data['shadow']['source'])
                $fieldFunc = 'set'.ucwords($field);
                $fieldArgs = [$value];
                if (method_exists($externalSource, $fieldFunc)) {
                    $externalSource->$fieldFunc(...$fieldArgs);
                }
            }
            $this->em->persist($externalSource);
        }

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

        $cid = $contest->getExternalid();

        $this->em->flush();
        return true;
    }

    /**
     * @param array{name?: string, short-name?: string, id?: string, label?: string,
     *              letter?: string, time_limit?: int, rgb?: string, color?: string,
     *              problems?: array{name?: string, short-name?: string, id?: string, label?: string,
     *                              letter?: string, label?: string, letter?: string}} $problems
     * @param string[]|null $ids
     * @param array<string, string[]> $messages
     */
    public function importProblemsData(Contest $contest, array $problems, array &$ids = null, ?array &$messages = []): bool
    {
        // For problemset.yaml the root key is called `problems`, so handle that case
        // TODO: Move this check away to make the $problems array shape easier
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

            $errors           = $this->validator->validate($problem);
            $hasProblemErrors = $errors->count();
            if ($hasProblemErrors) {
                /** @var ConstraintViolationInterface $error */
                foreach ($errors as $error) {
                    $messages['danger'][] = sprintf(
                        'Error: problems.%s.%s: %s',
                        $problem->getExternalid(),
                        $error->getPropertyPath(),
                        $error->getMessage()
                    );
                }
            }

            $contestProblem = new ContestProblem();
            $contestProblem
                ->setShortname($problemLabel)
                ->setColor($problemData['rgb'] ?? $problemData['color'] ?? null)
                // We need to set both the entities and the IDs because of the composite primary key.
                ->setProblem($problem)
                ->setContest($contest);

            $errors                  = $this->validator->validate($contestProblem);
            $hasContestProblemErrors = $errors->count();
            if ($hasContestProblemErrors) {
                /** @var ConstraintViolationInterface $error */
                foreach ($errors as $error) {
                    $messages['danger'][] = sprintf(
                        'Error: problems.%s.contestproblem.%s: %s',
                        $problem->getExternalid(),
                        $error->getPropertyPath(),
                        $error->getMessage()
                    );
                }
            }

            if ($hasProblemErrors || $hasContestProblemErrors) {
                return false;
            }

            $this->em->persist($problem);
            $this->em->persist($contestProblem);
            $this->em->flush();

            $ids[] = $problem->getExternalid();
        }

        $this->em->flush();

        return true;
    }

    /**
     * Get group data
     *
     * @return array<string[]>
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
            $data[] = [$category->getExternalid(), $category->getName()];
        }

        return $data;
    }

    /**
     * Get team data
     *
     * @return array<array<string|null>>
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
                $team->getExternalid(),
                $team->getIcpcId(),
                $team->getCategory()->getExternalid(),
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
     * @return ResultRow[]
     */
    public function getResultsData(
        int $sortOrder,
        bool $individuallyRanked = false,
        bool $honors = true,
    ): array {
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

        $ranks            = [];
        $groupWinners     = [];
        $data             = [];
        $lowestMedalPoints = 0;

        // For every team that we skip because it is not in a medal category, we need to include one
        // additional rank. So keep track of the number of skipped teams
        $skippedTeams     = 0;

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
            $skip      = false;

            if (!$contest->getMedalCategories()->contains($teamScore->team->getCategory())) {
                $skip = true;
                $skippedTeams++;
            }

            if ($numPoints === 0) {
                // Teams with 0 points won't get a medal, a rank or an honor.
                // They will always get an honorable mention.
                $data[] = new ResultRow(
                    $teamScore->team->getIcpcId(),
                    null,
                    'Honorable',
                    $teamScore->numPoints,
                    $teamScore->totalTime,
                    $maxTime,
                    null
                );
                continue;
            }

            if (!$skip && $rank - $skippedTeams <= $contest->getGoldMedals()) {
                $awardString = 'Gold Medal';
                $lowestMedalPoints = $teamScore->numPoints;
            } elseif (!$skip && $rank - $skippedTeams <= $contest->getGoldMedals() + $contest->getSilverMedals()) {
                $awardString = 'Silver Medal';
                $lowestMedalPoints = $teamScore->numPoints;
            } elseif (!$skip && $rank - $skippedTeams <= $contest->getGoldMedals() + $contest->getSilverMedals() + $contest->getBronzeMedals() + $contest->getB()) {
                $awardString = 'Bronze Medal';
                $lowestMedalPoints = $teamScore->numPoints;
            } elseif ($numPoints >= $median) {
                // Teams with equally solved number of problems get the same rank unless $full is true.
                if (!$individuallyRanked) {
                    if (!isset($ranks[$numPoints])) {
                        $ranks[$numPoints] = $rank;
                    }
                    $rank = $ranks[$numPoints];
                }
                if ($honors) {
                    if ($numPoints === $lowestMedalPoints
                        || $rank - $skippedTeams <= $contest->getGoldMedals() + $contest->getSilverMedals() + $contest->getBronzeMedals() + $contest->getB()) {
                        // Some teams out of the medal categories but ranked higher than bronze medallists may get more points.
                        $awardString = 'Highest Honors';
                    } elseif ($numPoints === $lowestMedalPoints - 1) {
                        $awardString = 'High Honors';
                    } else {
                        $awardString = 'Honors';
                    }
                } else {
                    $awardString = 'Ranked';
                }
            } else {
                $awardString = 'Honorable';
                $rank        = null;
            }

            $categoryId  = $teamScore->team->getCategory()->getCategoryid();
            if (isset($groupWinners[$categoryId])) {
                $groupWinner = null;
            } else {
                $groupWinners[$categoryId] = true;
                $groupWinner               = $teamScore->team->getCategory()->getName();
            }

            $data[] = new ResultRow(
                $teamScore->team->getIcpcId(),
                $rank,
                $awardString,
                $teamScore->numPoints,
                $teamScore->totalTime,
                $maxTime,
                $groupWinner,
            );
        }

        // Sort by rank/name.
        uasort($data, function (ResultRow $a, ResultRow $b) use ($teams) {
            if ($a->rank !== $b->rank) {
                // Honorable mention has no rank.
                if ($a->rank === null) {
                    return 1;
                } elseif ($b->rank === null) {
                    return -11;
                }
                return $a->rank <=> $b->rank;
            }
            $teamA = $teams[$a->teamId] ?? null;
            $teamB = $teams[$b->teamId] ?? null;
            $nameA = $teamA?->getEffectiveName();
            $nameB = $teamB?->getEffectiveName();
            $collator = new Collator('en');
            return $collator->compare($nameA, $nameB);
        });

        return array_values($data);
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
            } catch (ParseException $parseException) {
                $message = "File contents is not valid JSON or YAML: " . $parseException->getMessage();
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
     *
     * @param string[] $content
     */
    protected function importGroupsTsv(array $content, ?string &$message = null): int
    {
        $groupData = [];
        foreach ($content as $line) {
            $line = Utils::parseTsvLine(trim($line));
            $groupData[] = [
                'categoryid' => @$line[0],
                'name' => @$line[1],
                'allow_self_registration' => false,
            ];
        }

        return $this->importGroupData($groupData);
    }

    /**
     * Import groups JSON
     *
     * @param array<array{id?: string, icpc_id: string, name?: string, sortorder?: int,
     *                    color?: string, hidden?: bool, allow_self_registration?: bool}> $data
     * @param TeamCategory[]|null $saved The saved groups
     */
    public function importGroupsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        // TODO: can we have this use the DTO?
        $groupData = [];
        foreach ($data as $group) {
            $groupData[] = [
                'categoryid' => @$group['id'],
                'icpc_id' => @$group['icpc_id'],
                'name' => $group['name'] ?? '',
                'visible' => !($group['hidden'] ?? false),
                'sortorder' => @$group['sortorder'],
                'color' => @$group['color'],
                'allow_self_registration' => $group['allow_self_registration'] ?? false,
            ];
        }

        return $this->importGroupData($groupData, $saved, $message);
    }

    /**
     * Import group data from the given array
     *
     * @param array<array{categoryid: string, icpc_id?: string, name: string, visible?: bool,
     *              sortorder?: int|null, color?: string|null, allow_self_registration: bool}> $groupData
     * @param TeamCategory[]|null $saved The saved groups
     *
     * @throws NonUniqueResultException
     */
    protected function importGroupData(
        array $groupData,
        ?array &$saved = null,
        ?string &$message = null
    ): int {
        // We want to overwrite the ID so change the ID generator.
        $createdCategories = [];
        $updatedCategories = [];
        $allCategories     = [];
        $anyErrors         = [];

        foreach ($groupData as $index => $groupItem) {
            if (empty($groupItem['categoryid'])) {
                $categoryId = null;
                $teamCategory = null;
            } else {
                $categoryId = $groupItem['categoryid'];
                $teamCategory = $this->em->getRepository(TeamCategory::class)->findOneBy(['externalid' => $categoryId]);
            }
            $added = false;
            if (!$teamCategory) {
                $teamCategory = new TeamCategory();
                if ($categoryId !== null) {
                    $teamCategory->setExternalid($categoryId);
                }
                $added = true;
            }
            $teamCategory
                ->setName($groupItem['name'])
                ->setVisible($groupItem['visible'] ?? true)
                ->setSortorder($groupItem['sortorder'] ?? 0)
                ->setColor($groupItem['color'] ?? null)
                ->setIcpcid($groupItem['icpc_id'] ?? null);
            $teamCategory->setAllowSelfRegistration($groupItem['allow_self_registration']);

            $errors = $this->validator->validate($teamCategory);
            if ($errors->count()) {
                $messages = [];
                /** @var ConstraintViolationInterface $error */
                foreach ($errors as $error) {
                    $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                }

                $message .= sprintf("Group at index %d (%s) has errors:\n%s\n\n",
                    $index,
                    json_encode($groupItem),
                    implode("\n", $messages));
                $anyErrors = true;
            } else {
                $allCategories[] = $teamCategory;
                if ($added) {
                    $createdCategories[] = $teamCategory->getCategoryid();
                } else {
                    $updatedCategories[] = $teamCategory->getCategoryid();
                }
                if ($saved !== null) {
                    $saved[] = $teamCategory;
                }
            }
        }

        if ($anyErrors) {
            return -1;
        }

        foreach ($allCategories as $category) {
            $this->em->persist($category);
            $this->em->flush();
            $this->dj->auditlog('team_category', $category->getCategoryid(), 'replaced',
                'imported from tsv / json');
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
     * @param array<array{shortname?: string, short_name?: string, short-name?: string, id: string, icpc_id?: string, name: string, formal_name?: string,
     *                    country: string, logo?: array{href: string, mime: string, hash: string,
     *                                                 filename: string, width: string|int, height: string|int}}> $data
     * @param TeamAffiliation[]|null $saved The saved groups
     */
    public function importOrganizationsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        $organizationData = [];
        foreach ($data as $organization) {
            $organizationData[] = [
                'externalid' => @$organization['id'],
                'shortname' => $organization['short_name'] ?? $organization['short-name'] ?? $organization['shortname'] ?? $organization['name'],
                'name' => $organization['formal_name'] ?? $organization['name'],
                'country' => @$organization['country'],
                'icpc_id' => $organization['icpc_id'] ?? null,
            ];
        }

        return $this->importOrganizationData($organizationData, $saved, $message);
    }

    /**
     * Import organization data from the given array.
     *
     * @param array<array{externalid: string, shortname?: string, icpc_id?: string, name: string, country: string}> $organizationData
     * @param TeamAffiliation[]|null $saved The saved groups
     *
     * @throws NonUniqueResultException
     */
    protected function importOrganizationData(
        array $organizationData,
        ?array &$saved = null,
        ?string &$message = null,
    ): int {
        $createdOrganizations = [];
        $updatedOrganizations = [];
        $allOrganizations     = [];
        $anyErrors            = false;
        foreach ($organizationData as $index => $organizationItem) {
            $externalId      = $organizationItem['externalid'];
            $teamAffiliation = null;
            $added           = false;
            if ($externalId !== null) {
                $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $externalId]);
            }
            if (!$teamAffiliation) {
                $teamAffiliation = new TeamAffiliation();
                $teamAffiliation->setExternalid($externalId);
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
            $errors = $this->validator->validate($teamAffiliation);
            if ($errors->count()) {
                $messages = [];
                /** @var ConstraintViolationInterface $error */
                foreach ($errors as $error) {
                    $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                }

                $message .= sprintf("Organization at index %d (%s) has errors:\n%s\n\n",
                    $index,
                    json_encode($organizationItem),
                    implode("\n", $messages));
                $anyErrors = true;
            } else {
                $allOrganizations[] = $teamAffiliation;
                if ($added) {
                    $createdOrganizations[] = $teamAffiliation->getAffilid();
                } else {
                    $updatedOrganizations[] = $teamAffiliation->getAffilid();
                }
                if ($saved !== null) {
                    $saved[] = $teamAffiliation;
                }
            }
        }

        if ($anyErrors) {
            return -1;
        }

        foreach ($allOrganizations as $organization) {
            $this->em->persist($organization);
            $this->em->flush();
            $this->dj->auditlog('team_affiliation', $organization->getAffilid(), 'replaced',
                'imported from tsv / json');
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
     *
     * @param string[] $content
     * @throws NonUniqueResultException
     */
    protected function importTeamsTsv(array $content, ?string &$message = null): int
    {
        $teamData = [];
        foreach ($content as $line) {
            $line = Utils::parseTsvLine(trim($line));

            // teams.tsv contains data pertaining both to affiliations and teams.
            // Hence, return data for both tables.

            // We may do more integrity/format checking of the data here.

            // Set ICPC IDs to null if they are not given.
            $teamIcpcId = @$line[1];
            if (empty($teamIcpcId)) {
                $teamIcpcId = null;
            }
            $affiliationExternalid = preg_replace('/^INST-(U-)?/', '', $line[7] ?? '');
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
     * @param array<array{label?: string, name?: string, organization_id?: string,
     *              group_ids?: string[], icpc_id?: string, id?: string, display_name?: string,
     *              location?: array{description: string}, members?: string, public_description?: string}> $data
     * @param Team[]|null $saved The saved teams
     */
    public function importTeamsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        $teamData = [];
        foreach ($data as $team) {
            $teamData[] = [
                'team' => [
                    'teamid' => $team['id'] ?? null,
                    'icpcid' => $team['icpc_id'] ?? null,
                    'label' => $team['label'] ?? null,
                    'categoryid' => $team['group_ids'][0] ?? null,
                    'name' => $team['name'] ?? '',
                    'display_name' => $team['display_name'] ?? null,
                    'publicdescription' => $team['public_description'] ?? $team['members'] ?? '',
                    'location' => $team['location']['description'] ?? null,
                ],
                'team_affiliation' => [
                    'externalid' => $team['organization_id'] ?? null,
                ]
            ];
        }

        return $this->importTeamData($teamData, $message, $saved);
    }

    /**
     * @return array<string, Role>
     */
    private function getDjRoles(): array
    {
        $djRoles = [];
        $roles = ['team', 'jury', 'admin', 'balloon', 'clarification_rw', 'api_reader', 'api_writer', 'api_source_reader'];
        foreach ($roles as $role) {
            $djRoles[$role] = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => $role]);
        }
        return $djRoles;
    }

    /**
     * Import accounts JSON.
     *
     * @param array<array{id?: string, username?: string, name?: string, password?: string,
     *                    externalid?: string, type: string, team_id?: string, ip?: string}> $data
     * @param User[]|null $saved The saved users
     */
    public function importAccountsJson(array $data, ?string &$message = null, ?array &$saved = null): int
    {
        $djRoles = $this->getDjRoles();
        $juryCategory = $this->getOrCreateJuryCategory();
        $accountData = [];
        foreach ($data as $idx => $account) {
            foreach (['username', 'type'] as $required) {
                if (!key_exists($required, $account)) {
                    $message = sprintf("Missing key: '%s' for block: %d.", $required, $idx);
                    return -1;
                }
            }
            $juryTeam = null;
            $roles    = [];
            $type     = $account['type'];
            $username = $account['username'];

            // Special case for the World Finals, if the username is CDS we limit the access.
            // The user can see what every admin can see, but can not log in via the UI.
            if (isset($account['username']) && $account['username'] === 'cds') {
                $type = 'cds';
            } elseif ($type == 'judge') {
                $type = 'jury';
            } elseif (in_array($type, ['staff', 'analyst'])) {
                // Ignore type analyst and staff for now. We don't have a useful mapping yet.
                continue;
            }
            if ($type == 'cds') {
                $roles += [$djRoles['api_reader'], $djRoles['api_writer'], $djRoles['api_source_reader']];
            } elseif (!array_key_exists($type, $djRoles)) {
                $message = sprintf('Unknown role on index %d: %s', $idx, $type);
                return -1;
            } else {
                $roles[] = $djRoles[$type];
            }
            if ($type == 'admin' || $type == 'jury') {
                $roles[]  = $djRoles['team'];
                $juryTeam = [
                    'name'              => $account['name'] ?? $account['username'],
                    'externalid'        => $account['externalid'] ?? $account['username'],
                    'category'          => $juryCategory,
                    'publicdescription' => $account['name'] ?? $account['username'],
                ];
            }

            $accountData[] = [
                'user' => [
                    'name'           => $account['name'] ?? null,
                    'externalid'     => $account['id'] ?? $account['username'],
                    'username'       => $username,
                    'plain_password' => $account['password'] ?? null,
                    'teamid'         => $account['team_id'] ?? null,
                    'user_roles'     => $roles,
                    'ip_address'     => $account['ip'] ?? null,
                ],
                'team' => $juryTeam,
            ];
        }

        return $this->importAccountData($accountData, $saved, $message);
    }

    /**
     * Import team data from the given array.
     *
     * @param array<array{team: array{teamid: string|null, icpcid: string|null, label?: string|null,
     *                                categoryid: string|null, name: string|null, display_name?: string,
     *                                publicdescription?: string, location?: string|null, affilid?: string},
     *                    team_affiliation: array{externalid: string|null, shortname?: string, name?: string,
     *                                            country?: string}}> $teamData
     * @param Team[]|null $saved The saved teams
     *
     * @throws NonUniqueResultException
     */
    protected function importTeamData(array $teamData, ?string &$message, ?array &$saved = null): int
    {
        /** @var TeamAffiliation[] $createdAffiliations */
        $createdAffiliations = [];
        /** @var TeamCategory[] $createdCategories */
        $createdCategories   = [];
        $createdTeams        = [];
        $updatedTeams        = [];
        $allTeams            = [];
        $anyErrors           = false;
        foreach ($teamData as $index => $teamItem) {
            // It is legitimate that a team has no affiliation. Do not add it then.
            $teamAffiliation = null;
            $teamCategory    = null;
            if (!empty($teamItem['team_affiliation']['shortname'])) {
                // First look up if the affiliation already exists.
                $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['shortname' => $teamItem['team_affiliation']['shortname']]);
                if (!$teamAffiliation) {
                    foreach ($createdAffiliations as $createdAffiliation) {
                        if ($createdAffiliation->getShortname() === $teamItem['team_affiliation']['shortname']) {
                            $teamAffiliation = $createdAffiliation;
                            break;
                        }
                    }
                }
                if (!$teamAffiliation) {
                    $teamAffiliation  = new TeamAffiliation();
                    $propertyAccessor = PropertyAccess::createPropertyAccessor();
                    foreach ($teamItem['team_affiliation'] as $field => $value) {
                        $propertyAccessor->setValue($teamAffiliation, $field, $value);
                    }

                    $errors = $this->validator->validate($teamAffiliation);
                    if ($errors->count()) {
                        $messages = [];
                        /** @var ConstraintViolationInterface $error */
                        foreach ($errors as $error) {
                            $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                        }

                        $message .= sprintf("Organization for team at index %d (%s) has errors:\n%s\n\n",
                            $index,
                            json_encode($teamItem),
                            implode("\n", $messages));
                        $anyErrors = true;
                    } else {
                        $createdAffiliations[] = $teamAffiliation;
                    }
                }
            } elseif (!empty($teamItem['team_affiliation']['externalid'])) {
                $teamAffiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $teamItem['team_affiliation']['externalid']]);
                if (!$teamAffiliation) {
                    foreach ($createdAffiliations as $createdAffiliation) {
                        if ($createdAffiliation->getExternalid() === $teamItem['team_affiliation']['externalid']) {
                            $teamAffiliation = $createdAffiliation;
                            break;
                        }
                    }
                }

                if (!$teamAffiliation) {
                    $teamAffiliation = new TeamAffiliation();
                    $teamAffiliation
                        ->setExternalid($teamItem['team_affiliation']['externalid'])
                        ->setName($teamItem['team_affiliation']['externalid'] . ' - auto-create during import')
                        ->setShortname($teamItem['team_affiliation']['externalid'] . ' - auto-create during import');

                    $errors = $this->validator->validate($teamAffiliation);
                    if ($errors->count()) {
                        $messages = [];
                        /** @var ConstraintViolationInterface $error */
                        foreach ($errors as $error) {
                            $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                        }

                        $message .= sprintf("Organization for team at index %d (%s) has errors:\n%s\n\n",
                            $index,
                            json_encode($teamItem),
                            implode("\n", $messages));
                        $anyErrors = true;
                    } else {
                        $createdAffiliations[] = $teamAffiliation;
                    }
                }
            }
            $teamItem['team']['affiliation'] = $teamAffiliation;
            unset($teamItem['team']['affilid']);

            if (!empty($teamItem['team']['categoryid'])) {
                $teamCategory = $this->em->getRepository(TeamCategory::class)->findOneBy(['externalid' => $teamItem['team']['categoryid']]);
                if (!$teamCategory) {
                    foreach ($createdCategories as $createdCategory) {
                        if ($createdCategory->getExternalid() === $teamItem['team']['categoryid']) {
                            $teamCategory = $createdCategory;
                            break;
                        }
                    }
                }
                if (!$teamCategory) {
                    $teamCategory = new TeamCategory();
                    $teamCategory
                        ->setExternalid($teamItem['team']['categoryid'])
                        ->setName($teamItem['team']['categoryid'] . ' - auto-create during import');

                    $errors = $this->validator->validate($teamCategory);
                    if ($errors->count()) {
                        $messages = [];
                        /** @var ConstraintViolationInterface $error */
                        foreach ($errors as $error) {
                            $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                        }

                        $message .= sprintf("Group for team at index %d (%s) has errors:\n%s\n\n",
                            $index,
                            json_encode($teamItem),
                            implode("\n", $messages));
                        $anyErrors = true;
                    } else {
                        $createdCategories[] = $teamCategory;
                    }
                }
            }
            $teamItem['team']['category'] = $teamCategory;
            unset($teamItem['team']['categoryid']);

            // Determine if we need to set the team ID manually or automatically
            if (empty($teamItem['team']['teamid'])) {
                $team = null;
            } else {
                $team = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $teamItem['team']['teamid']]);
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

            $errors = $this->validator->validate($team);
            if ($errors->count()) {
                $messages = [];
                /** @var ConstraintViolationInterface $error */
                foreach ($errors as $error) {
                    $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                }

                $message .= sprintf("Team at index %d (%s) has errors:\n%s\n\n",
                    $index,
                    json_encode($teamItem),
                    implode("\n", $messages));
                $anyErrors = true;
            } else {
                $allTeams[] = $team;
            }

            if ($added) {
                $createdTeams[] = $team->getTeamid();
            } else {
                $updatedTeams[] = $team->getTeamid();
            }

            if ($saved !== null) {
                $saved[] = $team;
            }
        }

        if ($anyErrors) {
            return -1;
        }

        foreach ($createdAffiliations as $affiliation) {
            $this->em->persist($affiliation);
            $this->em->flush();
            $this->dj->auditlog('team_affiliation',
                $affiliation->getAffilid(),
                'added', 'imported from tsv / json');
        }

        foreach ($createdCategories as $category) {
            $this->em->persist($category);
            $this->em->flush();
            $this->dj->auditlog('team_category', $category->getCategoryid(),
                                    'added', 'imported from tsv');
        }

        foreach ($allTeams as $team) {
            $this->em->persist($team);
            $this->em->flush();
            $this->dj->auditlog('team', $team->getTeamid(), 'replaced', 'imported from tsv');
        }

        if ($contest = $this->dj->getCurrentContest()) {
            if (!empty($createdAffiliations)) {
                $affiliationIds = array_map(fn (TeamAffiliation $affiliation) => $affiliation->getAffilid(), $createdAffiliations);
                $this->eventLogService->log('team_affiliation', $affiliationIds,
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
     * @param array<array{user: array{name: string|null, externalid: string, username: string,
     *                           plain_password: string|null, teamid?: string|null,
     *                           team?: array{name: string, category: string, externalid: string}|null,
     *                           user_roles: Role[], ip_address: string|null},
     *               team?: array{name: string, externalid: string, category: TeamCategory,
     *                           publicdescription?: string}}> $accountData
     * @throws NonUniqueResultException
     */
    protected function importAccountData(
        array $accountData,
        ?array &$saved = null,
        ?string &$message = null
    ): int {
        $newTeams     = [];
        $anyErrors    = false;
        $allUsers     = [];
        foreach ($accountData as $index => $accountItem) {
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
                    $action = EventLogService::ACTION_CREATE;
                } else {
                    $action = EventLogService::ACTION_UPDATE;
                }
                $errors = $this->validator->validate($team);
                if ($errors->count()) {
                    $messages = [];
                    /** @var ConstraintViolationInterface $error */
                    foreach ($errors as $error) {
                        $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                    }

                    $message .= sprintf("Team for user at index %d (%s) has errors:\n%s\n\n",
                        $index,
                        json_encode($accountItem),
                        implode("\n", $messages));
                    $anyErrors = true;
                } else {
                    $newTeams[] = [
                        'team' => $team,
                        'action' => $action,
                    ];
                }
                $accountItem['user']['team'] = $team;
                unset($accountItem['user']['teamid']);
            }

            $user = $this->em->getRepository(User::class)->findOneBy(['username' => $accountItem['user']['username']]);
            if (!$user) {
                $user = new User();
            }

            if (array_key_exists('teamid', $accountItem['user'])) {
                $teamId = $accountItem['user']['teamid'];
                unset($accountItem['user']['teamid']);
                $team = null;
                if ($teamId !== null) {
                    $team  = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $teamId]);
                    if (!$team) {
                        $team = new Team();
                        $team
                            ->setExternalid((string)$teamId)
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

            $errors = $this->validator->validate($user);
            if ($errors->count()) {
                $messages = [];
                /** @var ConstraintViolationInterface $error */
                foreach ($errors as $error) {
                    $messages[] = sprintf('  • `%s`: %s', $error->getPropertyPath(), $error->getMessage());
                }

                $message .= sprintf("User at index %d (%s) has errors:\n%s\n\n",
                    $index,
                    json_encode($accountItem),
                    implode("\n", $messages));
                $anyErrors = true;
            } else {
                $allUsers[] = $user;

                if ($saved !== null) {
                    $saved[] = $user;
                }
            }
        }

        if ($anyErrors) {
            return -1;
        }

        foreach ($allUsers as $user) {
            $this->em->persist($user);
        }

        foreach ($newTeams as $newTeam) {
            $team = $newTeam['team'];
            $this->em->persist($team);
        }

        $this->em->flush();

        foreach ($allUsers as $user) {
            $this->dj->auditlog('user', $user->getUserid(), 'replaced', 'imported from tsv');
        }

        if ($contest = $this->dj->getCurrentContest()) {
            foreach ($newTeams as $newTeam) {
                $team = $newTeam['team'];
                $action = $newTeam['action'];
                $this->dj->auditlog('team', $team->getTeamid(), 'replaced',
                    'imported from tsv, autocreated for judge');
                $this->eventLogService->log('team', $team->getTeamid(), $action, $contest->getCid());
            }
        }

        return count($accountData);
    }

    /**
     * Import accounts TSV
     *
     * @param string[] $content
     */
    protected function importAccountsTsv(array $content, ?string &$message = null): int
    {
        $accountData  = [];
        $juryCategory = $this->getOrCreateJuryCategory();
        $djRoles      = $this->getDjRoles();
        $lineNr       = 1;
        foreach ($content as $line) {
            $lineNr++;
            $line = Utils::parseTsvLine(trim($line));
            if (count($line) <= 3) {
                $message = sprintf('Not enough values on line %d', $lineNr);
                return -1;
            }

            $team  = $juryTeam = null;
            $roles = [];
            $type = $line[0];
            // Special case for the World Finals, if the username is CDS we limit the access.
            // The user can see what every admin can see, but can not log in via the UI.
            if ($line[2] === 'cds') {
                $type = 'cds';
            } elseif ($type == 'judge') {
                $type = 'jury';
            } elseif (in_array($type, ['staff', 'analyst'])) {
                // Ignore type analyst and staff for now. We don't have a useful mapping yet.
                continue;
            }
            if ($type == 'cds') {
                $roles += [$djRoles['api_reader'], $djRoles['api_writer'], $djRoles['api_source_reader']];
            } elseif (!array_key_exists($type, $djRoles)) {
                $message = sprintf('Unknown role on line %d: %s', $lineNr, $type);
                return -1;
            } else {
                $roles[] = $djRoles[$type];
            }
            if ($type == 'admin' || $type == 'jury') {
                $roles[] = $djRoles['team'];
                $juryTeam = ['name' => $line[1], 'externalid' => $line[2], 'category' => $juryCategory, 'publicdescription' => $line[1]];
            }
            if ($type == 'team') {
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
                    $message = sprintf('Cannot parse team id on line %d from "%s"', $lineNr,
                        $line[2]);
                    return -1;
                }
                $team = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $teamId]);
                if ($team === null) {
                    $message = sprintf('Unknown team id %s on line %d', $teamId, $lineNr);
                    return -1;
                }
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

    private function getOrCreateJuryCategory(): TeamCategory
    {
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
        return $juryCategory;
    }
}
