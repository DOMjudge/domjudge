<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Entity\TestcaseWithContent;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\ScoreboardService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class PublicController
 *
 * @Route("/public")
 *
 * @package DOMJudgeBundle\Controller
 */
class PublicController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(
        DOMJudgeService $dj,
        ScoreboardService $scoreboardService,
        EntityManagerInterface $entityManager
    ) {
        $this->dj                = $dj;
        $this->scoreboardService = $scoreboardService;
        $this->entityManager     = $entityManager;
    }

    /**
     * @Route("", name="public_index")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function scoreboardAction(Request $request)
    {
        $response   = new Response();
        $static     = $request->query->getBoolean('static');
        $refreshUrl = $this->generateUrl('public_index');
        // Determine contest to use
        $contest = $this->dj->getCurrentContest(-1);

        if ($static) {
            $refreshParams = [
                'static' => 1,
            ];
            // For static scoreboards, allow to pass a contest= param
            if ($contestId = $request->query->get('contest')) {
                if ($contestId === 'auto') {
                    // Automatically detect the contest that is activated the latest
                    $contest      = null;
                    $activateTime = null;
                    foreach ($this->dj->getCurrentContests(-1) as $possibleContest) {
                        if (!$possibleContest->getPublic() || !$possibleContest->getEnabled()) {
                            continue;
                        }
                        if ($activateTime === null || $activateTime < $possibleContest->getActivatetime()) {
                            $activateTime = $possibleContest->getActivatetime();
                            $contest      = $possibleContest;
                        }
                    }
                } else {
                    // Find the contest with the given ID
                    $contest = null;
                    foreach ($this->dj->getCurrentContests(-1) as $possibleContest) {
                        if ($possibleContest->getCid() == $contestId || $possibleContest->getExternalid() == $contestId) {
                            $contest = $possibleContest;
                            break;
                        }
                    }

                    if ($contest) {
                        $refreshParams['contest'] = $contest->getCid();
                    } else {
                        throw new NotFoundHttpException('Specified contest not found');
                    }
                }
            }

            $refreshUrl = sprintf('?%s', http_build_query($refreshParams));
        }

        $data = $this->scoreboardService->getScoreboardTwigData($request, $response, $refreshUrl, false, true, $static,
                                                                $contest);

        if ($static) {
            $data['hide_menu'] = true;
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/partials/scoreboard.html.twig', $data, $response);
        }
        return $this->render('@DOMJudge/public/scoreboard.html.twig', $data, $response);
    }

    /**
     * @Route("/change-contest/{contestId}", name="public_change_contest")
     * @param Request         $request
     * @param RouterInterface $router
     * @param int             $contestId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId)
    {
        if ($this->isLocalReferrer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('public_index');
        }
        return $this->dj->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }

    /**
     * @Route("/team/{teamId}", name="public_team")
     * @param Request $request
     * @param int     $teamId
     * @return Response
     * @throws \Exception
     */
    public function teamAction(Request $request, int $teamId)
    {
        $team             = $this->entityManager->getRepository(Team::class)->find($teamId);
        $showFlags        = (bool)$this->dj->dbconfig_get('show_flags', true);
        $showAffiliations = (bool)$this->dj->dbconfig_get('show_affiliations', true);
        $data             = [
            'team' => $team,
            'showFlags' => $showFlags,
            'showAffiliations' => $showAffiliations,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/public/team_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/public/team.html.twig', $data);
        }
    }

    /**
     * @Route("/problems", name="public_problems")
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function problemsAction()
    {
        $contest            = $this->dj->getCurrentContest(-1);
        $showLimits         = (bool)$this->dj->dbconfig_get('show_limits_on_team_page');
        $defaultMemoryLimit = (int)$this->dj->dbconfig_get('memory_limit', 0);
        $timeFactorDiffers  = false;
        if ($showLimits) {
            $timeFactorDiffers = $this->entityManager->createQueryBuilder()
                    ->from('DOMJudgeBundle:Language', 'l')
                    ->select('COUNT(l)')
                    ->andWhere('l.allowSubmit = true')
                    ->andWhere('l.timeFactor <> 1')
                    ->getQuery()
                    ->getSingleScalarResult() > 0;
        }

        $problems = [];
        if ($contest && $contest->getFreezeData()->started()) {
            $problems = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:ContestProblem', 'cp')
                ->join('cp.problem', 'p')
                ->leftJoin('p.testcases', 'tc')
                ->select('p', 'cp', 'SUM(tc.sample) AS numsamples')
                ->andWhere('cp.contest = :contest')
                ->andWhere('cp.allowSubmit = 1')
                ->setParameter(':contest', $contest)
                ->addOrderBy('cp.shortname')
                ->groupBy('cp.probid')
                ->getQuery()
                ->getResult();
        }

        return $this->render('@DOMJudge/public/problems.html.twig', [
            'problems' => $problems,
            'showLimits' => $showLimits,
            'defaultMemoryLimit' => $defaultMemoryLimit,
            'timeFactorDiffers' => $timeFactorDiffers,
        ]);
    }


    /**
     * @Route("/problems/{probId}/text", name="public_problem_text", requirements={"probId": "\d+"})
     * @param int $probId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function problemTextAction(int $probId)
    {
        $contest = $this->dj->getCurrentContest(-1);
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->entityManager->getRepository(ContestProblem::class)->find([
                                                                                               'probid' => $probId,
                                                                                               'cid' => $contest->getCid(),
                                                                                           ]);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }

        $problem = $contestProblem->getProblem();

        switch ($problem->getProblemtextType()) {
            case 'pdf':
                $mimetype = 'application/pdf';
                break;
            case 'html':
                $mimetype = 'text/html';
                break;
            case 'txt':
                $mimetype = 'text/plain';
                break;
            default:
                $this->addFlash('danger', sprintf('Problem p%d text has unknown type', $probId));
                return $this->redirectToRoute('public_problems');
        }

        $filename    = sprintf('prob-%s.%s', $problem->getName(), $problem->getProblemtextType());
        $problemText = stream_get_contents($problem->getProblemtext());

        $response = new StreamedResponse();
        $response->setCallback(function () use ($problemText) {
            echo $problemText;
        });
        $response->headers->set('Content-Type', sprintf('%s; name="%s', $mimetype, $filename));
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $filename));
        $response->headers->set('Content-Length', strlen($problemText));

        return $response;
    }

    /**
     * @Route(
     *     "/{probId}/sample/{index}/{type}",
     *     name="public_problem_sample_testcase",
     *     requirements={"probId": "\d+", "index": "\d+", "type": "input|output"}
     *     )
     * @param int    $probId
     * @param int    $index
     * @param string $type
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function sampleTestcaseAction(int $probId, int $index, string $type)
    {
        $contest = $this->dj->getCurrentContest(-1);
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->entityManager->getRepository(ContestProblem::class)->find([
                                                                                               'probid' => $probId,
                                                                                               'cid' => $contest->getCid(),
                                                                                           ]);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }

        /** @var TestcaseWithContent $testcase */
        $testcase = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:TestcaseWithContent', 'tc')
            ->join('tc.problem', 'p')
            ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->select('tc')
            ->andWhere('tc.probid = :problem')
            ->andWhere('tc.sample = 1')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter(':problem', $probId)
            ->setParameter(':contest', $contest)
            ->orderBy('tc.testcaseid')
            ->setMaxResults(1)
            ->setFirstResult($index - 1)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$testcase) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }

        $extension = substr($type, 0, -3);
        $mimetype  = 'text/plain';

        $filename = sprintf("sample-%s.%s.%s", $contestProblem->getShortname(), $index, $extension);
        $content  = null;

        switch ($type) {
            case 'input':
                $content = $testcase->getInput();
                break;
            case 'output':
                $content = $testcase->getOutput();
                break;
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($content) {
            echo $content;
        });
        $response->headers->set('Content-Type', sprintf('%s; name="%s', $mimetype, $filename));
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Content-Length', strlen($content));

        return $response;
    }

    /**
     * @Route(
     *     "/{probId}/samples.zip",
     *     name="public_problem_sample_zip",
     *     requirements={"probId": "\d+"}
     *     )
     * @param int $probId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function sampleZipAction(int $probId)
    {
        $contest = $this->dj->getCurrentContest(-1);
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->entityManager->getRepository(ContestProblem::class)->find([
                                                                                               'probid' => $probId,
                                                                                               'cid' => $contest->getCid(),
                                                                                           ]);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }

        $zipFilename    = $this->dj->getSamplesZip($contestProblem);
        $outputFilename = sprintf('samples-%s.zip', $contestProblem->getShortname());

        $response = new StreamedResponse();
        $response->setCallback(function () use ($zipFilename) {
            $fp = fopen($zipFilename, 'rb');
            fpassthru($fp);
            unlink($zipFilename);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $outputFilename . '"');
        $response->headers->set('Content-Length', filesize($zipFilename));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }
}
