<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionSource;
use App\Entity\Team;
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
use Symfony\Component\Form\FormInterface;
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
        $data = ['languages' => []];
        if ($problem !== null) {
            $data['problem'] = $problem;
            $data['languages'] = $problem->getLanguages()->toArray();
        }

        $submitMethods = $this->config->get('submit_methods');

        $uploadForm = null;
        $pasteForm = null;

        if (in_array('upload', $submitMethods)) {
            $uploadForm = $this->formFactory
                ->createNamedBuilder('submit_problem', SubmitProblemType::class, $data, ['submission_mode' => 'upload'])
                ->setAction($this->generateUrl('team_submit'))
                ->getForm();
        }

        // Only create the paste form for the full page (not the modal)
        if (in_array('paste', $submitMethods) && !$request->isXmlHttpRequest()) {
            $pasteForm = $this->formFactory
                ->createNamedBuilder('submit_problem_paste', SubmitProblemType::class, $data, ['submission_mode' => 'paste'])
                ->setAction($this->generateUrl('team_submit'))
                ->getForm();
        }

        // Handle upload form submission
        if ($uploadForm !== null) {
            $uploadForm->handleRequest($request);
            if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
                return $this->handleSubmission($uploadForm, $team, $contest, 'upload');
            }
        }

        // Handle paste form submission
        if ($pasteForm !== null) {
            $pasteForm->handleRequest($request);
            if ($pasteForm->isSubmitted() && $pasteForm->isValid()) {
                return $this->handleSubmission($pasteForm, $team, $contest, 'paste');
            }
        }

        $templateData = [
            'problem' => $problem,
            'upload_form' => $uploadForm?->createView(),
            'paste_form' => $pasteForm?->createView(),
            'submit_methods' => $submitMethods,
        ];
        $templateData['validFilenameRegex'] = SubmissionService::FILENAME_REGEX;
        if ($contest && $team) {
            $templateData['rateLimitStatus'] = $this->submissionService->getRateLimitStatus($team, $contest);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submit_modal.html.twig', $templateData);
        } else {
            return $this->render('team/submit.html.twig', $templateData);
        }
    }

    private function handleSubmission(FormInterface $form, Team $team, ?Contest $contest, string $mode): Response
    {
        if ($contest === null) {
            $this->addFlash('danger', 'No active contest');
            return $this->redirectToRoute('team_index');
        }
        if (!$this->dj->checkrole('jury') && !$contest->getFreezeData()->started()) {
            $this->addFlash('danger', 'Contest has not yet started');
            return $this->redirectToRoute('team_index');
        }

        /** @var Problem $problem */
        $problem = $form->get('problem')->getData();
        /** @var Language $language */
        $language = $form->get('language')->getData();
        $entryPoint = $form->get('entry_point')->getData() ?: null;

        $tmpFile = null;
        if ($mode === 'paste') {
            $result = $this->buildPasteFiles($problem, $language, $contest, $form->get('code_content')->getData(), $entryPoint);
            if ($result === null) {
                return $this->redirectToRoute('team_index');
            }
            [$files, $tmpFile] = $result;
        } else {
            /** @var UploadedFile[]|UploadedFile $files */
            $files = $form->get('code')->getData();
            if (!is_array($files)) {
                $files = [$files];
            }
        }

        try {
            $message = '';
            $submission = $this->submissionService->submitSolution(
                $team, $this->dj->getUser(), $problem->getProbid(), $contest, $language, $files, SubmissionSource::TEAM_PAGE, null,
                null, $entryPoint, null, null, $message
            );

            if ($submission) {
                $this->addFlash('success', 'Submission done! Watch for the verdict in the list below.');
            } else {
                $this->addFlash('danger', $message);
            }
        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        return $this->redirectToRoute('team_index');
    }

    /**
     * Build an UploadedFile from pasted code content.
     *
     * @return array{UploadedFile[], string}|null [files, tmpFilePath], or null if the code content is empty
     */
    private function buildPasteFiles(Problem $problem, Language $language, Contest $contest, ?string $codeContent, ?string $entryPoint): ?array
    {
        if (empty($codeContent)) {
            $this->addFlash('danger', 'No code provided.');
            return null;
        }

        // Determine filename — find the contest problem for the current contest
        $contestProblem = $problem->getContestProblems()->filter(
            fn($cp) => $cp->getContest() === $contest
        )->first();
        $extensions = $language->getExtensions();
        $extension = $extensions[0] ?? 'txt';

        if ($language->getRequireEntryPoint() && $entryPoint !== null) {
            $filename = $entryPoint . '.' . $extension;
        } elseif ($contestProblem !== false) {
            $filename = $contestProblem->getShortname() . '.' . $extension;
        } else {
            $filename = 'submission.' . $extension;
        }

        $filename = SubmissionService::sanitizeFilename($filename);

        // Write to temp file and create UploadedFile
        $tmpFile = tempnam(sys_get_temp_dir(), 'dj_paste_');
        file_put_contents($tmpFile, $codeContent);
        $uploadedFile = new UploadedFile($tmpFile, $filename, null, null, true);

        return [[$uploadedFile], $tmpFile];
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
        if (!$contest) {
            throw new NotFoundHttpException('No active contest');
        }
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
        if ($showSampleOutput && $judging?->getResult() !== 'compiler-error') {
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
            || ($showCompile == self::ONLY_SHOW_COMPILE_OUTPUT_ON_ERROR && $judging?->getResult() === 'compiler-error');

        $data = [
            'judging' => $judging,
            'verificationRequired' => $verificationRequired,
            'showCompile' => $actuallyShowCompile,
            'allowDownload' => $allowDownload,
            'showSampleOutput' => $showSampleOutput,
            'runs' => $runs,
            'showTooLateResult' => $showTooLateResult,
            'thumbnailSize' => $this->config->get('thumbnail_size'),
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
