<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\JudgeTask;
use App\Entity\QueueTask;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/jury/queuetasks')]
class QueueTaskController extends BaseController
{
    final public const PRIORITY_MAP = [
        JudgeTask::PRIORITY_LOW => 'low',
        JudgeTask::PRIORITY_DEFAULT => 'default',
        JudgeTask::PRIORITY_HIGH => 'high',
    ];

    final public const PRIORITY_ICON_MAP = [
        JudgeTask::PRIORITY_LOW => 'thermometer-empty',
        JudgeTask::PRIORITY_DEFAULT => 'thermometer-half',
        JudgeTask::PRIORITY_HIGH => 'thermometer-full',
    ];

    public function __construct(
        EntityManagerInterface $em,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'jury_queue_tasks')]
    public function indexAction(): Response
    {
        /** @var QueueTask[] $queueTasks */
        $queueTasks = $this->em->createQueryBuilder()
            ->select('qt', 't')
            ->from(QueueTask::class, 'qt')
            ->innerJoin('qt.team', 't')
            ->addOrderBy('qt.priority')
            ->addOrderBy('qt.teamPriority')
            ->getQuery()->getResult();

        $tableFields = [
            'queuetaskid' => ['title' => 'ID'],
            'team.name' => ['title' => 'team'],
            'judging.judgingid' => ['title' => 'judgingid'],
            'priority' => ['title' => 'priority'],
            'teampriority' => ['title' => 'team priority'],
            'starttime' => ['title' => 'start time'],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $queueTasksTable = [];
        foreach ($queueTasks as $queueTask) {
            $queueTaskData = [];
            $queueTaskActions = [];
            // Get whatever fields we can from the language object itself
            foreach ($tableFields as $k => $v) {
                if ($propertyAccessor->isReadable($queueTask, $k)) {
                    $queueTaskData[$k] = ['value' => $propertyAccessor->getValue($queueTask, $k)];
                }
            }

            // Use priority map to set priority.
            $queueTaskData['priority']['value'] = static::PRIORITY_MAP[$queueTaskData['priority']['value']];

            // Add some links.
            $queueTaskData['team.name']['link'] = $this->generateUrl('jury_team', ['teamId' => $queueTask->getTeam()->getTeamid()]);
            $queueTaskData['judgingid']['link'] = $this->generateUrl('jury_submission_by_judging', ['jid' => $queueTask->getJudging()->getJudgingid()]);

            // Format start time.
            if (!empty($queueTaskData['starttime']['value'])) {
                $queueTaskData['starttime']['value'] = Utils::printtime($queueTaskData['starttime']['value'], "Y-m-d H:i:s (T)");
            } else {
                $queueTaskData['starttime']['value'] = 'not started yet';
            }

            foreach (static::PRIORITY_MAP as $priority => $readable) {
                $priorityAction = [
                    'icon' => static::PRIORITY_ICON_MAP[$priority],
                    'title' => 'change priority to ' . $readable,
                    'link' => $this->generateUrl('jury_queue_task_change_priority', [
                        'queueTaskId' => $queueTask->getQueueTaskid(),
                        'priority' => $priority,
                    ]),
                ];

                if ($priority === $queueTask->getPriority()) {
                    unset($priorityAction['link']);
                    $priorityAction['disabled'] = true;
                }

                $queueTaskActions[] = $priorityAction;
            }

            $queueTaskActions[] = [
                'icon' => 'list',
                'title' => 'view judgetasks',
                'link' => $this->generateUrl('jury_queue_task_judge_tasks', [
                    'queueTaskId' => $queueTask->getQueueTaskid(),
                ]),
            ];

            $queueTasksTable[] = [
                'data' => $queueTaskData,
                'actions' => $queueTaskActions,
            ];
        }

        return $this->render('jury/queue_tasks.html.twig', [
            'queueTasksTable' => $queueTasksTable,
            'tableFields' => $tableFields,
            'numActions' => 4,
        ]);
    }

    #[Route(path: '/{queueTaskId}/change-priority/{priority}', name: 'jury_queue_task_change_priority')]
    public function changePriorityAction(int $queueTaskId, int $priority): RedirectResponse
    {
        $queueTask = $this->em->getRepository(QueueTask::class)->find($queueTaskId);
        if (!$queueTask) {
            $this->addFlash('danger', 'Queue task does not exist (anymore)');
            return $this->redirectToRoute('jury_queue_tasks');
        }

        if (!isset(static::PRIORITY_MAP[$priority])) {
            throw new BadRequestHttpException('Invalid priority');
        }

        $queueTask->setPriority($priority);

        /** @var JudgeTask[] $judgeTasks */
        $judgeTasks = $this->em->createQueryBuilder()
            ->select('jt')
            ->from(JudgeTask::class, 'jt')
            ->addOrderBy('jt.judgetaskid')
            ->andWhere('jt.jobid = :jobid')
            ->setParameter('jobid', $queueTask->getJudging()->getJudgingid())
            ->getQuery()->getResult();

        foreach ($judgeTasks as $judgeTask) {
            $judgeTask->setPriority($priority);
        }

        $this->em->flush();

        return $this->redirectToRoute('jury_queue_tasks');
    }

    #[Route(path: '/{queueTaskId}/judgetasks', name: 'jury_queue_task_judge_tasks')]
    public function viewJudgeTasksAction(int $queueTaskId): Response
    {
        $queueTask = $this->em->getRepository(QueueTask::class)->find($queueTaskId);
        if (!$queueTask) {
            $this->addFlash('danger', 'Queue task does not exist (anymore)');
            return $this->redirectToRoute('jury_queue_tasks');
        }

        /** @var JudgeTask[] $judgeTasks */
        $judgeTasks = $this->em->createQueryBuilder()
            ->select('jt', 'jh', 'jr')
            ->from(JudgeTask::class, 'jt')
            ->leftJoin('jt.judgehost', 'jh')
            ->innerJoin('jt.judging_runs', 'jr')
            ->addOrderBy('jt.judgetaskid')
            ->andWhere('jt.jobid = :jobid')
            ->setParameter('jobid', $queueTask->getJudging()->getJudgingid())
            ->getQuery()->getResult();

        $tableFields = [
            'judgetaskid' => ['title' => 'ID'],
            'judgehost.hostname' => ['title' => 'judgehost'],
            'valid' => ['title' => 'valid'],
            'first_judging_run.runid' => ['title' => 'run ID'],
            'starttime' => ['title' => 'starttime'],
        ];

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $judgeTasksTable = [];
        foreach ($judgeTasks as $judgeTask) {
            $judgeTaskData = [];
            // Get whatever fields we can from the language object itself
            foreach ($tableFields as $k => $v) {
                if ($propertyAccessor->isReadable($judgeTask, $k)) {
                    $judgeTaskData[$k] = ['value' => $propertyAccessor->getValue($judgeTask, $k)];
                }
            }

            // Format start time.
            if (!empty($judgeTaskData['starttime']['value'])) {
                $judgeTaskData['starttime']['value'] = Utils::printtime($judgeTaskData['starttime']['value'], "Y-m-d H:i:s (T)");
            } else {
                $judgeTaskData['starttime']['value'] = 'not started yet';
            }

            // Add link or set empty value for judgehost.
            if (isset($judgeTaskData['judgehost.hostname']['value'])) {
                $judgeTaskData['judgehost.hostname']['value'] = Utils::printhost($judgeTaskData['judgehost.hostname']['value']);
                $judgeTaskData['judgehost.hostname']['link'] = $this->generateUrl('jury_judgehost', ['judgehostid' => $judgeTask->getJudgehost()->getJudgehostid()]);
            }

            $judgeTaskData['judgehost.hostname']['default'] = '-';
            $judgeTaskData['judgehost.hostname']['cssclass'] = 'text-monospace small';

            // Map valid field.
            $judgeTaskData['valid']['value'] = $judgeTaskData['valid']['value'] ? 'yes' : 'no';

            $judgeTasksTable[] = [
                'data' => $judgeTaskData,
                'actions' => [],
            ];
        }

        $firstJudgeTask = $judgeTasks[0] ?? null;

        return $this->render('jury/judge_tasks.html.twig', [
            'firstJudgeTask' => $firstJudgeTask,
            'judgeTaksPriority' => isset($firstJudgeTask) ? static::PRIORITY_MAP[$firstJudgeTask->getPriority()] : null,
            'queueTask' => $queueTask,
            'judgeTasksTable' => $judgeTasksTable,
            'tableFields' => $tableFields,
            'numActions' => 0,
        ]);
    }
}
