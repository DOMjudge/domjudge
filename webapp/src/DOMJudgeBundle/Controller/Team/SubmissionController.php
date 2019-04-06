<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Testcase;
use DOMJudgeBundle\Form\Type\SubmitProblemType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\SubmissionService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Language;

/**
 * Class SubmissionController
 *
 * @Route("/team")
 * @Security("is_granted('ROLE_TEAM')")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account.")
 * @package DOMJudgeBundle\Controller\Team
 */
class SubmissionController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        SubmissionService $submissionService,
        DOMJudgeService $dj,
        FormFactoryInterface $formFactory
    ) {
        $this->entityManager     = $entityManager;
        $this->submissionService = $submissionService;
        $this->dj                = $dj;
        $this->formFactory       = $formFactory;
    }

    /**
     * @Route("/submit", name="team_submit")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function createAction(Request $request)
    {
        $user    = $this->dj->getUser();
        $team    = $user->getTeam();
        $contest = $this->dj->getCurrentContest($user->getTeamid());
        $form    = $this->formFactory
            ->createBuilder(SubmitProblemType::class)
            ->setAction($this->generateUrl('team_submit'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($contest === null) {
                $this->addFlash('danger', 'No active contest');
            } elseif (!$this->dj->checkrole('jury') && !$contest->getFreezeData()->started()) {
                $this->addFlash('danger', 'Contest has not yet started');
            } else {
                /** @var Problem $problem */
                $problem = $form->get('problem')->getData();
                /** @var Language $language */
                $language = $form->get('language')->getData();
                /** @var UploadedFile[] $files */
                $files      = $form->get('code')->getData();
                $entryPoint = $form->get('entry_point')->getData() ?: null;
                $submission = $this->submissionService->submitSolution($team, $problem->getProbid(), $contest,
                                                                       $language, $files, null, $entryPoint, null, null,
                                                                       null, $message);

                if ($submission) {
                    $this->dj->auditlog('submission', $submission->getSubmitid(), 'added', 'via teampage',
                                                     null, $contest->getCid());
                    $this->addFlash('success',
                                    '<strong>Submission done!</strong> Watch for the verdict in the list below.');
                } else {
                    $this->addFlash('danger', $message);
                }
                return $this->redirectToRoute('team_index');
            }
        }

        $data = ['form' => $form->createView()];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/team/submit_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/team/submit.html.twig', $data);
        }
    }

    /**
     * @Route("/submission/{submitId}", name="team_submission")
     * @param Request $request
     * @param int     $submitId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(Request $request, int $submitId)
    {
        $verificationRequired = (bool)$this->dj->dbconfig_get('verification_required', false);;
        $showCompile      = $this->dj->dbconfig_get('show_compile', 2);
        $showSampleOutput = $this->dj->dbconfig_get('show_sample_output', 0);
        $user             = $this->dj->getUser();
        $team             = $user->getTeam();
        $contest          = $this->dj->getCurrentContest($team->getTeamid());
        /** @var Judging $judging */
        $judging = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judging', 'j')
            ->join('j.submission', 's')
            ->join('s.contest_problem', 'cp')
            ->join('cp.problem', 'p')
            ->join('s.language', 'l')
            ->select('j', 's', 'cp', 'p', 'l')
            ->andWhere('j.submitid = :submitId')
            ->andWhere('j.valid = 1')
            ->andWhere('s.team = :team')
            ->setParameter(':submitId', $submitId)
            ->setParameter(':team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        // Update seen status when viewing submission
        if ($judging && $judging->getSubmission()->getSubmittime() < $contest->getEndtime() && (!$verificationRequired || $judging->getVerified())) {
            $judging->setSeen(true);
            $this->entityManager->flush();
        }

        /** @var Testcase[] $runs */
        $runs = [];
        if ($showSampleOutput && $judging && $judging->getResult() !== 'compiler-error') {
            $runs = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Testcase', 't')
                ->join('t.testcase_content', 'tc')
                ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                ->leftJoin('jr.judging_run_output', 'jro')
                ->select('t', 'jr', 'tc', 'jro')
                ->andWhere('t.problem = :problem')
                ->andWhere('t.sample = 1')
                ->setParameter(':judging', $judging)
                ->setParameter(':problem', $judging->getSubmission()->getProblem())
                ->orderBy('t.rank')
                ->getQuery()
                ->getResult();
        }

        $data = [
            'judging' => $judging,
            'verificationRequired' => $verificationRequired,
            'showCompile' => $showCompile,
            'showSampleOutput' => $showSampleOutput,
            'runs' => $runs,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('@DOMJudge/team/submission_modal.html.twig', $data);
        } else {
            return $this->render('@DOMJudge/team/submission.html.twig', $data);
        }
    }
}
