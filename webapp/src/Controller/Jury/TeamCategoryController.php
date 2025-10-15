<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\DataTransferObject\SubmissionRestriction;
use App\Entity\Judging;
use App\Entity\Submission;
use App\Entity\Team;
use App\Entity\TeamCategory;
use App\Form\Type\TeamCategoryType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Twig\Attribute\AjaxTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/categories')]
class TeamCategoryController extends BaseController
{
    use JudgeRemainingTrait;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        KernelInterface $kernel,
        protected readonly EventLogService $eventLogService,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    /**
     * @return array{
     *     team_categories: list<array{
     *         data: array<string, array<string, mixed>>,
     *         actions: list<array<string, string>>,
     *         link: string,
     *         style: string
     *     }>,
     *     table_fields: array<string, array<string, mixed>>
     * }
     */
    #[Route(path: '', name: 'jury_team_categories')]
    #[Template(template: 'jury/team_categories.html.twig')]
    public function indexAction(): array
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
            'externalid' => ['title' => 'external ID', 'sort' => true],
            'icpcid' => ['title' => 'ICPC ID', 'sort' => true],
            'sortorder' => ['title' => 'sort', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'num_teams' => ['title' => '# teams', 'sort' => true],
            'visible' => ['title' => 'visible', 'sort' => true],
            'allow_self_registration' => ['title' => 'self-registration', 'sort' => true],
        ];

        $this->addSelectAllCheckbox($table_fields, 'categories');

        $propertyAccessor      = PropertyAccess::createPropertyAccessor();
        $team_categories_table = [];
        foreach ($teamCategories as $teamCategoryData) {
            /** @var TeamCategory $teamCategory */
            $teamCategory    = $teamCategoryData[0];
            $categorydata    = [];
            $categoryactions = [];

            $this->addEntityCheckbox($categorydata, $teamCategory, $teamCategory->getCategoryid(), 'category-checkbox');

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
        return [
            'team_categories' => $team_categories_table,
            'table_fields' => $table_fields,
        ];
    }

    /**
     * @return array{
     *     teamCategory: TeamCategory,
     *     submissions: PaginationInterface<int, Submission>,
     *     submissionCounts: array<string, int>,
     *     showContest: bool,
     *     showExternalResult: bool,
     *     showTestcases?: bool,
     *     refresh: array{after: int, url: string, ajax: bool}
     * }
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{categoryId<\d+>}', name: 'jury_team_category')]
    #[AjaxTemplate(
        normalTemplate: 'jury/team_category.html.twig',
        ajaxTemplate: 'jury/partials/submission_list.html.twig'
    )]
    public function viewAction(Request $request, SubmissionService $submissionService, int $categoryId): array
    {
        $teamCategory = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        /** @var PaginationInterface<int, Submission> $submissions */
        [$submissions, $submissionCounts] = $submissionService->getSubmissionList(
            $this->dj->getCurrentContests(honorCookie: true),
            new SubmissionRestriction(categoryId: $teamCategory->getCategoryid()),
            page: $request->query->getInt('page', 1),
        );

        $data = [
            'teamCategory' => $teamCategory,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->dj->getCurrentContests(honorCookie: true)) > 1,
            'showExternalResult' => $this->dj->shadowMode(),
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial.
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
        }

        return $data;
    }

    /**
     * @return array{
     *     teamCategory: TeamCategory,
     *     form: FormInterface
     * }|RedirectResponse
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{categoryId<\d+>}/edit', name: 'jury_team_category_edit')]
    #[Template(template: 'jury/team_category_edit.html.twig')]
    public function editAction(Request $request, int $categoryId): array|RedirectResponse
    {
        $teamCategory = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        $form = $this->createForm(TeamCategoryType::class, $teamCategory);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->saveEntity($teamCategory, $teamCategory->getCategoryid(), false);
            // Also emit an update event for all teams of the category, since the hidden property might have changed
            $teams = $teamCategory->getTeams();
            if (!$teams->isEmpty()) {
                $teamIds = array_map(fn(Team $team) => $team->getTeamid(), $teams->toArray());
                foreach ($this->contestsForEntity($teamCategory) as $contest) {
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

        return [
            'teamCategory' => $teamCategory,
            'form' => $form,
        ];
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/{categoryId<\d+>}/delete', name: 'jury_team_category_delete')]
    public function deleteAction(Request $request, int $categoryId): Response
    {
        $teamCategory = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        return $this->deleteEntities($request, [$teamCategory], $this->generateUrl('jury_team_categories'));
    }

    /**
     * @return array{form: FormInterface}|Response
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/add', name: 'jury_team_category_add')]
    #[Template(template: 'jury/team_category_add.html.twig')]
    public function addAction(Request $request): array|Response
    {
        $teamCategory = new TeamCategory();

        $form = $this->createForm(TeamCategoryType::class, $teamCategory);

        $form->handleRequest($request);

        if ($response = $this->processAddFormForExternalIdEntity(
            $form, $teamCategory,
            fn() => $this->generateUrl('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()])
        )) {
            return $response;
        }

        return [
            'form' => $form,
        ];
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/delete-multiple', name: 'jury_team_category_delete_multiple', methods: ['GET', 'POST'])]
    public function deleteMultipleAction(Request $request): Response
    {
        return $this->deleteMultiple(
            $request,
            TeamCategory::class,
            'categoryid',
            'jury_team_categories',
            'No categories could be deleted.'
        );
    }

    #[Route(path: '/{categoryId<\d+>}/request-remaining', name: 'jury_team_category_request_remaining')]
    public function requestRemainingRunsWholeTeamCategoryAction(string $categoryId): RedirectResponse
    {
        $category = $this->em->getRepository(TeamCategory::class)->find($categoryId);
        if (!$category) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }
        $contestId = $this->dj->getCurrentContest()->getCid();
        $this->judgeRemaining(contestId: $contestId, categoryId: $categoryId);
        return $this->redirectToRoute('jury_team_category', ['categoryId' => $categoryId]);
    }
}
