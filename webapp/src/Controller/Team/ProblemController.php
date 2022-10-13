<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected StatisticsService $stats;
    protected EntityManagerInterface $em;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        StatisticsService $stats,
        EntityManagerInterface $em
    ) {
        $this->dj     = $dj;
        $this->config = $config;
        $this->stats  = $stats;
        $this->em     = $em;
    }

    /**
     * @Route("/problems", name="team_problems")
     * @throws NonUniqueResultException
     */
    public function problemsAction(): Response
    {
        $teamId = $this->dj->getUser()->getTeam()->getTeamid();
        return $this->render('team/problems.html.twig',
            $this->dj->getTwigDataForProblemsAction($teamId, $this->stats));
    }


    /**
     * @Route("/problems/{probId<\d+>}/text", name="team_problem_text")
     */
    public function problemTextAction(int $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (
            int $probId,
            Contest $contest,
            ContestProblem $contestProblem
        ) {
            $problem = $contestProblem->getProblem();

            try {
                return $problem->getProblemTextStreamedResponse();
            } catch (BadRequestHttpException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('team_problems');
            }
        });
    }

    /**
     * @Route(
     *     "/{probId<\d+>}/attachment/{attachmentId<\d+>}",
     *     name="team_problem_attachment"
     *     )
     * @throws NonUniqueResultException
     */
    public function attachmentAction(int $probId, int $attachmentId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (
            int $probId,
            Contest $contest,
            ContestProblem $contestProblem
        ) use ($attachmentId) {
            return $this->dj->getAttachmentStreamedResponse($contestProblem,
                $attachmentId);
        });
    }

    /**
     * @Route("/{probId<\d+>}/samples.zip", name="team_problem_sample_zip")
     */
    public function sampleZipAction(int $probId): StreamedResponse
    {
        return $this->getBinaryFile($probId, function (int $probId, Contest $contest, ContestProblem $contestProblem) {
            return $this->dj->getSamplesZipStreamedResponse($contestProblem);
        });
    }

    /**
     * Get a binary file for the given problem ID using the given callable.
     *
     * Shared code between testcases, problem text and attachments.
     */
    protected function getBinaryFile(int $probId, callable $response): StreamedResponse
    {
        $user    = $this->dj->getUser();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
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

        return $response($probId, $contest, $contestProblem);
    }
}
