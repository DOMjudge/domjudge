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
        $queryBuilder = $this->getQueryBuilder(externalContestId: $externalContestId)
            ->select('clar', 'c', 'cp')
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

    public function getQueryBuilder(?string $externalContestId = null, ?int $internalContestId = null,
                                    ?string $externalClarificationId = null,
                                    ?string $problem = null
    ): QueryBuilder {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Clarification::class, 'clar')
            ->join('clar.contest', 'c')
            ->leftJoin('clar.in_reply_to', 'reply')
            ->leftJoin('clar.sender', 's')
            ->leftJoin('clar.recipient', 'r')
            ->leftJoin('clar.problem', 'p')
            ->leftJoin('c.problems', 'cp', Join::WITH, 'cp.problem = clar.problem');

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

        if (!$this->authService->checkRole('api_reader') &&
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

        if (!$this->authService->checkRole('api_reader')) {
            $queryBuilder
                // For non-API-reader users, only expose the problems after the contest has started.
                // `WF Access Policy` allows for clarifications before the contest, but not to disclose the problem
                // so referencing them in clarifications would violate referential integrity.
                ->andWhere('c.starttime < :now OR clar.problem IS NULL')
                ->setParameter('now', Utils::now())
                // Don't display future clarifications to non-jury users.
                ->andWhere('clar.submittime <= :now');
        }

        if (!is_null($problem)) {
            $queryBuilder
                ->andWhere('clar.problem = :problem')
                ->setParameter('problem', $problem);
        }

        return $queryBuilder;
    }
}
