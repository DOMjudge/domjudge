<?php declare(strict_types=1);

namespace DOMJudgeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use DOMJudgeBundle\Entity\Clarification;
use DOMJudgeBundle\Entity\Configuration;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TeamAffiliation;
use DOMJudgeBundle\Entity\TeamCategory;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\ScoreboardService;
use DOMJudgeBundle\Service\SubmissionService;
use DOMJudgeBundle\Utils\Utils;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Class ImportEventFeedCommand
 * @package DOMJudgeBundle\Command
 */
class ImportEventFeedCommand extends ContainerAwareCommand
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
     * This array will hold all events that are waiting on a dependant event because it has an ID that does not exist
     * yet. According to the official spec this can not happen, but in practice it does happen. We handle this by
     * storing these events here and checking whether there are any after saving any dependant event.
     *
     * This array is three dimensional:
     * - The first dimension is the type of the dependant event type
     * - The second dimension is the (external) ID of the dependant event
     * - The third dimension contains an array of all events that should be processed
     * @var array
     */
    protected $pendingEvents = [
        // Initialize it with all types that can be a dependant event. Note that Language is not here, as they should exist already
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
     * @param string|null            $name
     */
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService,
        TokenStorageInterface $tokenStorage,
        string $name = null
    ) {
        parent::__construct($name);
        $this->em                = $em;
        $this->dj                = $dj;
        $this->eventLogService   = $eventLogService;
        $this->scoreboardService = $scoreboardService;
        $this->submissionService = $submissionService;
        $this->tokenStorage      = $tokenStorage;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('import:eventfeed')
            ->setDescription(
                'Import contest data from an event feed following the Contest API specification (https://clics.ecs.baylor.edu/index.php?title=Contest_API)' . PHP_EOL . PHP_EOL .
                'The following assumptions and caveats are of note:' . PHP_EOL .
                '- The contest that will be imported to should already contain the problems,' . PHP_EOL .
                '  because the event feed does not contain the testcases' . PHP_EOL .
                '- Problems will be updated, but not their test_data_count, time_limit or ordinal' . PHP_EOL .
                '- Judgement types will not be imported, but only verified' . PHP_EOL .
                '- Languages will not be imported, but only verified' . PHP_EOL .
                '- Team members will not be imported' . PHP_EOL .
                '- Judgements will not be imported' . PHP_EOL .
                '- Runs will not be imported, but their verdict will be stored on the submission' . PHP_EOL .
                '- Awards will not be imported' . PHP_EOL .
                '- State will not be imported'
            )
            ->addArgument(
                'contest-id',
                InputArgument::REQUIRED,
                'Database ID of the contest to import into'
            )
            ->addArgument(
                'feed-url',
                InputArgument::REQUIRED,
                'URL or file location of the feed to import.' . PHP_EOL .
                'If an URL and it requires authentication, use username:password@ in the URL'
            )
            ->addOption(
                'basepath',
                'b',
                InputOption::VALUE_REQUIRED,
                'If `feed-url` is a local file, pass the path that will be used as the base directory for relative URL\'s'
            )
            ->addOption(
                'since-event',
                's',
                InputOption::VALUE_REQUIRED,
                'If given, only process events strictly after this event'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                sprintf('If given, also import the event feed when the data_source config option is not set to %d',
                        DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL)
            );
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set up logger
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ];
        $this->logger      = new ConsoleLogger($output, $verbosityLevelMap);

        pcntl_signal(SIGTERM, [$this, 'stopCommand']);
        pcntl_signal(SIGINT, [$this, 'stopCommand']);

        $dataSource = (int)$this->dj->dbconfig_get('data_source');
        if ($dataSource !== DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL) {
            if ($input->getOption('force')) {
                $this->logger->warning(sprintf('data_source configuration setting is set to %d; --force given so continuing...',
                                               $dataSource));
            } else {
                $this->logger->error(sprintf('data_source configuration setting is set to %d but should be %d. Use --force to continue.',
                                             $dataSource,
                                             DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL));
                return 1;
            }
        }

        $contest = $this->em->getRepository(Contest::class)->find($input->getArgument('contest-id'));
        if (!$contest) {
            $this->logger->error(sprintf('Contest with ID %s not found, exiting.', $input->getArgument('contest-id')));
            return 1;
        } else {
            $this->logger->notice(sprintf('Starting event feed import into contest with ID %d [DOMjudge/%s]',
                                          $contest->getCid(),
                                          $this->getContainer()->getParameter('domjudge.version')));
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
            ->from('DOMJudgeBundle:User', 'u')
            ->select('u')
            ->join('u.roles', 'r')
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
                $this->logger->error('Can not determine base URL. Did you pass an event-feed URL?');
                return 1;
            }

            $this->baseurl = $matches[1];

            $this->importFromUrl($feed);
        }

        if (!empty(array_filter($this->pendingEvents))) {
            $this->logger->warning(sprintf('Some events could not be processed, because they still have missing dependant events:'));
        }
        foreach ($this->pendingEvents as $type => $eventData) {
            foreach ($eventData as $id => $events) {
                foreach ($events as $event) {
                    $this->logger->warning(sprintf('Could not process %s event %s, because it is dependant on missing %s event %s',
                                                   $event['type'], $event['id'], $type, $id));
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
     * @throws \Exception
     */
    protected function importFromFile(string $path)
    {
        $this->logger->info(sprintf('Importing from local file %s', $path));

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
     * @throws \Exception
     */
    protected function importFromUrl(string $url)
    {
        $this->logger->info(sprintf('Importing from URL %s', $url));

        $client = new Client(['stream' => true]);

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
            $response = $client->get($fullUrl);
            if ($response->getStatusCode() !== 200) {
                $this->logger->warning(sprintf('Received non-200 response code %d, waiting for five seconds and trying again',
                                               $response->getStatusCode()));
                sleep(5);
            }

            $body   = $response->getBody();
            $buffer = '';
            while (!$body->eof() || !empty($buffer)) {
                // Read the stream until we find a newline or the end of the stream
                while (!$body->eof() && ($newlinePos = strpos($buffer, "\n")) === false) {
                    // Read 1 byte at a time to make sure we are also getting the last few events when the server
                    // keeps open the connection
                    $buffer .= $body->read(1);
                }
                $newlinePos = strpos($buffer, "\n");
                if ($newlinePos === false) {
                    $line   = $buffer;
                    $buffer = '';
                } else {
                    $line   = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1);
                }
                if (!empty($line)) {
                    $event = $this->dj->jsonDecode($line);
                    $this->importEvent($event);

                    $this->lastEventId = $event['id'];
                }

                if ($this->shouldStop) {
                    return;
                }
            }

            $this->logger->info(sprintf('End of stream reached, waiting for five seconds before rereading stream after event %s...',
                                        $this->lastEventId));
            sleep(5);
        }
    }

    /**
     * Import the given event
     * @param array $event
     * @throws \Exception
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

        $this->logger->debug(sprintf("Importing event with ID %s and type %s...", $event['id'], $event['type']));

        switch ($event['type']) {
            case 'runs':
            case 'awards':
            case 'team-members':
            case 'state':
                $this->logger->debug(sprintf("Ignoring event of type %s", $event['type']));
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
        }
    }

    /**
     * Import the given contest event
     * @param array $event
     * @throws \Exception
     */
    protected function importContest(array $event)
    {
        if ($event['op'] === EventLogService::ACTION_DELETE) {
            $this->logger->error(sprintf('Event %s contains a delete for contests, not supported', $event['id']));
            return;
        }

        $this->logger->info(sprintf('Importing contest %s event %s', $event['op'], $event['id']));

        // First, reload the contest so we can set it's data
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($this->contestId);

        // We need to convert the freeze to a value from the start instead of the end so perform some regex magic
        $duration     = $event['data']['duration'];
        $freeze       = $event['data']['scoreboard_freeze_duration'];
        $reltimeRegex = '/^(-)?(\d+):(\d{2}):(\d{2})(?:\.(\d{3}))?$/';
        preg_match($reltimeRegex, $duration, $durationData);
        preg_match($reltimeRegex, $freeze, $freezeData);

        $durationNegative     = ($durationData[1] === '-');
        $freezeNegative       = ($freezeData[1] === '-');
        $durationHourModifier = $durationNegative ? -1 : 1;
        $freezeHourModifier   = $freezeNegative ? -1 : 1;
        $fullDuration         = $durationNegative ? $duration : ('+' . $duration);

        $durationInSeconds = $durationHourModifier * $durationData[2] * 3600
            + 60 * $durationData[3]
            + (double)sprintf('%d.%d', $durationData[4], $durationData[5]);
        $freezeInSeconds = $freezeHourModifier * $freezeData[2] * 3600
            + 60 * $freezeData[3]
            + (double)sprintf('%d.%d', $freezeData[4], $freezeData[5]);
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

        // The timezones are given in ISO 8601 and we only support names.
        // This is why we will use the platform default timezone and just verify it matches
        $startTime       = $event['data']['start_time'] === null ? null : new \DateTime($event['data']['start_time']);
        if ($startTime !== null) {
            $timezone = new \DateTimeZone($startTime->format('e'));
            $defaultTimezone = new \DateTimeZone(date_default_timezone_get());
            if ($timezone->getOffset($startTime) !== $defaultTimezone->getOffset($startTime)) {
                $this->logger->warning(sprintf('Time zone offset (%s) of start time does not match system time zone %s',
                    $startTime->format('e'), date_default_timezone_get()));
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
        $this->eventLogService->log('contest', $contest->getCid(), EventLogService::ACTION_UPDATE, $contest->getCid());
    }

    /**
     * Validate the given judgement type event
     * @param array $event
     * @throws \Exception
     */
    protected function validateJudgementType(array $event)
    {
        if ($event['op'] !== EventLogService::ACTION_CREATE) {
            $this->logger->error(sprintf('Event %s contains a %s for judgement-types, not supported', $event['id'],
                                         $event['op']));
            return;
        }

        $this->logger->info(sprintf('Validating judgement-types %s event %s', $event['op'], $event['id']));

        $verdict         = $event['data']['id'];
        $verdictsFlipped = array_flip($this->verdicts);
        if (!isset($verdictsFlipped[$verdict])) {
            $this->logger->error(sprintf('Judgement type %s does not exist in DOMjudge', $verdict));
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
                $this->logger->error(sprintf('Judgement type %s has mismatching penalty: %d (feed) vs %d (us)',
                                             $verdict,
                                             $event['data']['penalty'], $penalty));
            }
            if ($solved !== $event['data']['solved']) {
                $this->logger->error(sprintf('Judgement type %s has mismatching solved: %d (feed) vs %d (us)', $verdict,
                                             $event['data']['solved'], $solved));
            }
        }
    }

    /**
     * Validate the given language event
     * @param array $event
     * @throws \Exception
     */
    protected function validateLanguage(array $event)
    {
        if ($event['op'] !== EventLogService::ACTION_CREATE) {
            $this->logger->error(sprintf('Event %s contains a %s for languages, not supported', $event['id'],
                                         $event['op']));
            return;
        }

        $this->logger->info(sprintf('Validating languages %s event %s', $event['op'], $event['id']));

        $extId = $event['data']['id'];
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $extId]);
        if (!$language) {
            $this->logger->error(sprintf('Can not find language with external ID %s in DOMjudge', $extId));
        } else {
            if (!$language->getAllowSubmit()) {
                $this->logger->error(sprintf('Language with external ID %s not submittable in DOMjudge', $extId));
            }
        }
    }

    /**
     * Import the given group event
     * @param array $event
     * @throws \Exception
     */
    protected function importGroup(array $event)
    {
        $this->logger->info(sprintf('Importing group %s event %s', $event['op'], $event['id']));

        $groupId = $event['data']['id'];
        if (!is_numeric($groupId)) {
            $this->logger->error(sprintf('Can not import group %s, as currently only integer ID\'s are supported',
                                         $groupId));
        }

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the category

            $category = $this->em->getRepository(TeamCategory::class)->find($groupId);
            if ($category) {
                $this->em->remove($category);
                $this->em->flush();
                $this->eventLogService->log('groups', $category->getCategoryid(), EventLogService::ACTION_DELETE,
                                            $this->contestId, null, $category->getCategoryid());
                return;
            } else {
                $this->logger->error(sprintf('Can not delete group %s, because it does not exist in DOMjudge',
                                             $groupId));
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
        $this->eventLogService->log('groups', $category->getCategoryid(), $action, $this->contestId);

        $this->processPendingEvents('group', $category->getCategoryid());
    }

    /**
     * Import the given organization event
     * @param array $event
     * @throws \Exception
     */
    protected function importOrganization(array $event)
    {
        $this->logger->info(sprintf('Importing organization %s event %s', $event['op'], $event['id']));

        $organizationId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the affiliation

            $affiliation = $this->em->getRepository(TeamAffiliation::class)->findOneBy(['externalid' => $organizationId]);
            if ($affiliation) {
                $this->em->remove($affiliation);
                $this->em->flush();
                $this->eventLogService->log('organizations', $affiliation->getAffilid(), EventLogService::ACTION_DELETE,
                                            $this->contestId, null, $affiliation->getExternalid());
                return;
            } else {
                $this->logger->error(sprintf('Can not delete organiation %s, because it does not exist in DOMjudge',
                                             $organizationId));
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
        $this->eventLogService->log('organizations', $affiliation->getAffilid(), $action, $this->contestId);

        $this->processPendingEvents('organization', $affiliation->getExternalid());
    }

    /**
     * Import the given problem event
     * @param array $event
     * @throws \Exception
     */
    protected function importProblem(array $event)
    {
        if ($event['op'] === EventLogService::ACTION_DELETE) {
            $this->logger->error(sprintf('Event %s contains a delete for problems, not supported', $event['id']));
            return;
        }

        $this->logger->info(sprintf('Importing problem %s event %s', $event['op'], $event['id']));

        $problemId = $event['data']['id'];

        // First, load the problem
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemId]);
        if (!$problem) {
            $this->logger->error(sprintf('Problem %s not found in DOMjudge. Can not import', $problemId));
            return;
        }

        // Now find the contest problem
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
                                                                                               'cid' => $this->contestId,
                                                                                               'probid' => $problem->getProbid()
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
            ->setColor($event['data']['rgb']);

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
     * @throws \Exception
     */
    protected function importTeam(array $event)
    {
        $this->logger->info(sprintf('Importing team %s event %s', $event['op'], $event['id']));

        $teamId = $event['data']['id'];
        $icpcId = $event['data']['icpc_id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the team

            $team = $this->em->getRepository(Team::class)->findOneBy(['teamid' => $teamId]);
            if ($team) {
                $this->em->remove($team);
                $this->em->flush();
                $this->eventLogService->log('teams', $team->getTeamid(), EventLogService::ACTION_DELETE,
                                            $this->contestId, null, $team->getExternalid());
                return;
            } else {
                $this->logger->error(sprintf('Can not delete team %s, because it does not exist in DOMjudge',
                                             $teamId));
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

        // Now check if we have all dependant data

        $groupIds = $event['data']['group_ids'] ?? [];
        $category = null;
        if (count($groupIds) > 1) {
            $this->logger->warning('Team belongs to more than one group; only using the first one');
        }
        if (count($groupIds) >= 1) {
            $groupId = reset($groupIds);
            if (!is_numeric($groupId)) {
                $this->logger->error(sprintf('Can not import group %s, as currently only integer ID\'s are supported',
                                             $groupId));
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
        if (!$contest->getPublic()) {
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
     * @throws \Exception
     */
    protected function importClarification(array $event)
    {
        $this->logger->info(sprintf('Importing clarification %s event %s', $event['op'], $event['id']));

        $clarificationId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to delete the team

            $clarification = $this->em->getRepository(Clarification::class)->findOneBy(['externalid' => $clarificationId]);
            if ($clarification) {
                $this->em->remove($clarification);
                $this->em->flush();
                $this->eventLogService->log('clarifications', $clarification->getClarid(),
                                            EventLogService::ACTION_DELETE,
                                            $this->contestId, null, $clarification->getExternalid());
                return;
            } else {
                $this->logger->error(sprintf('Can not delete clarification %s, because it does not exist in DOMjudge',
                                             $clarificationId));
            }
            return;
        }

        // First, load the clarification
        /** @var Clarification $clarification */
        $clarification = $this->em->getRepository(Clarification::class)->findOneBy(['externalid' => $clarificationId]);
        if ($clarification) {
            $action = EventLogService::ACTION_UPDATE;
        } else {
            $clarification = new Clarification();
            $clarification->setExternalid($clarificationId);
            $action = EventLogService::ACTION_CREATE;
        }

        // Now check if we have all dependant data

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
            $inReplyTo = $this->em->getRepository(Clarification::class)->findOneBy(['externalid' => $inReplyToId]);
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

        $time       = new \DateTime($event['data']['time']);
        $submitTime = sprintf('%d.%d', $time->getTimestamp(), $time->format('u'));

        $clarification
            ->setInReplyTo($inReplyTo)
            ->setSender($fromTeam)
            ->setRecipient($toTeam)
            ->setProblem($problem)
            ->setContest($contest)
            ->setBody($event['data']['text'])
            ->setSubmittime($submitTime);

        if ($inReplyTo) {
            $inReplyTo->setAnswered(true);
        }

        // Save data and emit event
        if ($action === EventLogService::ACTION_CREATE) {
            $this->em->persist($clarification);
        }
        $this->em->flush();
        $this->eventLogService->log('clarifications', $clarification->getClarid(), $action, $this->contestId);

        $this->processPendingEvents('clarification', $clarification->getExternalid());
    }

    /**
     * Import the given submission event
     * @param array $event
     * @throws \Exception
     */
    protected function importSubmission(array $event)
    {
        $this->logger->info(sprintf('Importing submission %s event %s', $event['op'], $event['id']));

        $submissionId = $event['data']['id'];

        if ($event['op'] === EventLogService::ACTION_DELETE) {
            // We need to mark the submission as not valid and then emit a delete event

            $submission = $this->em->getRepository(Submission::class)->findOneBy(['externalid' => $submissionId]);
            if ($submission) {
                $submission->setValid(false);
                $this->em->flush();
                $this->eventLogService->log('submissions', $submission->getSubmitid(), EventLogService::ACTION_DELETE,
                                            $this->contestId);

                $contest = $this->em->getRepository(Contest::class)->find($submission->getCid());
                $team    = $this->em->getRepository(Team::class)->find($submission->getTeamid());
                $problem = $this->em->getRepository(Problem::class)->find($submission->getProbid());
                $this->scoreboardService->calculateScoreRow($contest, $team, $problem);
                return;
            } else {
                $this->logger->error(sprintf('Can not delete submission %s, because it does not exist in DOMjudge',
                                             $submissionId));
            }
            return;
        }

        // First, load the submission
        /** @var Submission $submission */
        $submission = $this->em->getRepository(Submission::class)->findOneBy(['externalid' => $submissionId]);

        $languageId = $event['data']['language_id'];
        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $languageId]);
        if (!$language) {
            $this->logger->error(sprintf('Can not import submission %s because language %s is missing',
                                         $event['data']['id'],
                                         $languageId));
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
                                                                                               'cid' => $this->contestId,
                                                                                               'probid' => $problem->getProbid()
                                                                                           ]);

        if (!$contestProblem) {
            $this->logger->error(sprintf('Can not import submission %s because problem %s is not part of contest',
                                         $event['data']['id'],
                                         $problem->getExternalid()));
            return;
        }

        $teamId = $event['data']['team_id'];
        /** @var Team $team */
        $team = $this->em->getRepository(Team::class)->findOneBy(['teamid' => $teamId]);
        if (!$team) {
            $this->addPendingEvent('team', $teamId, $event);
            return;
        }

        $time       = new \DateTime($event['data']['time']);
        $submitTime = (float)sprintf('%d.%d', $time->getTimestamp(), $time->format('u'));

        $entryPoint = $event['data']['entry_point'] ?? null;
        if (empty($entryPoint)) {
            $entryPoint = null;
        }

        // If the submission is found, we can only update the valid status.
        // If any of the other fields are different, this is an error
        if ($submission) {
            $matches = true;
            if ($submission->getTeam()->getExternalid() !== $team->getExternalid()) {
                $this->logger->error(sprintf('Got new event for submission %s with different team ID (%s instead of %s)',
                                             $submission->getExternalid(), $team->getExternalid(),
                                             $submission->getTeam()->getExternalid()));
                $matches = false;
            }
            if ($submission->getProblem()->getExternalid() !== $problem->getExternalid()) {
                $this->logger->error(sprintf('Got new event for submission %s with different problem ID (%s instead of %s)',
                                             $submission->getExternalid(), $problem->getExternalid(),
                                             $submission->getProblem()->getExternalid()));
                $matches = false;
            }
            if ($submission->getLanguage()->getExternalid() !== $language->getExternalid()) {
                $this->logger->error(sprintf('Got new event for submission %s with different language ID (%s instead of %s)',
                                             $submission->getExternalid(), $language->getExternalid(),
                                             $submission->getLanguage()->getExternalid()));
                $matches = false;
            }
            if (abs(Utils::difftime((float)$submission->getSubmittime(), $submitTime)) >= 1) {
                $this->logger->error(sprintf('Got new event for submission %s with different submit time (%s instead of %s)',
                                             $submission->getExternalid(), $event['data']['time'],
                                             $submission->getAbsoluteSubmitTime()));
                $matches = false;
            }
            if ($entryPoint !== $submission->getEntryPoint()) {
                if ($submission->getEntryPoint() === null) {
                    // Special case: if we did not have an entrypoint yet, but we do get one now, update it
                    $submission->setEntryPoint($entryPoint);
                    $this->em->flush();
                    $this->logger->info(sprintf('Updated entrypoint for submission %s to %s',
                                                $submission->getExternalid(), $entryPoint));
                    $this->eventLogService->log('submissions', $submission->getSubmitid(),
                                                EventLogService::ACTION_UPDATE, $this->contestId);
                    $this->processPendingEvents('submission', $submission->getExternalid());
                    return;
                } elseif ($entryPoint === null) {
                    // Special case number two: if we get a null entry point but we have one already,
                    // ignore this and do not log any error
                    $this->logger->debug(sprintf('Received null entrypoint for submission %s, but we already have %s',
                                                 $submission->getExternalid(), $submission->getEntryPoint()));
                } else {
                    $this->logger->error(sprintf('Got new event for submission %s with different entrypoint (%s instead of %s)',
                                                 $submission->getExternalid(), $entryPoint,
                                                 $submission->getEntryPoint()));
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
                $this->eventLogService->log('submissions', $submission->getSubmitid(), EventLogService::ACTION_CREATE,
                                            $this->contestId);

                $contest = $this->em->getRepository(Contest::class)->find($submission->getCid());
                $team    = $this->em->getRepository(Team::class)->find($submission->getTeamid());
                $problem = $this->em->getRepository(Problem::class)->find($submission->getProbid());
                $this->scoreboardService->calculateScoreRow($contest, $team, $problem);
            }
        } else {
            // First, check if we actually have the source for this submission in the data
            if (empty($event['data']['files'][0]['href'])) {
                $this->logger->error(sprintf('Submission %s does not have source files in event', $submissionId));
                return;
            } elseif (($event['data']['files'][0]['mime'] ?? null) !== 'application/zip') {
                $this->logger->error(sprintf('Submission %s has non-ZIP source files in event', $submissionId));
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
                        $this->logger->error(sprintf('Can not create temporary file to download ZIP for submission %s',
                                                     $submissionId));
                        return;
                    }

                    $client   = new Client();
                    $response = $client->get($zipUrl, ['sink' => $zipFile]);
                    if ($response->getStatusCode() !== 200) {
                        $this->logger->error(sprintf('Can not download ZIP for submission %s', $submissionId));
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
                        $this->logger->error(sprintf('Can not create temporary file to extract ZIP contents for submission %s and file %s',
                                                     $submissionId, $filename));
                        $zip->close();
                        if ($shouldUnlink) {
                            unlink($zipFile);
                        }
                        return;
                    }
                    file_put_contents($tmpSubmissionFile, $content);
                    $filesToSubmit[] = new UploadedFile($tmpSubmissionFile, $filename, null, null, null, true);
                }

                // If the language requires an entry point but we do not have one, use automatic entry point detection
                if ($language->getRequireEntryPoint() && $entryPoint === null) {
                    $entryPoint = '__auto__';
                }

                // Submit the solution
                $contest    = $this->em->getRepository(Contest::class)->find($this->contestId);
                $submission = $this->submissionService->submitSolution(
                    $team, $contestProblem, $contest, $language, $filesToSubmit, null, $entryPoint, $submissionId,
                    $submitTime
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
     * @throws \Exception
     */
    protected function importJudgement(array $event)
    {
        $this->logger->info(sprintf('Importing judgement %s event %s', $event['op'], $event['id']));

        // First, find the submission for this judgement as we need it in all cases
        $submissionId = $event['data']['submission_id'] ?? null;
        /** @var Submission $submission */
        $submission = $this->em->getRepository(Submission::class)->findOneBy(['externalid' => $submissionId]);
        if (!$submission) {
            $this->addPendingEvent('submission', $submissionId, $event);
            return;
        }

        $verdict = $event['data']['judgement_type_id'] ?? null;

        if ($event['op'] === EventLogService::ACTION_DELETE || $verdict === null) {
            // We need to delete the judgement. We do this by setting the external result of the submission back to null
            $submission->setExternalresult(null);
            $this->em->flush();
            return;
        }

        // For create and update actions, check if the judgement exists
        $verdictsFlipped = array_flip($this->verdicts);
        if (!isset($verdictsFlipped[$verdict])) {
            $this->logger->error(sprintf('Can not import judgement %s, because judgement type %s does not exist in DOMjudge',
                                         $event['data']['id'], $verdict));
        }

        // Update the external result of the submission
        $submission->setExternalresult($verdictsFlipped[$verdict]);
        $this->em->flush();
    }

    /**
     * Process all pending events for the given type and (external) ID
     * @param string $type
     * @param mixed  $id
     * @throws \Exception
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
                $this->logger->debug(sprintf("Processing pending event with ID %s and type %s...", $event['id'],
                                             $event['type']));
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
        $this->logger->warning(sprintf('Can not currently import %s event %s, because it is dependant on %s %s',
                                       $event['type'], $event['id'], $type, $id));
        if (!isset($this->pendingEvents[$type][$id])) {
            $this->pendingEvents[$type][$id] = [];
        }

        $this->pendingEvents[$type][$id][] = $event;
    }
}
