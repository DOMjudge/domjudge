<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Team;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ScoreboardService;
use App\Twig\Attribute\AjaxTemplate;
use App\Twig\EventListener\CustomResponseListener;
use App\Utils\Scoreboard\Filter;
use App\Utils\Scoreboard\Scoreboard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team')]
class ScoreboardController extends BaseController
{
    public function __construct(
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly ScoreboardService $scoreboardService,
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    /**
     * @return array{refresh?: array{after: int, url: string, ajax: bool}, static: bool, contest?: Contest,
     *                scoreFilter?: Filter, scoreboard: Scoreboard, filterValues: array<string, string[]>,
     *                groupedAffiliations: null|array<array<string, array<array{id: string, name: string}>>>,
     *                showFlags: int, showAffiliationLogos: bool, showAffiliations: int, showPending: int,
     *                showTeamSubmissions: int, scoreInSeconds: bool, maxWidth: int, myTeamId: int,
     *                current_contest?: Contest|null}
     */
    #[Route(path: '/scoreboard', name: 'team_scoreboard')]
    #[AjaxTemplate(normalTemplate: 'team/scoreboard.html.twig', ajaxTemplate: 'partials/scoreboard.html.twig')]
    public function scoreboardAction(Request $request, CustomResponseListener $customResponseListener): array
    {
        if (!$this->config->get('enable_ranking')) {
            throw new BadRequestHttpException('Scoreboard is not available.');
        }

        $user       = $this->dj->getUser();
        $response   = new Response();
        $contest    = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
        $refreshUrl = $this->generateUrl('team_scoreboard');
        $data       = $this->scoreboardService->getScoreboardTwigData(
            $request, $response, $refreshUrl, false, false, false, $contest
        );
        $data['myTeamId'] = $user->getTeam()->getTeamid();

        $customResponseListener->setCustomResponse($response);

        if ($request->isXmlHttpRequest()) {
            $data['current_contest'] = $contest;
        }
        return $data;
    }

    /**
     * @return array{team: Team|null, showFlags: bool, showAffiliations: bool}
     */
    #[Route(path: '/team/{teamId<\d+>}', name: 'team_team')]
    #[AjaxTemplate(normalTemplate: 'team/team.html.twig', ajaxTemplate: 'team/team_modal.html.twig')]
    public function teamAction(int $teamId): array
    {
        if (!$this->config->get('enable_ranking')) {
            throw new BadRequestHttpException('Scoreboard is not available.');
        }

        /** @var Team|null $team */
        $team             = $this->em->getRepository(Team::class)->find($teamId);
        if ($team && $team->getCategory() && !$team->getCategory()->getVisible() && $teamId !== $this->dj->getUser()->getTeamId()) {
            $team = null;
        }
        $showFlags        = (bool)$this->config->get('show_flags');
        $showAffiliations = (bool)$this->config->get('show_affiliations');

        return [
            'team' => $team,
            'showFlags' => $showFlags,
            'showAffiliations' => $showAffiliations,
        ];
    }
}
