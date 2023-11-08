<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\ProblemAttachment;
use App\Entity\ProblemAttachmentContent;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use App\Entity\TestcaseGroup;
use App\Form\Type\ProblemAttachmentType;
use App\Form\Type\ProblemType;
use App\Form\Type\ProblemUploadType;
use App\Form\Type\TestcaseGroupType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportProblemService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * @Route("/jury/problems")
 * @IsGranted("ROLE_JURY")
 */
class ProblemTestcaseGroupController extends BaseController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected KernelInterface $kernel;
    protected EventLogService $eventLogService;
    protected SubmissionService $submissionService;
    protected ImportProblemService $importProblemService;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService        $dj,
        ConfigurationService   $config,
        KernelInterface        $kernel,
        EventLogService        $eventLogService,
        SubmissionService      $submissionService,
        ImportProblemService   $importProblemService
    )
    {
        $this->em = $em;
        $this->dj = $dj;
        $this->config = $config;
        $this->kernel = $kernel;
        $this->eventLogService = $eventLogService;
        $this->submissionService = $submissionService;
        $this->importProblemService = $importProblemService;
    }

    /**
     * @Route("/{probId<\d+>}/testcase-groups", name="jury_problem_testcase_groups")
     */
    public function indexAction(Request $request, int $probId): Response
    {
        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        $lockedContests = [];
        foreach ($problem->getContestProblems() as $contestproblem) {
            /** @var ContestProblem $contestproblem */
            if ($contestproblem->getContest()->isLocked()) {
                $lockedContests[] = 'c' . $contestproblem->getContest()->getCid();
                break;
            }
        }
        $problemIsLocked = !empty($lockedContests);

        $testcaseGroups = $this->em->getRepository(TestcaseGroup::class)->findBy(['problem' => $problem]);

        $tableFields = [
            'testcasegroupid' => ['title' => 'ID', 'sort' => true, 'default_sort' => true],
            'name' => ['title' => 'name', 'sort' => true],
            'points_percentage' => ['title' => '% of points', 'sort' => true],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $testcaseGroupsTable = [];
        foreach ($testcaseGroups as $tgp) {
            $testcaseGroupData = [];
            $testCaseGroupActions = [];
            // Get whatever fields we can from the problem object itself.
            foreach ($tableFields as $k => $v) {
                if ($propertyAccessor->isReadable($tgp, $k)) {
                    $testcaseGroupData[$k] = ['value' => $propertyAccessor->getValue($tgp, $k)];
                }
            }

            // Create action links
            if ($this->isGranted('ROLE_ADMIN')) {
                $testCaseGroupActions[] = [
                    'icon' => 'edit',
                    'title' => 'edit this testcase group',
                    'link' => $this->generateUrl('jury_problem_testcase_group_edit', [
                        'probId' => $problem->getProbid(),
                        'testcaseGroupId' => $tgp->getTestcasegroupid(),
                    ]),
                ];

                $deleteAction = [
                    'icon' => 'trash-alt',
                    'title' => 'delete this testcase group',
                    'link' => $this->generateUrl('jury_problem_testcase_group_delete', [
                        'probId' => $problem->getProbid(),
                        'testcaseGroupId' => $tgp->getTestcasegroupid(),
                    ]),
                    'ajaxModal' => true,
                ];
                if ($problemIsLocked) {
                    $deleteAction['title'] .= ' - problem belongs to a locked contest';
                    $deleteAction['disabled'] = true;
                    unset($deleteAction['link']);
                }
                $testCaseGroupActions[] = $deleteAction;
            }

            // Save this to our list of rows
            $testcaseGroupsTable[] = [
                'data' => $testcaseGroupData,
                'actions' => $testCaseGroupActions,
                'link' => $this->generateUrl('jury_problem_testcase_group_edit', [
                    'probId' => $problem->getProbid(),
                    'testcaseGroupId' => $tgp->getTestcasegroupid(),
                ]),
            ];
        }

        if ($problemIsLocked) {
            $this->addFlash('warning',
                'Problem belongs to locked contest ('
                . join($lockedContests)
                . ', disallowing editing.');
        }

        $data = [
            'problem' => $problem,
            'testcaseGroups' => $testcaseGroupsTable,
            'tableFields' => $tableFields,
            'numActions' => $this->isGranted('ROLE_ADMIN') ? 2 : 0,
//            'addForm' => $this->createForm(TestcaseGroupType::class)->createView(),
            'allowEdit' => $this->isGranted('ROLE_ADMIN') && empty($lockedContest),
        ];

        return $this->render('jury/problem_testcase_groups.html.twig', $data);
    }

    /**
     * @Route("/{probId<\d+>}/testcase-groups/{testcaseGroupId<\d+>}/delete", name="jury_problem_testcase_group_delete")
     * @IsGranted("ROLE_ADMIN")
     */
    public function deleteAction(Request $request, int $probId, int $testcaseGroupId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot delete problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $probId]);
            }
        }

        $testcaseGroup = $problem->getTestcaseGroups()->filter(fn(TestcaseGroup $tgp) => $tgp->getTestcasegroupid() === $testcaseGroupId)->first();
        if (!$testcaseGroup) {
            throw new NotFoundHttpException(sprintf('Testcase group with ID %s not found in problem %s', $testcaseGroupId, $probId));
        }

        return $this->deleteEntities($request, $this->em, $this->dj, $this->eventLogService, $this->kernel,
            [$testcaseGroup], $this->generateUrl('jury_problem_testcase_groups', ['probId' => $probId]));
    }

    /**
     * @Route("/{probId<\d+>}/testcase-groups/add", name="jury_problem_testcase_group_add")
     * @Route("/{probId<\d+>}/testcase-groups/{testcaseGroupId<\d+>}/edit", name="jury_problem_testcase_group_edit")
     * @IsGranted("ROLE_ADMIN")
     */
    public function addEditAction(Request $request, int $probId, ?int $testcaseGroupId = null): Response
    {
        $editing = $testcaseGroupId !== null;

        /** @var Problem $problem */
        $problem = $this->em->getRepository(Problem::class)->find($probId);
        if (!$problem) {
            throw new NotFoundHttpException(sprintf('Problem with ID %s not found', $probId));
        }

        foreach ($problem->getContestProblems() as $contestProblem) {
            /** @var ContestProblem $contestProblem */
            if ($contestProblem->getContest()->isLocked()) {
                $this->addFlash('danger', 'Cannot edit problem, it belongs to locked contest c' . $contestProblem->getContest()->getCid());
                return $this->redirectToRoute('jury_problem', ['probId' => $problem->getProbid()]);
            }
        }

        if ($editing) {
            $testcaseGroup = $problem->getTestcaseGroups()->filter(fn(TestcaseGroup $tgp) => $tgp->getTestcasegroupid() === $testcaseGroupId)->first();
            if (!$testcaseGroup) {
                throw new NotFoundHttpException(sprintf('Testcase group with ID %s not found in problem %s', $testcaseGroupId, $probId));
            }
        } else {
            $testcaseGroup = new TestcaseGroup();
            $testcaseGroup->setProblem($problem);
        }

        $form = $this->createForm(TestcaseGroupType::class, $testcaseGroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($testcaseGroup);
            $this->saveEntity($this->em, $this->eventLogService, $this->dj, $testcaseGroup, null, true);
            return $this->redirect($this->generateUrl(
                'jury_problem_testcase_groups',
                ['probId' => $problem->getProbid()]
            ));
        }

        return $this->render('jury/problem_testcase_group_add.html.twig', [
            'editing' => $editing,
            'problem' => $problem,
            'form' => $form->createView(),
        ]);
    }
}
