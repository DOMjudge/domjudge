<?php declare(strict_types=1);

namespace App\Service;

use App\DataTransferObject\ApiInfo;
use App\DataTransferObject\Shadowing\ClarificationEvent;
use App\DataTransferObject\Shadowing\ContestData;
use App\DataTransferObject\Shadowing\ContestEvent;
use App\DataTransferObject\Shadowing\Event;
use App\DataTransferObject\Shadowing\EventData;
use App\DataTransferObject\Shadowing\EventType;
use App\DataTransferObject\Shadowing\GroupEvent;
use App\DataTransferObject\Shadowing\JudgementEvent;
use App\DataTransferObject\Shadowing\JudgementTypeEvent;
use App\DataTransferObject\Shadowing\LanguageEvent;
use App\DataTransferObject\Shadowing\OrganizationEvent;
use App\DataTransferObject\Shadowing\ProblemEvent;
use App\DataTransferObject\Shadowing\RunEvent;
use App\DataTransferObject\Shadowing\StateEvent;
use App\DataTransferObject\Shadowing\SubmissionEvent;
use App\DataTransferObject\Shadowing\TeamEvent;
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
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Utils\Utils;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZipArchive;

class ExternalContestSourceService
{
    protected HttpClientInterface $httpClient;

    protected ?ExternalContestSource $source = null;

    protected bool $contestLoaded = false;
    protected ?ContestData $cachedContestData = null;
    protected ?ApiInfo $cachedApiInfoData = null;
    protected ?string $loadingError = null;
    protected bool $shouldStopReading = false;
    /** @var array<string, string> $verdicts */
    protected array $verdicts = [];
    protected ?string $basePath = null;

    /**
     * This array will hold all events that are waiting on a dependent event
     * because it has an ID that does not exist yet. According to the official
     * spec this can not happen, but in practice it does happen. We handle
     * this by storing these events here and checking whether there are any
     * after saving any dependent event.
     *
     * This array is three-dimensional:
     * - The first dimension is the type of the dependent event type
     * - The second dimension is the (external) ID of the dependent event
     * - The third dimension contains an array of all events that should be processed
     *
     * @var array<string, array<string, array<Event<EventData>>>> $pendingEvents
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
        HttpClientInterface $httpClient,
        protected readonly DOMJudgeService $dj,
        protected readonly EntityManagerInterface $em,
        #[Autowire(service: 'monolog.logger.event-feed-importer')]
        protected readonly LoggerInterface $logger,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLog,
        protected readonly SubmissionService $submissionService,
        protected readonly ScoreboardService $scoreboardService,
        protected readonly SerializerInterface $serializer,
        #[Autowire('%domjudge.version%')]
        string $domjudgeVersion
    ) {
        $clientOptions = [
            'headers' => [
                'User-Agent' => 'DOMjudge/' . $domjudgeVersion,
            ],
        ];
        if ($this->config->get('external_contest_sources_allow_untrusted_certificates')) {
            $clientOptions['verify_host'] = false;
            $clientOptions['verify_peer'] = false;
        }
        $this->httpClient = $httpClient->withOptions($clientOptions);
    }

    public function setSource(ExternalContestSource $source): void
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

        return $this->cachedContestData->id;
    }

    public function getContestName(): string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedContestData->name;
    }

    public function getContestStartTime(): ?float
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }
        if (isset($this->cachedContestData->startTime)) {
            return Utils::toEpochFloat($this->cachedContestData->startTime);
        } else {
            $this->logger->warning('Contest has no start time, is the contest paused?');
            return null;
        }
    }

    public function getContestDuration(): string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedContestData->duration;
    }

    public function getApiVersion(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData->version;
    }

    public function getApiVersionUrl(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData->versionUrl;
    }

    public function getApiProviderName(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData->provider?->name ?? $this->cachedApiInfoData->name;
    }

    public function getApiProviderVersion(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData->provider?->version ?? $this->cachedApiInfoData->domjudge?->version;
    }

    public function getApiProviderBuildDate(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData->provider?->buildDate;
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

    /**
     * @param string[] $eventsToSkip
     */
    public function import(bool $fromStart, array $eventsToSkip, ?callable $progressReporter = null): bool
    {
        // We need the verdicts to validate judgement-types.
        $this->verdicts = $this->dj->getVerdicts(mergeExternal: true);

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
        return match ($this->source->getType()) {
            ExternalContestSource::TYPE_CCS_API => $this->importFromCcsApi($eventsToSkip, $progressReporter),
            ExternalContestSource::TYPE_CONTEST_PACKAGE => $this->importFromContestArchive($eventsToSkip, $progressReporter),
            default => false,
        };
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

    /**
     * @param string[] $eventsToSkip
     */
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
                // Pass both since_id and since_token to support both versions of the event feed
                $fullUrl .= '?since_id=' . $this->getLastReadEventId();
                $fullUrl .= '&since_token=' . $this->getLastReadEventId();
            }
            $response = $this->httpClient->request('GET', $fullUrl, ['buffer' => false]);
            $statusCode = $response->getStatusCode();
            $this->source->setLastHTTPCode($statusCode);
            $this->em->flush();
            if ($statusCode !== 200) {
                $this->logger->warning(
                    'Received non-200 response code %d, waiting for five seconds ' .
                    'and trying again. Press ^C to quit.',
                    [$statusCode]
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
                        $event = $this->serializer->deserialize($line, Event::class, 'json', ['api_version' => $this->getApiVersion()]);
                        $this->importEvent($event, $eventsToSkip);

                        $eventId = $event->id;
                        $this->setLastEvent($eventId);
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
                    if (!str_starts_with($e->getMessage(), 'OpenSSL SSL_read: error:0A000126')) {
                        // Ignore error of not fully compliant TLS implementation on server-side
                        $this->logger->error(
                            'Received error while reading event feed: %s',
                            [$e->getMessage()]
                        );
                    }
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

    /**
     * @param string[] $eventsToSkip
     */
    protected function importFromContestArchive(array $eventsToSkip, ?callable $progressReporter = null): bool
    {
        $file = fopen($this->source->getSource() . '/event-feed.ndjson', 'r');

        $skipEventsUpTo = $this->getLastReadEventId();

        $this->readEventsFromFile($file,
            function (
                string $line,
                &$shouldStop
            ) use (
                $eventsToSkip,
                &$skipEventsUpTo,
                $progressReporter
            ) {
                $lastEventId          = $this->getLastReadEventId();
                $readingToLastEventId = false;
                $event = $this->serializer->deserialize($line, Event::class, 'json', ['api_version' => $this->getApiVersion()]);

                if ($skipEventsUpTo === null) {
                    $this->importEvent($event, $eventsToSkip);
                    $lastEventId = $event->id;
                } elseif ($event->id === $skipEventsUpTo) {
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
     * The callback will be called for every found event and will receive two
     * arguments:
     * - The event line to process
     * - A boolean that can be set to true (pass-by-reference) to stop processing
     *
     * @param resource                     $filePointer
     * @param callable(string, bool): void $callback
     */
    protected function readEventsFromFile($filePointer, callable $callback): void
    {
        $buffer = '';
        while (!feof($filePointer) || !empty($buffer)) {
            // Read the file until we find a newline or the end of the stream
            while (!feof($filePointer) && !str_contains($buffer, "\n")) {
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

            $shouldStop = false;
            $callback($line, $shouldStop);
            /** @phpstan-ignore-next-line The callable can modify $shouldStop but currently we can't indicate this */
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
                        $this->basePath = $matches[1];
                        if ($this->source->getUsername()) {
                            $auth = [$this->source->getUsername()];
                            if (is_string($this->source->getPassword())) {
                                $auth[] = $this->source->getPassword();
                            }
                            $clientOptions['auth_basic'] = $auth;
                        } else {
                            $clientOptions['auth_basic'] = null;
                        }
                        $this->httpClient = $this->httpClient->withOptions($clientOptions);
                        $contestResponse = $this->httpClient->request('GET', $this->source->getSource());
                        $this->cachedContestData = $this->serializer->deserialize($contestResponse->getContent(), ContestData::class, 'json');

                        $apiInfoResponse = $this->httpClient->request('GET', '');
                        $this->cachedApiInfoData = $this->serializer->deserialize($apiInfoResponse->getContent(), ApiInfo::class, 'json');
                    }
                } catch (HttpExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
                    $this->cachedContestData = null;
                    $this->cachedApiInfoData = null;
                    $this->loadingError = $e->getMessage();
                }
                $this->contestLoaded = true;
                break;
            case ExternalContestSource::TYPE_CONTEST_PACKAGE:
                $this->cachedContestData = null;
                $contestFile = $this->source->getSource() . '/contest.json';
                $eventFeedFile = $this->source->getSource() . '/event-feed.ndjson';
                $apiInfoFile = $this->source->getSource() . '/api.json';
                if (!is_dir($this->source->getSource())) {
                    $this->loadingError = 'Contest package directory not found';
                } elseif (!is_file($contestFile)) {
                    $this->loadingError = 'contest.json not found in archive';
                } elseif (!is_file($eventFeedFile)) {
                    $this->loadingError = 'event-feed.ndjson not found in archive';
                } else {
                    try {
                        $this->cachedContestData = $this->serializer->deserialize(file_get_contents($contestFile), ContestData::class, 'json');
                    } catch (Exception $e) {
                        $this->loadingError = $e->getMessage();
                    }

                    if (is_file($apiInfoFile)) {
                        try {
                            $this->cachedApiInfoData = $this->serializer->deserialize(file_get_contents($apiInfoFile), ApiInfo::class, 'json');
                        } catch (Exception $e) {
                            $this->loadingError = $e->getMessage();
                        }
                    }
                }
                break;
        }
    }

    /**
     * Import the given event.
     *
     * @param Event<EventData> $event
     * @param string[]         $eventsToSkip
     *
     * @throws DBALException
     * @throws NonUniqueResultException
     * @throws TransportExceptionInterface
     */
    public function importEvent(Event $event, array $eventsToSkip): void
    {
        // Check whether we have received an exit signal.
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($this->shouldStopReading()) {
            return;
        }

        if ($event->id !== null && in_array($event->id, $eventsToSkip)) {
            $this->logger->info("Skipping event with ID %s and type %s as requested",
                                [$event->id, $event->type->value]);
            return;
        }

        if ($event->id !== null) {
            $this->logger->debug("Importing event with ID %s and type %s...",
                                 [$event->id, $event->type->value]);
        } else {
            $this->logger->debug("Importing event with type %s...",
                                 [$event->type->value]);
        }

        // Note the @vars here are to make PHPStan understand the correct types.
        $method = match ($event->type) {
            EventType::ACCOUNTS, EventType::AWARDS, EventType::MAP_INFO, EventType::PERSONS, EventType::START_STATUS, EventType::TEAM_MEMBERS => $this->ignoreEvent(...),
            EventType::STATE => $this->validateState(...),
            EventType::CONTESTS => $this->validateAndUpdateContest(...),
            EventType::JUDGEMENT_TYPES => $this->importJudgementType(...),
            EventType::LANGUAGES => $this->validateLanguage(...),
            EventType::GROUPS => $this->validateAndUpdateGroup(...),
            EventType::ORGANIZATIONS => $this->validateAndUpdateOrganization(...),
            EventType::PROBLEMS => $this->validateAndUpdateProblem(...),
            EventType::TEAMS => $this->validateAndUpdateTeam(...),
            EventType::CLARIFICATIONS => $this->importClarification(...),
            EventType::SUBMISSIONS => $this->importSubmission(...),
            EventType::JUDGEMENTS => $this->importJudgement(...),
            EventType::RUNS => $this->importRun(...),
        };

        foreach ($event->data as $eventData) {
            $method($event, $eventData);
        }
    }

    /**
     * @param Event<EventData> $event
     */
    protected function ignoreEvent(Event $event, EventData $data): void
    {
        $this->logger->debug("Ignoring event of type %s", [$event->type->value]);
    }

    /**
     * @param Event<EventData> $event
     */
    protected function validateState(Event $event, EventData $data): void
    {
        if (!$data instanceof StateEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }
        if ($data->endOfUpdates) {
            $this->logger->info('End of updates encountered');
        }
    }

    /**
     * @param Event<EventData> $event
     *
     * @throws NonUniqueResultException
     */
    protected function validateAndUpdateContest(Event $event, EventData $data): void
    {
        if (!$data instanceof ContestEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }
        if (!$this->warningIfUnsupported(
            $event,
            $data->id,
            [EventLogService::ACTION_CREATE, EventLogService::ACTION_UPDATE])
        ) {
            return;
        }

        // First, reload the contest so we can check its data..
        /** @var Contest $contest */
        $contest = $this->em
            ->getRepository(Contest::class)
            ->find($this->getSourceContestId());

        // We need to convert the freeze to a value from the start instead of
        // the end so perform some regex magic.
        $duration     = $data->duration;
        $freeze       = $data->scoreboardFreezeDuration;
        $reltimeRegex = '/^(-)?(\d+):(\d{2}):(\d{2})(?:\.(\d{3}))?$/';
        preg_match($reltimeRegex, $duration, $durationData);

        $durationNegative = ($durationData[1] === '-');
        $fullDuration     = $durationNegative ? $duration : ('+' . $duration);

        if ($freeze !== null) {
            preg_match($reltimeRegex, $freeze, $freezeData);
            $freezeNegative     = ($freezeData[1] === '-');
            $freezeHourModifier = $freezeNegative ? -1 : 1;
            $freezeInSeconds    = $freezeHourModifier * (int)$freezeData[2] * 3600
                + 60 * (int)$freezeData[3]
                + (double)sprintf('%d.%03d', $freezeData[4], $freezeData[5] ?? 0);
            $durationHourModifier = $durationNegative ? -1 : 1;
            $durationInSeconds    = $durationHourModifier * (int)$durationData[2] * 3600
                                    + 60 * (int)$durationData[3]
                                    + (double)sprintf('%d.%03d', $durationData[4], $durationData[5] ?? 0);
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
        $startTime = $data->startTime ? new DateTime($data->startTime) : null;
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
                'end_time_string'    => preg_replace('/\.000$/', '', $fullDuration),
                'freeze_time_string' => preg_replace('/\.000$/', '', $fullFreeze),
            ];
        } else {
            $toCheck = [
                'start_time_enabled' => false,
            ];
        }

        $toCheck['name'] = $data->name;

        // Also compare the penalty time
        $penaltyTime = $data->penaltyTime;
        if ($this->config->get('penalty_time') != $penaltyTime) {
            $this->logger->warning(
                'Penalty time does not match between feed (%d) and local (%d)',
                [$penaltyTime, $this->config->get('penalty_time')]
            );
        }

        $this->compareOrCreateValues($event, $data->id, $contest, $toCheck);

        $this->em->flush();
        $this->eventLog->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE, $this->getSourceContestId());
    }

    /**
     * @param Event<EventData> $event
     */
    protected function importJudgementType(Event $event, EventData $data): void
    {
        if (!$data instanceof JudgementTypeEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        if (!$this->warningIfUnsupported($event, $data->id, [EventLogService::ACTION_CREATE])) {
            return;
        }

        $verdict         = $data->id;
        $verdictsFlipped = array_flip($this->verdicts);
        if (!isset($verdictsFlipped[$verdict])) {
            // Verdict not found, import it as a custom verdict; assume it has a penalty.
            $customVerdicts = $this->config->get('external_judgement_types');
            $customVerdicts[$verdict] = str_replace(' ', '-', $data->name);
            $this->config->saveChanges(['external_judgement_types' => $customVerdicts], $this->eventLog, $this->dj);
            $this->verdicts = $this->dj->getVerdicts(mergeExternal: true);
            $penalty = true;
            $solved = false;
            $this->logger->warning('Judgement type %s not found locally, importing as external verdict', [$verdict]);
        } else {
            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            $penalty = true;
            $solved = false;
            if ($verdict === 'AC') {
                $penalty = false;
                $solved = true;
            } elseif ($verdict === 'CE') {
                $penalty = (bool)$this->config->get('compile_penalty');
            }
        }

        $extraDiff = [];

        if ($penalty !== $data->penalty) {
            $extraDiff['penalty'] = [$penalty, $data->penalty];
        }
        if ($solved !== $data->solved) {
            $extraDiff['solved'] = [$solved, $data->solved];
        }

        // Entity doesn't matter, since we do not compare anything besides the extra data
        $this->compareOrCreateValues($event, $data->id, $this->source->getContest(), [], $extraDiff, false);
    }

    /**
     * @param Event<EventData> $event
     */
    protected function validateLanguage(Event $event, EventData $data): void
    {
        if (!$data instanceof LanguageEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        if (!$this->warningIfUnsupported($event, $data->id, [EventLogService::ACTION_CREATE])) {
            return;
        }

        $extId = $data->id;
        $language = $this->em
            ->getRepository(Language::class)
            ->findOneBy(['externalid' => $extId]);
        if (!$language) {
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
        } elseif (!$language->getAllowSubmit()) {
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DATA_MISMATCH, [
                'diff' => [
                    'allow_submit' => [
                        'us'       => false,
                        'external' => true,
                    ],
                ],
            ]);
        } else {
            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DATA_MISMATCH);
        }
    }

    /**
     * @param Event<EventData> $event
     */
    protected function validateAndUpdateGroup(Event $event, EventData $data): void
    {
        if (!$data instanceof GroupEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        $groupId = $data->id;

        /** @var TeamCategory|null $category */
        $category = $this->em
            ->getRepository(TeamCategory::class)
            ->findOneBy(['externalid' => $groupId]);

        if ($event->operation->value === EventLogService::ACTION_DELETE) {
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
                [$data->name]
            );
            $category = new TeamCategory();
            $this->em->persist($category);
            $action = EventLogService::ACTION_CREATE;
        }

        $toCheck = [
            'externalid' => $data->id,
            'name'       => $data->name,
            'visible'    => !($data->hidden ?? false),
            'icpcid'     => $data->icpcId,
        ];

        // Add DOMjudge specific fields that might be useful to import
        if (isset($data->sortorder)) {
            $toCheck['sortorder'] = $data->sortorder;
        }
        if (isset($data->color)) {
            $toCheck['color'] = $data->color;
        }

        $this->compareOrCreateValues($event, $data->id, $category, $toCheck);

        $this->em->flush();
        $this->eventLog->log('groups', $category->getCategoryid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('group', $category->getExternalid());
    }

    /**
     * @param Event<EventData> $event
     */
    protected function validateAndUpdateOrganization(Event $event, EventData $data): void
    {
        if (!$data instanceof OrganizationEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        $organizationId = $data->id;

        /** @var TeamAffiliation|null $affiliation */
        $affiliation = $this->em
            ->getRepository(TeamAffiliation::class)
            ->findOneBy(['externalid' => $organizationId]);

        if ($event->operation->value === EventLogService::ACTION_DELETE) {
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
                [$data->formalName ?? $data->name]
            );
            $affiliation = new TeamAffiliation();
            $this->em->persist($affiliation);
            $action = EventLogService::ACTION_CREATE;
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheck = [
            'externalid' => $data->id,
            'name'       => $data->formalName ?? $data->name,
            'shortname'  => $data->name,
            'icpcid'     => $data->icpcId,
        ];
        if (isset($data->country)) {
            $toCheck['country'] = $data->country;
        }

        $this->compareOrCreateValues($event, $data->id, $affiliation, $toCheck);

        $this->em->flush();
        $this->eventLog->log('organizations', $affiliation->getAffilid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('organization', $affiliation->getExternalid());
    }

    /**
     * @param Event<EventData> $event
     */
    protected function validateAndUpdateProblem(Event $event, EventData $data): void
    {
        if (!$data instanceof ProblemEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        if (!$this->warningIfUnsupported($event, $data->id, [
            EventLogService::ACTION_CREATE,
            EventLogService::ACTION_UPDATE,
        ])) {
            return;
        }

        $problemId = $data->id;

        // First, load the problem.
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // Now find the contest problem.
        $contestProblem = $this->em
            ->getRepository(ContestProblem::class)
            ->find([
                       'contest' => $this->getSourceContest(),
                       'problem' => $problem,
                   ]);
        if (!$contestProblem) {
            // Note: we can't handle updates to non-existing problems, since we require things
            // like the testcases
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheckProblem = [
            'name'      => $data->name,
            'timelimit' => $data->timeLimit,
        ];

        if ($contestProblem->getShortname() !== $data->label) {
            $this->logger->warning(
                'Contest problem short name does not match between feed (%s) and local (%s), updating',
                [$data->label, $contestProblem->getShortname()]
            );
            $contestProblem->setShortname($data->label);
        }
        if ($contestProblem->getColor() !== ($data->rgb)) {
            $this->logger->warning(
                'Contest problem color does not match between feed (%s) and local (%s), updating',
                [$data->rgb, $contestProblem->getColor()]
            );
            $contestProblem->setColor($data->rgb);
        }

        $this->compareOrCreateValues($event, $data->id, $problem, $toCheckProblem);

        $this->em->flush();
        $this->eventLog->log('problems', $problem->getProbid(), EventLogService::ACTION_UPDATE, $this->getSourceContestId());

        $this->processPendingEvents('problem', $problem->getProbid());
    }

    /**
     * @param Event<EventData> $event
     */
    protected function validateAndUpdateTeam(Event $event, EventData $data): void
    {
        if (!$data instanceof TeamEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        $teamId = $data->id;

        /** @var Team|null $team */
        $team = $this->em
            ->getRepository(Team::class)
            ->findOneBy(['externalid' => $teamId]);

        if ($event->operation->value === EventLogService::ACTION_DELETE) {
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
                [$data->formalName ?? $data->name]
            );
            $team = new Team();
            $this->em->persist($team);
            $action = EventLogService::ACTION_CREATE;
        }

        if (!empty($data->organizationId)) {
            $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $data->organizationId]);
            if (!$affiliation) {
                $affiliation = new TeamAffiliation();
                $this->em->persist($affiliation);
            }
            $team->setAffiliation($affiliation);
        }

        if (!empty($data->groupIds[0])) {
            $category = $this->em->getRepository(TeamCategory::class)->findOneBy(['externalid' => $data->groupIds[0]]);
            if (!$category) {
                $category = new TeamCategory();
                $this->em->persist($category);
            }
            $team->setCategory($category);
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheck = [
            'externalid'             => $data->id,
            'name'                   => $data->formalName ?? $data->name,
            'display_name'           => $data->displayName,
            'affiliation.externalid' => $data->organizationId,
            'category.externalid'    => $data->groupIds[0] ?? null,
            'icpcid'                 => $data->icpcId,
        ];
        if (isset($data->country)) {
            $toCheck['country'] = $data->country;
        }

        $this->compareOrCreateValues($event, $data->id, $team, $toCheck);

        $this->em->flush();
        $this->eventLog->log('teams', $team->getTeamid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('team', $team->getTeamid());
    }

    /**
     * @param Event<EventData> $event
     *
     * @throws NonUniqueResultException
     */
    protected function importClarification(Event $event, EventData $data): void
    {
        if (!$data instanceof ClarificationEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        $clarificationId = $data->id;

        if ($event->operation->value === EventLogService::ACTION_DELETE) {
            // We need to delete the team

            $clarification = $this->em
                ->getRepository(Clarification::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $clarificationId,
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
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }

            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the clarification
        $clarification = $this->em
            ->getRepository(Clarification::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $clarificationId,
                        ]);
        if ($clarification) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $clarification = new Clarification();
            $clarification->setExternalid($clarificationId);
            $action = EventLogService::ACTION_CREATE;
        }

        // Now check if we have all dependent data.
        $fromTeamId = $data->fromTeamId;
        $fromTeam   = null;
        if ($fromTeamId !== null) {
            $fromTeam = $this->em
                ->getRepository(Team::class)
                ->findOneBy(['externalid' => $fromTeamId]);
            if (!$fromTeam) {
                $this->addPendingEvent('team', $fromTeamId, $event, $data);
                return;
            }
        }

        $toTeamId = $data->toTeamId;
        $toTeam   = null;
        if ($toTeamId !== null) {
            $toTeam = $this->em
                ->getRepository(Team::class)
                ->findOneBy(['externalid' => $toTeamId]);
            if (!$toTeam) {
                $this->addPendingEvent('team', $toTeamId, $event, $data);
                return;
            }
        }

        $inReplyToId = $data->replyToId;
        $inReplyTo   = null;
        if ($inReplyToId !== null) {
            $inReplyTo = $this->em
                ->getRepository(Clarification::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $inReplyToId,
                            ]);
            if (!$inReplyTo) {
                $this->addPendingEvent('clarification', $inReplyToId, $event, $data);
                return;
            }
        }

        $problemId = $data->problemId;
        $problem   = null;
        if ($problemId !== null) {
            $problem = $this->em
                ->getRepository(Problem::class)
                ->findOneBy(['externalid' => $problemId]);
            if (!$problem) {
                $this->addPendingEvent('problem', $problemId, $event, $data);
                return;
            }
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $contest = $this->em
            ->getRepository(Contest::class)
            ->find($this->getSourceContestId());

        $submitTime = Utils::toEpochFloat($data->time);

        $clarification
            ->setInReplyTo($inReplyTo)
            ->setSender($fromTeam)
            ->setRecipient($toTeam)
            ->setProblem($problem)
            ->setContest($contest)
            ->setBody($data->text)
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
     * @param Event<EventData> $event
     *
     * @throws TransportExceptionInterface
     * @throws DBALException
     * @throws NonUniqueResultException
     */
    protected function importSubmission(Event $event, EventData $data): void
    {
        if (!$data instanceof SubmissionEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        $submissionId = $data->id;

        if ($event->operation->value === EventLogService::ACTION_DELETE) {
            // We need to mark the submission as not valid and then emit a delete event.

            $submission = $this->em
                ->getRepository(Submission::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $submissionId,
                            ]);
            if ($submission) {
                $this->markSubmissionAsValidAndRecalcScore($submission, false);
                return;
            } else {
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the submission
        $submission = $this->em
            ->getRepository(Submission::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $submissionId,
                        ]);

        $languageId = $data->languageId;
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $languageId]);
        if (!$language) {
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'language', 'id' => $languageId],
                ],
            ]);
            return;
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $problemId = $data->problemId;
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->addPendingEvent('problem', $problemId, $event, $data);
            return;
        }

        // Find the contest problem.
        $contestProblem = $this->em
            ->getRepository(ContestProblem::class)
            ->findOneBy([
                       'contest' => $this->getSourceContest(),
                       'problem' => $problem,
                   ]);

        if (!$contestProblem) {
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'contest-problem', 'id' => $problem->getExternalid()],
                ],
            ]);
            return;
        }

        $teamId = $data->teamId;
        $team = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $teamId]);
        if (!$team) {
            $this->addPendingEvent('team', $teamId, $event, $data);
            return;
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $submitTime = Utils::toEpochFloat($data->time);

        $entryPoint = $data->entryPoint;
        if (empty($entryPoint)) {
            $entryPoint = null;
        }

        $submissionDownloadSucceeded = true;

        // If the submission is found, we can only update the valid status.
        // If any of the other fields are different, this is an error.
        if ($submission) {
            $diff = [];
            if ($submission->getTeam()->getTeamid() !== $team->getTeamid()) {
                $diff['team_id'] = [
                    'us'       => $submission->getTeam()->getExternalid(),
                    'external' => $team->getExternalid(),
                ];
            }
            if ($submission->getProblem()->getExternalid() !== $problem->getExternalid()) {
                $diff['problem_id'] = [
                    'us'       => $submission->getProblem()->getExternalid(),
                    'external' => $problem->getExternalid(),
                ];
            }
            if ($submission->getLanguage()->getExternalid() !== $language->getExternalid()) {
                $diff['language_id'] = [
                    'us'       => $submission->getLanguage()->getExternalid(),
                    'external' => $language->getExternalid(),
                ];
            }
            if (abs(Utils::difftime((float)$submission->getSubmittime(), $submitTime)) >= 1) {
                $diff['time'] = [
                    'us'       => $submission->getAbsoluteSubmitTime(),
                    'external' => $data->time,
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
                        'external' => $entryPoint,
                    ];
                }
            }
            if (!empty($diff)) {
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DATA_MISMATCH, ['diff' => $diff]);
                return;
            }

            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DATA_MISMATCH);

            // If the submission was not valid before, mark it valid now and recalculate the scoreboard.
            if (!$submission->getValid()) {
                $this->markSubmissionAsValidAndRecalcScore($submission, true);
            }
        } else {
            // First, check if we actually have the source for this submission in the data.
            if (empty($data->files[0]?->href)) {
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                    'message' => 'No source files in event',
                ]);
                $submissionDownloadSucceeded = false;
            } elseif ($data->files[0]->mime !== null && $data->files[0]->mime !== 'application/zip') {
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                    'message' => 'Non-ZIP source files in event',
                ]);
                $submissionDownloadSucceeded = false;
            } else {
                $zipUrl = $data->files[0]->href;
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
                        $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                            'message' => 'Cannot create temporary file to download ZIP',
                        ]);
                        $submissionDownloadSucceeded = false;
                    }

                    if ($submissionDownloadSucceeded) {
                        try {
                            $response = $this->httpClient->request('GET', $zipUrl);
                            $ziphandler = fopen($zipFile, 'w');
                            if ($response->getStatusCode() !== 200) {
                                // TODO: Retry a couple of times.
                                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                                    'message' => 'Cannot download ZIP from ' . $zipUrl,
                                ]);
                                $submissionDownloadSucceeded = false;
                            }
                        } catch (TransportExceptionInterface $e) {
                            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                                'message' => 'Cannot download ZIP from ' . $zipUrl . ': ' . $e->getMessage(),
                            ]);
                            if (isset($ziphandler)) {
                                fclose($ziphandler);
                            }
                            unlink($zipFile);
                            $submissionDownloadSucceeded = false;
                        }
                    }

                    if (isset($response, $ziphandler) && $submissionDownloadSucceeded) {
                        foreach ($this->httpClient->stream($response) as $chunk) {
                            fwrite($ziphandler, $chunk->getContent());
                        }
                        fclose($ziphandler);
                    }
                }
            }

            if ($submissionDownloadSucceeded && isset($zipFile, $tmpdir)) {
                // Open the ZIP file.
                $zip = new ZipArchive();
                $zip->open($zipFile);

                // Determine the files to submit.
                /** @var UploadedFile[] $filesToSubmit */
                $filesToSubmit = [];
                for ($zipFileIdx = 0; $zipFileIdx < $zip->numFiles; $zipFileIdx++) {
                    $filename = $zip->getNameIndex($zipFileIdx);
                    $content = $zip->getFromName($filename);

                    if (!($tmpSubmissionFile = tempnam($tmpdir, "submission_source_"))) {
                        $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                            'message' => 'Cannot create temporary file to extract ZIP contents for file ' . $filename,
                        ]);
                        $submissionDownloadSucceeded = false;
                        continue;
                    }
                    file_put_contents($tmpSubmissionFile, $content);
                    $filesToSubmit[] = new UploadedFile(
                        $tmpSubmissionFile, $filename,
                        null, null, true
                    );
                }
            } else {
                $filesToSubmit = [];
            }

            // If the language requires an entry point but we do not have one, use automatic entry point detection.
            if ($language->getRequireEntryPoint() && $entryPoint === null) {
                $entryPoint = '__auto__';
            }

            // Submit the solution
            $contest = $this->em->getRepository(Contest::class)->find($this->getSourceContestId());
            $submission = $this->submissionService->submitSolution(
                team: $team,
                user: null,
                problem: $contestProblem,
                contest: $contest,
                language: $language,
                files: $filesToSubmit,
                source: 'shadowing',
                entryPoint: $entryPoint,
                externalId: $submissionId,
                submitTime: $submitTime,
                message: $message,
                forceImportInvalid: !$submissionDownloadSucceeded
            );
            if (!$submission) {
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                    'message' => 'Cannot add submission: ' . $message,
                ]);
                // Clean up the temporary submission files.
                foreach ($filesToSubmit as $file) {
                    unlink($file->getRealPath());
                }
                if (isset($zip)) {
                    $zip->close();
                }
                if (isset($shouldUnlink) && $shouldUnlink && isset($zipFile)) {
                    unlink($zipFile);
                }
                return;
            }

            // Clean up the ZIP.
            if (isset($zip)) {
                $zip->close();
            }
            if (isset($shouldUnlink) && $shouldUnlink && isset($zipFile)) {
                unlink($zipFile);
            }

            // Clean up the temporary submission files.
            foreach ($filesToSubmit as $file) {
                unlink($file->getRealPath());
            }
        }

        if ($submissionDownloadSucceeded) {
            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_SUBMISSION_ERROR);
        }

        $this->processPendingEvents('submission', $submission->getExternalid());
    }

    /**
     * @param Event<EventData> $event
     *
     * @throws DBALException
     */
    protected function importJudgement(Event $event, EventData $data): void
    {
        if (!$data instanceof JudgementEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        // Note that we do not emit events for imported judgements, as we will generate our own.
        $judgementId = $data->id;

        if ($event->operation->value === EventLogService::ACTION_DELETE) {
            // We need to delete the judgement.

            $judgement = $this->em
                ->getRepository(ExternalJudgement::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContestId(),
                                'externalid' => $judgementId,
                            ]);
            if ($judgement) {
                $this->em->remove($judgement);
                $this->em->flush();
                return;
            } else {
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the external judgement.
        $judgement = $this->em
            ->getRepository(ExternalJudgement::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContestId(),
                            'externalid' => $judgementId,
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
        $submissionId = $data->submissionId;
        $submission = $this->em
            ->getRepository(Submission::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContestId(),
                            'externalid' => $submissionId,
                        ]);
        if (!$submission) {
            $this->addPendingEvent('submission', $submissionId, $event, $data);
            return;
        }

        $startTime = Utils::toEpochFloat($data->startTime);
        $endTime = null;
        if (isset($data->endTime)) {
            $endTime = Utils::toEpochFloat($data->endTime);
        }

        $judgementTypeId = $data->judgementTypeId;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID.
        if ($judgementTypeId !== null && !isset($verdictsFlipped[$judgementTypeId])) {
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'judgement-type', 'id' => $judgementTypeId],
                ],
            ]);
            return;
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

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

    /**
     * @param Event<EventData> $event
     */
    protected function importRun(Event $event, EventData $data): void
    {
        if (!$data instanceof RunEvent) {
            throw new InvalidArgumentException('Invalid event data type');
        }

        // Note that we do not emit events for imported runs, as we will generate our own.
        $runId = $data->id;

        if ($event->operation->value === EventLogService::ACTION_DELETE) {
            // We need to delete the run.

            $run = $this->em
                ->getRepository(ExternalRun::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $runId,
                            ]);
            if ($run) {
                $this->em->remove($run);
                $this->em->flush();
                return;
            } else {
                $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the external run.
        $run     = $this->em
            ->getRepository(ExternalRun::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $runId,
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
        $judgementId = $data->judgementId;
        $externalJudgement = $this->em
            ->getRepository(ExternalJudgement::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $judgementId,
                        ]);
        if (!$externalJudgement) {
            $this->addPendingEvent('judgement', $judgementId, $event, $data);
            return;
        }

        $time    = Utils::toEpochFloat($data->time);
        $runTime = $data->runTime ?? 0.0;

        $judgementTypeId = $data->judgementTypeId;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID.
        if (!isset($verdictsFlipped[$judgementTypeId])) {
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'judgement-type', 'id' => $judgementTypeId],
                ],
            ]);
            return;
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $rank    = $data->ordinal;
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
            $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'testcase', 'id' => $rank],
                ],
            ]);
            return;
        }

        $this->removeWarning($event->type, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

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

    protected function processPendingEvents(string $type, string|int $id): void
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

    /**
     * @param Event<EventData> $event
     */
    protected function addPendingEvent(string $type, string|int $id, Event $event, ClarificationEvent|SubmissionEvent|JudgementEvent|RunEvent $data): void
    {
        // First, check if we already have pending events for this event.
        // We do this by loading the warnings with the correct hash.
        $hash = ExternalSourceWarning::calculateHash(
            ExternalSourceWarning::TYPE_DEPENDENCY_MISSING,
            $event->type->value,
            $data->id
        );
        $warning = $this->em
            ->getRepository(ExternalSourceWarning::class)
            ->findOneBy([
                            'externalContestSource' => $this->source,
                            'hash'                  => $hash,
                        ]);

        $dependencies = [];
        if ($warning) {
            $dependencies = $warning->getContent()['dependencies'];
        }

        $event = new Event(
            id: $event->id,
            type: $event->type,
            operation: $event->operation,
            objectId: $id,
            data: [$data],
        );
        $dependencies[$type . '-' . $id] = ['type' => $type, 'id' => $id, 'event' => $event];
        $this->addOrUpdateWarning($event, $data->id, ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
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

    /**
     * @param Event<EventData>                       $event
     * @param array<string,mixed>                    $values
     * @param array<string, array{0: bool, 1: bool}> $extraDiff
     */
    private function compareOrCreateValues(
        Event $event,
        ?string $entityId,
        BaseApiEntity $entity,
        array $values,
        array $extraDiff = [],
        bool $updateEntity = true
    ): void {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $diff             = [];
        foreach ($values as $field => $value) {
            try {
                $ourValue = $propertyAccessor->getValue($entity, $field);
            } catch (UnexpectedTypeException) {
                // Subproperty that doesn't exist, it is null.
                $ourValue = null;
            } catch (UninitializedPropertyException) {
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
                $this->addOrUpdateWarning($event, $entityId, ExternalSourceWarning::TYPE_DATA_MISMATCH, [
                    'diff' => $fullDiff,
                ]);
            }
        } else {
            $this->removeWarning($event->type, $entityId, ExternalSourceWarning::TYPE_DATA_MISMATCH);
        }
    }

    /**
     * @param Event<EventData> $event
     * @param string[]         $supportedActions
     *
     * @return bool True iff supported
     */
    protected function warningIfUnsupported(Event $event, ?string $entityId, array $supportedActions): bool
    {
        if (!in_array($event->operation->value, $supportedActions)) {
            $this->addOrUpdateWarning($event, $entityId, ExternalSourceWarning::TYPE_UNSUPORTED_ACTION, [
                'action' => $event->operation->value,
            ]);
            return false;
        }

        // Clear warnings since this action is supported.
        $this->removeWarning($event->type, $event->id, ExternalSourceWarning::TYPE_UNSUPORTED_ACTION);

        return true;
    }

    /**
     * @param Event<EventData>     $event
     * @param array<string, mixed> $content
     */
    protected function addOrUpdateWarning(
        Event $event,
        ?string $entityId,
        string $type,
        array $content = []
    ): void {
        $hash    = ExternalSourceWarning::calculateHash($type, $event->type->value, $entityId);
        $warning = $this->em
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
                ->setEntityType($event->type->value)
                ->setEntityId($entityId);
            $this->em->persist($warning);
        }

        $warning
            ->setLastEventId($event->id)
            ->setLastTime(Utils::now())
            ->setContent($content);

        $this->em->flush();
    }

    protected function removeWarning(EventType $eventType, ?string $entityId, string $type): void
    {
        $hash = ExternalSourceWarning::calculateHash($type, $eventType->value, $entityId);
        $warning = $this->em
            ->getRepository(ExternalSourceWarning::class)
            ->findOneBy(['externalContestSource' => $this->source, 'hash' => $hash]);
        if ($warning) {
            $this->em->remove($warning);
            $this->em->flush();
        }
    }
}
