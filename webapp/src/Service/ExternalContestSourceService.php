<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\BaseApiEntity;
use App\Entity\Clarification;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalContestSource;
use App\Entity\ExternalJudgement;
use App\Entity\ExternalRun;
use App\Entity\ExternalSourceWarning;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Role;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Entity\User;
use App\Utils\Utils;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use JsonException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

class ExternalContestSourceService
{
    protected HttpClientInterface $httpClient;
    protected DOMJudgeService $dj;
    protected EntityManagerInterface $em;
    protected LoggerInterface $logger;
    protected ConfigurationService $config;
    protected EventLogService $eventLog;
    protected SubmissionService $submissionService;
    protected ScoreboardService $scoreboardService;

    protected ?ExternalContestSource $source = null;

    protected bool $contestLoaded = false;
    protected ?array $cachedContestData = null;
    protected ?string $loadingError = null;
    protected bool $shouldStopReading = false;
    protected array $verdicts = [];

    /**
     * This array will hold all events that are waiting on a dependent event
     * because it has an ID that does not exist yet. According to the official
     * spec this can not happen, but in practice it does happen. We handle
     * this by storing these events here and checking whether there are any
     * after saving any dependent event.
     *
     * This array is three dimensional:
     * - The first dimension is the type of the dependent event type
     * - The second dimension is the (external) ID of the dependent event
     * - The third dimension contains an array of all events that should be processed
     */
    protected array $pendingEvents = [
        // Initialize it with all types that can be a dependent event.
        // Note that Language is not here, as they should exist already.
        'team'          => [],
        'group'         => [],
        'organization'  => [],
        'problem'       => [],
        'clarification' => [],
        'submission'    => [],
    ];

    public function __construct(
        HttpClientInterface    $httpClient,
        DOMJudgeService        $dj,
        EntityManagerInterface $em,
        LoggerInterface        $eventFeedImporterLogger,
        ConfigurationService   $config,
        EventLogService        $eventLog,
        SubmissionService      $submissionService,
        ScoreboardService      $scoreboardService
    ) {
        $clientOptions           = [
            'headers' => [
                'User-Agent' => 'DOMjudge/' . DOMJUDGE_VERSION,
            ],
        ];
        $this->httpClient        = $httpClient->withOptions($clientOptions);
        $this->dj                = $dj;
        $this->em                = $em;
        $this->logger            = $eventFeedImporterLogger;
        $this->config            = $config;
        $this->eventLog          = $eventLog;
        $this->submissionService = $submissionService;
        $this->scoreboardService = $scoreboardService;
    }

    public function setSource(ExternalContestSource $source)
    {
        $this->source = $source;
    }

    public function getSourceContest(): Contest
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->source->getContest();
    }

    public function getSourceContestId(): int
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->source->getContest()->getCid();
    }

    public function isValidContestSource(): bool
    {
        $this->loadContest();
        return ($this->cachedContestData !== null);
    }

    public function getContestId(): string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedContestData['id'];
    }

    public function getContestName(): string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedContestData['name'];
    }

    public function getContestStartTime(): float
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return Utils::toEpochFloat($this->cachedContestData['start_time']);
    }

    public function getContestDuration(): string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedContestData['duration'];
    }

    public function getLoadingError(): string
    {
        if ($this->isValidContestSource()) {
            throw new LogicException('The contest source is valid');
        }

        return $this->loadingError;
    }

    public function getLastReadEventId(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        // We use a direct query to not have to reload the source.
        return $this->em->createQuery(
            'SELECT ecs.lastEventId
                FROM App\Entity\ExternalContestSource ecs
                WHERE ecs.extsourceid = :extsourceid')
                        ->setParameter('extsourceid', $this->source->getExtsourceid())
                        ->getSingleScalarResult();
    }

    public function import(bool $fromStart, array $eventsToSkip, ?callable $progressReporter = null): bool
    {
        // We need the verdicts to validate judgement-types.
        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $this->verdicts = include $verdictsConfig;

        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        if ($fromStart) {
            $this->setLastEvent(null);
        } else {
            // We do this to mark the reader active.
            $this->setLastEvent($this->source->getLastEventId());
        }

        $this->loadPendingEvents();

        switch ($this->source->getType()) {
            case ExternalContestSource::TYPE_CCS_API:
                return $this->importFromCcsApi($eventsToSkip, $progressReporter);
            case ExternalContestSource::TYPE_CONTEST_ARCHIVE:
                return $this->importFromContestArchive($eventsToSkip, $progressReporter);
        }

        return false;
    }

    public function stopReading(): void
    {
        $this->shouldStopReading = true;
    }

    public function shouldStopReading(): bool
    {
        return $this->shouldStopReading;
    }

    protected function setLastEvent(?string $eventId): void
    {
        // We use a direct query since we need to reload the source otherwise and we don't want that since that would
        // take more queries.
        $this->em
            ->createQuery(
                'UPDATE App\Entity\ExternalContestSource ecs
                SET ecs.lastEventId = :lastEventId, ecs.lastPollTime = :lastPollTime
                WHERE ecs.extsourceid = :extsourceid'
            )
            ->setParameter('lastEventId', $eventId)
            ->setParameter('lastPollTime', Utils::now())
            ->setParameter('extsourceid', $this->source->getExtsourceid())
            ->execute();
    }

    protected function importFromCcsApi(array $eventsToSkip, ?callable $progressReporter = null): bool
    {
        while (true) {
            // Check whether we have received an exit signal.
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->shouldStopReading()) {
                return true;
            }

            $fullUrl = $this->source->getSource() . '/event-feed';
            if ($this->getLastReadEventId() !== null) {
                $fullUrl .= '?since_id=' . $this->getLastReadEventId();
            }
            $response = $this->httpClient->request('GET', $fullUrl, ['buffer' => false]);
            if ($response->getStatusCode() !== 200) {
                $this->logger->warning(
                    'Received non-200 response code %d, waiting for five seconds ' .
                    'and trying again. Press ^C to quit.',
                    [$response->getStatusCode()]
                );
                sleep(5);
                continue;
            }

            $buffer = '';

            $processBuffer = function () use ($eventsToSkip, &$buffer, $progressReporter) {
                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line   = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1);

                    if (!empty($line)) {
                        $event = $this->dj->jsonDecode($line);
                        $this->importEvent($event, $eventsToSkip);

                        $this->setLastEvent($event['id']);
                        $progressReporter(false);
                    }

                    if (function_exists('pcntl_signal_dispatch')) {
                        pcntl_signal_dispatch();
                    }
                    if ($this->shouldStopReading()) {
                        return false;
                    }
                }

                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                return !$this->shouldStopReading();
            };

            while (true) {
                // A timeout of 0.0 means we get chunks immediately and the user
                // can cancel at any time.
                try {
                    $receivedData = false;
                    foreach ($this->httpClient->stream($response, 0.0) as $chunk) {
                        // We first need to check for timeouts, as we can not call
                        // ->isLast() or ->getContent() on them.
                        if (!$chunk->isTimeout()) {
                            $receivedData = true;
                            if ($chunk->isLast()) {
                                // Last chunk received, exit out of the inner while(true).
                                break 2;
                            } else {
                                $buffer .= $chunk->getContent();
                            }
                        }
                        if (!$processBuffer()) {
                            return true;
                        }
                    }
                    if (!$receivedData) {
                        $hundred_ms = 100 * 1000 * 1000;
                        time_nanosleep(0, $hundred_ms);
                    } else {
                        // Indicate we are still alive.
                        $this->setLastEvent($this->getLastReadEventId());
                    }
                } catch (TransportException $e) {
                    $this->logger->error(
                        'Received error while reading event feed: %s',
                        [$e->getMessage()]
                    );
                }
            }

            // We still need to finish everything that is still in the buffer.
            if (!$processBuffer()) {
                return true;
            }

            $this->logger->info(
                'End of stream reached, waiting for five seconds before ' .
                'rereading stream after event %s. Press ^C to quit.',
                [$this->lastEventId ?? 'none']
            );
            sleep(5);
        }
    }

    protected function importFromContestArchive(array $eventsToSkip, ?callable $progressReporter = null): bool
    {
        $file = fopen($this->source->getSource() . '/event-feed.ndjson', 'r');

        $skipEventsUpTo = $this->getLastReadEventId();

        $this->readEventsFromFile($file,
            function (array $event, string $line, &$shouldStop) use (
                $eventsToSkip,
                $file,
                &$skipEventsUpTo,
                $progressReporter
            ) {
                $lastEventId          = $this->getLastReadEventId();
                $readingToLastEventId = false;
                if ($skipEventsUpTo === null) {
                    $this->importEvent($event, $eventsToSkip);
                    $lastEventId = $event['id'];
                } elseif ($event['id'] === $skipEventsUpTo) {
                    $skipEventsUpTo = null;
                } else {
                    $readingToLastEventId = true;
                }

                $this->setLastEvent($lastEventId);

                $progressReporter($readingToLastEventId);

                if ($this->shouldStopReading) {
                    $shouldStop = true;
                }
            });

        fclose($file);

        return true;
    }

    /**
     * Read events from the given file.
     *
     * The callback will be called for every found event and will receive three
     * arguments:
     * - The event to process
     * - The line the event was on
     * - A boolean that can be set to true (pass-by-reference) to stop processing
     *
     * @param resource $filePointer
     * @throws JsonException
     */
    protected function readEventsFromFile($filePointer, callable $callback): void
    {
        $buffer = '';
        while (!feof($filePointer) || !empty($buffer)) {
            // Read the file until we find a newline or the end of the stream
            while (!feof($filePointer) && (strpos($buffer, "\n")) === false) {
                $buffer .= fread($filePointer, 1024);
            }
            $newlinePos = strpos($buffer, "\n");
            if ($newlinePos === false) {
                $line   = $buffer;
                $buffer = '';
            } else {
                $line   = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);
            }

            $event      = $this->dj->jsonDecode($line);
            $shouldStop = false;
            $callback($event, $line, $shouldStop);
            if ($shouldStop) {
                return;
            }
        }
    }

    protected function loadContest(): void
    {
        if ($this->source === null) {
            throw new LogicException('You need to call setSource() first');
        }
        if ($this->contestLoaded) {
            return;
        }
        switch ($this->source->getType()) {
            case ExternalContestSource::TYPE_CCS_API:
                try {
                    // The base URL is the URL of the CCS API root.
                    if (preg_match('/^(.*\/)contests\/.*/',
                                   $this->source->getSource(), $matches) === 0) {
                        $this->loadingError      = 'Cannot determine base URL. Did you pass a CCS API contest URL?';
                        $this->cachedContestData = null;
                    } else {
                        $clientOptions = [
                            'base_uri' => $matches[1],
                        ];
                        if ($this->source->getUsername()) {
                            $auth = [$this->source->getUsername()];
                            if (is_string($this->source->getPassword() ?? null)) {
                                $auth[] = $this->source->getPassword();
                            }
                            $clientOptions['auth_basic'] = $auth;
                        } else {
                            $clientOptions['auth_basic'] = null;
                        }
                        $this->httpClient        = $this->httpClient->withOptions($clientOptions);
                        $contestResponse         = $this->httpClient->request('GET', $this->source->getSource());
                        $this->cachedContestData = $contestResponse->toArray();
                    }
                } catch (HttpExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
                    $this->cachedContestData = null;
                    $this->loadingError      = $e->getMessage();
                }
                $this->contestLoaded = true;
                break;
            case ExternalContestSource::TYPE_CONTEST_ARCHIVE:
                $this->cachedContestData = null;
                $contestFile             = $this->source->getSource() . '/contest.json';
                $eventFeedFile           = $this->source->getSource() . '/event-feed.ndjson';
                if (!is_dir($this->source->getSource())) {
                    $this->loadingError = 'Contest archive directory not found';
                } elseif (!is_file($contestFile)) {
                    $this->loadingError = 'contest.json not found in archive';
                } elseif (!is_file($eventFeedFile)) {
                    $this->loadingError = 'event-feed.ndjson not found in archive';
                } else {
                    try {
                        $this->cachedContestData = $this->dj->jsonDecode(file_get_contents($contestFile));
                    } catch (JsonException $e) {
                        $this->loadingError = $e->getMessage();
                    }
                }
                break;
        }
    }

    /**
     * Import the given event.
     * @param string[]
     * @throws DBALException
     * @throws NonUniqueResultException
     * @throws TransportExceptionInterface
     */
    public function importEvent(array $event, array $eventsToSKip): void
    {
        // Check whether we have received an exit signal.
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($this->shouldStopReading()) {
            return;
        }

        if (in_array($event['id'], $eventsToSKip)) {
            $this->logger->info("Skipping event with ID %s and type %s as requested",
                                [$event['id'], $event['type']]);
            return;
        }

        $this->logger->debug("Importing event with ID %s and type %s...",
                             [$event['id'], $event['type']]);

        switch ($event['type']) {
            case 'awards':
            case 'team-members':
            case 'state':
                $this->logger->debug("Ignoring event of type %s", [$event['type']]);
                if (isset($event['data']['end_of_updates'])) {
                    $this->logger->info('End of updates encountered');
                }
                break;
            case 'contests':
                $this->validateAndUpdateContest($event);
                break;
            case 'judgement-types':
                $this->validateJudgementType($event);
                break;
            case 'languages':
                $this->validateLanguage($event);
                break;
            case 'groups':
                $this->validateAndUpdateGroup($event);
                break;
            case 'organizations':
                $this->validateAndUpdateOrganization($event);
                break;
            case 'problems':
                $this->validateAndUpdateProblem($event);
                break;
            case 'teams':
                $this->validateAndUpdateTeam($event);
                break;
            case 'accounts':
                $this->validateAndUpdateAccount($event);
                break;
            case 'clarifications':
                $this->importClarification($event);
                break;
            case 'submissions':
                $this->importSubmission($event);
                break;
            case 'judgements':
                $this->importJudgement($event);
                break;
            case 'runs':
                $this->importRun($event);
                break;
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function validateAndUpdateContest(array $event): void
    {
        if (!$this->warningIfUnsupported($event, [EventLogService::ACTION_CREATE, EventLogService::ACTION_UPDATE])) {
            return;
        }

        // First, reload the contest so we can check its data..
        /** @var Contest $contest */
        $contest = $this->em
            ->getRepository(Contest::class)
            ->find($this->getSourceContestId());

        // We need to convert the freeze to a value from the start instead of
        // the end so perform some regex magic.
        $duration     = $event['data']['duration'];
        $freeze       = $event['data']['scoreboard_freeze_duration'];
        $reltimeRegex = '/^(-)?(\d+):(\d{2}):(\d{2})(?:\.(\d{3}))?$/';
        preg_match($reltimeRegex, $duration, $durationData);

        $durationNegative = ($durationData[1] === '-');
        $fullDuration     = $durationNegative ? $duration : ('+' . $duration);

        if ($freeze !== null) {
            preg_match($reltimeRegex, $freeze, $freezeData);
            $freezeNegative       = ($freezeData[1] === '-');
            $freezeHourModifier   = $freezeNegative ? -1 : 1;
            $freezeInSeconds      = $freezeHourModifier * $freezeData[2] * 3600
                                    + 60 * $freezeData[3]
                                    + (double)sprintf('%d.%03d', $freezeData[4], $freezeData[5]);
            $durationHourModifier = $durationNegative ? -1 : 1;
            $durationInSeconds    = $durationHourModifier * $durationData[2] * 3600
                                    + 60 * $durationData[3]
                                    + (double)sprintf('%d.%03d', $durationData[4], $durationData[5]);
            $freezeStartSeconds   = $durationInSeconds - $freezeInSeconds;
            $freezeHour           = floor($freezeStartSeconds / 3600);
            $freezeMinutes        = floor(($freezeStartSeconds % 3600) / 60);
            $freezeSeconds        = floor(($freezeStartSeconds % 60) / 60);
            $freezeMilliseconds   = $freezeStartSeconds - floor($freezeStartSeconds);

            $fullFreeze = sprintf(
                '%s%d:%02d:%02d.%03d',
                $freezeHour < 0 ? '' : '+',
                $freezeHour,
                $freezeMinutes,
                $freezeSeconds,
                $freezeMilliseconds
            );
        } else {
            $fullFreeze = null;
        }

        // The timezones are given in ISO 8601 and we only support names.
        // This is why we will use the platform default timezone and just verify it matches.
        $startTime = $event['data']['start_time'] === null ? null : new DateTime($event['data']['start_time']);
        if ($startTime !== null) {
            // We prefer to use our default timezone, since that is a timezone name
            // The feed only has timezone offset, so we will only use it if the offset
            // differs from our local timezone offset
            $timezoneToUse = date_default_timezone_get();
            $feedTimezone  = new DateTimeZone($startTime->format('e'));
            if ($contest->getStartTimeObject()) {
                $ourTimezone = new DateTimeZone($contest->getStartTimeObject()->format('e'));
            } else {
                $ourTimezone = new DateTimeZone(date_default_timezone_get());
            }
            if ($feedTimezone->getOffset($startTime) !== $ourTimezone->getOffset($startTime)) {
                $timezoneToUse = $feedTimezone->getName();
                $this->logger->warning(
                    'Timezone does not match between feed (%s) and local (%s)',
                    [$feedTimezone->getName(), $ourTimezone->getName()]
                );
            }
            $toCheck = [
                'start_time_enabled' => true,
                'start_time_string'  => $startTime->format('Y-m-d H:i:s ') . $timezoneToUse,
                'end_time'           => $contest->getAbsoluteTime($fullDuration),
                'freeze_time'        => $contest->getAbsoluteTime($fullFreeze),
            ];
        } else {
            $toCheck = [
                'start_time_enabled' => false,
            ];
        }

        $toCheck['name'] = $event['data']['name'];

        // Also compare the penalty time
        $penaltyTime = (int)$event['data']['penalty_time'];
        if ($this->config->get('penalty_time') != $penaltyTime) {
            $this->logger->warning(
                'Penalty time does not match between feed (%d) and local (%d)',
                [$penaltyTime, $this->config->get('penalty_time')]
            );
        }

        $this->compareOrCreateValues($event, $contest, $toCheck);

        $this->em->flush();
        $this->eventLog->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE, $this->getSourceContestId());
    }

    protected function validateJudgementType(array $event): void
    {
        if (!$this->warningIfUnsupported($event, [EventLogService::ACTION_CREATE])) {
            return;
        }

        $verdict         = $event['data']['id'];
        $verdictsFlipped = array_flip($this->verdicts);
        if (!isset($verdictsFlipped[$verdict])) {
            // TODO: We should handle this. Kattis has JE (judge error) which we do not have but want to show.
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
        } else {
            $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            $penalty = true;
            $solved  = false;
            if ($verdict === 'AC') {
                $penalty = false;
                $solved  = true;
            } elseif ($verdict === 'CE') {
                $penalty = (bool)$this->config->get('compile_penalty');
            }

            $extraDiff = [];

            if ($penalty !== $event['data']['penalty']) {
                $extraDiff['penalty'] = [$penalty, $event['data']['penalty']];
            }
            if ($solved !== $event['data']['solved']) {
                $extraDiff['solved'] = [$solved, $event['data']['solved']];
            }

            // Entity doesn't matter, since we do not compare anything besides the extra data
            $this->compareOrCreateValues($event, $this->source->getContest(), [], $extraDiff, false);
        }
    }

    protected function validateLanguage(array $event): void
    {
        if (!$this->warningIfUnsupported($event, [EventLogService::ACTION_CREATE])) {
            return;
        }

        $extId = $event['data']['id'];
        /** @var Language $language */
        $language = $this->em
            ->getRepository(Language::class)
            ->findOneBy(['externalid' => $extId]);
        if (!$language) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
        } elseif (!$language->getAllowSubmit()) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DATA_MISMATCH, [
                'diff' => [
                    'allow_submit' => [
                        'us'       => false,
                        'external' => true,
                    ]
                ]
            ]);
        } else {
            $this->removeWarning($event, ExternalSourceWarning::TYPE_DATA_MISMATCH);
        }
    }

    protected function validateAndUpdateGroup(array $event): void
    {
        $groupId = $event['data']['id'];

        /** @var TeamCategory|null $category */
        $category = $this->em
            ->getRepository(TeamCategory::class)
            ->findOneBy(['externalid' => $groupId]);

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // Delete category if we still have it
            if ($category) {
                $this->logger->warning(
                    'Category with name %s should not exist, deleting',
                    [$category->getName()]
                );
                $this->em->remove($category);
                $this->em->flush();
            }
            return;
        }

        $action = EventLogService::ACTION_UPDATE;

        if (!$category) {
            $this->logger->warning(
                'Category with name %s should exist, creating',
                [$event['data']['name']]
            );
            $category = new TeamCategory();
            $this->em->persist($category);
            $action = EventLogService::ACTION_CREATE;
        }

        $toCheck = [
            'externalid' => $event['data']['id'],
            'name'       => $event['data']['name'],
            'visible'    => !($event['data']['hidden'] ?? false),
            'icpcid'     => $event['data']['icpc_id'] ?? null,
        ];

        // Add DOMjudge specific fields that might be useful to import
        if (isset($event['data']['sortorder'])) {
            $toCheck['sortorder'] = $event['data']['sortorder'];
        }
        if (isset($event['data']['color'])) {
            $toCheck['color'] = $event['data']['color'];
        }

        $this->compareOrCreateValues($event, $category, $toCheck);

        $this->em->flush();
        $this->eventLog->log('groups', $category->getCategoryid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('group', $category->getExternalid());
    }

    protected function validateAndUpdateOrganization(array $event): void
    {
        $organizationId = $event['data']['id'];

        /** @var TeamAffiliation|null $affiliation */
        $affiliation = $this->em
            ->getRepository(TeamAffiliation::class)
            ->findOneBy(['externalid' => $organizationId]);

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // Delete affiliation if we still have it
            if ($affiliation) {
                $this->logger->warning(
                    'Affiliation with name %s should not exist, deleting',
                    [$affiliation->getName()]
                );
                $this->em->remove($affiliation);
                $this->em->flush();
            }
            return;
        }

        $action = EventLogService::ACTION_UPDATE;

        if (!$affiliation) {
            $this->logger->warning(
                'Affiliation with name %s should exist, creating',
                [$event['data']['formal_name'] ?? $event['data']['name']]
            );
            $affiliation = new TeamAffiliation();
            $this->em->persist($affiliation);
            $action = EventLogService::ACTION_CREATE;
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheck = [
            'externalid' => $event['data']['id'],
            'name'       => $event['data']['formal_name'] ?? $event['data']['name'],
            'shortname'  => $event['data']['name'],
            'icpcid'     => $event['data']['icpc_id'] ?? null,
        ];
        if (isset($event['data']['country'])) {
            $toCheck['country'] = $event['data']['country'];
        }

        $this->compareOrCreateValues($event, $affiliation, $toCheck);

        $this->em->flush();
        $this->eventLog->log('organizations', $affiliation->getAffilid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('organization', $affiliation->getExternalid());
    }

    protected function validateAndUpdateProblem(array $event): void
    {
        if (!$this->warningIfUnsupported($event, [EventLogService::ACTION_CREATE, EventLogService::ACTION_UPDATE])) {
            return;
        }

        $problemId = $event['data']['id'];

        // First, load the problem.
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // Now find the contest problem.
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em
            ->getRepository(ContestProblem::class)
            ->find([
                       'contest' => $this->getSourceContest(),
                       'problem' => $problem,
                   ]);
        if (!$contestProblem) {
            // Note: we can't handle updates to non-existing problems, since we require things
            // like the testcases
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheckProblem = [
            'name'      => $event['data']['name'],
            'timelimit' => $event['data']['time_limit'],
        ];

        if ($contestProblem->getShortname() !== $event['data']['label']) {
            $this->logger->warning(
                'Contest problem short name does not match between feed (%s) and local (%s), updating',
                [$event['data']['label'], $contestProblem->getShortname()]
            );
            $contestProblem->setShortname($event['data']['label']);
        }
        if ($contestProblem->getColor() !== ($event['data']['rgb'] ?? null)) {
            $this->logger->warning(
                'Contest problem color does not match between feed (%s) and local (%s), updating',
                [$event['data']['rgb'] ?? null, $contestProblem->getColor()]
            );
            $contestProblem->setColor($event['data']['rgb'] ?? null);
        }

        $this->compareOrCreateValues($event, $problem, $toCheckProblem);

        $this->em->flush();
        $this->eventLog->log('problems', $problem->getProbid(), EventLogService::ACTION_UPDATE, $this->getSourceContestId());

        $this->processPendingEvents('problem', $problem->getProbid());
    }

    protected function validateAndUpdateTeam(array $event): void
    {
        $teamId = $event['data']['id'];

        /** @var Team|null $team */
        $team = $this->em
            ->getRepository(Team::class)
            ->findOneBy(['externalid' => $teamId]);

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // Delete team if we still have it
            if ($team) {
                $this->logger->warning(
                    'Team with name %s should not exist, deleting',
                    [$team->getName()]
                );
                $this->em->remove($team);
                $this->em->flush();
            }
            return;
        }

        $action = EventLogService::ACTION_UPDATE;

        if (!$team) {
            $this->logger->warning(
                'Team with name %s should exist, creating',
                [$event['data']['formal_name'] ?? $event['data']['name']]
            );
            $team = new Team();
            $this->em->persist($team);
            $action = EventLogService::ACTION_CREATE;
        }

        if (!empty($event['data']['organization_id'])) {
            $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $event['data']['organization_id']]);
            if (!$affiliation) {
                $affiliation = new TeamAffiliation();
                $this->em->persist($affiliation);
            }
            $team->setAffiliation($affiliation);
        }

        if (!empty($event['data']['group_ids'][0])) {
            $category = $this->em->getRepository(TeamCategory::class)->findOneBy(['externalid' => $event['data']['group_ids'][0]]);
            if (!$category) {
                $category = new TeamCategory();
                $this->em->persist($category);
            }
            $team->setCategory($category);
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheck = [
            'externalid'             => $event['data']['id'],
            'name'                   => $event['data']['formal_name'] ?? $event['data']['name'],
            'display_name'           => $event['data']['display_name'] ?? null,
            'affiliation.externalid' => $event['data']['organization_id'],
            'category.externalid'    => $event['data']['group_ids'][0] ?? null,
            'icpcid'                 => $event['data']['icpc_id'] ?? null,
        ];
        if (isset($event['data']['country'])) {
            $toCheck['country'] = $event['data']['country'];
        }

        $this->compareOrCreateValues($event, $team, $toCheck);

        $this->em->flush();
        $this->eventLog->log('teams', $team->getTeamid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('team', $team->getTeamid());
    }

    protected function validateAndUpdateAccount(array $event): void
    {
        $userId = $event['data']['id'];

        /** @var User|null $user */
        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy(['externalid' => $userId]);

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // Delete user if we still have it
            if ($user) {
                $this->logger->warning(
                    'Account with username %s should not exist, deleting',
                    [$user->getUsername()]
                );
                $this->em->remove($user);
                $this->em->flush();
            }
            return;
        }

        $action = EventLogService::ACTION_UPDATE;

        if (!$user) {
            $this->logger->warning(
                'Account with username %s should exist, creating',
                [$event['data']['username']]
            );
            $user = new User();
            $this->em->persist($user);
            if (!empty($event['data']['team_id'])) {
                $team = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $event['data']['team_id']]);
                if (!$team) {
                    $team = new Team();
                    $this->em->persist($team);
                }
                $user->setTeam($team);
            }
            $action = EventLogService::ACTION_CREATE;
        }

        $toCheck = [
            'externalid'      => $event['data']['id'],
            'username'        => $event['data']['username'],
            'ip_address'      => $event['data']['ip'] ?? null,
            'name'            => $event['data']['name'] ?? null,
            'team.externalid' => $event['data']['team_id'],
        ];

        $type = $event['data']['type'] ?? null;

        if ($user->getUserid() && $type && $user->getType() !== $type) {
            $this->logger->warning(
                'Type of user %s does not match between feed (%s) and local (%s), updating',
                [$user->getUsername(), $type, $user->getType()]
            );
        }

        $typeMapping = [
            'admin' => 'admin',
            'judge' => 'jury',
            'team'  => 'team',
        ];

        if (isset($typeMapping[$type])) {
            $role = $this->em->getRepository(Role::class)->findOneBy(['dj_role' => $typeMapping[$type]]);
            $user->addUserRole($role);
        }

        $this->compareOrCreateValues($event, $user, $toCheck);

        $this->em->flush();
        $this->eventLog->log('accounts', $user->getUserid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('account', $user->getUserid());
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function importClarification(array $event): void
    {
        $clarificationId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the team

            $clarification = $this->em
                ->getRepository(Clarification::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $clarificationId
                            ]);
            if ($clarification) {
                $this->em->remove($clarification);
                $this->em->flush();
                $this->eventLog->log('clarifications', $clarification->getClarid(),
                                     EventLogService::ACTION_DELETE,
                                     $this->getSourceContestId(), null,
                                     $clarification->getExternalid());
                return;
            } else {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }

            $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the clarification
        /** @var Clarification $clarification */
        $clarification = $this->em
            ->getRepository(Clarification::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $clarificationId
                        ]);
        if ($clarification) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $clarification = new Clarification();
            $clarification->setExternalid($clarificationId);
            $action = EventLogService::ACTION_CREATE;
        }

        // Now check if we have all dependent data.
        $fromTeamId = $event['data']['from_team_id'] ?? null;
        $fromTeam   = null;
        if ($fromTeamId !== null) {
            /** @var Team $fromTeam */
            $fromTeam = $this->em
                ->getRepository(Team::class)
                ->findOneBy(['externalid' => $fromTeamId]);
            if (!$fromTeam) {
                $this->addPendingEvent('team', $fromTeamId, $event);
                return;
            }
        }

        $toTeamId = $event['data']['to_team_id'] ?? null;
        $toTeam   = null;
        if ($toTeamId !== null) {
            /** @var Team $toTeam */
            $toTeam = $this->em
                ->getRepository(Team::class)
                ->findOneBy(['externalid' => $toTeamId]);
            if (!$toTeam) {
                $this->addPendingEvent('team', $toTeamId, $event);
                return;
            }
        }

        $inReplyToId = $event['data']['reply_to_id'] ?? null;
        $inReplyTo   = null;
        if ($inReplyToId !== null) {
            /** @var Clarification $inReplyTo */
            $inReplyTo = $this->em
                ->getRepository(Clarification::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $inReplyToId
                            ]);
            if (!$inReplyTo) {
                $this->addPendingEvent('clarification', $inReplyToId, $event);
                return;
            }
        }

        $problemId = $event['data']['problem_id'] ?? null;
        $problem   = null;
        if ($problemId !== null) {
            /** @var Problem $problem */
            $problem = $this->em
                ->getRepository(Problem::class)
                ->findOneBy(['externalid' => $problemId]);
            if (!$problem) {
                $this->addPendingEvent('problem', $problemId, $event);
                return;
            }
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $contest = $this->em
            ->getRepository(Contest::class)
            ->find($this->getSourceContestId());

        $submitTime = Utils::toEpochFloat($event['data']['time']);

        $clarification
            ->setInReplyTo($inReplyTo)
            ->setSender($fromTeam)
            ->setRecipient($toTeam)
            ->setProblem($problem)
            ->setContest($contest)
            ->setBody($event['data']['text'])
            ->setSubmittime($submitTime);

        if ($inReplyTo) {
            // Mark both the original message and the reply as answered.
            $inReplyTo->setAnswered(true);
            $clarification->setAnswered(true);
        } elseif ($fromTeam === null) {
            // Clarifications from jury are automatically answered.
            $clarification->setAnswered(true);
        }
        // Note: when a team sends a clarification and the jury never responds, but does click
        // 'set answered', it will not be marked as answered during import. These clarifications
        // need to be handled manually.

        // Save data and emit event.
        if ($action === EventLogService::ACTION_CREATE) {
            $this->em->persist($clarification);
        }
        $this->em->flush();
        $this->eventLog->log('clarifications', $clarification->getClarid(), $action,
                             $this->getSourceContestId());

        $this->processPendingEvents('clarification', $clarification->getExternalid());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws DBALException
     * @throws NonUniqueResultException
     */
    protected function importSubmission(array $event): void
    {
        $submissionId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to mark the submission as not valid and then emit a delete event.

            $submission = $this->em
                ->getRepository(Submission::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $submissionId
                            ]);
            if ($submission) {
                $this->markSubmissionAsValidAndRecalcScore($submission, false);
                return;
            } else {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the submission
        /** @var Submission $submission */
        $submission = $this->em
            ->getRepository(Submission::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $submissionId
                        ]);

        $languageId = $event['data']['language_id'];
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $languageId]);
        if (!$language) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'language', 'id' => $languageId],
                ],
            ]);
            return;
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $problemId = $event['data']['problem_id'];
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->addPendingEvent('problem', $problemId, $event);
            return;
        }

        // Find the contest problem.
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em
            ->getRepository(ContestProblem::class)
            ->find([
                       'contest' => $this->getSourceContest(),
                       'problem' => $problem,
                   ]);

        if (!$contestProblem) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'contest-problem', 'id' => $problem->getExternalid()],
                ],
            ]);
            return;
        }

        $teamId = $event['data']['team_id'];
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $teamId]);
        if (!$team) {
            $this->addPendingEvent('team', $teamId, $event);
            return;
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $submitTime = Utils::toEpochFloat($event['data']['time']);

        $entryPoint = $event['data']['entry_point'] ?? null;
        if (empty($entryPoint)) {
            $entryPoint = null;
        }

        // If the submission is found, we can only update the valid status.
        // If any of the other fields are different, this is an error.
        if ($submission) {
            $diff = [];
            if ($submission->getTeam()->getTeamid() !== $team->getTeamid()) {
                $diff['team_id'] = [
                    'us'       => $submission->getTeam()->getExternalid(),
                    'external' => $team->getExternalid()
                ];
            }
            if ($submission->getProblem()->getExternalid() !== $problem->getExternalid()) {
                $diff['problem_id'] = [
                    'us'       => $submission->getProblem()->getExternalid(),
                    'external' => $problem->getExternalid()
                ];
            }
            if ($submission->getLanguage()->getExternalid() !== $language->getExternalid()) {
                $diff['language_id'] = [
                    'us'       => $submission->getLanguage()->getExternalid(),
                    'external' => $language->getExternalid()
                ];
            }
            if (abs(Utils::difftime((float)$submission->getSubmittime(), $submitTime)) >= 1) {
                $diff['time'] = [
                    'us'       => $submission->getAbsoluteSubmitTime(),
                    'external' => $event['data']['time']
                ];
            }
            if ($entryPoint !== $submission->getEntryPoint()) {
                if ($submission->getEntryPoint() === null) {
                    // Special case: if we did not have an entrypoint yet, but we do get one now, update it
                    $submission->setEntryPoint($entryPoint);
                    $this->em->flush();
                    $this->eventLog->log('submissions', $submission->getSubmitid(),
                                         EventLogService::ACTION_UPDATE, $this->getSourceContestId());
                    $this->processPendingEvents('submission', $submission->getExternalid());
                    return;
                } elseif ($entryPoint !== null) {
                    $diff['entry_point'] = [
                        'us'       => $submission->getEntryPoint(),
                        'external' => $entryPoint
                    ];
                }
            }
            if (!empty($diff)) {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DATA_MISMATCH, ['diff' => $diff]);
                return;
            }

            $this->removeWarning($event, ExternalSourceWarning::TYPE_DATA_MISMATCH);

            // If the submission was not valid before, mark it valid now and recalculate the scoreboard.
            if (!$submission->getValid()) {
                $this->markSubmissionAsValidAndRecalcScore($submission, true);
            }
        } else {
            // First, check if we actually have the source for this submission in the data.
            if (empty($event['data']['files'][0]['href'])) {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                    'message' => 'No source files in event',
                ]);
                return;
            } elseif (($event['data']['files'][0]['mime'] ?? null) !== 'application/zip') {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                    'message' => 'Non-ZIP source files in event',
                ]);
                return;
            } else {
                $zipUrl = $event['data']['files'][0]['href'];
                if (preg_match('/^https?:\/\//', $zipUrl) === 0) {
                    // Relative URL, prepend the base URL.
                    $zipUrl = ($this->basePath ?? '') . $zipUrl;
                }

                $tmpdir = $this->dj->getDomjudgeTmpDir();

                // Check if we have a local file.
                if (file_exists($zipUrl)) {
                    // Yes, use it directly
                    $zipFile      = $zipUrl;
                    $shouldUnlink = false;
                } else {
                    // No, download the ZIP file.
                    $shouldUnlink = true;
                    if (!($zipFile = tempnam($tmpdir, "submission_zip_"))) {
                        $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                            'message' => 'Cannot create temporary file to download ZIP',
                        ]);
                        return;
                    }

                    try {
                        $response   = $this->httpClient->request('GET', $zipUrl);
                        $ziphandler = fopen($zipFile, 'w');
                        if ($response->getStatusCode() !== 200) {
                            // TODO: Retry a couple of times.
                            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                                'message' => 'Cannot download ZIP from ' . $zipUrl,
                            ]);
                            unlink($zipFile);
                            return;
                        }
                    } catch (TransportExceptionInterface $e) {
                        $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                            'message' => 'Cannot download ZIP from ' . $zipUrl . ': ' . $e->getMessage(),
                        ]);
                        unlink($zipFile);
                        return;
                    }

                    foreach ($this->httpClient->stream($response) as $chunk) {
                        fwrite($ziphandler, $chunk->getContent());
                    }
                    fclose($ziphandler);
                }

                // Open the ZIP file.
                $zip = new ZipArchive();
                $zip->open($zipFile);

                // Determine the files to submit.
                /** @var UploadedFile[] $filesToSubmit */
                $filesToSubmit = [];
                for ($zipFileIdx = 0; $zipFileIdx < $zip->numFiles; $zipFileIdx++) {
                    $filename = $zip->getNameIndex($zipFileIdx);
                    $content  = $zip->getFromName($filename);

                    if (!($tmpSubmissionFile = tempnam($tmpdir, "submission_source_"))) {
                        $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                            'message' => 'Cannot create temporary file to extract ZIP contents for file ' . $filename,
                        ]);
                        $zip->close();
                        if ($shouldUnlink) {
                            unlink($zipFile);
                        }
                        return;
                    }
                    file_put_contents($tmpSubmissionFile, $content);
                    $filesToSubmit[] = new UploadedFile(
                        $tmpSubmissionFile, $filename,
                        null, null, true
                    );
                }

                // If the language requires an entry point but we do not have one, use automatic entry point detection.
                if ($language->getRequireEntryPoint() && $entryPoint === null) {
                    $entryPoint = '__auto__';
                }

                // Submit the solution
                $contest    = $this->em->getRepository(Contest::class)->find($this->getSourceContestId());
                $submission = $this->submissionService->submitSolution(
                    $team, null, $contestProblem, $contest, $language, $filesToSubmit, 'shadowing', null,
                    null, $entryPoint, $submissionId, $submitTime,
                    $message
                );
                if (!$submission) {
                    $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                        'message' => 'Cannot add submission: ' . $message,
                    ]);
                    // Clean up the temporary submission files.
                    foreach ($filesToSubmit as $file) {
                        unlink($file->getRealPath());
                    }
                    $zip->close();
                    if ($shouldUnlink) {
                        unlink($zipFile);
                    }
                    return;
                }

                // Clean up the ZIP.
                $zip->close();
                if ($shouldUnlink) {
                    unlink($zipFile);
                }

                // Clean up the temporary submission files.
                foreach ($filesToSubmit as $file) {
                    unlink($file->getRealPath());
                }
            }
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_SUBMISSION_ERROR);

        $this->processPendingEvents('submission', $submission->getExternalid());
    }

    /**
     * @throws DBALException
     */
    protected function importJudgement(array $event): void
    {
        // Note that we do not emit events for imported judgements, as we will generate our own.
        $judgementId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the judgement.

            $judgement = $this->em
                ->getRepository(ExternalJudgement::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContestId(),
                                'externalid' => $judgementId
                            ]);
            if ($judgement) {
                $this->em->remove($judgement);
                $this->em->flush();
                return;
            } else {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the external judgement.
        /** @var ExternalJudgement $judgement */
        $judgement = $this->em
            ->getRepository(ExternalJudgement::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContestId(),
                            'externalid' => $judgementId
                        ]);
        $persist   = false;
        if (!$judgement) {
            $judgement = new ExternalJudgement();
            $judgement
                ->setExternalid($judgementId)
                ->setContest($this->em->getRepository(Contest::class)->find($this->getSourceContestId()));
            $persist = true;
        }

        // Now check if we have all dependent data.
        $submissionId = $event['data']['submission_id'] ?? null;
        /** @var Submission $submission */
        $submission = $this->em
            ->getRepository(Submission::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContestId(),
                            'externalid' => $submissionId
                        ]);
        if (!$submission) {
            $this->addPendingEvent('submission', $submissionId, $event);
            return;
        }

        $startTime = Utils::toEpochFloat($event['data']['start_time']);
        $endTime   = null;
        if (isset($event['data']['end_time'])) {
            $endTime = Utils::toEpochFloat($event['data']['end_time']);
        }

        $judgementTypeId = $event['data']['judgement_type_id'] ?? null;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID.
        if ($judgementTypeId !== null && !isset($verdictsFlipped[$judgementTypeId])) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'judgement-type', 'id' => $judgementTypeId],
                ],
            ]);
            return;
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $judgement
            ->setSubmission($submission)
            ->setStarttime($startTime)
            ->setEndtime($endTime)
            ->setResult($judgementTypeId === null ? null : $verdictsFlipped[$judgementTypeId]);

        if ($persist) {
            $this->em->persist($judgement);
        }

        $this->em->flush();

        // Now we need to update the validness of the judgements: the newest one is valid and the
        // others are invalid. So we load all judgements for this submission order by decreasing
        // starttime and update them.
        /** @var ExternalJudgement[] $externalJudgements */
        $externalJudgements = $this->em->createQueryBuilder()
                                       ->from(ExternalJudgement::class, 'ej')
                                       ->select('ej')
                                       ->andWhere('ej.submission = :submission')
                                       ->setParameter('submission', $submission)
                                       ->orderBy('ej.starttime', 'DESC')
                                       ->getQuery()
                                       ->getResult();

        foreach ($externalJudgements as $idx => $externalJudgement) {
            $externalJudgement->setValid($idx === 0);
        }

        $this->em->flush();

        $contestId = $submission->getContest()->getCid();
        $teamId    = $submission->getTeam()->getTeamid();
        $problemId = $submission->getProblem()->getProbid();

        // Now we need to update the scoreboard cache for this cell to get this judgement result in.
        $this->em->clear();
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        $team    = $this->em->getRepository(Team::class)->find($teamId);
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        $this->scoreboardService->calculateScoreRow($contest, $team, $problem);

        $this->processPendingEvents('judgement', $judgement->getExternalid());
    }

    protected function importRun(array $event): void
    {
        // Note that we do not emit events for imported runs, as we will generate our own.
        $runId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the run.

            $run = $this->em
                ->getRepository(ExternalRun::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $runId
                            ]);
            if ($run) {
                $this->em->remove($run);
                $this->em->flush();
                return;
            } else {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($event, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the external run.
        /** @var ExternalRun $run */
        $run     = $this->em
            ->getRepository(ExternalRun::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $runId
                        ]);
        $persist = false;
        if (!$run) {
            $run = new ExternalRun();
            $run
                ->setExternalid($runId)
                ->setContest($this->em->getRepository(Contest::class)->find($this->getSourceContest()));
            $persist = true;
        }

        // Now check if we have all dependent data.
        $judgementId = $event['data']['judgement_id'] ?? null;
        /** @var ExternalJudgement $externalJudgement */
        $externalJudgement = $this->em
            ->getRepository(ExternalJudgement::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $judgementId
                        ]);
        if (!$externalJudgement) {
            $this->addPendingEvent('judgement', $judgementId, $event);
            return;
        }

        $time    = Utils::toEpochFloat($event['data']['time']);
        $runTime = $event['data']['run_time'] ?? null;

        $judgementTypeId = $event['data']['judgement_type_id'] ?? null;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID.
        if (!isset($verdictsFlipped[$judgementTypeId])) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'judgement-type', 'id' => $judgementTypeId],
                ],
            ]);
            return;
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $rank    = $event['data']['ordinal'];
        $problem = $externalJudgement->getSubmission()->getContestProblem();

        // Find the testcase belonging to this run.
        /** @var Testcase|null $testcase */
        $testcase = $this->em->createQueryBuilder()
                             ->from(Testcase::class, 't')
                             ->select('t')
                             ->andWhere('t.problem = :problem')
                             ->andWhere('t.ranknumber = :ranknumber')
                             ->setParameter('problem', $problem->getProblem())
                             ->setParameter('ranknumber', $rank)
                             ->getQuery()
                             ->getOneOrNullResult();

        if ($testcase === null) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'testcase', 'id' => $rank],
                ],
            ]);
        }

        $this->removeWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $run
            ->setExternalJudgement($externalJudgement)
            ->setTestcase($testcase)
            ->setEndtime($time)
            ->setRuntime($runTime)
            ->setResult($judgementTypeId === null ? null : $verdictsFlipped[$judgementTypeId]);

        if ($persist) {
            $this->em->persist($run);
        }
        $this->em->flush();
    }

    protected function processPendingEvents(string $type, $id): void
    {
        // Process pending events.
        if (isset($this->pendingEvents[$type][$id])) {
            // Get all pending events.
            $pending = $this->pendingEvents[$type][$id];
            // Mark them as non-pending. Note that they might depend on more events,
            // but then they'll be re-added automatically in the correct place.
            unset($this->pendingEvents[$type][$id]);
            foreach ($pending as $event) {
                $this->importEvent($event, []);
            }
        }
    }

    protected function addPendingEvent(string $type, $id, array $event): void
    {
        // First, check if we already have pending events for this event.
        // We do this by loading the warnings with the correct hash.
        $hash = ExternalSourceWarning::calculateHash(
            ExternalSourceWarning::TYPE_DEPENDENCY_MISSING,
            $event['type'],
            $event['data']['id']
        );
        /** @var ExternalSourceWarning|null $warning */
        $warning = $this->em
            ->getRepository(ExternalSourceWarning::class)
            ->findOneBy([
                            'externalContestSource' => $this->source,
                            'hash'                  => $hash
                        ]);

        $dependencies = [];
        if ($warning) {
            $dependencies = $warning->getContent()['dependencies'];
        }

        $dependencies[$type . '-' . $id] = ['type' => $type, 'id' => $id, 'event' => $event];
        $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
            'dependencies' => $dependencies,
        ]);

        // Also store them locally in our data structure, so we can easily find them later.
        if (!isset($this->pendingEvents[$type][$id])) {
            $this->pendingEvents[$type][$id] = [];
        }

        $this->pendingEvents[$type][$id][] = $event;
    }

    protected function loadPendingEvents(): void
    {
        /** @var ExternalSourceWarning[] $warnings */
        $warnings = $this->em
            ->getRepository(ExternalSourceWarning::class)
            ->findBy([
                         'externalContestSource' => $this->source,
                         'type'                  => ExternalSourceWarning::TYPE_DEPENDENCY_MISSING,
                     ]);
        foreach ($warnings as $warning) {
            $dependencies = $warning->getContent()['dependencies'];
            foreach ($dependencies as $dependency) {
                if (!isset($dependency['event'])) {
                    continue;
                }

                $type  = $dependency['type'];
                $id    = $dependency['id'];
                $event = $dependency['event'];

                if (!isset($this->pendingEvents[$type][$id])) {
                    $this->pendingEvents[$type][$id] = [];
                }

                $this->pendingEvents[$type][$id][] = $event;
            }
        }
    }

    /**
     * @throws NonUniqueResultException
     * @throws DBALException
     */
    private function markSubmissionAsValidAndRecalcScore(Submission $submission, bool $valid): void
    {
        $submission->setValid($valid);

        $contestId = $submission->getContest()->getCid();
        $teamId    = $submission->getTeam()->getTeamid();
        $problemId = $submission->getProblem()->getProbid();

        $this->em->flush();
        $this->eventLog->log('submissions', $submission->getSubmitid(),
                             $valid ? EventLogService::ACTION_CREATE : EventLogService::ACTION_DELETE,
                             $this->getSourceContestId());

        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        $team    = $this->em->getRepository(Team::class)->find($teamId);
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        $this->scoreboardService->calculateScoreRow($contest, $team, $problem);
    }

    private function compareOrCreateValues(
        array         $event,
        BaseApiEntity $entity,
        array         $values,
        array         $extraDiff = [],
        bool          $updateEntity = true
    ): void {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $diff             = [];
        foreach ($values as $field => $value) {
            try {
                $ourValue = $propertyAccessor->getValue($entity, $field);
            } catch (UnexpectedTypeException $e) {
                // Subproperty that doesn't exist, it is null.
                $ourValue = null;
            } catch (UninitializedPropertyException $e) {
                // Property that is not initialized, assume it is null
                $ourValue = null;
            }
            if ($value !== $ourValue) {
                $diff[$field] = $ourValue;
            }
        }

        if (!empty($diff) || !empty($extraDiff)) {
            $fullDiff = [];
            foreach ($diff as $field => $ourValue) {
                $fullDiff[$field] = [
                    'us'       => $ourValue,
                    'external' => $values[$field],
                ];
            }
            foreach ($extraDiff as $field => $diffValues) {
                $fullDiff[$field] = [
                    'us'       => $diffValues[0],
                    'external' => $diffValues[1],
                ];
            }
            if ($updateEntity) {
                foreach ($values as $field => $value) {
                    // If the field contains a . and the value is null, it is an association we should
                    // clear
                    $parts = explode('.', $field);
                    if (count($parts) === 2 && $value === null) {
                        $propertyAccessor->setValue($entity, $parts[0], $value);
                    } else {
                        $propertyAccessor->setValue($entity, $field, $value);
                    }
                }
            } else {
                $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_DATA_MISMATCH, [
                    'diff' => $fullDiff
                ]);
            }
        } else {
            $this->removeWarning($event, ExternalSourceWarning::TYPE_DATA_MISMATCH);
        }
    }

    /**
     * @return bool True iff supported
     */
    protected function warningIfUnsupported(array $event, array $supportedActions): bool
    {
        if (!in_array($event['op'], $supportedActions)) {
            $this->addOrUpdateWarning($event, ExternalSourceWarning::TYPE_UNSUPORTED_ACTION, [
                'action' => $event['op']
            ]);
            return false;
        }

        // Clear warnings since this action is supported.
        $this->removeWarning($event, ExternalSourceWarning::TYPE_UNSUPORTED_ACTION);

        return true;
    }

    protected function addOrUpdateWarning(
        array  $event,
        string $type,
        array  $content = []
    ): void {
        $eventId    = $event['id'];
        $entityType = $event['type'];
        $entityId   = $event['data']['id'];
        $hash       = ExternalSourceWarning::calculateHash($type, $entityType, $entityId);
        $warning    = $this->em
            ->getRepository(ExternalSourceWarning::class)
            ->findOneBy(['externalContestSource' => $this->source, 'hash' => $hash]);
        if (!$warning) {
            $warning = new ExternalSourceWarning();
            // Reload the source since the entity manager might have lost it.
            $this->source = $this->em
                ->getRepository(ExternalContestSource::class)
                ->find($this->source->getExtsourceid());
            $warning
                ->setExternalContestSource($this->source)
                ->setType($type)
                ->setEntityType($entityType)
                ->setEntityId($entityId);
            $this->em->persist($warning);
        }

        $warning
            ->setLastEventId($eventId)
            ->setLastTime(Utils::now())
            ->setContent($content);

        $this->em->flush();
    }

    protected function removeWarning(array $event, string $type): void
    {
        $entityType = $event['type'];
        $entityId   = $event['data']['id'];
        $hash       = ExternalSourceWarning::calculateHash($type, $entityType, $entityId);
        $warning    = $this->em
            ->getRepository(ExternalSourceWarning::class)
            ->findOneBy(['externalContestSource' => $this->source, 'hash' => $hash]);
        if ($warning) {
            $this->em->remove($warning);
            $this->em->flush();
        }
    }
}
