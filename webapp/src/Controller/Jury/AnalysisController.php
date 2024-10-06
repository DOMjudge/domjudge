<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Team;
use App\Service\DOMJudgeService;
use App\Service\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/analysis')]
class AnalysisController extends AbstractController
{
    public function __construct(
        private readonly DOMJudgeService $dj,
        private readonly StatisticsService $stats,
        private readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '', name: 'analysis_index')]
    public function indexAction(
        #[MapQueryParameter]
        ?string $view = null
    ): Response {
        $em = $this->em;
        $contest = $this->dj->getCurrentContest();

        if ($contest === null) {
            return $this->render('jury/error.html.twig', [
                'error' => 'No contest selected',
            ]);
        }

        $filterKeys = array_keys(StatisticsService::FILTERS);
        $view = $view ?: reset($filterKeys);

        $problems = $this->stats->getContestProblems($contest);
        $teams = $this->stats->getTeams($contest, $view);
        $misc = $this->stats->getMiscContestStatistics($contest, $teams, $view);

        $maxDelayedJudgings = 10;
        $delayedTimeDiff = 5;
        /** @var array<array{'submitid': int, 'judgingid': int, 'submittime': float,
         *                   'timediff': float, 'num_judgings': int}> $delayedJudgings */
        $delayedJudgings = $em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->innerJoin(Judging::class, 'j', Expr\Join::WITH, 's.submitid = j.submission')
            ->select('s.submitid, MIN(j.judgingid) AS judgingid, s.submittime, MIN(j.starttime) - s.submittime AS timediff, COUNT(j.judgingid) AS num_judgings')
            ->andWhere('s.contest = :contest')
            ->setParameter('contest', $contest)
            ->andWhere('s.team IN (:teams)')
            ->setParameter('teams', $teams)
            ->groupBy('s.submitid')
            ->andHaving('timediff > :timediff')
            ->setParameter('timediff', $delayedTimeDiff)
            ->orderBy('timediff', 'DESC')
            ->getQuery()->getResult();

        return $this->render('jury/analysis/contest_overview.html.twig', [
            'contest' => $contest,
            'problems' => $problems,
            'teams' => $teams,
            'submissions' => $misc['submissions'],
            'delayed_judgings' => [
                'data' => array_slice($delayedJudgings, 0, $maxDelayedJudgings),
                'overflow' => count($delayedJudgings) - $maxDelayedJudgings,
                'delay' => $delayedTimeDiff,
            ],
            'misc' => $misc,
            'filters' => StatisticsService::FILTERS,
            'view' => $view,
        ]);
    }

    #[Route(path: '/team/{teamid}', name: 'analysis_team')]
    public function teamAction(Team $team): Response
    {
        $contest = $this->dj->getCurrentContest();

        if ($contest === null) {
            return $this->render('jury/error.html.twig', [
                'error' => 'No contest selected',
            ]);
        }

        return $this->render('jury/analysis/team.html.twig',
            $this->stats->getTeamStats($contest, $team)
        );
    }

    #[Route(path: '/problem/{probid}', name: 'analysis_problem')]
    public function problemAction(
        #[MapEntity(id: 'probid')]
        Problem $problem,
        #[MapQueryParameter]
        ?string $view = null
    ): Response {
        $contest = $this->dj->getCurrentContest();

        if ($contest === null) {
            return $this->render('jury/error.html.twig', [
                'error' => 'No contest selected',
            ]);
        }

        $filterKeys = array_keys(StatisticsService::FILTERS);
        $view = $view ?: reset($filterKeys);

        return $this->render('jury/analysis/problem.html.twig',
            $this->stats->getProblemStats($contest, $problem, $view)
        );
    }

    #[Route(path: '/languages', name: 'analysis_languages')]
    public function languagesAction(
        #[MapQueryParameter]
        ?string $view = null
    ): Response {
        $contest = $this->dj->getCurrentContest();

        if ($contest === null) {
            return $this->render('jury/error.html.twig', [
                'error' => 'No contest selected',
            ]);
        }

        $filterKeys = array_keys(StatisticsService::FILTERS);
        $view = $view ?: reset($filterKeys);

        return $this->render('jury/analysis/languages.html.twig',
            $this->stats->getLanguagesStats($contest, $view)
        );
    }
}
