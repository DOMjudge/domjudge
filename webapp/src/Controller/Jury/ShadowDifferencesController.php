<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use App\Controller\BaseController;
use App\Entity\ExternalJudgement;
use App\Entity\Judging;
use App\Entity\Submission;
use App\Service\DOMJudgeService;
use App\Service\SubmissionService;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/jury/shadow-differences')]
class ShadowDifferencesController extends BaseController
{
    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly SubmissionService $submissions,
        protected readonly RequestStack $requestStack,
        protected readonly EntityManagerInterface $em
    ) {}

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '', name: 'jury_shadow_differences')]
    public function indexAction(
        Request $request,
        #[MapQueryParameter(name: 'view')]
        ?string $viewFromRequest = null,
        #[MapQueryParameter(name: 'verificationview')]
        ?string $verificationViewFromRequest = null,
        #[MapQueryParameter]
        string $external = 'all',
        #[MapQueryParameter]
        string $local = 'all',
    ): Response {
        $shadowMode = DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL;
        $dataSource = $this->config->get('data_source');
        if ($dataSource != $shadowMode) {
            $this->addFlash('danger', sprintf(
                'Shadow differences only supported when data_source is %d',
                $shadowMode
            ));
            return $this->redirectToRoute('jury_index');
        }

        if (!$this->dj->getCurrentContest()) {
            $this->addFlash('danger', 'Shadow differences need an active contest.');
            return $this->redirectToRoute('jury_index');
        }

        // Close the session, as this might take a while and we don't need the session below.
        $this->requestStack->getSession()->save();

        $contest  = $this->dj->getCurrentContest();
        $verdicts = array_merge(['judging' => 'JU'], $this->dj->getVerdicts(mergeExternal: true));

        $verdicts['import-error'] = 'IE';

        $used         = [];
        $verdictTable = [];
        // Pre-fill $verdictTable to get a consistent ordering.
        foreach ($verdicts as $verdict => $abbrev) {
            foreach ($verdicts as $verdict2 => $abbrev2) {
                $verdictTable[$verdict][$verdict2] = [];
            }
        }

        /** @var Submission[] $submissions */
        $submissions = $this->em->createQueryBuilder()
            ->from(Submission::class, 's', 's.submitid')
            ->leftJoin('s.external_judgements', 'ej', Join::WITH, 'ej.valid = 1')
            ->leftJoin('s.judgings', 'j', Join::WITH, 'j.valid = 1')
            ->select('s', 'ej', 'j')
            ->andWhere('s.contest = :contest')
            ->andWhere('s.externalid IS NOT NULL')
            ->setParameter('contest', $contest)
            ->getQuery()
            ->getResult();

        // Helper function to add verdicts.
        $addVerdict = function ($unknownVerdict) use ($verdicts, &$verdictTable) {
            // Add column to existing rows.
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$verdict][$unknownVerdict] = [];
            }
            // Add verdict to known verdicts.
            $verdicts[$unknownVerdict] = $unknownVerdict;
            // Add row.
            $verdictTable[$unknownVerdict] = [];
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$unknownVerdict][$verdict] = [];
            }
        };

        // Build up the verdict matrix.
        foreach ($submissions as $submitid => $submission) {
            /** @var ExternalJudgement|null $externalJudgement */
            $externalJudgement = $submission->getExternalJudgements()->first();
            /** @var Judging|null $localJudging */
            $localJudging = $submission->getJudgings()->first();

            if ($localJudging && $localJudging->getResult()) {
                $localResult = $localJudging->getResult();
            } else {
                $localResult = 'judging';
            }

            if ($submission->isImportError()) {
                $localResult = 'import-error';
            }

            if ($externalJudgement && $externalJudgement->getResult()) {
                $externalResult = $externalJudgement->getResult();
            } else {
                $externalResult = 'judging';
            }

            // Add verdicts to data structures if they are unknown up to now.
            foreach ([$externalResult, $localResult] as $result) {
                if (!array_key_exists($result, $verdicts)) {
                    $addVerdict($result);
                }
            }

            // Mark them as used, so we can filter out unused cols/rows later.
            $used[$externalResult] = true;
            $used[$localResult]    = true;

            // Append submitid to list of orig->new verdicts.
            $verdictTable[$externalResult][$localResult][] = $submitid;
        }

        $viewTypes = [0 => 'unjudged local', 1 => 'unjudged external', 2 => 'diff', 3 => 'all'];
        $view      = 2;
        if ($viewFromRequest) {
            $index = array_search($viewFromRequest, $viewTypes);
            if ($index !== false) {
                $view = $index;
            }
        }

        $verificationViewTypes = [0 => 'all', 1 => 'unverified', 2 => 'verified'];
        $verificationView      = 0;
        if ($verificationViewFromRequest) {
            $index = array_search($verificationViewFromRequest, $verificationViewTypes);
            if ($index !== false) {
                $verificationView = $index;
            }
        }

        $restrictions = ['with_external_id' => true];
        if ($viewTypes[$view] == 'unjudged local') {
            $restrictions['judged'] = false;
        }
        if ($viewTypes[$view] == 'unjudged external') {
            $restrictions['externally_judged'] = false;
        }
        if ($viewTypes[$view] == 'diff') {
            $restrictions['external_diff'] = true;
        }
        if ($verificationViewTypes[$verificationView] == 'unverified') {
            $restrictions['externally_verified'] = false;
        }
        if ($verificationViewTypes[$verificationView] == 'verified') {
            $restrictions['externally_verified'] = true;
        }
        if ($external !== 'all') {
            $restrictions['external_result'] = $external;
        }
        if ($local !== 'all') {
            $restrictions['result'] = $local;
        }

        /** @var Submission[] $submissions */
        [$submissions, $submissionCounts] = $this->submissions->getSubmissionList(
            $this->dj->getCurrentContests(honorCookie: true),
            $restrictions,
            limit: 0,
            showShadowUnverified: true
        );

        $data = [
            'verdicts' => $verdicts,
            'used' => $used,
            'verdictTable' => $verdictTable,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'verificationViewTypes' => $verificationViewTypes,
            'verificationView' => $verificationView,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'external' => $external,
            'local' => $local,
            'showExternalResult' => true,
            'showContest' => false,
            'showTestcases' => true,
            'showExternalTestcases' => true,
            'refresh' => [
                'after' => 15,
                'url' => $request->getRequestUri(),
                'ajax' => true,
            ],
        ];
        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('jury/partials/shadow_submissions.html.twig', $data);
        } else {
            return $this->render('jury/shadow_differences.html.twig', $data);
        }
    }
}
