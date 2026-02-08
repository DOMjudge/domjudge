<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Contest;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ExternalContestSourceService;
use Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
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
class ImportEventFeedCommand
{
    protected SymfonyStyle $style;

    protected ?Contest $contest = null;

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly ConfigurationService $config,
        protected readonly DOMJudgeService $dj,
        protected readonly TokenStorageInterface $tokenStorage,
        protected readonly ?Profiler $profiler,
        protected readonly ExternalContestSourceService $sourceService,
    ) {
    }

    /**
     * @param list<string> $eventsToSkip
     * @throws ClientExceptionInterface
     * @throws NonUniqueResultException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: "ID('s) of events to skip.", shortcut: 'k')]
        array $eventsToSkip = [],
        #[Argument(description: 'The ID of the contest to use.')]
        ?string $contestId = null,
        #[Option(description: 'Restart importing events from the beginning. If this option is not given, importing will resume where it left off.', shortcut: 's')]
        bool $fromStart = false,
    ): int {
        $this->style = new SymfonyStyle($input, $output);
        // Disable SQL logging and profiling. This would cause a serious memory leak otherwise
        // since this is a long-running process.
        $configuration = $this->em->getConnection()->getConfiguration();
        $middlewares = $configuration->getMiddlewares();
        $middlewares = array_filter(
            $middlewares,
            static fn (MiddlewareInterface $middleware): bool => !$middleware instanceof Middleware && !$middleware instanceof DebugMiddleware
        );
        $this->em->getConnection()->getConfiguration()->setMiddlewares($middlewares);
        $this->profiler?->disable();

        $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);

        pcntl_signal(SIGTERM, $this->stopCommand(...));
        pcntl_signal(SIGINT, $this->stopCommand(...));

        if (!$this->loadSource($input, $contestId)) {
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

        if (!$this->validateContestSource()) {
            return Command::FAILURE;
        }

        $this->style->success('Starting import. Press ^C to quit (might take a bit to be detected).');

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('[%bar%] %message%');
        $progressBar->setMessage('Start reading feed...');
        $progressBar->start();

        $progressReporter = function ($readingToLastEventId) use ($progressBar): void {
            if ($readingToLastEventId) {
                $progressBar->setMessage('Scanning file for start event ' . $this->sourceService->getLastReadEventId());
            } else {
                $progressBar->setMessage('Read up to event ' . $this->sourceService->getLastReadEventId());
            }
            $progressBar->advance();
        };

        $statusReporter = function (string $message) use ($progressBar): void {
            $progressBar->setMessage($message);
            $progressBar->display();
        };

        if (!$this->sourceService->import($fromStart, $eventsToSkip, $progressReporter, $statusReporter)) {
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
    protected function loadSource(InputInterface $input, ?string $contestId = null): bool
    {
        if (!$contestId) {
            if ($input->isInteractive()) {
                /** @var Contest[] $contests */
                $contests = $this->em->getRepository(Contest::class)->findAll();
                $choices = [];
                foreach ($contests as $contest) {
                    $choices[] = sprintf(
                        '%s: %s',
                        $contest->getExternalid(),
                        $contest->getName()
                    );
                }
                $answer = $this->style->choice('Which contest do you want to use?', $choices);
                // Parse the answer. Ideally we would set ID's as array keys, but since IDs are integers, Symfony will
                // not return them (only if they are strings and even casting them to strings makes PHP change them back
                // to integers).
                // We start the answers with the ID, so we can just cast them.
                $contestId = $answer;
            } else {
                $this->style->error('No contestid provided and not running in interactive mode.');
                return false;
            }
        }

        $contest = $this->em->getRepository(Contest::class)->findByExternalId($contestId);
        if ($contest === null) {
            $this->style->error('Contest not found.');
            return false;
        }
        if (!$contest->isExternalSourceEnabled()) {
            $this->style->error('Contest does not have shadow mode enabled.');
            return false;
        }
        $this->contest = $contest;
        $this->sourceService->setSourceContest($contest);

        return true;
    }

    /**
     * Validate the configured contest to the source.
     *
     * @return bool False if the import should stop, true otherwise.
     */
    protected function validateContestSource(): bool
    {
        $contest = $this->sourceService->getSourceContest();
        $ourId   = $contest->getExternalid();
        $theirId = $this->sourceService->getContestId();
        if ($ourId !== $theirId) {
            $this->style->warning(
                "Contest ID in external system $theirId does not match ID in DOMjudge ($ourId)."
            );
            if (!$this->style->confirm('Do you want to continue anyway?', default: false)) {
                return false;
            }
        }

        if ($contest->getScoreboardType() !== $this->sourceService->getScoreboardType()) {
            $this->style->warning(sprintf(
                "Scoreboard type in external system (%s) does not match type in DOMjudge (%s).",
                $this->sourceService->getScoreboardType()->value,
                $contest->getScoreboardType()->value,
            ));
            if (!$this->style->confirm('Do you want to continue anyway?', default: false)) {
                return false;
            }
        }

        return true;
    }
}
