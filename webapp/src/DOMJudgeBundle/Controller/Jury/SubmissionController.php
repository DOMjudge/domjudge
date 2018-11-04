<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\Team;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\SubmissionService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class SubmissionController extends Controller
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param SubmissionService      $submissionService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        SubmissionService $submissionService
    ) {
        $this->entityManager     = $entityManager;
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->submissionService = $submissionService;
    }

    /**
     * @Route("/submissions/", name="jury_submissions")
     */
    public function indexAction(Request $request)
    {
        $viewTypes = [0 => 'newest', 1 => 'unverified', 2 => 'unjudged', 3 => 'all'];
        $view      = 0;
        if (($submissionViewCookie = $this->DOMJudgeService->getCookie('domjudge_submissionview')) &&
            isset($viewTypes[$submissionViewCookie])) {
            $view = $submissionViewCookie;
        }

        if ($request->query->has('view')) {
            $index = array_search($request->query->get('view'), $viewTypes);
            if ($index !== false) {
                $view = $index;
            }
        }

        $response = $this->DOMJudgeService->setCookie('domjudge_submissionview', (string)$view);

        $refresh = [
            'after' => 15,
            'url' => $this->generateUrl('jury_submissions', ['view' => $viewTypes[$view]])
        ];

        $restrictions = [];
        if ($viewTypes[$view] == 'unverified') {
            $restrictions['verified'] = 0;
        }
        if ($viewTypes[$view] == 'unjudged') {
            $restrictions['judged'] = 0;
        }

        $contests = $this->DOMJudgeService->getCurrentContests();
        if ($contest = $this->DOMJudgeService->getCurrentContest()) {
            $contests = [$contest->getCid() => $contest];
        }

        $limit = $viewTypes[$view] == 'newest' ? 50 : 0;

        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $this->submissionService->getSubmissionList($contests, $restrictions,
                                                                                            $limit);

        // Load preselected filters
        $filters          = $this->DOMJudgeService->jsonDecode((string)$this->DOMJudgeService->getCookie('domjudge_submissionsfilter') ?: '[]');
        $filteredProblems = $filteredLanguages = $filteredTeams = [];
        if (isset($filters['problem-id'])) {
            /** @var Problem[] $filteredProblems */
            $filteredProblems = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Problem', 'p')
                ->select('p')
                ->where('p.probid IN (:problemIds)')
                ->setParameter(':problemIds', $filters['problem-id'])
                ->getQuery()
                ->getResult();
        }
        if (isset($filters['language-id'])) {
            /** @var Language[] $filteredLanguages */
            $filteredLanguages = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Language', 'lang')
                ->select('lang')
                ->where('lang.langid IN (:langIds)')
                ->setParameter(':langIds', $filters['language-id'])
                ->getQuery()
                ->getResult();
        }
        if (isset($filters['team-id'])) {
            /** @var Team[] $filteredTeams */
            $filteredTeams = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Team', 't')
                ->select('t')
                ->where('t.teamid IN (:teamIds)')
                ->setParameter(':teamIds', $filters['team-id'])
                ->getQuery()
                ->getResult();
        }

        return $this->render('@DOMJudge/jury/submissions.html.twig', [
            'refresh' => $refresh,
            'viewTypes' => $viewTypes,
            'view' => $view,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($contests) > 1,
            'hasFilters' => !empty($filters),
            'filteredProblems' => $filteredProblems,
            'filteredLanguages' => $filteredLanguages,
            'filteredTeams' => $filteredTeams,
        ], $response);
    }
}
