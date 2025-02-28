<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\Testcase;
use App\Form\Type\SubmitProblemType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team')]
class SubmissionController extends BaseController
{
    final public const NEVER_SHOW_COMPILE_OUTPUT = 0;
    final public const ONLY_SHOW_COMPILE_OUTPUT_ON_ERROR = 1;
    final public const ALWAYS_SHOW_COMPILE_OUTPUT = 2;

    public function __construct(
        EntityManagerInterface $em,
        protected readonly SubmissionService $submissionService,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly FormFactoryInterface $formFactory,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '/submit/{problem}', name: 'team_submit')]
    public function createAction(Request $request, ?Problem $problem = null): Response
    {
        $user    = $this->dj->getUser();
        $team    = $user->getTeam();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
        $data = [];
        if ($problem !== null) {
            $data['problem'] = $problem;
        }
        $form    = $this->formFactory
            ->createBuilder(SubmitProblemType::class, $data)
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
                    $team, $this->dj->getUser(), $problem->getProbid(), $contest, $language, $files, 'team page', null,
                    null, $entryPoint, null, null, $message
                );

                if ($submission) {
                    $this->addFlash(
                        'success',
                        'Submission done! Watch for the verdict in the list below.'
                    );
                } else {
                    $this->addFlash('danger', $message);
                }
                return $this->redirectToRoute('team_index');
            }
        }

        $data = ['form' => $form->createView(), 'problem' => $problem];
        $data['validFilenameRegex'] = SubmissionService::FILENAME_REGEX;

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submit_modal.html.twig', $data);
        } else {
            return $this->render('team/submit.html.twig', $data);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/submission/{submitId<\d+>}', name: 'team_submission')]
    public function viewAction(Request $request, int $submitId): Response
    {
        $verificationRequired = (bool)$this->config->get('verification_required');
        $showCompile          = $this->config->get('show_compile');
        $showSampleOutput     = $this->config->get('show_sample_output');
        $allowDownload        = (bool)$this->config->get('allow_team_submission_download');
        $showTooLateResult    = $this->config->get('show_too_late_result');
        $user                 = $this->dj->getUser();
        $team                 = $user->getTeam();
        $contest              = $this->dj->getCurrentContest($team->getTeamid());
        /** @var Judging|null $judging */
        $judging = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->join('s.contest_problem', 'cp')
            ->join('cp.problem', 'p')
            ->join('s.language', 'l')
            ->select('j', 's', 'cp', 'p', 'l')
            ->andWhere('j.submission = :submitId')
            ->andWhere('j.valid = 1')
            ->andWhere('s.team = :team')
            ->setParameter('submitId', $submitId)
            ->setParameter('team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        // Update seen status when viewing submission.
        if ($judging && $judging->getSubmission()->getSubmittime() < $contest->getEndtime() &&
            (!$verificationRequired || $judging->getVerified())) {
            $judging->setSeen(true);
            $this->em->flush();
        }

        $runs = [];
        if ($showSampleOutput && $judging && $judging->getResult() !== 'compiler-error') {
            $outputDisplayLimit    = (int)$this->config->get('output_display_limit');
            $outputTruncateMessage = sprintf("\n[output display truncated after %d B]\n", $outputDisplayLimit);

            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->join('t.content', 'tc')
                ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                ->leftJoin('jr.output', 'jro')
                ->select('t', 'jr', 'tc')
                ->andWhere('t.problem = :problem')
                ->andWhere('t.sample = 1')
                ->setParameter('judging', $judging)
                ->setParameter('problem', $judging->getSubmission()->getProblem())
                ->orderBy('t.ranknumber');

            if ($outputDisplayLimit < 0) {
                $queryBuilder
                    ->addSelect('tc.output AS output_reference')
                    ->addSelect('jro.output_run AS output_run')
                    ->addSelect('jro.output_diff AS output_diff')
                    ->addSelect('jro.output_error AS output_error')
                    ->addSelect('jro.output_system AS output_system')
                    ->addSelect('jro.team_message AS team_message');
            } else {
                $queryBuilder
                    ->addSelect('TRUNCATE(tc.output, :outputDisplayLimit, :outputTruncateMessage) AS output_reference')
                    ->addSelect('TRUNCATE(jro.output_run, :outputDisplayLimit, :outputTruncateMessage) AS output_run')
                    ->addSelect('TRUNCATE(jro.output_diff, :outputDisplayLimit, :outputTruncateMessage) AS output_diff')
                    ->addSelect('TRUNCATE(jro.output_error, :outputDisplayLimit, :outputTruncateMessage) AS output_error')
                    ->addSelect('TRUNCATE(jro.output_system, :outputDisplayLimit, :outputTruncateMessage) AS output_system')
                    ->addSelect('TRUNCATE(jro.team_message, :outputDisplayLimit, :outputTruncateMessage) AS team_message')
                    ->setParameter('outputDisplayLimit', $outputDisplayLimit)
                    ->setParameter('outputTruncateMessage', $outputTruncateMessage);
            }

            $runs = $queryBuilder
                ->getQuery()
                ->getResult();
        }

        $actuallyShowCompile = $showCompile == self::ALWAYS_SHOW_COMPILE_OUTPUT
            || ($showCompile == self::ONLY_SHOW_COMPILE_OUTPUT_ON_ERROR && $judging->getResult() === 'compiler-error');

        $data = [
            'judging' => $judging,
            'verificationRequired' => $verificationRequired,
            'showCompile' => $actuallyShowCompile,
            'allowDownload' => $allowDownload,
            'showSampleOutput' => $showSampleOutput,
            'runs' => $runs,
            'showTooLateResult' => $showTooLateResult,
        ];
        if ($actuallyShowCompile) {
            $data['size'] = 'xl';
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submission_modal.html.twig', $data);
        } else {
            return $this->render('team/submission.html.twig', $data);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/submission/{submitId<\d+>}/download', name: 'team_submission_download')]
    public function downloadAction(int $submitId): Response
    {
        $allowDownload = (bool)$this->config->get('allow_team_submission_download');
        if (!$allowDownload) {
            throw new NotFoundHttpException('Submission download not allowed');
        }

        $user = $this->dj->getUser();
        $team = $user->getTeam();
        /** @var Submission|null $submission */
        $submission = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.files', 'f')
            ->select('s, f')
            ->andWhere('s.submitid = :submitId')
            ->andWhere('s.team = :team')
            ->setParameter('submitId', $submitId)
            ->setParameter('team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        if ($submission === null) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found',
                $submitId));
        }

        if ($this->submissionService->getSubmissionFileCount($submission) === 1) {
            return $this->submissionService->getSubmissionFileResponse($submission);
        }

        return $this->submissionService->getSubmissionZipResponse($submission);
    }
}
