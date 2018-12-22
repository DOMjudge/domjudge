<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\TeamCategory;
use DOMJudgeBundle\Form\Type\TeamCategoryType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\SubmissionService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class TeamCategoryController extends Controller
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
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * TeamCategoryController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService        $DOMJudgeService
     * @param EventLogService        $eventLogService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService
    ) {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
        $this->eventLogService = $eventLogService;
    }

    /**
     * @Route("/categories/", name="jury_team_categories")
     */
    public function indexAction(Request $request, Packages $assetPackage)
    {
        $em             = $this->entityManager;
        $teamCategories = $em->createQueryBuilder()
            ->select('c', 'COUNT(t.teamid) AS num_teams')
            ->from('DOMJudgeBundle:TeamCategory', 'c')
            ->leftJoin('c.teams', 't')
            ->orderBy('c.sortorder', 'ASC')
            ->addOrderBy('c.categoryid', 'ASC')
            ->groupBy('c.categoryid')
            ->getQuery()->getResult();
        $table_fields   = [
            'categoryid' => ['title' => 'ID', 'sort' => true],
            'sortorder' => ['title' => 'sort', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'num_teams' => ['title' => '# teams', 'sort' => true],
            'visible' => ['title' => 'visible', 'sort' => true],
        ];

        // Insert external ID field when configured to use it
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
            // Get whatever fields we can from the category object itself
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
                    'link' => $this->generateUrl('legacy.jury_delete', [
                        'table' => 'team_category',
                        'categoryid' => $teamCategory->getCategoryid(),
                        'referrer' => 'categories',
                        'desc' => $teamCategory->getName(),
                    ])
                ];
            }

            $categorydata['num_teams'] = ['value' => $teamCategoryData['num_teams']];
            $categorydata['visible']   = ['value' => $teamCategory->getVisible() ? 'yes' : 'no'];

            $team_categories_table[] = [
                'data' => $categorydata,
                'actions' => $categoryactions,
                'link' => $this->generateUrl('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()]),
                'style' => $teamCategory->getColor() ? sprintf('background-color: %s;', $teamCategory->getColor()) : '',
            ];
        }
        return $this->render('@DOMJudge/jury/team_categories.html.twig', [
            'team_categories' => $team_categories_table,
            'table_fields' => $table_fields,
            'num_actions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
            'edited' => $request->query->getBoolean('edited'),
        ]);
    }

    /**
     * @Route("/categories/{categoryId}", name="jury_team_category", requirements={"categoryId": "\d+"})
     * @param Request           $request
     * @param SubmissionService $submissionService
     * @param int               $categoryId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function viewAction(Request $request, SubmissionService $submissionService, int $categoryId)
    {
        /** @var TeamCategory $teamCategory */
        $teamCategory = $this->entityManager->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        $restrictions = ['categoryid' => $teamCategory->getCategoryid()];
        /** @var Submission[] $submissions */
        list($submissions, $submissionCounts) = $submissionService->getSubmissionList(
            $this->DOMJudgeService->getCurrentContests(),
            $restrictions
        );

        $data = [
            'teamCategory' => $teamCategory,
            'submissions' => $submissions,
            'submissionCounts' => $submissionCounts,
            'showContest' => count($this->DOMJudgeService->getCurrentContest()) > 1,
            'edited' => $request->query->getBoolean('edited'),
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_team_category', ['categoryId' => $teamCategory->getCategoryid()]),
                'ajax' => true,
            ],
        ];

        // For ajax requests, only return the submission list partial
        if ($request->isXmlHttpRequest()) {
            $data['showTestcases'] = false;
            return $this->render('@DOMJudge/jury/partials/submission_list.html.twig', $data);
        }

        return $this->render('@DOMJudge/jury/team_category.html.twig', $data);
    }

    /**
     * @Route("/categories/{categoryId}/edit", name="jury_team_category_edit", requirements={"categoryId": "\d+"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param int     $categoryId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, int $categoryId)
    {
        /** @var TeamCategory $teamCategory */
        $teamCategory = $this->entityManager->getRepository(TeamCategory::class)->find($categoryId);
        if (!$teamCategory) {
            throw new NotFoundHttpException(sprintf('Team category with ID %s not found', $categoryId));
        }

        $form = $this->createForm(TeamCategoryType::class, $teamCategory);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->DOMJudgeService->auditlog('team_category', $teamCategory->getCategoryid(),
                                             'updated');
            return $this->redirect($this->generateUrl('jury_team_category',
                                                      ['categoryId' => $teamCategory->getCategoryid(), 'edited' => true]));
        }

        return $this->render('@DOMJudge/jury/team_category_edit.html.twig', [
            'teamCategory' => $teamCategory,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/categories/add", name="jury_team_category_add")
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        $teamCategory = new TeamCategory();

        $form = $this->createForm(TeamCategoryType::class, $teamCategory);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($teamCategory);
            $this->entityManager->flush();
            $this->DOMJudgeService->auditlog('team_category', $teamCategory->getCategoryid(),
                                             'added');
            return $this->redirect($this->generateUrl('jury_team_category',
                                                      ['categoryId' => $teamCategory->getCategoryid()]));
        }

        return $this->render('@DOMJudge/jury/team_category_add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
