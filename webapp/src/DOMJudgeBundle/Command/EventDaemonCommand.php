<?php declare(strict_types=1);

namespace DOMJudgeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Event;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Utils\FreezeData;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Class EventDaemonCommand
 *
 * Generate events for a contest that cannot be generated in other
 * code. This includes:
 * - Initial "create events" at MAX(contest:activatetime,daemon start)
 *   for static data: contest, teams, problems, ...
 * - Contest state change events: start, freeze, end, finalize, ...
 *
 * @package DOMJudgeBundle\Command
 */
class EventDaemonCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var bool
     */
    protected $shouldStop = false;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        TokenStorageInterface $tokenStorage,
        string $name = null
    ) {
        parent::__construct($name);
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
        $this->tokenStorage    = $tokenStorage;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('eventdaemon')
            ->setDescription('Generate events for a contest that cannot be generated in other code')
            ->addOption(
                'contest',
                'C',
                InputOption::VALUE_REQUIRED,
                'Run for contest with the given ID or shortname. The contest defaults to the current active contest if it is unique.'
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

        $selectedContest = null;
        $contests        = $this->DOMJudgeService->getCurrentContests();
        if (count($contests) == 1) {
            $selectedContest = reset($contests);
        }

        if ($input->getOption('contest')) {
            $selectedContest = null;
            foreach ($contests as $contest) {
                if ($contest->getCid() == $input->getOption('contest') || $contest->getShortname() === $input->getOption('contest')) {
                    $selectedContest = $contest;
                    break;
                }
            }

            if ($selectedContest === null) {
                $this->logger->error(sprintf('No contest found with ID or shortname \'%s\'',
                                             $input->getOption('contest')));
                return 1;
            }
        } elseif ($selectedContest === null) {
            $this->logger->error('No contest ID specified and no unique active contest found.');
            return 1;
        }

        $selectedContestId      = $selectedContest->getCid();
        $initialEventsLoaded    = false;
        $contestStartOld        = 'TBC';
        $contestStartEnabledOld = 'TBC';
        $freezeData             = null;

        $this->logger->notice(sprintf('Eventdaemon started for cid=%d [DOMjudge/%s]', $selectedContest->getCid(),
                                      $this->getContainer()->getParameter('domjudge.version')));

        // Find an admin user
        /** @var User $user */
        $user = $this->entityManager->createQueryBuilder()
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

        while (true) {
            // Make sure we clear the entity manager class, to make sure we have fresh objects
            $this->entityManager->clear();

            // Reload the contest to get the new data
            $selectedContest = $this->entityManager->getRepository(Contest::class)->find($selectedContestId);

            // Check whether we have received an exit signal
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->shouldStop) {
                $this->logger->notice('Received signal, exiting.');
                return 0;
            }

            $contests = $this->DOMJudgeService->getCurrentContests();

            if (!array_key_exists($selectedContest->getCid(), $contests)) {
                $this->logger->error(sprintf('Contest ID \'%s\' not found (anymore) in active contests.',
                                             $selectedContest->getCid()));
                return 1;
            }

            $contestStart        = $selectedContest->getStarttime();
            $contestStartEnabled = $selectedContest->getStarttimeEnabled();
            if ($contestStartOld !== $contestStart || $contestStartEnabledOld !== $contestStartEnabled) {
                $contestId = $selectedContest->getApiId($this->eventLogService, $this->entityManager);
                $url       = sprintf('/contests/%s', $contestId);
                $this->DOMJudgeService->withAllRoles(function () use ($url, $selectedContest) {
                    $this->insertEvent($selectedContest, 'contests',
                                       $this->DOMJudgeService->internalApiRequest($url, Request::METHOD_GET));
                });
                $contestStartOld        = $contestStart;
                $contestStartEnabledOld = $contestStartEnabled;
            }

            if (!$initialEventsLoaded) {
                if (!$this->initializeEvents($selectedContest)) {
                    return 1;
                }
                $initialEventsLoaded = true;
            }

            $freezeDataOld = $freezeData;
            $freezeData    = new FreezeData($selectedContest);
            if ($freezeDataOld === null) {
                $freezeDataOld = $freezeData;
            }

            // Check for contest state changes:
            if ($freezeDataOld->running() !== $freezeData->running() ||
                $freezeDataOld->showFrozen() !== $freezeData->showFrozen() ||
                $freezeDataOld->showFinal(false) !== $freezeData->showFinal(false) ||
                $freezeDataOld->finalized() !== $freezeData->finalized()) {
                $this->logger->notice('Inserting contest state update event.');
                $this->eventLogService->log('state', '', EventLogService::ACTION_UPDATE, $selectedContest->getCid(),
                                            null, '');
            }

            // FIXME: generate contest state change events, ideally triggered by an alarm.
            usleep(50000);
        }

        return 0;
    }

    public function stopCommand()
    {
        $this->shouldStop = true;
    }

    /**
     * @param Contest $contest
     * @param string  $endpoint
     * @param         $data
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function insertEvent(Contest $contest, string $endpoint, $data)
    {
        /** @var Event $event */
        $event = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Event', 'e')
            ->select('e')
            ->andWhere('e.cid = :cid')
            ->andWhere('e.endpointtype = :endpoint')
            ->andWhere('e.endpointid = :endpointid')
            ->setParameter(':cid', $contest->getCid())
            ->setParameter(':endpoint', $endpoint)
            ->setParameter(':endpointid', $data['id'])
            ->setMaxResults(1)
            ->orderBy('e.eventid', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();

        $json = $this->DOMJudgeService->jsonEncode($data);

        // Check if there's already an old event and create/update
        // depending on previous state.
        if (!$event || $event->getAction() === EventLogService::ACTION_DELETE) {
            $this->eventLogService->log($endpoint, null, EventLogService::ACTION_CREATE, $contest->getCid(), $json,
                                        $data['id']);
        } else {
            if (empty($event->getContent()) || $this->DOMJudgeService->jsonEncode($event->getContent()) !== $json) {
                $this->eventLogService->log($endpoint, null, 'update', $contest->getCid(), $json, $data['id']);
            } else {
                $this->logger->debug(sprintf('Skipping create %s/%s: already present', $endpoint, $data['id']));
            }
        }
    }

    /**
     * @param Contest $contest
     * @return bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    protected function initializeEvents(Contest $contest)
    {
        $this->logger->notice('Initializing configuration events.');

        foreach ($this->eventLogService->apiEndpoints as $endpoint => $endpointData) {
            if ($endpointData[EventLogService::KEY_TYPE] === EventLogService::TYPE_CONFIGURATION &&
                isset($endpointData[EventLogService::KEY_URL])) {
                $contestId = $contest->getApiId($this->eventLogService, $this->entityManager);

                $url = sprintf('/contests/%s%s', $contestId, $endpointData[EventLogService::KEY_URL]);
                $this->DOMJudgeService->withAllRoles(function () use ($url, &$data) {
                    $data = $this->DOMJudgeService->internalApiRequest($url);
                });

                if ($data === null) {
                    $this->logger->error(sprintf('No response data for endpoint \'%s\'.' . $endpoint));
                    return false;
                }

                if (!is_array($data)) {
                    $this->logger->error(sprintf('Endpoint \'%s\' did not return a JSON list.', $endpoint));
                    return false;
                }

                // Special case 'contests' since it is a single object:
                if ($endpoint === 'contests') {
                    $this->logger->info(sprintf('Inserting %s create event.', $endpoint));
                    $this->insertEvent($contest, $endpoint, $data);
                    continue;
                }

                usort($data, function ($a, $b) {
                    if (is_int($a['id']) && is_int($b['id'])) {
                        return $a['id'] <=> $b['id'];
                    }
                    return strcmp((string)$a['id'], (string)$b['id']);
                });

                $this->logger->info(sprintf('Inserting %d %s create event(s).', count($data), $endpoint));
                foreach ($data as $row) {
                    $this->insertEvent($contest, $endpoint, $row);
                }
            }
        }

        return true;
    }
}
