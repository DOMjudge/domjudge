<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Team;
use App\Service\ConfigurationService;
use App\Service\ScoreboardService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\DOMJudgeService;
use App\Controller\BaseController;
use App\Service\SubmissionService;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Class EditorController
 *
 * @package App\Controller\Team
 */
#[Route('/team/editor')]
#[IsGranted('ROLE_TEAM')]
#[Security('user.getTeam() !== null')]
class EditorController extends BaseController
{
    public function __construct(
        protected LoggerInterface        $logger,
        protected EntityManagerInterface $em,
        protected DOMJudgeService        $dj,
        protected SubmissionService      $submissionService,
        protected ScoreboardService      $scoreboardService,
        protected ConfigurationService   $config
    )
    {
    }

    #[Route('/{probId<\d+>}/{langId}', name: 'team_editor')]
    public function viewAction(Request $request, int $probId, string $langId): Response
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        /** @var Language $language */
        $language = $this->em->getRepository(Language::class)->find($langId);
        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s not found', $langId));
        }

        try {
            /** @var Language $language */
            $language = $this->em->getRepository(Language::class)->createQueryBuilder('lang')
                ->andWhere('lang.langid = :langId')
                ->setParameter('langId', $langId)
                ->andWhere('lang.allowSubmit = 1')
                ->getQuery()
                ->setMaxResults(1)
                ->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            $this->logger->error($e->getMessage());
            throw new HttpException(500, 'Something went wrong getting allowed language');
        }

        if (!$language) {
            throw new NotFoundHttpException(sprintf('Language with ID %s is not allowed', $langId));
        }

        $team = $this->dj->getUser()->getTeam();
        $contest = $this->dj->getCurrentContest($team->getTeamid());

        if (!$contest->getProblems()
            ->map(fn(ContestProblem $p) => $p->getProbid())
            ->contains($problem->getProbid())
        ) {
            $this->addFlash('warning', 'This problem is not part of the current contest');
            return $this->redirectToRoute('team_problems', ['cid' => $contest->getCid()]);
        }

        /** @var Submission|null $submission */
        $submission = $this->getLatestSubmission($team, $problem, $language, $contest);

        /** @var SubmissionFile[] $files */
        $files = [];

        if ($submission) {
            $files = $submission->getFiles();
        } else {
            $submission = (new Submission())
                ->setTeam($team)
                ->setProblem($problem)
                ->setLanguage($language)
                ->setContest($contest)
                ->setEntryPoint(null)
                ->setOriginalSubmission(null);

            $attachments = $problem->getAttachments()->filter(
                fn(ProblemAttachment $attachment) => $attachment->getType() === $language->getLangid()
            );

            foreach ($attachments as $rank => $attachment) {
                $files[] = (new SubmissionFile())
                    ->setFilename($attachment->getName())
                    ->setRank($rank)
                    ->setSourcecode($attachment->getContent()->getContent());
            }

            if (empty($files)) {
                $this->logger->error(
                    sprintf('Problem %s without pre-set attachment for %s',
                        $problem->getProbid(),
                        $language->getLangid()
                    )
                );
                $this->addFlash('warning', 'This problem is not setup for a web code editor using selected language');
                $route = $request->headers->get('referer');
                return $this->redirect($route);
            }
        }

        $data = [
            'problem' => $submission->getProblem(),
            'language' => $submission->getLanguage(),
            // FIXME: allow user to change entrypoint file
            'entry_point' => $submission->getEntryPoint(),
        ];

        foreach ($files as $file) {
            $data['source' . $file->getRank()] = $file->getSourcecode();
        }

        $enabledSubmission = $contest->getAllowSubmit();

        $formBuilder = $this->createFormBuilder($data)
            ->add('entry_point', HiddenType::class)
            ->add('save', SubmitType::class, [
                'attr' => [
                    'class' => 'btn-outline-info',
                ],
                'disabled' => !$enabledSubmission
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Save & Submit',
                'attr' => [
                    'class' => 'btn-outline-success'
                ],
                'disabled' => !$enabledSubmission
            ]);

        foreach ($files as $file) {
            $formBuilder->add('source' . $file->getRank(), TextareaType::class);
        }

        $form = $formBuilder->getForm();

        // Handle the form if it is submitted
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $form->getData();

            /** @var UploadedFile[] $filesToSubmit */
            $filesToSubmit = [];
            $tmpdir = $this->dj->getDomjudgeTmpDir();

            // FIXME: this will not work with dynamic number of files in the future
            // $files are loaded from pre-set templates or last submission (no new can be created without refactoring)
            foreach ($files as $file) {
                if (!($tmpfname = tempnam($tmpdir, "team_editor-"))) {
                    throw new ServiceUnavailableHttpException(null, "Could not create temporary file.");
                }
                file_put_contents($tmpfname, $submittedData['source' . $file->getRank()]);
                $filesToSubmit[] = new UploadedFile($tmpfname, $file->getFilename(), null, null, true);
            }

            $team = $this->dj->getUser()->getTeam();
            /** @var Language $language */
            $language = $submittedData['language'];
            $entryPoint = $submittedData['entry_point'];
            if ($language->getRequireEntryPoint() && $entryPoint === null) {
                $entryPoint = '__auto__';
            }

            $ignoreSubmission = !$form->get('submit')->isClicked();

            $submittedSubmission = $this->submissionService->submitSolution(
                $team,
                $this->dj->getUser(),
                $submittedData['problem'],
                $submission->getContest(),
                $language,
                $filesToSubmit,
                'team/editor',
                null,
                null,
                $entryPoint,
                null,
                null,
                $message,
                $ignoreSubmission
            );

            foreach ($filesToSubmit as $file) {
                unlink($file->getRealPath());
            }

            if (!$submittedSubmission) {
                $this->addFlash('danger', $message);
                return $this->redirectToRoute('team_editor', [
                    'probId' => $problem->getProbid(),
                    'langId' => $language->getLangid()
                ]);
            }

            return $this->redirectToRoute('team_editor', [
                'probId' => $problem->getProbid(),
                'langId' => $language->getLangid()
            ]);
        }

        return $this->render('team/team_editor.html.twig', array_merge(
            $this->getStatusData($request, $submission, $contest),
            [
                'language' => $language,
                'problem' => $problem,
                'submission' => $submission,
                'files' => $files,
                'form' => $form->createView(),
                'selected' => $request->query->get('ranknumber'),
                'static' => false
            ]
        ));
    }

    #[Route('/status/{submitId<\d+>}', name: 'team_editor_status')]
    public function statusAction(Request $request, int $submitId): Response
    {
        $submission = $this->getTeamSubmission($this->dj->getUser()->getTeam(), $submitId);
        if (!$submission) {
            throw new NotFoundHttpException(sprintf('Team submission with ID %s not found', $submitId));
        }

        $team = $this->dj->getUser()->getTeam();
        $contest = $this->dj->getCurrentContest($team->getTeamid());

        $data = $this->getStatusData($request, $submission, $contest);

        if (!$request->isXmlHttpRequest()) {
            $this->logger->error('Unintended status usage (not xhr request)');
            throw new HttpException(500, 'Something went wrong fetching last submission status');
        }

        return $this->render('team/partials/team_editor_status.html.twig', $data);
    }

    public function getStatusData(Request $request, Submission $submission, Contest $contest): array
    {
        return ($this->em->contains($submission) && $submission->getValid()) ? array_merge(
            $this->scoreboardService->getScoreboardTwigData(
                $request, null, '', true, false, true, $contest
            ),
            [
                'showFlags' => $this->config->get('show_flags'),
                'refresh' => [
                    'after' => 5,
                    'url' => $this->generateUrl('team_editor_status', ['submitId' => $submission->getSubmitid()]),
                    'ajax' => true,
                ],
                'maxWidth' => $this->config->get('team_column_width'),
                'limitToTeams' => [$this->dj->getUser()->getTeam()],
                'limitToProblems' => [$submission->getProblem()],
                'displayRank' => true
            ],
            $this->getSubmissionsData($submission)
        ) : [];
    }

    public function getSubmissionsData(Submission $submission): array
    {
        return [
            'submissions' => [$submission],
            'allowDownload' => (bool)$this->config->get('allow_team_submission_download'),
            'verificationRequired' => (bool)$this->config->get('verification_required')
        ];
    }

    protected function getLatestSubmission(Team $team, Problem $problem, Language $language, Contest $contest): ?Submission
    {
        return $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.team', 't')
            ->join('s.problem', 'p')
            ->join('s.language', 'l')
            ->join('s.contest', 'c')
            ->join('s.files', 'f')
            ->select('s')
            ->andWhere('t.teamid = :teamid')
            ->setParameter('teamid', $team->getTeamid())
            ->andWhere('p.probid = :probid')
            ->setParameter('probid', $problem->getProbid())
            ->andWhere('l.langid = :langid')
            ->setParameter('langid', $language->getLangid())
            ->andWhere('c.cid = :cid')
            ->setParameter('cid', $contest->getCid())
            ->orderBy('s.submittime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    protected function getTeamSubmission(Team $team, int $submitId): ?Submission
    {
        return $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.team', 't')
            ->select('s')
            ->andWhere('s.submitid = :submitid')
            ->setParameter('submitid', $submitId)
            ->andWhere('t.teamid = :teamid')
            ->setParameter('teamid', $team->getTeamid())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
