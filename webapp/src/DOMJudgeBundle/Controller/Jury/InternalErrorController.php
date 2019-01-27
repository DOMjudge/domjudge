<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Jury;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\InternalError;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury")
 * @Security("has_role('ROLE_JURY')")
 */
class InternalErrorController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Route("/internal-errors/", name="jury_internal_errors")
     */
    public function indexAction()
    {
        /** @var InternalError[] $internalErrors */
        $internalErrors = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:InternalError', 'e')
            ->select('e')
            ->orderBy('e.status')
            ->addOrderBy('e.errorid')
            ->getQuery()->getResult();

        $table_fields = [
            'errorid' => ['title' => 'ID'],
            'judgingid' => ['title' => 'jid'],
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
                }
            }

            $internalerrordata['time']['value'] = Utils::printtime($internalerrordata['time']['value'], '%F %T');

            // Save this to our list of rows
            $internal_errors_table[] = [
                'data' => $internalerrordata,
                'actions' => [],
                'link' => $this->generateUrl('jury_internal_error', ['errorId' => $internal->getErrorid()]),
                'cssclass' => $internal->getStatus() === 'open' ? 'unseen' : 'disabled',
            ];
        }

        return $this->render('@DOMJudge/jury/internal_errors.html.twig', [
            'internal_errors' => $internal_errors_table,
            'table_fields' => $table_fields,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_internal_errors'),
            ]
        ]);
    }

    /**
     * @Route("/internal-errors/{errorId}", methods={"GET"}, name="jury_internal_error")
     * @param int $errorId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(int $errorId)
    {
        /** @var InternalError $internalError */
        $internalError = $this->entityManager->getRepository(InternalError::class)->find($errorId);
        if (!$internalError) {
            throw new NotFoundHttpException(sprintf('Internal Error with ID %s not found', $errorId));
        }

        $disabled     = $internalError->getDisabled();
        $affectedLink = $affectedText = null;
        switch ($disabled['kind']) {
            case 'problem':
                $affectedLink = $this->generateUrl('jury_problem', ['probId' => $disabled['probid']]);
                $idData       = ['cid' => $internalError->getCid(), 'probid' => $disabled['probid']];
                /** @var ContestProblem $problem */
                $problem      = $this->entityManager->getRepository(ContestProblem::class)->find($idData);
                $affectedText = sprintf('%s - %s', $problem->getShortname(), $problem->getProblem()->getName());
                break;
            case 'judgehost':
                $affectedLink = $this->generateUrl('jury_judgehost', ['hostname' => $disabled['hostname']]);
                $affectedText = $disabled['hostname'];
                break;
            case 'language':
                $affectedLink = $this->generateUrl('jury_language', ['langId' => $disabled['langid']]);
                $affectedText = $disabled['langid'];
                break;
        }

        return $this->render('@DOMJudge/jury/internal_error.html.twig', [
            'internalError' => $internalError,
            'affectedLink' => $affectedLink,
            'affectedText' => $affectedText,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_internal_error', ['errorId' => $internalError->getErrorid()]),
            ]
        ]);
    }

    /**
     * @Route(
     *     "/internal-errors/{errorId}/{action}",
     *     name="jury_internal_error_handle",
     *     methods={"POST"},
     *     requirements={"action": "ignore|resolve"}
     * )
     * @param int    $errorId
     * @param string $action
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleAction(int $errorId, string $action)
    {
        /** @var InternalError $internalError */
        $internalError = $this->entityManager->getRepository(InternalError::class)->find($errorId);
        $status        = $action === 'ignore' ? InternalError::STATUS_IGNROED : InternalError::STATUS_RESOLVED;
        $this->entityManager->transactional(function () use ($internalError, $status) {
            $internalError->setStatus($status);
            if ($status === InternalError::STATUS_RESOLVED) {
                $this->DOMJudgeService->setInternalError($internalError->getDisabled(), (int)$internalError->getCid(),
                                                         true);
                $this->DOMJudgeService->auditlog('internal_error', $internalError->getErrorid(),
                                                 sprintf('internal error: %s', $status));
            }
        });

        return $this->redirectToRoute('jury_internal_error', ['errorId' => $internalError->getErrorid()]);
    }
}
