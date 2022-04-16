<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Clarification;
use App\Entity\Language;
use App\Form\Type\PrintType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\ScoreboardService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MiscController
 *
 * @Route("/team")
 * @IsGranted("ROLE_TEAM")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account. ")
 *
 * @package App\Controller\Team
 */
class MiscController extends BaseController
{
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EntityManagerInterface $em;
    protected ScoreboardService $scoreboardService;
    protected SubmissionService $submissionService;

    /**
     * MiscController constructor.
     */
    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService
    ) {
        $this->dj                = $dj;
        $this->config            = $config;
        $this->em                = $em;
        $this->scoreboardService = $scoreboardService;
        $this->submissionService = $submissionService;
    }

    /**
     * @Route("", name="team_index")
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function homeAction(Request $request): Response
    {
        $user    = $this->dj->getUser();
        $team    = $user->getTeam();
        $teamId  = $team->getTeamid();
        $contest = $this->dj->getCurrentContest($teamId);

        $data = [
            'team' => $team,
            'contest' => $contest,
            'refresh' => [
                'after' => 30,
                'url' => $this->generateUrl('team_index'),
                'ajax' => true,
            ],
            'maxWidth' => $this->config->get('team_column_width'),
        ];
        if ($contest) {
            $scoreboard = $this->scoreboardService
                ->getTeamScoreboard($contest, $teamId, false);
            $data = array_merge(
                $data,
                $this->scoreboardService->getScoreboardTwigData(
                    $request, null, '', true, false, false,
                    $contest, $scoreboard
                )
            );
            $data['limitToTeams'] = [$team];
            $data['verificationRequired'] = $this->config->get('verification_required');
            // We need to clear the entity manager, because loading the team scoreboard seems to break getting submission
            // contestproblems for the contest we get the scoreboard for.
            $this->em->clear();
            $data['submissions'] = $this->submissionService->getSubmissionList(
                [$contest->getCid() => $contest],
                ['teamid' => $teamId],
                0
            )[0];

            /** @var Clarification[] $clarifications */
            $clarifications = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender IS NULL')
                ->andWhere('c.recipient = :team OR c.recipient IS NULL')
                ->setParameter('contest', $contest)
                ->setParameter('team', $team)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();

            /** @var Clarification[] $clarificationRequests */
            $clarificationRequests = $this->em->createQueryBuilder()
                ->from(Clarification::class, 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender = :team')
                ->setParameter('contest', $contest)
                ->setParameter('team', $team)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();

            $data['clarifications']        = $clarifications;
            $data['clarificationRequests'] = $clarificationRequests;
            $data['categories']            = $this->config->get('clar_categories');
            $data['allowDownload']         = (bool)$this->config->get('allow_team_submission_download');
        }

        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('team/partials/index_content.html.twig', $data);
        }

        return $this->render('team/index.html.twig', $data);
    }

    /**
     * @Route("/change-contest/{contestId<-?\d+>}", name="team_change_contest")
     */
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId): Response
    {
        if ($this->isLocalReferer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('team_index');
        }
        return $this->dj->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }

    /**
     * @Route("/print", name="team_print")
     */
    public function printAction(Request $request): Response
    {
        if (!$this->config->get('print_command')) {
            throw new AccessDeniedHttpException("Printing disabled in config");
        }

        $form = $this->createForm(PrintType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var UploadedFile $file */
            $file             = $data['code'];
            $realfile         = $file->getRealPath();
            $originalfilename = $file->getClientOriginalName() ?? '';

            $langid   = $data['langid'];
            $username = $this->getUser()->getUsername();

            $team = $this->dj->getUser()->getTeam();
            $ret  = $this->dj->printFile($realfile, $originalfilename, $langid,
                $username, $team->getEffectiveName(), $team->getTeamid(), $team->getRoom());

            return $this->render('team/print_result.html.twig', [
                'success' => $ret[0],
                'output' => $ret[1],
            ]);
        }

        /** @var Language[] $languages */
        $languages = $this->em->createQueryBuilder()
            ->from(Language::class, 'l')
            ->select('l')
            ->andWhere('l.allowSubmit = 1')
            ->getQuery()
            ->getResult();

        return $this->render('team/print.html.twig', [
            'form' => $form->createView(),
            'languages' => $languages,
        ]);
    }

    /**
     * @Route("/docs", name="team_docs")
     */
    public function docsAction(): Response
    {
        return $this->render('team/docs.html.twig');
    }
}
