<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Clarification;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

readonly class ClarificationService
{
    public function __construct(
        protected AuthorizedUserService $authService,
        protected ConfigurationService $config,
        protected EntityManagerInterface $em,
    ) {}

    /**
     * @return array<string, string>
     */
    public function getClarificationCategories(): array
    {
        return $this->config->get('clar_categories');
    }

    /**
     * @return string[]
     */
    public function getClarificationDefaultAnswers(): array
    {
        return $this->config->get('clar_answers');
    }

    public function getClarificationDefaultProblemQueue(): string
    {
        return $this->config->get('clar_default_problem_queue');
    }

    /**
     * @return array<string, string>
     */
    public function getClarificationQueues(): array
    {
        return $this->config->get('clar_queues');
    }

    public function getClarificationMaximumBodyLength(): int
    {
        return $this->config->get('clar_max_body_length');
    }

    /**
     * @return Clarification[]
     */
    public function getClarifications(?string $externalContestId, string $currentQueue): array
    {
        $queryBuilder = $this->getQueryBuilder(externalContestId: $externalContestId, includeProblemsOutsideContest: true)
            // `p` is rendered for every row, so fetch it along instead of
            // lazy loading the problem of each clarification separately.
            ->select('clar', 'c', 'cp', 'p')
            ->orderBy('clar.submittime', 'DESC')
            ->addOrderBy('clar.clarid', 'DESC');

        if ($currentQueue === "unassigned") {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('clar.queue'),
                $queryBuilder->expr()->eq('clar.queue', ':queue')
            ))
                ->setParameter('queue', $currentQueue);
        } elseif ($currentQueue !== "all") {
            $queryBuilder->andWhere('clar.queue = :queue')
                ->setParameter('queue', $currentQueue);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * Build a query for the clarifications the current user is allowed to see.
     *
     * Team facing pages should pass `onlyForRecipientTeam` so that they keep
     * showing what the team sees: jury and admin users are exempt from the
     * visibility restriction below, and would otherwise see the clarifications
     * addressed to every other team on their own team pages. A null
     * `recipientTeamId` then limits the result to clarifications sent to
     * everyone.
     *
     * A clarification may reference a problem that is not (or no longer) part
     * of its contest. Those are only meaningful to the jury, which renders them
     * as unlinked, so by default they are left out: everything contest facing
     * would otherwise expose a dangling problem id. Jury pages should pass
     * `includeProblemsOutsideContest` to opt in to them.
     */
    public function getQueryBuilder(?string $externalContestId = null, ?int $internalContestId = null,
                                    ?string $externalClarificationId = null,
                                    ?string $problem = null,
                                    bool $onlyForRecipientTeam = false,
                                    ?int $recipientTeamId = null,
                                    bool $includeProblemsOutsideContest = false
    ): QueryBuilder {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'clar')
            ->join('clar.contest', 'c')
            ->leftJoin('clar.in_reply_to', 'reply')
            ->leftJoin('clar.sender', 's')
            ->leftJoin('clar.recipient', 'r')
            ->leftJoin('clar.problem', 'p')
            ->leftJoin('c.problems', 'cp', Join::WITH, 'cp.problem = clar.problem');

        if (!$includeProblemsOutsideContest) {
            // `cp` is only set when the clarification's problem is part of its
            // contest, so this drops the clarifications pointing elsewhere.
            $queryBuilder->andWhere('clar.problem IS NULL OR cp.problem IS NOT NULL');
        }

        if (!is_null($internalContestId)) {
            $queryBuilder
                ->andWhere('clar.contest = :cid')
                ->setParameter('cid', $internalContestId);
        }
        if (!is_null($externalContestId)) {
            $queryBuilder
                ->andWhere('c.externalid = :contestId')
                ->setParameter('contestId', $externalContestId);
        }

        if (!is_null($externalClarificationId)) {
            $queryBuilder
                ->andWhere('clar.externalid = :clarification')
                ->setParameter('clarification', $externalClarificationId);
        }

        // Staff handling clarifications sees every clarification. Note that
        // `clarification_rw` is implied by `jury` (and therefore by `api_reader`),
        // but can also be granted on its own to someone who only answers
        // clarifications and has no other jury permissions.
        $isClarificationStaff = $this->authService->checkRole('api_reader') ||
            $this->authService->checkRole('clarification_rw');

        if (!$isClarificationStaff &&
            !$this->authService->checkRole('judgehost')) {
            if ($this->authService->checkRole('team')) {
                $queryBuilder
                    ->andWhere('clar.sender = :team OR clar.recipient = :team OR (clar.sender IS NULL AND clar.recipient IS NULL)')
                    ->setParameter('team', $this->authService->getUser()->getTeam());
            } else {
                $queryBuilder
                    ->andWhere('clar.sender IS NULL')
                    ->andWhere('clar.recipient IS NULL');
            }
        }

        if (!$isClarificationStaff) {
            $queryBuilder
                // For non-staff users, only expose the problems after the contest has started.
                // `WF Access Policy` allows for clarifications before the contest, but not to disclose the problem
                // so referencing them in clarifications would violate referential integrity.
                ->andWhere('c.starttime < :now OR clar.problem IS NULL')
                ->setParameter('now', Utils::now())
                // Don't display future clarifications to non-jury users.
                ->andWhere('clar.submittime <= :now');
        }

        if ($onlyForRecipientTeam) {
            $queryBuilder
                ->andWhere('clar.recipient IS NULL OR clar.recipient = :recipientTeam')
                ->setParameter('recipientTeam', $recipientTeamId);
        }

        if (!is_null($problem)) {
            $queryBuilder
                ->andWhere('clar.problem = :problem')
                ->setParameter('problem', $problem);
        }

        return $queryBuilder;
    }
}
