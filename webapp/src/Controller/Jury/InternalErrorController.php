<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Doctrine\DBAL\Types\InternalErrorStatusType;
use App\Entity\InternalError;
use App\Entity\Judgehost;
use App\Entity\JudgeTask;
use App\Entity\Problem;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\RejudgingService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/internal-errors')]
class InternalErrorController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj,
        protected readonly RejudgingService $rejudgingService,
        protected readonly RequestStack $requestStack,
        protected readonly EventLogService $eventLogService,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '', name: 'jury_internal_errors')]
    public function indexAction(): Response
    {
        /** @var InternalError[] $internalErrors */
        $internalErrors = $this->em->createQueryBuilder()
            ->from(InternalError::class, 'e')
            ->leftJoin('e.judging', 'j')
            ->select('e')
            ->getQuery()->getResult();

        $table_fields = [
            'errorid' => ['title' => 'ID', 'default_sort' => true, 'default_sort_order' => 'desc'],
            'judging.judgingid' => ['title' => 'jid'],
            'description' => ['title' => 'description'],
            'time' => ['title' => 'time'],
            'status' => ['title' => 'status'],
        ];

        $propertyAccessor      = PropertyAccess::createPropertyAccessor();
        $internal_errors_table = [];
        foreach ($internalErrors as $internal) {
            $internalerrordata = [];
            // Get whatever fields we can from the problem object itself
            foreach ($table_fields as $k => $v) {
                if ($propertyAccessor->isReadable($internal, $k)) {
                    $internalerrordata[$k] = ['value' => $propertyAccessor->getValue($internal, $k)];
                } else {
                    $internalerrordata[$k] = ['value' => null];
                }
            }

            $internalerrordata['time']['value'] = Utils::printtime($internalerrordata['time']['value'], 'Y-m-d H:i:s');

            // Save this to our list of rows
            $internal_errors_table[] = [
                'data' => $internalerrordata,
                'actions' => [],
                'link' => $this->generateUrl('jury_internal_error', ['errorId' => $internal->getErrorid()]),
                'cssclass' => $internal->getStatus() === 'open' ? 'unseen' : 'disabled',
            ];
        }

        return $this->render('jury/internal_errors.html.twig', [
            'internal_errors' => $internal_errors_table,
            'table_fields' => $table_fields,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_internal_errors'),
            ]
        ]);
    }

    #[Route(path: '/{errorId<\d+>}', methods: ['GET'], name: 'jury_internal_error')]
    public function viewAction(int $errorId): Response
    {
        $internalError = $this->em->getRepository(InternalError::class)->find($errorId);
        if (!$internalError) {
            throw new NotFoundHttpException(sprintf('Internal Error with ID %s not found', $errorId));
        }

        $disabled     = $internalError->getDisabled();
        $affectedLink = $affectedText = null;
        switch ($disabled['kind']) {
            case 'problem':
                $affectedLink = $this->generateUrl('jury_problem', ['probId' => $disabled['probid']]);
                $problem      = $this->em->getRepository(Problem::class)->find($disabled['probid']);
                $affectedText = $problem->getName();
                break;
            case 'judgehost':
                // Judgehosts get disabled by their hostname, so we need to look it up here.
                $judgehost    = $this->em->getRepository(Judgehost::class)->findOneBy(['hostname' => $disabled['hostname']]);
                $affectedText = $disabled['hostname'];
                if ($judgehost) {
                    $affectedLink = $this->generateUrl('jury_judgehost', ['judgehostid' => $judgehost->getJudgehostid()]);
                } else {
                    $affectedText .= ' (deleted)';
                }
                break;
            case 'language':
                $affectedLink = $this->generateUrl('jury_language', ['langId' => $disabled['langid']]);
                $affectedText = $disabled['langid'];
                break;
            case 'executable':
                $affectedLink = $this->generateUrl('jury_executable', ['execId' => $disabled['execid']]);
                $affectedText = $disabled['execid'];
                break;
        }

        return $this->render('jury/internal_error.html.twig', [
            'internalError' => $internalError,
            'affectedLink' => $affectedLink,
            'affectedText' => $affectedText,
        ]);
    }

    #[Route(path: '/{errorId<\d+>}/{action<ignore|resolve>}', name: 'jury_internal_error_handle', methods: ['POST'])]
    public function handleAction(Request $request, ?Profiler $profiler, int $errorId, string $action): Response
    {
        /** @var InternalError $internalError */
        $internalError = $this->em->createQueryBuilder()
            ->from(InternalError::class, 'e')
            ->leftJoin('e.affectedJudgings', 'j')
            ->leftJoin('j.submission', 's')
            ->leftJoin('j.contest', 'c')
            ->leftJoin('s.team', 't')
            ->leftJoin('s.rejudging', 'r')
            ->select('e, j, s, c, t, r')
            ->where('e.errorid = :id')
            ->setParameter('id', $errorId)
            ->getQuery()
            ->getSingleResult();
        if ($action === 'ignore') {
            $internalError->setStatus(InternalErrorStatusType::STATUS_IGNORED);
            $this->dj->auditlog('internal_error', $internalError->getErrorid(),
                sprintf('internal error: %s', InternalErrorStatusType::STATUS_IGNORED));
            $this->em->flush();
            return $this->redirectToRoute('jury_internal_error', ['errorId' => $internalError->getErrorid()]);
        }

        // Action is resolve now, use AJAX to do this

        if ($request->isXmlHttpRequest()) {
            $profiler?->disable();
            $progressReporter = function (int $progress, string $log, ?string $message = null) {
                echo $this->dj->jsonEncode(['progress' => $progress, 'log' => htmlspecialchars($log), 'message' => $message]);
                ob_flush();
                flush();
            };
            return $this->streamResponse($this->requestStack, function () use ($progressReporter, $internalError) {
                $this->em->wrapInTransaction(function () use ($progressReporter, $internalError) {
                    $internalError->setStatus(InternalErrorStatusType::STATUS_RESOLVED);
                    $this->dj->setInternalError(
                        $internalError->getDisabled(),
                        $internalError->getContest(),
                        true
                    );

                    $this->dj->auditlog('internal_error', $internalError->getErrorid(),
                        sprintf('internal error: %s', InternalErrorStatusType::STATUS_RESOLVED));

                    $affectedJudgings = $internalError->getAffectedJudgings();
                    if (!$affectedJudgings->isEmpty()) {
                        $skipped          = [];
                        $rejudging        = $this->rejudgingService->createRejudging(
                            'Internal Error ' . $internalError->getErrorid() . ' resolved',
                            JudgeTask::PRIORITY_DEFAULT,
                            $affectedJudgings->getValues(),
                            false,
                            0,
                            0,
                            null,
                            $skipped,
                            $progressReporter);
                        if ($rejudging === null) {
                            $this->addFlash('warning', 'All submissions that are affected by this internal error are already part of another rejudging.');
                        } else {
                            $rejudgingUrl = $this->generateUrl('jury_rejudging', ['rejudgingId' => $rejudging->getRejudgingid()]);
                            $internalErrorUrl = $this->generateUrl('jury_internal_error', ['errorId' => $internalError->getErrorid()]);
                            $message = sprintf(
                                'Rejudging <a href="%s">r%d</a> created for internal error <a href="%s">%d</a>.',
                                $rejudgingUrl,
                                $rejudging->getRejudgingid(),
                                $internalErrorUrl,
                                $internalError->getErrorid()
                            );
                            $progressReporter(100, '', $message);
                        }
                    } else {
                        $progressReporter(100, '', 'No affected judgings.');
                    }
                });
            });
        }

        return $this->render('jury/internal_error_resolve.html.twig', [
            'url' => $this->generateUrl('jury_internal_error_handle', ['errorId' => $errorId, 'action' => $action]),
        ]);
    }
}
