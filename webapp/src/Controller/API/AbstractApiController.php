<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractApiController extends AbstractFOSRestController
{
    final public const GROUP_DEFAULT = 'Default';
    final public const GROUP_NONSTRICT = 'Nonstrict';
    final public const GROUP_RESTRICTED = 'Restricted';
    final public const GROUP_RESTRICTED_NONSTRICT = 'RestrictedNonstrict';

    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly EventLogService $eventLogService
    ) {}

    /**
     * Get the query builder used for getting contests.
     *
     * @param bool $onlyActive return only contests that are active
     */
    protected function getContestQueryBuilder(bool $onlyActive = false): QueryBuilder
    {
        $now = Utils::now();
        $qb = $this->em->createQueryBuilder();
        $qb
            ->from(Contest::class, 'c')
            ->select('c')
            ->andWhere('c.enabled = 1')
            ->orderBy('c.activatetime');

        if ($onlyActive || !$this->dj->checkrole('api_reader')) {
            $qb
                ->andWhere('c.activatetime <= :now')
                ->andWhere('c.deactivatetime IS NULL OR c.deactivatetime > :now')
                ->setParameter('now', $now);
        }

        // Filter on contests this user has access to
        if (!$this->dj->checkrole('api_reader') && !$this->dj->checkrole('judgehost')) {
            if ($this->dj->checkrole('team') && $this->dj->getUser()->getTeam()) {
                $qb->leftJoin('c.teams', 'ct')
                    ->leftJoin('c.team_categories', 'tc')
                    ->leftJoin('tc.teams', 'tct')
                    ->andWhere('ct.teamid = :teamid OR tct.teamid = :teamid OR c.openToAllTeams = 1')
                    ->setParameter('teamid', $this->dj->getUser()->getTeam());
            } else {
                $qb->andWhere('c.public = 1');
            }
        }

        return $qb;
    }

    /**
     * @throws NonUniqueResultException
     */
    protected function getContestId(Request $request): int
    {
        if (!$request->attributes->has('cid')) {
            throw new BadRequestHttpException('cid parameter missing');
        }

        $qb = $this->getContestQueryBuilder($request->query->getBoolean('onlyActive', false));
        $qb
            ->andWhere('c.externalid = :cid')
            ->setParameter('cid', $request->attributes->get('cid'));

        /** @var Contest|null $contest */
        $contest = $qb->getQuery()->getOneOrNullResult();

        if ($contest === null) {
            throw new NotFoundHttpException(sprintf('Contest with ID \'%s\' not found', $request->attributes->get('cid')));
        }

        return $contest->getCid();
    }
}
