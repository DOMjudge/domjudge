<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Testcase;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ProblemController
 *
 * @Route("/team")
 * @IsGranted("ROLE_TEAM")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account.")
 *
 * @package App\Controller\Team
 */
class ProblemController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * ProblemController constructor.
     *
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EntityManagerInterface $em
     */
    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em
    ) {
        $this->dj     = $dj;
        $this->config = $config;
        $this->em     = $em;
    }

    /**
     * @Route("/problems", name="team_problems")
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function problemsAction()
    {
        return $this->render('team/problems.html.twig',
            $this->dj->getTwigDataForProblemsAction($this->dj->getUser()->getTeamid()));
    }


    /**
     * @Route("/problems/{probId<\d+>}/text", name="team_problem_text")
     * @param int $probId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function problemTextAction(int $probId)
    {
        $user    = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeamid());
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
            'problem' => $probId,
            'contest' => $contest,
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
                return $this->redirectToRoute('team_problems');
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
     *     "/{probId<\d+>}/sample/{index<\d+>}/{type<input|output>}",
     *     name="team_problem_sample_testcase"
     *     )
     * @param int    $probId
     * @param int    $index
     * @param string $type
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function sampleTestcaseAction(int $probId, int $index, string $type)
    {
        $user    = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeamid());
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
            'problem' => $probId,
            'contest' => $contest,
        ]);
        if (!$contestProblem) {
            throw new NotFoundHttpException(sprintf('Problem p%d not found or not available', $probId));
        }

        /** @var Testcase $testcase */
        $testcase = $this->em->createQueryBuilder()
            ->from(Testcase::class, 'tc')
            ->join('tc.problem', 'p')
            ->join('p.contest_problems', 'cp', Join::WITH, 'cp.contest = :contest')
            ->join('tc.content', 'tcc')
            ->select('tc', 'tcc')
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
                $content = $testcase->getContent()->getInput();
                break;
            case 'output':
                $content = $testcase->getContent()->getOutput();
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
     * @Route("/{probId<\d+>}/samples.zip", name="team_problem_sample_zip")
     * @param int $probId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function sampleZipAction(int $probId)
    {
        $user    = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeamid());
        $notfound_msg = sprintf('Problem p%d not found or not available', $probId);
        if (!$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException($notfound_msg);
        }
        /** @var ContestProblem $contestProblem */
        $contestProblem = $this->em->getRepository(ContestProblem::class)->find([
            'problem' => $probId,
            'contest' => $contest,
        ]);
        if (!$contestProblem) {
            throw new NotFoundHttpException($notfound_msg);
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
