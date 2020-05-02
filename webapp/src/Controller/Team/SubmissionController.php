<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Judging;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Testcase;
use App\Form\Type\SubmitProblemType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Language;

/**
 * Class SubmissionController
 *
 * @Route("/team")
 * @IsGranted("ROLE_TEAM")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account.")
 * @package App\Controller\Team
 */
class SubmissionController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    public function __construct(
        EntityManagerInterface $em,
        SubmissionService $submissionService,
        DOMJudgeService $dj,
        ConfigurationService $config,
        FormFactoryInterface $formFactory
    ) {
        $this->em                = $em;
        $this->submissionService = $submissionService;
        $this->dj                = $dj;
        $this->config            = $config;
        $this->formFactory       = $formFactory;
    }

    /**
     * @Route("/submit", name="team_submit")
     * @param Request $request
     * @return Response
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
                if (!is_array($files)) {
                    $files = [$files];
                }
                $entryPoint = $form->get('entry_point')->getData() ?: null;
                $submission = $this->submissionService->submitSolution(
                    $team, $problem->getProbid(), $contest, $language, $files,
                    null, $entryPoint, null, null, $message
                );

                if ($submission) {
                    $this->dj->auditlog('submission', $submission->getSubmitid(), 'added',
                                        'via teampage', null, $contest->getCid());
                    $this->addFlash(
                        'success',
                        '<strong>Submission done!</strong> Watch for the verdict in the list below.'
                    );
                } else {
                    $this->addFlash('danger', $message);
                }
                return $this->redirectToRoute('team_index');
            }
        }

        $data = ['form' => $form->createView()];

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submit_modal.html.twig', $data);
        } else {
            return $this->render('team/submit.html.twig', $data);
        }
    }

    /**
     * @Route("/submission/{submitId<\d+>}", name="team_submission")
     * @param Request $request
     * @param int     $submitId
     * @return Response
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, int $submitId)
    {
        $verificationRequired = (bool)$this->config->get('verification_required');;
        $showCompile      = $this->config->get('show_compile');
        $showSampleOutput = $this->config->get('show_sample_output');
        $allowDownload    = (bool)$this->config->get('allow_team_submission_download');
        $user             = $this->dj->getUser();
        $team             = $user->getTeam();
        $contest          = $this->dj->getCurrentContest($team->getTeamid());
        /** @var Judging $judging */
        $judging = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
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
        if ($judging && $judging->getSubmission()->getSubmittime() < $contest->getEndtime() &&
            (!$verificationRequired || $judging->getVerified())) {
            $judging->setSeen(true);
            $this->em->flush();
        }

        /** @var Testcase[] $runs */
        $runs = [];
        if ($showSampleOutput && $judging && $judging->getResult() !== 'compiler-error') {
            $runs = $this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->join('t.content', 'tc')
                ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                ->leftJoin('jr.output', 'jro')
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
            'allowDownload' => $allowDownload,
            'showSampleOutput' => $showSampleOutput,
            'runs' => $runs,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submission_modal.html.twig', $data);
        } else {
            return $this->render('team/submission.html.twig', $data);
        }
    }

    /**
     * @Route("/submission/{submitId<\d+>}/download", name="team_submission_download")
     * @param int $submitId
     *
     * @return Response
     * @throws NonUniqueResultException
     */
    public function downloadAction($submitId)
    {
        $allowDownload = (bool)$this->config->get('allow_team_submission_download');
        if (!$allowDownload) {
            throw new NotFoundHttpException('Submission download not allowed');
        }

        $user = $this->dj->getUser();
        $team = $user->getTeam();
        /** @var Submission $submission */
        $submission = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.files', 'f')
            ->select('s, f')
            ->andWhere('s.submitid = :submitId')
            ->andWhere('s.team = :team')
            ->setParameter(':submitId', $submitId)
            ->setParameter(':team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        if ($submission === null) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found',
                $submitId));
        }

        return $this->submissionService->getSubmissionZipResponse($submission);
    }
}
