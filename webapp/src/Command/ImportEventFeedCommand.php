<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Clarification;
use App\Entity\Configuration;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\ExternalJudgement;
use App\Entity\ExternalRun;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\Testcase;
use App\Entity\User;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class ImportEventFeedCommand
 * @package App\Command
 */
class ImportEventFeedCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var string
     */
    protected $domjudgeVersion;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var int */
    protected $contestId;

    /** @var string */
    protected $baseurl;

    /** @var string|null */
    protected $sinceEventId = null;

    /** @var bool */
    protected $shouldStop = false;

    /** @var string|null */
    protected $lastEventId = null;

    /** @var array */
    protected $verdicts = [];

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
     * @var array
     */
    protected $pendingEvents = [
        // Initialize it with all types that can be a dependent event. Note that Language is not here, as they should exist already
        'team' => [],
        'group' => [],
        'organization' => [],
        'problem' => [],
        'clarification' => [],
        'submission' => [],
    ];

    /**
     * ImportEventFeedCommand constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     * @param EventLogService        $eventLogService
     * @param ScoreboardService      $scoreboardService
     * @param SubmissionService      $submissionService
     * @param TokenStorageInterface  $tokenStorage
     * @param LoggerInterface        $logger
     * @param bool                   $debug
     * @param string                 $domjudgeVersion
     * @param string|null            $name
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        bool $debug,
        string $domjudgeVersion,
        string $name = null
    ) {
        parent::__construct($name);
        $this->em                = $em;
        $this->dj                = $dj;
        $this->eventLogService   = $eventLogService;
        $this->scoreboardService = $scoreboardService;
        $this->submissionService = $submissionService;
        $this->tokenStorage      = $tokenStorage;
        $this->logger            = $logger;
        $this->debug             = $debug;
        $this->domjudgeVersion   = $domjudgeVersion;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('import:eventfeed')
            ->setDescription('Import contest data from an event feed following ' .
                             'the Contest API specification')
            ->setHelp(
                'Import contest data from an event feed following the Contest API specification:' . PHP_EOL .
                'https://clics.ecs.baylor.edu/index.php?title=Contest_API' . PHP_EOL . PHP_EOL .
                'Note the following assumptions and caveats:' . PHP_EOL .
                '- The contest to import into should already contain the problems,' . PHP_EOL .
                '  because the event feed does not contain the testcases.' . PHP_EOL .
                '- Problems will be updated, but not their test_data_count, time_limit or ordinal.' . PHP_EOL .
                '- Judgement types will not be imported, but only verified.' . PHP_EOL .
                '- Languages will not be imported, but only verified.' . PHP_EOL .
                '- Team members will not be imported.' . PHP_EOL .
                '- Awards will not be imported.' . PHP_EOL .
                '- State will not be imported.'
            )
            ->addArgument(
                'contest-id',
                InputArgument::REQUIRED,
                'Database ID of the contest to import into'
            )
            ->addArgument(
                'feed-url',
                InputArgument::REQUIRED,
                'URL or file location of the feed to import;' . PHP_EOL .
                'if a URL requires authentication, use' . PHP_EOL .
                'username:password@ in front of the hostname'
            )
            ->addOption(
                'basepath',
                'b',
                InputOption::VALUE_REQUIRED,
                'If `feed-url` is a local file, pass the path that will' . PHP_EOL .
                'be used as the base directory for relative URL\'s'
            )
            ->addOption(
                'since-event',
                's',
                InputOption::VALUE_REQUIRED,
                'Only process events strictly after this event'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                sprintf('Also import the event feed when the data_source%s' .
                        'config option is not set to %d',
                        PHP_EOL, DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL)
            );
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Disable SQL logging if we do not run explicitly in debug mode.
        // This would cause a serious memory leak otherwise since this is a
        // long runnning process.
        if (!$this->debug) {
            $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        }

        pcntl_signal(SIGTERM, [$this, 'stopCommand']);
        pcntl_signal(SIGINT, [$this, 'stopCommand']);

        $dataSource = (int)$this->dj->dbconfig_get('data_source', DOMJudgeService::DATA_SOURCE_LOCAL);
        $importDataSource = DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL;
        if ($dataSource !== $importDataSource) {
            if ($input->getOption('force')) {
                $this->logger->warning(
                    'data_source configuration setting is set to %d; --force given so continuing...',
                    [ $dataSource ]
                );
            } else {
                $this->logger->error(
                    'data_source configuration setting is set to %d but should be %d. Use --force to continue.',
                    [ $dataSource, $importDataSource ]
                );
                return 1;
            }
        }

        $contest = $this->em->getRepository(Contest::class)->find($input->getArgument('contest-id'));
        if (!$contest) {
            $this->logger->error(
                'Contest with ID %s not found, exiting.',
                [ $input->getArgument('contest-id') ]
            );
            return 1;
        } else {
            $this->logger->notice(
                'Starting event feed import into contest with ID %d [DOMjudge/%s]',
                [ $contest->getCid(), $this->domjudgeVersion ]
            );
        }

        // For teams and team categories we want to overwrite the ID so change the ID generator
        $metadata = $this->em->getClassMetaData(TeamCategory::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());

        $metadata = $this->em->getClassMetaData(Team::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());

        // Find an admin user as we need one to make sure we can read all events
        /** @var User $user */
        $user = $this->em->createQueryBuilder()
            ->from(User::class, 'u')
            ->select('u')
            ->join('u.user_roles', 'r')
            ->andWhere('r.dj_role = :role')
            ->setParameter(':role', 'admin')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$user) {
            $this->logger->error('No admin user found. Please create at least one');
        }
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);

        $this->contestId    = $contest->getCid();
        $this->sinceEventId = $input->getOption('since-event');

        // We need the verdicts to validate judgement-types
        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $this->verdicts = include $verdictsConfig;

        $feed = $input->getArgument('feed-url');
        if (file_exists($feed)) {
            if (!$input->getOption('basepath')) {
                $this->logger->error('For local files the --basepath option is required');
                return 1;
            }

            $this->baseurl = $input->getOption('basepath');
            if (substr($this->baseurl, -1, 1) !== '/') {
                $this->baseurl .= '/';
            }

            $this->importFromFile($feed);
        } else {
            if (preg_match('/^(.*\/)contests\/.*\/event-feed$/', $feed, $matches) === 0) {
                $this->logger->error('Cannot determine base URL. Did you pass an event-feed URL?');
                return 1;
            }

            $this->baseurl = $matches[1];

            $this->importFromUrl($feed);
        }

        if (!empty(array_filter($this->pendingEvents))) {
            $this->logger->warning('Some events could not be processed, because ' .
                                   'they still have missing dependent events:');
        }
        foreach ($this->pendingEvents as $type => $eventData) {
            foreach ($eventData as $id => $events) {
                foreach ($events as $event) {
                    $this->logger->warning(
                        'Could not process %s event %s, because it is ' .
                        'dependent on missing %s event %s',
                        [ $event['type'], $event['id'], $type, $id ]
                    );
                }
            }
        }

        return 0;
    }

    /**
     * Process a stop command from a signal handler
     */
    public function stopCommand()
    {
        $this->shouldStop = true;
    }

    /**
     * Import events from the given local file
     * @param string $path
     * @return void
     * @throws Exception
     */
    protected function importFromFile(string $path)
    {
        $this->logger->info('Importing from local file %s', [ $path ]);

        $file = fopen($path, 'r');

        // If we have a 'since event ID', ignore everything up to and including it
        $sinceEventIdFound = $this->sinceEventId === null;

        $buffer = '';
        while (!feof($file) || !empty($buffer)) {
            // Read the file until we find a newline or the end of the stream
            while (!feof($file) && ($newlinePos = strpos($buffer, "\n")) === false) {
                $buffer .= fread($file, 1024);
            }
            $newlinePos = strpos($buffer, "\n");
            if ($newlinePos === false) {
                $line   = $buffer;
                $buffer = '';
            } else {
                $line   = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);
            }
            $event = $this->dj->jsonDecode($line);

            if ($sinceEventIdFound) {
                $this->importEvent($event);
                $this->lastEventId = $event['id'];
            } elseif ($event['id'] === $this->sinceEventId) {
                $sinceEventIdFound = true;
            }

            if ($this->shouldStop) {
                return;
            }
        }

        fclose($file);
    }

    /**
     * Import events from the given URL
     * @param string $url
     * @return void
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    protected function importFromUrl(string $url)
    {
        $this->logger->info(
            'Importing from URL %s. Press ^C to quit (might take a bit to be detected).',
            [ $url ]
        );

        $client = HttpClient::create();

        while (true) {
            // Check whether we have received an exit signal
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->shouldStop) {
                $this->logger->notice('Received signal, exiting.');
                return;
            }

            $fullUrl = $url;
            if ($this->lastEventId !== null) {
                $fullUrl .= '?since_id=' . $this->lastEventId;
            } elseif ($this->sinceEventId !== null) {
                $fullUrl .= '?since_id=' . $this->sinceEventId;
            }
            $response = $client->request('GET', $fullUrl, ['buffer' => false]);
            if ($response->getStatusCode() !== 200) {
                $this->logger->warning(
                    'Received non-200 response code %d, waiting for five seconds '.
                    'and trying again. Press ^C to quit.',
                    [ $response->getStatusCode() ]
                );
                sleep(5);
                continue;
            }

            $buffer = '';

            $processBuffer = function() use (&$buffer) {
                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line   = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1);

                    if (!empty($line)) {
                        $event = $this->dj->jsonDecode($line);
                        $this->importEvent($event);

                        $this->lastEventId = $event['id'];
                    }

                    if (function_exists('pcntl_signal_dispatch')) {
                        pcntl_signal_dispatch();
                    }
                    if ($this->shouldStop) {
                        return false;
                    }
                }

                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                return !$this->shouldStop;
            };

            while (true) {
                // A timeout of 0.0 means we get chunks immediately and the user
                // can cancel at any time.
                foreach ($client->stream($response, 0.0) as $chunk) {
                    // We first need to check for timeouts, as we can not call
                    // ->isLast() or ->getContent() on them
                    if (!$chunk->isTimeout()) {
                        if ($chunk->isLast()) {
                            // Last chunk received, exit out of the inner while(true)
                            break 2;
                        } else {
                            $buffer .= $chunk->getContent();
                        }
                    }
                    if (!$processBuffer()) {
                        return;
                    }
                }
            }

            // We still need to finish everything that is still in the buffer
            if (!$processBuffer()) {
                return;
            }

            $this->logger->info(
                'End of stream reached, waiting for five seconds before '.
                'rereading stream after event %s. Press ^C to quit.',
                [ $this->lastEventId ?? 'none' ]
            );
            sleep(5);
        }
    }

    /**
     * Import the given event
     * @param array $event
     * @throws Exception
     */
    protected function importEvent(array $event)
    {
        // Check whether we have received an exit signal
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($this->shouldStop) {
            $this->logger->notice('Received signal, exiting.');
            return;
        }

        $this->logger->debug("Importing event with ID %s and type %s...",
                             [ $event['id'], $event['type'] ]);

        switch ($event['type']) {
            case 'awards':
            case 'team-members':
            case 'state':
                $this->logger->debug("Ignoring event of type %s", [ $event['type'] ]);
                break;
            case 'contests':
                $this->importContest($event);
                break;
            case 'judgement-types':
                $this->validateJudgementType($event);
                break;
            case 'languages':
                $this->validateLanguage($event);
                break;
            case 'groups':
                $this->importGroup($event);
                break;
            case 'organizations':
                $this->importOrganization($event);
                break;
            case 'problems':
                $this->importProblem($event);
                break;
            case 'teams':
                $this->importTeam($event);
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
     * Import the given contest event
     * @param array $event
     * @throws Exception
     */
    protected function importContest(array $event)
    {
        if ($event['op'] === EventLogService::ACTION_DELETE) {
            $this->logger->error(
                'Event %s contains a delete for contests, not supported',
                [ $event['id'] ]
            );
            return;
        }

        $this->logger->info('Importing contest %s event %s', [ $event['op'], $event['id'] ]);

        // First, reload the contest so we can set it's data
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->contestId);

        // We need to convert the freeze to a value from the start instead of
        // the end so perform some regex magic.
        $duration     = $event['data']['duration'];
        $freeze       = $event['data']['scoreboard_freeze_duration'];
        $reltimeRegex = '/^(-)?(\d+):(\d{2}):(\d{2})(?:\.(\d{3}))?$/';
        preg_match($reltimeRegex, $duration, $durationData);

        $durationNegative     = ($durationData[1] === '-');
        $fullDuration         = $durationNegative ? $duration : ('+' . $duration);

        if ($freeze !== null) {
            preg_match($reltimeRegex, $freeze, $freezeData);
            $freezeNegative       = ($freezeData[1] === '-');
            $freezeHourModifier   = $freezeNegative ? -1 : 1;
            $freezeInSeconds    = $freezeHourModifier * $freezeData[2] * 3600
                                  + 60 * $freezeData[3]
                                  + (double)sprintf('%d.%03d', $freezeData[4], $freezeData[5]);
            $durationHourModifier = $durationNegative ? -1 : 1;
            $durationInSeconds  = $durationHourModifier * $durationData[2] * 3600
                                  + 60 * $durationData[3]
                                  + (double)sprintf('%d.%03d', $durationData[4], $durationData[5]);
            $freezeStartSeconds = $durationInSeconds - $freezeInSeconds;
            $freezeHour         = floor($freezeStartSeconds / 3600);
            $freezeMinutes      = floor(($freezeStartSeconds % 3600) / 60);
            $freezeSeconds      = floor(($freezeStartSeconds % 60) / 60);
            $freezeMilliseconds = $freezeStartSeconds - floor($freezeStartSeconds);

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
        // This is why we will use the platform default timezone and just verify it matches
        $startTime = $event['data']['start_time'] === null ? null : new DateTime($event['data']['start_time']);
        if ($startTime !== null) {
            $timezone        = new \DateTimeZone($startTime->format('e'));
            $defaultTimezone = new \DateTimeZone(date_default_timezone_get());
            if ($timezone->getOffset($startTime) !== $defaultTimezone->getOffset($startTime)) {
                $this->logger->warning(
                    'Time zone offset (%s) of start time does not match system time zone %s',
                    [ $startTime->format('e'), date_default_timezone_get() ]
                );
            }
            // Now set the data
            $contest
                ->setStarttimeEnabled(true)
                ->setStarttimeString($startTime->format('Y-m-d H:i:s') . ' ' . date_default_timezone_get())
                ->setEndtimeString($fullDuration)
                ->setFreezetimeString($fullFreeze)
                ->updateTimes();

        } else {
            // Now set the data
            $contest
                ->setName($event['data']['name'])
                ->setStarttimeEnabled(false);
        }

        // Also update the penalty time
        /** @var Configuration $penaltyTimeConfig */
        $penaltyTimeConfig = $this->em->getRepository(Configuration::class)->findOneBy(['name' => 'penalty_time']);
        $penaltyTimeConfig->setValue((int)$event['data']['penalty_time']);

        // Save data and emit event
        $this->em->flush();
        // For contests we know we always do an update action as the contest must exist for this script to run
        $this->eventLogService->log('contest', $contest->getCid(), EventLogService::ACTION_UPDATE,
                                    $contest->getCid());
    }

    /**
     * Validate the given judgement type event
     * @param array $event
     * @throws Exception
     */
    protected function validateJudgementType(array $event)
    {
        if ($event['op'] !== EventLogService::ACTION_CREATE) {
            $this->logger->error(
                'Event %s contains a(n) %s for judgement-types, not supported',
                [ $event['id'], $event['op'] ]
            );
            return;
        }

        $this->logger->info(
            'Validating judgement-types %s event %s',
            [ $event['op'], $event['id'] ]
        );

        $verdict         = $event['data']['id'];
        $verdictsFlipped = array_flip($this->verdicts);
        if (!isset($verdictsFlipped[$verdict])) {
            // TODO: we should handle this. Kattis has JE (judge error) which we do not have but want to show
            $this->logger->error('Judgement type %s does not exist', [ $verdict ]);
        } else {
            $penalty = true;
            $solved  = false;
            if ($verdict === 'AC') {
                $penalty = false;
                $solved  = true;
            } elseif ($verdict === 'CE') {
                $penalty = (bool)$this->dj->dbconfig_get('compile_penalty', false);
            }

            if ($penalty !== $event['data']['penalty']) {
                $this->logger->error(
                    'Judgement type %s has mismatching penalty: %d (feed) vs %d (us)',
                    [ $verdict, $event['data']['penalty'], $penalty ]
                );
            }
            if ($solved !== $event['data']['solved']) {
                $this->logger->error(
                    'Judgement type %s has mismatching solved: %d (feed) vs %d (us)',
                    [ $verdict, $event['data']['solved'], $solved ]
                );
            }
        }
    }

    /**
     * Validate the given language event
     * @param array $event
     * @throws Exception
     */
    protected function validateLanguage(array $event)
    {
        if ($event['op'] !== EventLogService::ACTION_CREATE) {
            $this->logger->error(
                'Event %s contains a(n) %s for languages, not supported',
                [ $event['id'], $event['op'] ]
            );
            return;
        }

        $this->logger->info('Validating languages %s event %s', [ $event['op'], $event['id'] ]);

        $extId = $event['data']['id'];
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $extId]);
        if (!$language) {
            $this->logger->error('Cannot find language with external ID %s', [ $extId ]);
        } else {
            if (!$language->getAllowSubmit()) {
                $this->logger->error('Language with external ID %s not submittable', [ $extId ]);
            }
        }
    }

    /**
     * Import the given group event
     * @param array $event
     * @throws Exception
     */
    protected function importGroup(array $event)
    {
        $this->logger->info('Importing group %s event %s', [ $event['op'], $event['id'] ]);

        $groupId = $event['data']['id'];
        if (!is_numeric($groupId)) {
            $this->logger->error(
                'Cannot import group %s: only integer ID\'s are supported',
                [ $groupId ]
            );
        }

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the category

            $category = $this->em->getRepository(TeamCategory::class)->find($groupId);
            if ($category) {
                $this->em->remove($category);
                $this->em->flush();
                $this->eventLogService->log('groups', $category->getCategoryid(),
                                            EventLogService::ACTION_DELETE,
                                            $this->contestId, null, $category->getCategoryid());
                return;
            } else {
                $this->logger->error('Cannot delete nonexistent group %s', [ $groupId ]);
            }
            return;
        }

        // First, load the category
        /** @var TeamCategory $category */
        $category = $this->em->getRepository(TeamCategory::class)->find($groupId);
        if ($category) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $category = new TeamCategory();
            $action   = EventLogService::ACTION_CREATE;
        }

        $category
            ->setCategoryid((int)$event['data']['id'])
            ->setName($event['data']['name'])
            ->setVisible(!($event['data']['hidden'] ?? false));

        // Save data and emit event
        if ($action === EventLogService::ACTION_CREATE) {
            $this->em->persist($category);
        }
        $this->em->flush();
        $this->eventLogService->log('groups', $category->getCategoryid(), $action,
                                    $this->contestId);

        $this->processPendingEvents('group', $category->getCategoryid());
    }

    /**
     * Import the given organization event
     * @param array $event
     * @throws Exception
     */
    protected function importOrganization(array $event)
    {
        $this->logger->info('Importing organization %s event %s', [ $event['op'], $event['id'] ]);

        $organizationId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the affiliation

            $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $organizationId]);
            if ($affiliation) {
                $this->em->remove($affiliation);
                $this->em->flush();
                $this->eventLogService->log('organizations', $affiliation->getAffilid(),
                                            EventLogService::ACTION_DELETE,
                                            $this->contestId, null, $affiliation->getExternalid());
                return;
            } else {
                $this->logger->error('Cannot delete nonexistent organiation %s', [ $organizationId ]);
            }
            return;
        }

        // First, load the affiliation
        /** @var TeamAffiliation $affiliation */
        $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $organizationId]);
        if ($affiliation) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $affiliation = new TeamAffiliation();
            $affiliation->setExternalid($organizationId);
            $action = EventLogService::ACTION_CREATE;
        }

        $affiliation
            ->setName($event['data']['formal_name'] ?? $event['data']['name'])
            ->setShortname($event['data']['name']);
        if (isset($event['data']['country'])) {
            $affiliation->setCountry($event['data']['country']);
        }

        // Save data and emit event
        if ($action === EventLogService::ACTION_CREATE) {
            $this->em->persist($affiliation);
        }
        $this->em->flush();
        $this->eventLogService->log('organizations', $affiliation->getAffilid(), $action,
                                    $this->contestId);

        $this->processPendingEvents('organization', $affiliation->getExternalid());
    }

    /**
     * Import the given problem event
     * @param array $event
     * @throws Exception
     */
    protected function importProblem(array $event)
    {
        if ($event['op'] === EventLogService::ACTION_DELETE) {
            $this->logger->error(
                'Event %s contains a delete for problems, not supported',
                [ $event['id'] ]
            );
            return;
        }

        $this->logger->info('Importing problem %s event %s', [ $event['op'], $event['id'] ]);

        $problemId = $event['data']['id'];

        // First, load the problem
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->logger->error('Problem %s not found, cannot import', [ $problemId ]);
            return;
        }

        // Now find the contest problem
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
            'contest' => $this->contestId,
            'problem' => $problem,
        ]);
        if ($contestProblem) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $contestProblem = new ContestProblem();
            $contestProblem
                ->setProblem($problem)
                ->setContest($this->em->getRepository(Contest::class)->find($this->contestId));
            $action = EventLogService::ACTION_CREATE;
            $problem->addContestProblem($contestProblem);
        }

        $problem->setName($event['data']['name']);

        $contestProblem
            ->setShortname($event['data']['label'])
            ->setColor($event['data']['rgb'] ?? null);

        // Save data and emit event
        if ($action === EventLogService::ACTION_CREATE) {
            $this->em->persist($contestProblem);
        }
        $this->em->flush();
        $this->eventLogService->log('problems', $problem->getProbid(), $action, $this->contestId);

        $this->processPendingEvents('problem', $problem->getExternalid());
    }

    /**
     * Import the given team event
     * @param array $event
     * @throws Exception
     */
    protected function importTeam(array $event)
    {
        $this->logger->info('Importing team %s event %s', [ $event['op'], $event['id'] ]);

        $teamId = $event['data']['id'];
        $icpcId = $event['data']['icpc_id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the team

            $team = $this->em->getRepository(Team::class)->findOneBy(['teamid' => $teamId]);
            if ($team) {
                $this->em->remove($team);
                $this->em->flush();
                $this->eventLogService->log('teams', $team->getTeamid(),
                                            EventLogService::ACTION_DELETE,
                                            $this->contestId, null, $team->getExternalid());
                return;
            } else {
                $this->logger->error('Cannot delete nonexistent team %s', [ $teamId ]);
            }
            return;
        }

        // First, load the team
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->findOneBy(['teamid' => $teamId]);
        if ($team) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $team = new Team();
            $team
                ->setTeamid($teamId)
                ->setExternalid($icpcId);
            $action = EventLogService::ACTION_CREATE;
        }

        // Now check if we have all dependent data

        $groupIds = $event['data']['group_ids'] ?? [];
        $category = null;
        if (count($groupIds) > 1) {
            $this->logger->warning('Team belongs to more than one group; only using the first one');
        }
        if (count($groupIds) >= 1) {
            $groupId = reset($groupIds);
            if (!is_numeric($groupId)) {
                $this->logger->error(
                    'Cannot import group %s: only integer ID\'s are supported',
                    [ $groupId ]
                );
            } else {
                /** @var TeamCategory $category */
                $category = $this->em->getRepository(TeamCategory::class)->find($groupId);
                if (!$category) {
                    $this->addPendingEvent('group', $groupId, $event);
                    return;
                }
            }
        }

        $organizationId = $event['data']['organization_id'] ?? null;
        $affiliation    = null;
        if ($organizationId !== null) {
            /** @var TeamAffiliation $affiliation */
            $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $organizationId]);
            if (!$affiliation) {
                $this->addPendingEvent('organization', $organizationId, $event);
                return;
            }

        }
        $team
            ->setCategory($category)
            ->setAffiliation($affiliation)
            ->setName($event['data']['name']);

        // Also check if this is a private contest. If so, we need to add the team to the contest
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->contestId);
        if (!$contest->isOpenToAllTeams()) {
            if (!$contest->getTeams()->contains($team)) {
                $contest->addTeam($team);
            }
        }

        // Save data and emit event
        if ($action === EventLogService::ACTION_CREATE) {
            $this->em->persist($team);
        }
        $this->em->flush();
        $this->eventLogService->log('teams', $team->getTeamid(), $action, $this->contestId);

        $this->processPendingEvents('team', $team->getExternalid());
    }

    /**
     * Import the given clarification event
     * @param array $event
     * @throws Exception
     */
    protected function importClarification(array $event)
    {
        $this->logger->info('Importing clarification %s event %s', [ $event['op'], $event['id'] ]);

        $clarificationId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the team

            $clarification = $this->em->getRepository(Clarification::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $clarificationId]);
            if ($clarification) {
                $this->em->remove($clarification);
                $this->em->flush();
                $this->eventLogService->log('clarifications', $clarification->getClarid(),
                                            EventLogService::ACTION_DELETE,
                                            $this->contestId, null,
                                            $clarification->getExternalid());
                return;
            } else {
                $this->logger->error('Cannot delete nonexistent clarification %s', [ $clarificationId ]);
            }
            return;
        }

        // First, load the clarification
        /** @var Clarification $clarification */
        $clarification = $this->em->getRepository(Clarification::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $clarificationId]);
        if ($clarification) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $clarification = new Clarification();
            $clarification->setExternalid($clarificationId);
            $action = EventLogService::ACTION_CREATE;
        }

        // Now check if we have all dependent data

        $fromTeamId = $event['data']['from_team_id'] ?? null;
        $fromTeam   = null;
        if ($fromTeamId !== null) {
            /** @var Team $fromTeam */
            $fromTeam = $this->em->getRepository(Team::class)->findOneBy(['teamid' => $fromTeamId]);
            if (!$fromTeam) {
                $this->addPendingEvent('team', $fromTeamId, $event);
                return;
            }
        }

        $toTeamId = $event['data']['to_team_id'] ?? null;
        $toTeam   = null;
        if ($toTeamId !== null) {
            /** @var Team $toTeam */
            $toTeam = $this->em->getRepository(Team::class)->findOneBy(['teamid' => $toTeamId]);
            if (!$toTeam) {
                $this->addPendingEvent('team', $toTeamId, $event);
                return;
            }
        }

        $inReplyToId = $event['data']['reply_to_id'] ?? null;
        $inReplyTo   = null;
        if ($inReplyToId !== null) {
            /** @var Clarification $inReplyTo */
            $inReplyTo = $this->em->getRepository(Clarification::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $inReplyToId]);
            if (!$inReplyTo) {
                $this->addPendingEvent('clarification', $inReplyToId, $event);
                return;
            }
        }

        $problemId = $event['data']['problem_id'] ?? null;
        $problem   = null;
        if ($problemId !== null) {
            /** @var Problem $problem */
            $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
            if (!$problem) {
                $this->addPendingEvent('problem', $problemId, $event);
                return;
            }
        }

        $contest = $this->em->getRepository(Contest::class)->find($this->contestId);

        $submitTime = Utils::to_epoch_float($event['data']['time']);

        $clarification
            ->setInReplyTo($inReplyTo)
            ->setSender($fromTeam)
            ->setRecipient($toTeam)
            ->setProblem($problem)
            ->setContest($contest)
            ->setBody($event['data']['text'])
            ->setSubmittime($submitTime);

        if ($inReplyTo) {
            // Mark both the original message as well as the reply as answered
            $inReplyTo->setAnswered(true);
            $clarification->setAnswered(true);
        } elseif ($fromTeam === null) {
            // Clarifications from jury are automatically answered
            $clarification->setAnswered(true);
        }
        // Note: when a team sends a clarification and the jury never responds, but does click
        // 'set answered', it will not be marked as answered during import. These clarifications
        // need to be handled manually.

        // Save data and emit event
        if ($action === EventLogService::ACTION_CREATE) {
            $this->em->persist($clarification);
        }
        $this->em->flush();
        $this->eventLogService->log('clarifications', $clarification->getClarid(), $action,
                                    $this->contestId);

        $this->processPendingEvents('clarification', $clarification->getExternalid());
    }

    /**
     * Import the given submission event
     * @param array $event
     * @throws Exception
     */
    protected function importSubmission(array $event)
    {
        $this->logger->info('Importing submission %s event %s', [ $event['op'], $event['id'] ]);

        $submissionId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to mark the submission as not valid and then emit a delete event

            $submission = $this->em->getRepository(Submission::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $submissionId]);
            if ($submission) {
                $submission->setValid(false);
                $this->em->flush();
                $this->eventLogService->log('submissions', $submission->getSubmitid(),
                                            EventLogService::ACTION_DELETE,
                                            $this->contestId);

                $contest = $this->em->getRepository(Contest::class)->find($submission->getCid());
                $team    = $this->em->getRepository(Team::class)->find($submission->getTeamid());
                $problem = $this->em->getRepository(Problem::class)->find($submission->getProbid());
                $this->scoreboardService->calculateScoreRow($contest, $team, $problem);
                return;
            } else {
                $this->logger->error('Cannot delete nonexistent submission %s', [ $submissionId ]);
            }
            return;
        }

        // First, load the submission
        /** @var Submission $submission */
        $submission = $this->em->getRepository(Submission::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $submissionId]);

        $languageId = $event['data']['language_id'];
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $languageId]);
        if (!$language) {
            $this->logger->error(
                'Cannot import submission %s because language %s is missing',
                [ $event['data']['id'], $languageId ]
            );
            return;
        }

        $problemId = $event['data']['problem_id'];
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->addPendingEvent('problem', $problemId, $event);
            return;
        }

        // Find the contest problem
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
            'contest' => $this->contestId,
            'problem' => $problem,
        ]);

        if (!$contestProblem) {
            $this->logger->error(
                'Cannot import submission %s because problem %s is not part of contest',
                [ $event['data']['id'], $problem->getExternalid() ]
            );
            return;
        }

        $teamId = $event['data']['team_id'];
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->findOneBy(['teamid' => $teamId]);
        if (!$team) {
            $this->addPendingEvent('team', $teamId, $event);
            return;
        }

        $submitTime = Utils::to_epoch_float($event['data']['time']);

        $entryPoint = $event['data']['entry_point'] ?? null;
        if (empty($entryPoint)) {
            $entryPoint = null;
        }

        // If the submission is found, we can only update the valid status.
        // If any of the other fields are different, this is an error
        if ($submission) {
            $matches = true;
            if ($submission->getTeam()->getExternalid() !== $team->getExternalid()) {
                $this->logger->error(
                    'Got new event for submission %s with different team ID (%s instead of %s)',
                    [
                        $submission->getExternalid(),
                        $team->getExternalid(),
                        $submission->getTeam()->getExternalid()
                    ]
                );
                $matches = false;
            }
            if ($submission->getProblem()->getExternalid() !== $problem->getExternalid()) {
                $this->logger->error(
                    'Got new event for submission %s with different problem ID (%s instead of %s)',
                    [
                        $submission->getExternalid(),
                        $problem->getExternalid(),
                        $submission->getProblem()->getExternalid()
                    ]
                );
                $matches = false;
            }
            if ($submission->getLanguage()->getExternalid() !== $language->getExternalid()) {
                $this->logger->error(
                    'Got new event for submission %s with different language ID (%s instead of %s)',
                    [
                        $submission->getExternalid(),
                        $language->getExternalid(),
                        $submission->getLanguage()->getExternalid()
                    ]
                );
                $matches = false;
            }
            if (abs(Utils::difftime((float)$submission->getSubmittime(), $submitTime)) >= 1) {
                $this->logger->error(
                    'Got new event for submission %s with different submit time (%s instead of %s)',
                    [
                        $submission->getExternalid(),
                        $event['data']['time'],
                        $submission->getAbsoluteSubmitTime()
                    ]
                );
                $matches = false;
            }
            if ($entryPoint !== $submission->getEntryPoint()) {
                if ($submission->getEntryPoint() === null) {
                    // Special case: if we did not have an entrypoint yet, but we do get one now, update it
                    $submission->setEntryPoint($entryPoint);
                    $this->em->flush();
                    $this->logger->info(
                        'Updated entrypoint for submission %s to %s',
                        [ $submission->getExternalid(), $entryPoint ]
                    );
                    $this->eventLogService->log('submissions', $submission->getSubmitid(),
                                                EventLogService::ACTION_UPDATE, $this->contestId);
                    $this->processPendingEvents('submission', $submission->getExternalid());
                    return;
                } elseif ($entryPoint === null) {
                    // Special case number two: if we get a null entry point but we have one already,
                    // ignore this and do not log any error
                    $this->logger->debug(
                        'Received null entrypoint for submission %s, but we already have %s',
                        [ $submission->getExternalid(), $submission->getEntryPoint() ]
                    );
                } else {
                    $this->logger->error(
                        'Got new event for submission %s with different entrypoint (%s instead of %s)',
                        [ $submission->getExternalid(), $entryPoint, $submission->getEntryPoint() ]
                    );
                    $matches = false;
                }
            }
            if (!$matches) {
                return;
            }

            // If the submission was not valid before, mark it valid now and recalculate the scoreboard
            if (!$submission->getValid()) {
                $submission->setValid(true);

                $this->em->flush();
                $this->eventLogService->log('submissions', $submission->getSubmitid(),
                                            EventLogService::ACTION_CREATE,
                                            $this->contestId);

                $contest = $this->em->getRepository(Contest::class)->find($submission->getCid());
                $team    = $this->em->getRepository(Team::class)->find($submission->getTeamid());
                $problem = $this->em->getRepository(Problem::class)->find($submission->getProbid());
                $this->scoreboardService->calculateScoreRow($contest, $team, $problem);
            }
        } else {
            // First, check if we actually have the source for this submission in the data
            if (empty($event['data']['files'][0]['href'])) {
                $this->logger->error(
                    'Submission %s does not have source files in event',
                    [ $submissionId ]
                );
                return;
            } elseif (($event['data']['files'][0]['mime'] ?? null) !== 'application/zip') {
                $this->logger->error(
                    'Submission %s has non-ZIP source files in event',
                    [ $submissionId ]
                );
                return;
            } else {
                $zipUrl = $event['data']['files'][0]['href'];
                if (preg_match('/^https?:\/\//', $zipUrl) === 0) {
                    // Relative URL. First check if it starts with a /. If so, remove it as our baseurl already contains it
                    if (strpos($zipUrl, '/') === 0) {
                        $zipUrl = substr($zipUrl, 1);
                    }

                    // Now prepend the base URL
                    $zipUrl = $this->baseurl . $zipUrl;
                }

                $tmpdir = $this->dj->getDomjudgeTmpDir();

                // Check if we have a local file
                if (file_exists($zipUrl)) {
                    // Yes, use it directly
                    $zipFile      = $zipUrl;
                    $shouldUnlink = false;
                } else {
                    // No, download the ZIP file
                    $shouldUnlink = true;
                    if (!($zipFile = tempnam($tmpdir, "submission_zip_"))) {
                        $this->logger->error(
                            'Cannot create temporary file to download ZIP for submission %s',
                            [ $submissionId ]
                        );
                        return;
                    }

                    $client = new Client();
                    try {
                        $response = $client->get($zipUrl, ['sink' => $zipFile]);
                        if ($response->getStatusCode() !== 200) {
                            // TODO: retry a couple of times
                            $this->logger->error(
                                'Cannot download ZIP for submission %s',
                                [ $submissionId ]
                            );
                            unlink($zipFile);
                            return;
                        }
                    } catch (RequestException $e) {
                        $this->logger->error(
                            'Cannot download ZIP for submission %s: %s',
                            [ $submissionId, $e->getMessage() ]
                        );
                        unlink($zipFile);
                        return;
                    }
                }

                // Open the ZIP file
                $zip = new \ZipArchive();
                $zip->open($zipFile);

                // Determine the files to submit
                /** @var UploadedFile[] $filesToSubmit */
                $filesToSubmit = [];
                for ($zipFileIdx = 0; $zipFileIdx < $zip->numFiles; $zipFileIdx++) {
                    $filename = $zip->getNameIndex($zipFileIdx);
                    $content  = $zip->getFromName($filename);

                    if (!($tmpSubmissionFile = tempnam($tmpdir, "submission_source_"))) {
                        $this->logger->error(
                            'Cannot create temporary file to extract ZIP contents for submission %s and file %s',
                            [ $submissionId, $filename ]
                        );
                        $zip->close();
                        if ($shouldUnlink) {
                            unlink($zipFile);
                        }
                        return;
                    }
                    file_put_contents($tmpSubmissionFile, $content);
                    $filesToSubmit[] = new UploadedFile(
                        $tmpSubmissionFile, $filename,
                        null, null, null, true
                    );
                }

                // If the language requires an entry point but we do not have one, use automatic entry point detection
                if ($language->getRequireEntryPoint() && $entryPoint === null) {
                    $entryPoint = '__auto__';
                }

                // Submit the solution
                $contest    = $this->em->getRepository(Contest::class)->find($this->contestId);
                $submission = $this->submissionService->submitSolution(
                    $team, $contestProblem, $contest, $language, $filesToSubmit,
                    null, $entryPoint, $submissionId, $submitTime
                );

                // Clean up the ZIP
                $zip->close();
                if ($shouldUnlink) {
                    unlink($zipFile);
                }

                // Clean up the temporary submission files
                foreach ($filesToSubmit as $file) {
                    unlink($file->getRealPath());
                }
            }
        }

        $this->processPendingEvents('submission', $submission->getExternalid());
    }

    /**
     * Import the given judgement event
     * @param array $event
     * @throws Exception
     */
    protected function importJudgement(array $event)
    {
        // Note that we do not emit events for imported judgements, as we will generate our own
        $this->logger->info('Importing judgement %s event %s', [ $event['op'], $event['id'] ]);

        $judgementId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the judgement

            $judgement = $this->em->getRepository(ExternalJudgement::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $judgementId]);
            if ($judgement) {
                $this->em->remove($judgement);
                $this->em->flush();
                return;
            } else {
                $this->logger->error('Cannot delete nonexistent judgement %s', [ $judgementId ]);
            }
            return;
        }

        // First, load the external judgement
        /** @var ExternalJudgement $judgement */
        $judgement = $this->em->getRepository(ExternalJudgement::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $judgementId]);
        $persist   = false;
        if (!$judgement) {
            $judgement = new ExternalJudgement();
            $judgement
                ->setExternalid($judgementId)
                ->setContest($this->em->getRepository(Contest::class)->find($this->contestId));
            $persist = true;
        }

        // Now check if we have all dependent data

        $submissionId = $event['data']['submission_id'] ?? null;
        /** @var Submission $submission */
        $submission = $this->em->getRepository(Submission::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $submissionId]);
        if (!$submission) {
            $this->addPendingEvent('submission', $submissionId, $event);
            return;
        }


        $startTime = Utils::to_epoch_float($event['data']['start_time']);
        $endTime   = null;
        if (isset($event['data']['end_time'])) {
            $endTime = Utils::to_epoch_float($event['data']['end_time']);
        }

        $judgementTypeId = $event['data']['judgement_type_id'] ?? null;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID
        if ($judgementTypeId !== null && !isset($verdictsFlipped[$judgementTypeId])) {
            $this->logger->error(
                'Cannot import judgement %s, because judgement type %s does not exist',
                [ $event['data']['id'], $judgementTypeId ]
            );
        }

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
            ->setParameter(':submission', $submission)
            ->orderBy('ej.starttime', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($externalJudgements as $idx => $externalJudgement) {
            $externalJudgement->setValid($idx === 0);
        }

        $this->em->flush();

        // Now we need to update the scoreboard cache for this cell to get this judgement result in
        $this->em->clear();
        $contest = $this->em->getRepository(Contest::class)->find($submission->getCid());
        $team    = $this->em->getRepository(Team::class)->find($submission->getTeamid());
        $problem = $this->em->getRepository(Problem::class)->find($submission->getProbid());
        $this->scoreboardService->calculateScoreRow($contest, $team, $problem);

        $this->processPendingEvents('judgement', $judgement->getExternalid());
    }

    /**
     * Import the given run event
     * @param array $event
     * @throws Exception
     */
    protected function importRun(array $event)
    {
        // Note that we do not emit events for imported runs, as we will generate our own
        $this->logger->info('Importing run %s event %s', [ $event['op'], $event['id'] ]);

        $runId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the run

            $run = $this->em->getRepository(ExternalRun::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $runId]);
            if ($run) {
                $this->em->remove($run);
                $this->em->flush();
                return;
            } else {
                $this->logger->error('Cannot delete nonexistent run %s', [ $runId ]);
            }
            return;
        }

        // First, load the external run
        /** @var ExternalRun $run */
        $run     = $this->em->getRepository(ExternalRun::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $runId]);
        $persist = false;
        if (!$run) {
            $run = new ExternalRun();
            $run
                ->setExternalid($runId)
                ->setContest($this->em->getRepository(Contest::class)->find($this->contestId));
            $persist = true;
        }

        // Now check if we have all dependent data

        $judgementId = $event['data']['judgement_id'] ?? null;
        /** @var ExternalJudgement $externalJudgement */
        $externalJudgement = $this->em->getRepository(ExternalJudgement::class)->findOneBy(['contest' => $this->contestId, 'externalid' => $judgementId]);
        if (!$externalJudgement) {
            $this->addPendingEvent('judgement', $judgementId, $event);
            return;
        }


        $time    = Utils::to_epoch_float($event['data']['time']);
        $runTime = $event['data']['run_time'] ?? null;

        $judgementTypeId = $event['data']['judgement_type_id'] ?? null;
        $verdictsFlipped = array_flip($this->verdicts);
        // Set the result based on the judgement type ID
        if (!isset($verdictsFlipped[$judgementTypeId])) {
            $this->logger->error(
                'Cannot import run %s, because judgement type %s does not exist',
                [ $event['data']['id'], $judgementTypeId ]
            );
        }

        $rank    = $event['data']['ordinal'];
        $problem = $externalJudgement->getSubmission()->getContestProblem();

        // Find the testcase belonging to this this run
        /** @var Testcase|null $testcase */
        $testcase = $this->em->createQueryBuilder()
            ->from(Testcase::class, 't')
            ->select('t')
            ->andWhere('t.problem = :problem')
            ->andWhere('t.rank = :rank')
            ->setParameter(':problem', $problem)
            ->setParameter(':rank', $rank)
            ->getQuery()
            ->getSingleResult();

        if ($testcase === null) {
            $this->logger->error(
                'Cannot import run %s, because the testcase with rank %s does not exist for problem %s',
                [ $event['data']['id'], $rank, $problem->getShortname() ]
            );
        }

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

    /**
     * Process all pending events for the given type and (external) ID
     * @param string $type
     * @param mixed  $id
     * @throws Exception
     */
    protected function processPendingEvents(string $type, $id)
    {
        // Process pending events
        if (isset($this->pendingEvents[$type][$id])) {
            // Get all pending events
            $pending = $this->pendingEvents[$type][$id];
            // Mark them as non-pending. Note that they might depend on more events,
            // but then they'll be readded automatically in the correct place
            unset($this->pendingEvents[$type][$id]);
            foreach ($pending as $event) {
                $this->logger->debug(
                    'Processing pending event with ID %s and type %s...',
                    [ $event['id'], $event['type'] ]
                );
                $this->importEvent($event);
            }
        }
    }

    /**
     * Add a pending event for the given type and (external) ID
     * @param string $type
     * @param mixed  $id
     * @param array  $event
     */
    protected function addPendingEvent(string $type, $id, array $event)
    {
        $this->logger->warning(
            'Cannot currently import %s event %s, because it is dependent on %s %s',
            [ $event['type'], $event['id'], $type, $id ]
        );
        if (!isset($this->pendingEvents[$type][$id])) {
            $this->pendingEvents[$type][$id] = [];
        }

        $this->pendingEvents[$type][$id][] = $event;
    }
}
