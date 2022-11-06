<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Judging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Form\Type\TeamCategoryType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/categories")
 * @IsGranted("ROLE_JURY")
 */
class TeamCategoryController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected KernelInterface $kernel;
    protected EventLogService $eventLogService;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        ConfigurationService $config,
        KernelInterface $kernel,
        EventLogService $eventLogService
    ) {
        $this->em              = $em;
        $this->dj              = $dj;
        $this->config          = $config;
        $this->eventLogService = $eventLogService;
        $this->kernel          = $kernel;
    }

    /**
     * @Route("", name="jury_team_categories")
     */
    public function indexAction(): Response
    {
        $em             = $this->em;
        $teamCategories = $em->createQueryBuilder()
            ->select('c', 'COUNT(t.teamid) AS num_teams')
            ->from(TeamCategory::class, 'c')
            ->leftJoin('c.teams', 't')
            ->orderBy('c.sortorder', 'ASC')
            ->addOrderBy('c.categoryid', 'ASC')
            ->groupBy('c.categoryid')
            ->getQuery()->getResult();
        $table_fields   = [
            'categoryid' => ['title' => 'ID', 'sort' => true],
            'icpcid' => ['title' => 'ICPC ID', 'sort' => true],
            'sortorder' => ['title' => 'sort', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'num_teams' => ['title' => '# teams', 'sort' => true],
            'visible' => ['title' => 'visible', 'sort' => true],
            'allow_self_registration' => ['title' => 'self-registration', 'sort' => true],
        ];

        // Insert external ID field when configured to use it.
        if ($externalIdField = $this->eventLogService->externalIdFieldForEntity(TeamCategory::class)) {
            $table_fields = array_slice($table_fields, 0, 1, true) +
                [$externalIdField => ['title' => 'external ID', 'sort' => true]] +
                array_slice($table_fields, 1, null, true);
        }

        $propertyAccessor      = PropertyAccess::createPropertyAccessor();
        $team_categories_table = [];
        foreach ($teamCategories as $teamCategoryData) {
            /** @var TeamCategory $teamCategory */
            $teamCategory    = $teamCategoryData[0];
            $categorydata    = [];
            $categoryactions = [];
            // Get whatever fields we can from the category object itself.
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($teamCategory, $k)) {
                    $categorydata[$k] = ['value' => $propertyAccessor->getValue($teamCategory, $k)];
                }
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $categoryactions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this category',
                    'link' => $this->generateUrl('jury_team_category_edit', [
                        'categoryId' => $teamCategory->getCategoryid(),
                    ])
                ];
                $categoryactions[] = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this category',
                    'link' => $this->generateUrl('jury_team_category_delete', [
                        'categoryId' => $teamCategory->getCategoryid(),
                    ]),
                    'ajaxModal' => true,
                ];
            }

            $categorydata['num_teams']               = ['value' => $teamCategoryData['num_teams']];
            $categorydata['visible']                 = ['value' => $teamCategory->getVisible() ? 'yes' : 'no'];
            $categorydata['allow_self_registration'] = ['value' => $teamCategory->getAllowSelfRegistration() ? 'yes' : 'no'];

            $team_categories_table[] = [
                'data' => $categorydata,
                'actions' => $categoryactions,
                'link' => $this->generateUrl('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()]),
                'style' => $teamCategory->getColor() ? sprintf('background-color: %s;', $teamCategory->getColor()) : '',
            ];
        }
        return $this->render('jury/team_categories.html.twig', [
            'team_categories' => $team_categories_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
        ]);
    }

    /**
     * @Route("/{categoryId<\d+>}", name="jury_team_category")
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function viewAction(Request $request, SubmissionService $submissionService, int $categoryId): Response
    {
        /** @var TeamCategory $teamCategory */
        $teamCategory = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        $restrictions = ['categoryid' => $teamCategory->getCategoryid()];
        /** @var Submission[] $submissions */
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(),
            $restrictions
        );

        $data = [
            'teamCategory' => $teamCategory,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->dj->getCurrentContests()) > 1,
            'showExternalResult' => $this->config->get('data_source') ==
                DOMJudgeService::DATA_SOURCE_CONFIGURATION_AND_LIVE_EXTERNAL,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial.
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('jury/team_category.html.twig', $data);
    }

    /**
     * @Route("/{categoryId<\d+>}/edit", name="jury_team_category_edit")
     * @IsGranted("ROLE_ADMIN")
     */
    public function editAction(Request $request, int $categoryId): Response
    {
        /** @var TeamCategory $teamCategory */
        $teamCategory = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        $form = $this->createForm(TeamCategoryType::class, $teamCategory);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $teamCategory,
                              $teamCategory->getCategoryid(), false);
            // Also emit an update event for all teams of the category, since the hidden property might have changed
            $teams = $teamCategory->getTeams();
            if (!$teams->isEmpty()) {
                $teamIds = array_map(fn(Team $team) => $team->getTeamid(), $teams->toArray());
                foreach ($this->contestsForEntity($teamCategory, $this->dj) as $contest) {
                    $this->eventLogService->log(
                        'teams',
                        $teamIds,
                        EventLogService::ACTION_UPDATE,
                        $contest->getCid(),
                        null,
                        null,
                        false
                    );
                }
            }
            $this->addFlash('scoreboard_refresh', 'If the category sort order was changed, it may be necessary to recalculate any cached scoreboards.');
            return $this->redirectToRoute('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()]);
        }

        return $this->render('jury/team_category_edit.html.twig', [
            'teamCategory' => $teamCategory,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{categoryId<\d+>}/delete", name="jury_team_category_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteAction(Request $request, int $categoryId): Response
    {
        /** @var TeamCategory $teamCategory */
        $teamCategory = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
                                     [$teamCategory], $this->generateUrl('jury_team_categories'));
    }

    /**
     * @Route("/add", name="jury_team_category_add")
     * @IsGranted("ROLE_ADMIN")
     */
    public function addAction(Request $request): Response
    {
        $teamCategory = new TeamCategory();

        $form = $this->createForm(TeamCategoryType::class, $teamCategory);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($teamCategory);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $teamCategory, null, true);
            return $this->redirectToRoute('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()]);
        }

        return $this->render('jury/team_category_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{categoryId<\d+>}/request-remaining", name="jury_team_category_request_remaining")
     */
    public function requestRemainingRunsWholeTeamCategoryAction(string $categoryId): RedirectResponse
    {
        /** @var TeamCategory $category */
        $category = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$category) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }
        $contestId = $this->dj->getCurrentContest()->getCid();
        $query = $this->em->createQueryBuilder()
                          ->from(Judging::class, 'j')
                          ->select('j')
                          ->join('j.submission', 's')
                          ->join('s.team', 't')
                          ->join('t.category', 'tc')
                          ->andWhere('j.valid = true')
                          ->andWhere('j.result != :compiler_error')
                          ->andWhere('tc.category = :categoryId')
                          ->setParameter('compiler_error', 'compiler-error')
                          ->setParameter('categoryId', $categoryId);
        if ($contestId > -1) {
            $query->andWhere('s.contest = :contestId')
                  ->setParameter('contestId', $contestId);
        }
        $judgings = $query->getQuery()
                          ->getResult();
        $this->judgeRemaining($judgings);
        return $this->redirect($this->generateUrl('jury_team_category', ['categoryId' => $categoryId]));
    }
}
