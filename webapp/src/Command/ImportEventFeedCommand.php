<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Contest;
use App\Entity\ExternalContestSource;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ExternalContestSourceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

#[AsCommand(
    name: 'import:eventfeed',
    description: 'Import contest data from an event feed following the Contest API specification'
)]
class ImportEventFeedCommand extends Command
{
    protected SymfonyStyle $style;

    protected ?ExternalContestSource $source = null;

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly ConfigurationService $config,
        protected readonly DOMJudgeService $dj,
        protected readonly TokenStorageInterface $tokenStorage,
        protected readonly ?Profiler $profiler,
        protected readonly ExternalContestSourceService $sourceService,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Import contest data from an event feed following the Contest API specification:' . PHP_EOL .
                'Note the following assumptions and caveats:' . PHP_EOL .
                '- Configuration data will only be verified.' . PHP_EOL .
                '- Team members will not be imported.' . PHP_EOL .
                '- Awards will not be imported.' . PHP_EOL .
                '- State will not be imported.'
            )
            ->addArgument(
                'contestid',
                InputArgument::OPTIONAL,
                'The ID of the contest to use.'
            )
            ->addOption(
                'from-start',
                's',
                InputOption::VALUE_NONE,
                'Restart importing events from the beginning. ' .
                'If this option is not given, importing will resume where it left off.'
            )
            ->addOption(
                'skip-event-id',
                'k',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "ID('s) of events to skip."
            );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->style = new SymfonyStyle($input, $output);
        // Disable SQL logging and profiling. This would cause a serious memory leak otherwise
        // since this is a long-running process.
        $this->em->getConnection()->getConfiguration()->setSQLLogger();
        $this->profiler?->disable();

        $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        pcntl_signal(SIGTERM, $this->stopCommand(...));
        pcntl_signal(SIGINT, $this->stopCommand(...));

        if (!$this->loadSource($input, $output)) {
            return Command::FAILURE;
        }

        if (!$this->dj->shadowMode()) {
            $this->style->error("shadow_mode configuration setting is set to 'false' but should be 'true'.");
            return Command::FAILURE;
        }

        // Find an admin user as we need one to make sure we can read all events.
        /** @var User|null $user */
        $user = $this->em->createQueryBuilder()
                         ->from(User::class, 'u')
                         ->select('u')
                         ->join('u.user_roles', 'r')
                         ->andWhere('r.dj_role = :role')
                         ->setParameter('role', 'admin')
                         ->setMaxResults(1)
                         ->getQuery()
                         ->getOneOrNullResult();
        if (!$user) {
            $this->style->error('No admin user found. Please create at least one');
            return Command::FAILURE;
        }
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);

        $fromStart    = $input->getOption('from-start');
        $eventsToSkip = $input->getOption('skip-event-id');

        if (!$this->compareContestId()) {
            return Command::FAILURE;
        }

        $this->style->success('Starting import. Press ^C to quit (might take a bit to be detected).');

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('[%bar%] %message%');
        $progressBar->setMessage('Start reading feed...');
        $progressBar->start();

        $progressReporter = function ($readingToLastEventId) use ($progressBar) {
            if ($readingToLastEventId) {
                $progressBar->setMessage('Scanning file for start event ' . $this->sourceService->getLastReadEventId());
            } else {
                $progressBar->setMessage('Read up to event ' . $this->sourceService->getLastReadEventId());
            }
            $progressBar->advance();
        };

        if (!$this->sourceService->import($fromStart, $eventsToSkip, $progressReporter)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    public function stopCommand(): void
    {
        $this->sourceService->stopReading();
    }

    /**
     * Load the source for the contest with the given ID or ask for a contest if null.
     *
     * @return bool False if the import should stop, true otherwise.
     */
    protected function loadSource(InputInterface $input, OutputInterface $output): bool
    {
        if (!$input->getArgument('contestid')) {
            if ($input->isInteractive()) {
                /** @var Contest[] $contests */
                $contests = $this->em->getRepository(Contest::class)->findAll();
                $choices = [];
                foreach ($contests as $contest) {
                    $choices[] = sprintf(
                        '%s: %s',
                        $contest->getCid(),
                        $contest->getName()
                    );
                }
                $answer = $this->style->choice('Which contest do you want to use?', $choices);
                // Parse the answer. Ideally we would set ID's as array keys, but since IDs are integers, Symfony will
                // not return them (only if they are strings and even casting them to strings makes PHP change them back
                // to integers).
                // We start the answers with the ID, so we can just cast them.
                $contestId = (int)$answer;
            } else {
                $this->style->error('No contestid provided and not running in interactive mode.');
                return false;
            }
        } else {
            $contestId = $input->getArgument('contestid');
        }

        /** @var ExternalContestSource|null $source */
        $source = $this->em->createQueryBuilder()
            ->from(ExternalContestSource::class, 'ecs')
            ->select('ecs')
            ->join('ecs.contest', 'c')
            ->andWhere('c.cid = :cid')
            ->setParameter('cid', $contestId)
            ->getQuery()
            ->getOneOrNullResult();
        if ($source === null) {
            $this->style->error('Contest does not have an external contest configured yet');
            return false;
        }
        $this->sourceService->setSource($source);

        return true;
    }

    /**
     * Compare the external contest ID of the configured contest to the source.
     *
     * @return bool False if the import should stop, true otherwise.
     */
    protected function compareContestId(): bool
    {
        $contest = $this->sourceService->getSourceContest();
        $ourId   = $contest->getExternalid();
        $theirId = $this->sourceService->getContestId();
        if ($ourId !== $theirId) {
            $this->style->warning(
                "Contest ID in external system $theirId does not match external ID in DOMjudge ($ourId)."
            );
            if (!$this->style->confirm('Do you want to continue anyway?', default: false)) {
                return false;
            }
        }

        return true;
    }
}
