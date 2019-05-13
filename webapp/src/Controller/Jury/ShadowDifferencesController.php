<?php declare(strict_types=1);

namespace App\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use App\Controller\BaseController;
use App\Entity\ExternalJudgement;
use App\Entity\Judging;
use App\Entity\Submission;
use App\Service\DOMJudgeService;
use App\Service\SubmissionService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/shadow-differences")
 * @IsGranted("ROLE_ADMIN")
 */
class ShadowDifferencesController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var SubmissionService
     */
    protected $submissions;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    public function __construct(
        DOMJudgeService $dj,
        SubmissionService $submissions,
        SessionInterface $session,
        EntityManagerInterface $em
    ) {
        $this->dj          = $dj;
        $this->submissions = $submissions;
        $this->session     = $session;
        $this->em          = $em;
    }

    /**
     * @Route("", name="jury_shadow_differences")
     */
    public function indexAction(Request $request)
    {
        $shadowMode = DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL;
        $dataSource = $this->dj->dbconfig_get('data_source', DOMJudgeService::DATA_SOURCE_LOCAL);
        if ($dataSource != $shadowMode) {
            $this->addFlash('danger', sprintf(
                'Shadow differences only supported when data_source is %d',
                $shadowMode
            ));
            return $this->redirectToRoute('jury_index');
        }

        // Close the session, as this might take a while and we don't need the session below
        $this->session->save();

        $contest        = $this->dj->getCurrentContest();
        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $verdicts       = array_merge(['judging' => 'JU'], include $verdictsConfig);

        $used         = [];
        $verdictTable = [];
        // pre-fill $verdictTable to get a consistent ordering
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
            ->setParameter(':contest', $contest)
            ->getQuery()
            ->getResult();

        // Helper function to add verdicts
        $addVerdict = function ($unknownVerdict) use ($verdicts, &$verdictTable) {
            // add column to existing rows
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$verdict][$unknownVerdict] = [];
            }
            // add verdict to known verdicts
            $verdicts[$unknownVerdict] = $unknownVerdict;
            // add row
            $verdictTable[$unknownVerdict] = [];
            foreach ($verdicts as $verdict => $abbreviation) {
                $verdictTable[$unknownVerdict][$verdict] = [];
            }
        };

        // Build up the verdict matrix
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

            if ($externalJudgement && $externalJudgement->getResult()) {
                $externalResult = $externalJudgement->getResult();
            } else {
                $externalResult = 'judging';
            }

            // add verdicts to data structures if they are unknown up to now
            foreach ([$externalResult, $localResult] as $result) {
                if (!array_key_exists($result, $verdicts)) {
                    $addVerdict($result);
                }
            }

            // mark them as used, so we can filter out unused cols/rows later
            $used[$externalResult] = true;
            $used[$localResult]    = true;

            // append submitid to list of orig->new verdicts
            $verdictTable[$externalResult][$localResult][] = $submitid;
        }

        $viewTypes = [0 => 'unjudged local', 1 => 'unjudged external', 2 => 'diff', 3 => 'all'];
        $view      = 2;
        if ($request->query->has('view')) {
            $index = array_search($request->query->get('view'), $viewTypes);
            if ($index !== false) {
                $view = $index;
            }
        }

        $restrictions = [];
        if ($viewTypes[$view] == 'unjudged local') {
            $restrictions['judged'] = 0;
        }
        if ($viewTypes[$view] == 'unjudged external') {
            $restrictions['externally_judged'] = 0;
        }
        if ($viewTypes[$view] == 'diff') {
            $restrictions['external_diff'] = 1;
        }
        if ($request->query->get('external', 'all') !== 'all') {
            $restrictions['external_result'] = $request->query->get('external');
        }
        if ($request->query->get('local', 'all') !== 'all') {
            $restrictions['result'] = $request->query->get('local');
        }

        $contests = [$contest->getCid() => $contest];

        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $this->submissions->getSubmissionList(
            $contests, $restrictions, 0, true
        );

        $data = [
            'verdicts' => $verdicts,
            'used' => $used,
            'verdictTable' => $verdictTable,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'external' => $request->query->get('external', 'all'),
            'local' => $request->query->get('local', 'all'),
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
