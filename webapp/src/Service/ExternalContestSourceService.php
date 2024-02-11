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
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Utils\EventFeedFormat;
use App\Utils\Utils;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use JsonException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    protected ?ExternalContestSource $source = null;

    protected bool $contestLoaded = false;
    /**
     * @var array{scoreboard_type: string, external_id: string, end_time: string, duration: string, name: string,
     *            scoreboard_freeze_duration: string, id: string, allow_submit: bool, allow_submit: bool,
     *            penalty_time: int, start_time?: string|null, formal_name?: string|null, shortname?: string|null,
     *            scoreboard_thaw_time?: string, warning_message?: string|null} $cachedContestData
     */
    protected ?array $cachedContestData = null;
    /**
     * @var array{version: string, version_url: string, name: string,
     *             provider?: array{name?: string, build_date?: string, version?: string},
     *             domjudge?: array{apiversion: int, version: string, environment: string, doc_url: string}} $cachedApiInfoData
     */
    protected ?array $cachedApiInfoData = null;
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
     * This array is three dimensional:
     * - The first dimension is the type of the dependent event type
     * - The second dimension is the (external) ID of the dependent event
     * - The third dimension contains an array of all events that should be processed
     *
     * @var array<string, array<string, array{token?: string, id: string, type: string, time: string, op?: string,
     *                                        end_of_updates?: bool,
     *                                        data?: array{run_time?: float, time?: string, contest_time?: string,
     *                                                     ordinal?: int, id: string, judgement_id?: string,
     *                                                     judgement_type_id: string|null, max_run_time?: float|null,
     *                                                     start_time: string, start_contest_time?: string, end_time?: string|null,
     *                                                     end_contest_time?: string|null, submission_id: string,
     *                                                     output_compile_as_string: null, language_id?: string, externalid?: null,
     *                                                     team_id: string, problem_id?: string, entry_point?: string|null,
     *                                                     old_result?: null, files?: array{href: string}}|mixed[]}>> $pendingEvents
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

        return $this->cachedContestData['id'];
    }

    public function getContestName(): string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedContestData['name'];
    }

    public function getContestStartTime(): ?float
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }
        if (isset($this->cachedContestData['start_time'])) {
            return Utils::toEpochFloat($this->cachedContestData['start_time']);
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

        return $this->cachedContestData['duration'];
    }

    public function getApiVersion(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData['version'] ?? null;
    }

    public function getApiVersionUrl(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData['version_url'] ?? null;
    }

    public function getApiProviderName(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData['provider']['name'] ?? $this->cachedApiInfoData['name'] ?? null;
    }

    public function getApiProviderVersion(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData['provider']['version'] ?? null;
    }

    public function getApiProviderBuildDate(): ?string
    {
        if (!$this->isValidContestSource()) {
            throw new LogicException('The contest source is not valid');
        }

        return $this->cachedApiInfoData['provider']['build_date'] ?? null;
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
                        $event = $this->dj->jsonDecode($line);
                        $this->importEvent($event, $eventsToSkip);

                        if ($this->getEventFeedFormat($event) === EventFeedFormat::Format_2022_07) {
                            $eventId = $event['token'] ?? null;
                        } else {
                            $eventId = $event['id'];
                        }
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

    /**
     * @param string[] $eventsToSkip
     */
    protected function importFromContestArchive(array $eventsToSkip, ?callable $progressReporter = null): bool
    {
        $file = fopen($this->source->getSource() . '/event-feed.ndjson', 'r');

        $skipEventsUpTo = $this->getLastReadEventId();

        $this->readEventsFromFile($file,
            function (
                array $event,
                string $line,
                &$shouldStop
            ) use (
                $eventsToSkip,
                &$skipEventsUpTo,
                $progressReporter
            ) {
                $lastEventId          = $this->getLastReadEventId();
                $readingToLastEventId = false;

                if ($this->getEventFeedFormat($event) === EventFeedFormat::Format_2022_07) {
                    $eventId = $event['token'] ?? null;
                } else {
                    $eventId = $event['id'];
                }

                if ($skipEventsUpTo === null) {
                    $this->importEvent($event, $eventsToSkip);
                    $lastEventId = $eventId;
                } elseif ($eventId === $skipEventsUpTo) {
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

            $event      = $this->dj->jsonDecode($line);
            $shouldStop = false;
            $callback($event, $line, $shouldStop);
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
                            if (is_string($this->source->getPassword() ?? null)) {
                                $auth[] = $this->source->getPassword();
                            }
                            $clientOptions['auth_basic'] = $auth;
                        } else {
                            $clientOptions['auth_basic'] = null;
                        }
                        $this->httpClient = $this->httpClient->withOptions($clientOptions);
                        $contestResponse = $this->httpClient->request('GET', $this->source->getSource());
                        $this->cachedContestData = $contestResponse->toArray();

                        $apiInfoResponse = $this->httpClient->request('GET', '');
                        $this->cachedApiInfoData = $apiInfoResponse->toArray();
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
                        $this->cachedContestData = $this->dj->jsonDecode(file_get_contents($contestFile));
                    } catch (JsonException $e) {
                        $this->loadingError = $e->getMessage();
                    }

                    if (is_file($apiInfoFile)) {
                        try {
                            $this->cachedApiInfoData = $this->dj->jsonDecode(file_get_contents($apiInfoFile));
                        } catch (JsonException $e) {
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
     * @param array{token?: string, id: string, type: string, time: string, op?: string, end_of_updates?: bool,
     *              data?: array{run_time?: float, time?: string, contest_time?: string, ordinal?: int,
     *                           id: string, judgement_id?: string, judgement_type_id: string|null,
     *                           max_run_time?: float|null, start_time: string, start_contest_time?: string,
     *                           end_time?: string|null, end_contest_time?: string|null, submission_id: string,
     *                           output_compile_as_string: null, language_id?: string, externalid?: string|null,
     *                           team_id: string, problem_id?: string, entry_point?: string|null, old_result?: null,
     *                           files?: array{href: string}}|mixed[]
     *             } $event
     * @param string[] $eventsToSkip
     * @throws DBALException
     * @throws NonUniqueResultException
     * @throws TransportExceptionInterface
     */
    public function importEvent(array $event, array $eventsToSkip): void
    {
        // Check whether we have received an exit signal.
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($this->shouldStopReading()) {
            return;
        }

        if ($this->getEventFeedFormat($event) === EventFeedFormat::Format_2022_07) {
            $eventId = $event['token'] ?? null;
            if (!isset($event['data'])) {
                $operation = EventLogService::ACTION_DELETE;
                $data      = [['id' => $event['id']]];
            } else {
                $operation = EventLogService::ACTION_CREATE;
                $data      = $event['data'];
                if (!isset($data[0])) {
                    $data = [$data];
                }
            }
        } else {
            $eventId   = $event['id'];
            $operation = $event['op'];
            $data      = [$event['data']];
        }
        $entityType = $event['type'];
        if ($entityType === 'contest') {
            $entityType = 'contests';
        }

        if ($eventId !== null && in_array($eventId, $eventsToSkip)) {
            $this->logger->info("Skipping event with ID %s and type %s as requested",
                                [$eventId, $event['type']]);
            return;
        }

        if ($eventId !== null) {
            $this->logger->debug("Importing event with ID %s and type %s...",
                                 [$eventId, $event['type']]);
        } else {
            $this->logger->debug("Importing event with type %s...",
                                 [$event['type']]);
        }

        foreach ($data as $dataItem) {
            switch ($entityType) {
                case 'awards':
                case 'team-members':
                case 'accounts':
                case 'state':
                    $this->logger->debug("Ignoring event of type %s", [$entityType]);
                    if (isset($event['end_of_updates'])) {
                        $this->logger->info('End of updates encountered');
                    }
                    break;
                case 'contests':
                    $this->validateAndUpdateContest($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'judgement-types':
                    $this->importJudgementType($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'languages':
                    $this->validateLanguage($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'groups':
                    $this->validateAndUpdateGroup($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'organizations':
                    $this->validateAndUpdateOrganization($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'problems':
                    $this->validateAndUpdateProblem($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'teams':
                    $this->validateAndUpdateTeam($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'clarifications':
                    $this->importClarification($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'submissions':
                    $this->importSubmission($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'judgements':
                    $this->importJudgement($entityType, $eventId, $operation, $dataItem);
                    break;
                case 'runs':
                    $this->importRun($entityType, $eventId, $operation, $dataItem);
                    break;
            }
        }
    }

    /**
     * @param array{id: string, name: string, duration: string, scoreboard_type: string, penalty_time: int,
     *              formal_name?: string, start_time?: string|null, countdown_pause_time?: int|null,
     *              scoreboard_freeze_duration: string|null, scoreboard_thaw_time?: string|null,
     *              banner: array{0: array{href: string, filename: string, mime: string, width: int, height: int}}} $data
     * @throws NonUniqueResultException
     */
    protected function validateAndUpdateContest(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        if (!$this->warningIfUnsupported(
            $operation,
            $eventId,
            $entityType,
            $data['id'],
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
        $duration     = $data['duration'];
        $freeze       = $data['scoreboard_freeze_duration'];
        $reltimeRegex = '/^(-)?(\d+):(\d{2}):(\d{2})(?:\.(\d{3}))?$/';
        preg_match($reltimeRegex, $duration, $durationData);

        $durationNegative = ($durationData[1] === '-');
        $fullDuration     = $durationNegative ? $duration : ('+' . $duration);

        if ($freeze !== null) {
            preg_match($reltimeRegex, $freeze, $freezeData);
            $freezeNegative       = ($freezeData[1] === '-');
            $freezeHourModifier   = $freezeNegative ? -1 : 1;
            $freezeInSeconds      = $freezeHourModifier * (int)$freezeData[2] * 3600
                                    + 60 * (int)$freezeData[3]
                                    + (double)sprintf('%d.%03d', $freezeData[4], $freezeData[5]);
            $durationHourModifier = $durationNegative ? -1 : 1;
            $durationInSeconds    = $durationHourModifier * (int)$durationData[2] * 3600
                                    + 60 * (int)$durationData[3]
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
        $startTime = isset($data['start_time']) ? new DateTime($data['start_time']) : null;
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

        $toCheck['name'] = $data['name'];

        // Also compare the penalty time
        $penaltyTime = (int)$data['penalty_time'];
        if ($this->config->get('penalty_time') != $penaltyTime) {
            $this->logger->warning(
                'Penalty time does not match between feed (%d) and local (%d)',
                [$penaltyTime, $this->config->get('penalty_time')]
            );
        }

        $this->compareOrCreateValues($eventId, $entityType, $data['id'], $contest, $toCheck);

        $this->em->flush();
        $this->eventLog->log('contests', $contest->getCid(), EventLogService::ACTION_UPDATE, $this->getSourceContestId());
    }

    /**
     * @param array{id: string, name: string, penalty: bool, solved: bool} $data description
     */
    protected function importJudgementType(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        if (!$this->warningIfUnsupported($operation, $eventId, $entityType, $data['id'], [EventLogService::ACTION_CREATE])) {
            return;
        }

        $verdict         = $data['id'];
        $verdictsFlipped = array_flip($this->verdicts);
        if (!isset($verdictsFlipped[$verdict])) {
            // Verdict not found, import it as a custom verdict; assume it has a penalty.
            $customVerdicts = $this->config->get('external_judgement_types');
            $customVerdicts[$verdict] = str_replace(' ', '-', $data['name']);
            $this->config->saveChanges(['external_judgement_types' => $customVerdicts], $this->eventLog, $this->dj);
            $this->verdicts = $this->dj->getVerdicts(mergeExternal: true);
            $penalty = true;
            $solved = false;
            $this->logger->warning('Judgement type %s not found locally, importing as external verdict', [$verdict]);
        } else {
            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
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

        if ($penalty !== $data['penalty']) {
            $extraDiff['penalty'] = [$penalty, $data['penalty']];
        }
        if ($solved !== $data['solved']) {
            $extraDiff['solved'] = [$solved, $data['solved']];
        }

        // Entity doesn't matter, since we do not compare anything besides the extra data
        $this->compareOrCreateValues($eventId, $entityType, $data['id'], $this->source->getContest(), [], $extraDiff, false);
    }

    /**
     * @param array{id: string, name: string, entry_point_required: true, entry_point_name?: string|null,
     *              extensions: string[],
     *              compiler?: array{command: string, args?: string, version?: string, version_command?: string},
     *              runner?: array{command: string, args?: string, version?: string, version_command?: string}} $data
     */
    protected function validateLanguage(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        if (!$this->warningIfUnsupported($operation, $eventId, $entityType, $data['id'], [EventLogService::ACTION_CREATE])) {
            return;
        }

        $extId = $data['id'];
        $language = $this->em
            ->getRepository(Language::class)
            ->findOneBy(['externalid' => $extId]);
        if (!$language) {
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
        } elseif (!$language->getAllowSubmit()) {
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DATA_MISMATCH, [
                'diff' => [
                    'allow_submit' => [
                        'us'       => false,
                        'external' => true,
                    ]
                ]
            ]);
        } else {
            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DATA_MISMATCH);
        }
    }

    /**
     * @param array{id: string, name: string, icpc_id?: string|null, type?: string|null, location?: string|null,
     *              hidden?: bool, sortorder?: int|null, color?: string|null} $data
     */
    protected function validateAndUpdateGroup(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        $groupId = $data['id'];

        /** @var TeamCategory|null $category */
        $category = $this->em
            ->getRepository(TeamCategory::class)
            ->findOneBy(['externalid' => $groupId]);

        if ($operation === EventLogService::ACTION_DELETE) {
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
                [$data['name']]
            );
            $category = new TeamCategory();
            $this->em->persist($category);
            $action = EventLogService::ACTION_CREATE;
        }

        $toCheck = [
            'externalid' => $data['id'],
            'name'       => $data['name'],
            'visible'    => !($data['hidden'] ?? false),
            'icpcid'     => $data['icpc_id'] ?? null,
        ];

        // Add DOMjudge specific fields that might be useful to import
        if (isset($data['sortorder'])) {
            $toCheck['sortorder'] = $data['sortorder'];
        }
        if (isset($data['color'])) {
            $toCheck['color'] = $data['color'];
        }

        $this->compareOrCreateValues($eventId, $entityType, $data['id'], $category, $toCheck);

        $this->em->flush();
        $this->eventLog->log('groups', $category->getCategoryid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('group', $category->getExternalid());
    }

    /**
     * @param array{id: string, name: string, icpc_id?: string|null, formal_name?: string|null, country?: string,
     *              country_flag?: array{0: array<string, string>}, url?: string, twitter_hashtag?: string,
     *              twitter_account?: string, location?: array{0: array<string, string>}, logo?: array{0: array<string, string>}} $data
     */
    protected function validateAndUpdateOrganization(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        $organizationId = $data['id'];

        /** @var TeamAffiliation|null $affiliation */
        $affiliation = $this->em
            ->getRepository(TeamAffiliation::class)
            ->findOneBy(['externalid' => $organizationId]);

        if ($operation === EventLogService::ACTION_DELETE) {
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
                [$data['formal_name'] ?? $data['name']]
            );
            $affiliation = new TeamAffiliation();
            $this->em->persist($affiliation);
            $action = EventLogService::ACTION_CREATE;
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheck = [
            'externalid' => $data['id'],
            'name'       => $data['formal_name'] ?? $data['name'],
            'shortname'  => $data['name'],
            'icpcid'     => $data['icpc_id'] ?? null,
        ];
        if (isset($data['country'])) {
            $toCheck['country'] = $data['country'];
        }

        $this->compareOrCreateValues($eventId, $entityType, $data['id'], $affiliation, $toCheck);

        $this->em->flush();
        $this->eventLog->log('organizations', $affiliation->getAffilid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('organization', $affiliation->getExternalid());
    }

    /**
     * @param array{id: string, name: string, time_limit: int, label?: string|null, rgb?: string|null} $data
     */
    protected function validateAndUpdateProblem(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        if (!$this->warningIfUnsupported($operation, $eventId, $entityType, $data['id'], [EventLogService::ACTION_CREATE, EventLogService::ACTION_UPDATE])) {
            return;
        }

        $problemId = $data['id'];

        // First, load the problem.
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
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
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheckProblem = [
            'name'      => $data['name'],
            'timelimit' => $data['time_limit'],
        ];

        if ($contestProblem->getShortname() !== $data['label']) {
            $this->logger->warning(
                'Contest problem short name does not match between feed (%s) and local (%s), updating',
                [$data['label'], $contestProblem->getShortname()]
            );
            $contestProblem->setShortname($data['label']);
        }
        if ($contestProblem->getColor() !== ($data['rgb'] ?? null)) {
            $this->logger->warning(
                'Contest problem color does not match between feed (%s) and local (%s), updating',
                [$data['rgb'] ?? null, $contestProblem->getColor()]
            );
            $contestProblem->setColor($data['rgb'] ?? null);
        }

        $this->compareOrCreateValues($eventId, $entityType, $data['id'], $problem, $toCheckProblem);

        $this->em->flush();
        $this->eventLog->log('problems', $problem->getProbid(), EventLogService::ACTION_UPDATE, $this->getSourceContestId());

        $this->processPendingEvents('problem', $problem->getProbid());
    }

    /**
     * @param array{id: string, name: string, formal_name?: string|null, icpc_id?: string|null, country?: string|null,
     *              organization_id?: string|null, group_ids?: string[], display_name?: string|null, country?: string|null} $data
     */
    protected function validateAndUpdateTeam(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        $teamId = $data['id'];

        /** @var Team|null $team */
        $team = $this->em
            ->getRepository(Team::class)
            ->findOneBy(['externalid' => $teamId]);

        if ($operation === EventLogService::ACTION_DELETE) {
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
                [$data['formal_name'] ?? $data['name']]
            );
            $team = new Team();
            $this->em->persist($team);
            $action = EventLogService::ACTION_CREATE;
        }

        if (!empty($data['organization_id'])) {
            $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $data['organization_id']]);
            if (!$affiliation) {
                $affiliation = new TeamAffiliation();
                $this->em->persist($affiliation);
            }
            $team->setAffiliation($affiliation);
        }

        if (!empty($data['group_ids'][0])) {
            $category = $this->em->getRepository(TeamCategory::class)->findOneBy(['externalid' => $data['group_ids'][0]]);
            if (!$category) {
                $category = new TeamCategory();
                $this->em->persist($category);
            }
            $team->setCategory($category);
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);

        $toCheck = [
            'externalid'             => $data['id'],
            'name'                   => $data['formal_name'] ?? $data['name'],
            'display_name'           => $data['display_name'] ?? null,
            'affiliation.externalid' => $data['organization_id'] ?? null,
            'category.externalid'    => $data['group_ids'][0] ?? null,
            'icpcid'                 => $data['icpc_id'] ?? null,
        ];
        if (isset($data['country'])) {
            $toCheck['country'] = $data['country'];
        }

        $this->compareOrCreateValues($eventId, $entityType, $data['id'], $team, $toCheck);

        $this->em->flush();
        $this->eventLog->log('teams', $team->getTeamid(), $action, $this->getSourceContestId());

        $this->processPendingEvents('team', $team->getTeamid());
    }

    /**
     * @param array{id: string, text: string, time: string, contest_time: string, from_team_id?: string|null,
     *              to_team_id?: string|null, reply_to_id: string|null, problem_id?: string|null} $data
     * @throws NonUniqueResultException
     */
    protected function importClarification(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        $clarificationId = $data['id'];

        if ($operation === EventLogService::ACTION_DELETE) {
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
                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }

            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the clarification
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
        $fromTeamId = $data['from_team_id'] ?? null;
        $fromTeam   = null;
        if ($fromTeamId !== null) {
            $fromTeam = $this->em
                ->getRepository(Team::class)
                ->findOneBy(['externalid' => $fromTeamId]);
            if (!$fromTeam) {
                $this->addPendingEvent('team', $fromTeamId, $operation, $entityType, $eventId, $data);
                return;
            }
        }

        $toTeamId = $data['to_team_id'] ?? null;
        $toTeam   = null;
        if ($toTeamId !== null) {
            $toTeam = $this->em
                ->getRepository(Team::class)
                ->findOneBy(['externalid' => $toTeamId]);
            if (!$toTeam) {
                $this->addPendingEvent('team', $toTeamId, $operation, $entityType, $eventId, $data);
                return;
            }
        }

        $inReplyToId = $data['reply_to_id'] ?? null;
        $inReplyTo   = null;
        if ($inReplyToId !== null) {
            $inReplyTo = $this->em
                ->getRepository(Clarification::class)
                ->findOneBy([
                                'contest'    => $this->getSourceContest(),
                                'externalid' => $inReplyToId
                            ]);
            if (!$inReplyTo) {
                $this->addPendingEvent('clarification', $inReplyToId, $operation, $entityType, $eventId, $data);
                return;
            }
        }

        $problemId = $data['problem_id'] ?? null;
        $problem   = null;
        if ($problemId !== null) {
            $problem = $this->em
                ->getRepository(Problem::class)
                ->findOneBy(['externalid' => $problemId]);
            if (!$problem) {
                $this->addPendingEvent('problem', $problemId, $operation, $entityType, $eventId, $data);
                return;
            }
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $contest = $this->em
            ->getRepository(Contest::class)
            ->find($this->getSourceContestId());

        $submitTime = Utils::toEpochFloat($data['time']);

        $clarification
            ->setInReplyTo($inReplyTo)
            ->setSender($fromTeam)
            ->setRecipient($toTeam)
            ->setProblem($problem)
            ->setContest($contest)
            ->setBody($data['text'])
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
     * @param array{id: string, language_id: string, problem_id: string, team_id: string,
     *              time: string, contest_time: string, entry_point?: string|null,
     *              files: array<array{href: string, filename: string, mime?: string}>,
     *              reaction?: array<array<string, string>>} $data
     * @throws TransportExceptionInterface
     * @throws DBALException
     * @throws NonUniqueResultException
     */
    protected function importSubmission(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        $submissionId = $data['id'];

        if ($operation === EventLogService::ACTION_DELETE) {
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
                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the submission
        $submission = $this->em
            ->getRepository(Submission::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $submissionId
                        ]);

        $languageId = $data['language_id'];
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $languageId]);
        if (!$language) {
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'language', 'id' => $languageId],
                ],
            ]);
            return;
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $problemId = $data['problem_id'];
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->addPendingEvent('problem', $problemId, $operation, $entityType, $eventId, $data);
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
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'contest-problem', 'id' => $problem->getExternalid()],
                ],
            ]);
            return;
        }

        $teamId = $data['team_id'];
        $team = $this->em->getRepository(Team::class)->findOneBy(['externalid' => $teamId]);
        if (!$team) {
            $this->addPendingEvent('team', $teamId, $operation, $entityType, $eventId, $data);
            return;
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $submitTime = Utils::toEpochFloat($data['time']);

        $entryPoint = $data['entry_point'] ?? null;
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
                    'external' => $data['time']
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
                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DATA_MISMATCH, ['diff' => $diff]);
                return;
            }

            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DATA_MISMATCH);

            // If the submission was not valid before, mark it valid now and recalculate the scoreboard.
            if (!$submission->getValid()) {
                $this->markSubmissionAsValidAndRecalcScore($submission, true);
            }
        } else {
            // First, check if we actually have the source for this submission in the data.
            if (empty($data['files'][0]['href'])) {
                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                    'message' => 'No source files in event',
                ]);
                $submissionDownloadSucceeded = false;
            } elseif (($data['files'][0]['mime'] ?? null) !== 'application/zip') {
                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                    'message' => 'Non-ZIP source files in event',
                ]);
                $submissionDownloadSucceeded = false;
            } else {
                $zipUrl = $data['files'][0]['href'];
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
                        $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
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
                                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                                    'message' => 'Cannot download ZIP from ' . $zipUrl,
                                ]);
                                $submissionDownloadSucceeded = false;
                            }
                        } catch (TransportExceptionInterface $e) {
                            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
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

                if ($submissionDownloadSucceeded) {
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
                            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
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
                $contest    = $this->em->getRepository(Contest::class)->find($this->getSourceContestId());
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
                    $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR, [
                        'message' => 'Cannot add submission: ' . $message,
                    ]);
                    // Clean up the temporary submission files.
                    foreach ($filesToSubmit as $file) {
                        unlink($file->getRealPath());
                    }
                    if (isset($zip)) {
                        $zip->close();
                    }
                    if ($shouldUnlink) {
                        unlink($zipFile);
                    }
                    return;
                }

                // Clean up the ZIP.
                if (isset($zip)) {
                    $zip->close();
                }
                if ($shouldUnlink) {
                    unlink($zipFile);
                }

                // Clean up the temporary submission files.
                foreach ($filesToSubmit as $file) {
                    unlink($file->getRealPath());
                }
            }
        }

        if ($submissionDownloadSucceeded) {
            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_SUBMISSION_ERROR);
        }

        $this->processPendingEvents('submission', $submission->getExternalid());
    }

    /**
     * @param array{start_time: string, start_contest_time: string, id: string, submission_id: string,
     *              max_run_time?: int|null, end_time?: string|null, output_compile_as_string?: string|null, judgement_type_id?: string|null} $data
     * @throws DBALException
     */
    protected function importJudgement(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        // Note that we do not emit events for imported judgements, as we will generate our own.
        $judgementId = $data['id'];

        if ($operation === EventLogService::ACTION_DELETE) {
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
                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the external judgement.
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
        $submissionId = $data['submission_id'];
        $submission = $this->em
            ->getRepository(Submission::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContestId(),
                            'externalid' => $submissionId
                        ]);
        if (!$submission) {
            $this->addPendingEvent('submission', $submissionId, $operation, $entityType, $eventId, $data);
            return;
        }

        $startTime = Utils::toEpochFloat($data['start_time']);
        $endTime = null;
        if (isset($data['end_time'])) {
            $endTime = Utils::toEpochFloat($data['end_time']);
        }

        $judgementTypeId = $data['judgement_type_id'] ?? null;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID.
        if ($judgementTypeId !== null && !isset($verdictsFlipped[$judgementTypeId])) {
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'judgement-type', 'id' => $judgementTypeId],
                ],
            ]);
            return;
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

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
     * @param array{id: string, judgement_id: string, ordinal: int, judgement_type_id?: string|null,
     *              time?: string|null, contest_time?: string|null, run_time?: int|null} $data
     */
    protected function importRun(string $entityType, ?string $eventId, string $operation, array $data): void
    {
        // Note that we do not emit events for imported runs, as we will generate our own.
        $runId = $data['id'];

        if ($operation === EventLogService::ACTION_DELETE) {
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
                $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            }
            $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND);
            return;
        }

        // First, load the external run.
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
        $judgementId = $data['judgement_id'];
        $externalJudgement = $this->em
            ->getRepository(ExternalJudgement::class)
            ->findOneBy([
                            'contest'    => $this->getSourceContest(),
                            'externalid' => $judgementId
                        ]);
        if (!$externalJudgement) {
            $this->addPendingEvent('judgement', $judgementId, $operation, $entityType, $eventId, $data);
            return;
        }

        $time    = Utils::toEpochFloat($data['time']);
        $runTime = $data['run_time'] ?? 0.0;

        $judgementTypeId = $data['judgement_type_id'] ?? null;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID.
        if (!isset($verdictsFlipped[$judgementTypeId])) {
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'judgement-type', 'id' => $judgementTypeId],
                ],
            ]);
            return;
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

        $rank    = $data['ordinal'];
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
            $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
                'dependencies' => [
                    ['type' => 'testcase', 'id' => $rank],
                ],
            ]);
            return;
        }

        $this->removeWarning($entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING);

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
     * @param array{run_time?: float, time?: string, contest_time?: string, ordinal?: int,
     *              id: string, judgement_id?: string, judgement_type_id?: string|null,
     *              max_run_time?: float|null, start_time?: string, start_contest_time?: string,
     *              end_time?: string|null, end_contest_time?: string|null, submission_id?: string,
     *              output_compile_as_string?: string|null, language_id?: string, externalid?: string|null,
     *              team_id?: string, problem_id?: string, entry_point?: string|null, old_result?: null,
     *              files?: array<array{href: string, filename: string, mime?: string}>} $data
     */
    protected function addPendingEvent(string $type, string|int $id, string $operation, string $entityType, ?string $eventId, array $data): void
    {
        // First, check if we already have pending events for this event.
        // We do this by loading the warnings with the correct hash.
        $hash = ExternalSourceWarning::calculateHash(
            ExternalSourceWarning::TYPE_DEPENDENCY_MISSING,
            $entityType,
            $data['id']
        );
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

        $event = [
            'op'    => $operation,
            'type'  => $entityType,
            'id'    => $eventId,
            'data'  => $data,
        ];
        $dependencies[$type . '-' . $id] = ['type' => $type, 'id' => $id, 'event' => $event];
        $this->addOrUpdateWarning($eventId, $entityType, $data['id'], ExternalSourceWarning::TYPE_DEPENDENCY_MISSING, [
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
     * @param array{'affiliation.externalid'?: string|null, 'category.externalid'?: string|null, color?: string|null,
     *              country?: string|null, display_name?: string|null, end_time?: string, externalid?: string,
     *              freeze_time?: string|null, icpc_id?: string|null, label?: string, name?: string, rgb?: string|null,
     *              shortname?: string, sortorder?: int, start_time_enabled?: bool, start_time_string?: string,
     *              timelimit?: int, visible?: bool} $values
     * @param array<string, array{0: bool, 1: bool}> $extraDiff
     */
    private function compareOrCreateValues(
        ?string       $eventId,
        string        $entityType,
        ?string       $entityId,
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
                $this->addOrUpdateWarning($eventId, $entityType, $entityId, ExternalSourceWarning::TYPE_DATA_MISMATCH, [
                    'diff' => $fullDiff
                ]);
            }
        } else {
            $this->removeWarning($entityType, $entityId, ExternalSourceWarning::TYPE_DATA_MISMATCH);
        }
    }

    /**
     * @param string[] $supportedActions
     * @return bool True iff supported
     */
    protected function warningIfUnsupported(string $operation, ?string $eventId, string $entityType, ?string $entityId, array $supportedActions): bool
    {
        if (!in_array($operation, $supportedActions)) {
            $this->addOrUpdateWarning($eventId, $entityType, $entityId, ExternalSourceWarning::TYPE_UNSUPORTED_ACTION, [
                'action' => $operation
            ]);
            return false;
        }

        // Clear warnings since this action is supported.
        $this->removeWarning($entityType, $entityId, ExternalSourceWarning::TYPE_UNSUPORTED_ACTION);

        return true;
    }

    /**
     * @param array<string, mixed> $content
     */
    protected function addOrUpdateWarning(
        ?string $eventId,
        string $entityType,
        ?string $entityId,
        string $type,
        array  $content = []
    ): void {
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

    protected function removeWarning(string $entityType, ?string $entityId, string $type): void
    {
        $hash       = ExternalSourceWarning::calculateHash($type, $entityType, $entityId);
        $warning    = $this->em
            ->getRepository(ExternalSourceWarning::class)
            ->findOneBy(['externalContestSource' => $this->source, 'hash' => $hash]);
        if ($warning) {
            $this->em->remove($warning);
            $this->em->flush();
        }
    }

    /**
     * @param array{token?: string, id: string, type: string, time: string, op?: string, end_of_updates?: bool,
     *              data?: array{run_time?: float, time?: string, contest_time?: string, ordinal?: int,
     *                           id: string, judgement_id?: string, judgement_type_id: string|null,
     *                           max_run_time?: float|null, start_time: string, start_contest_time?: string,
     *                           end_time?: string|null, end_contest_time?: string|null, submission_id?: string,
     *                           output_compile_as_string: null, language_id?: string, externalid?: string|null,
     *                           team_id: string, problem_id?: string, entry_point?: string|null, old_result?: null,
     *                           files?: array{href: string}}|mixed[]
     *             } $event
     */
    protected function getEventFeedFormat(array $event): EventFeedFormat
    {
        return match ($this->getApiVersion()) {
            '2020-03', '2021-11' => EventFeedFormat::Format_2020_03,
            '2022-07', '2023-06' => EventFeedFormat::Format_2022_07,
            default => isset($event['op']) ? EventFeedFormat::Format_2020_03 : EventFeedFormat::Format_2022_07,
        };
    }
}
