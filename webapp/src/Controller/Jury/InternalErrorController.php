<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Doctrine\DBAL\Types\InternalErrorStatusType;
use App\Entity\ContestProblem;
use App\Entity\InternalError;
use App\Entity\Problem;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jury/internal-errors")
 * @IsGranted("ROLE_JURY")
 */
class InternalErrorController extends BaseController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    public function __construct(EntityManagerInterface $em, DOMJudgeService $dj)
    {
        $this->em = $em;
        $this->dj = $dj;
    }

    /**
     * @Route("", name="jury_internal_errors")
     */
    public function indexAction()
    {
        /** @var InternalError[] $internalErrors */
        $internalErrors = $this->em->createQueryBuilder()
            ->from(InternalError::class, 'e')
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

        return $this->render('jury/internal_errors.html.twig', [
            'internal_errors' => $internal_errors_table,
            'table_fields' => $table_fields,
            'refresh' => [
                'after' => 15,
                'url' => $this->generateUrl('jury_internal_errors'),
            ]
        ]);
    }

    /**
     * @Route("/{errorId<\d+>}", methods={"GET"}, name="jury_internal_error")
     * @param int $errorId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(int $errorId)
    {
        /** @var InternalError $internalError */
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
                $affectedLink = $this->generateUrl('jury_judgehost', ['hostname' => $disabled['hostname']]);
                $affectedText = $disabled['hostname'];
                break;
            case 'language':
                $affectedLink = $this->generateUrl('jury_language', ['langId' => $disabled['langid']]);
                $affectedText = $disabled['langid'];
                break;
        }

        return $this->render('jury/internal_error.html.twig', [
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
     *     "/{errorId<\d+>}/{action<ignore|resolve>}",
     *     name="jury_internal_error_handle",
     *     methods={"POST"}
     * )
     * @param int    $errorId
     * @param string $action
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function handleAction(int $errorId, string $action)
    {
        /** @var InternalError $internalError */
        $internalError = $this->em->getRepository(InternalError::class)->find($errorId);
        $status        = $action === 'ignore' ? InternalErrorStatusType::STATUS_IGNROED : InternalErrorStatusType::STATUS_RESOLVED;
        $this->em->transactional(function () use ($internalError, $status) {
            $internalError->setStatus($status);
            if ($status === InternalErrorStatusType::STATUS_RESOLVED) {
                $this->dj->setInternalError(
                    $internalError->getDisabled(),
                    $internalError->getContest(),
                    true
                );
                $this->dj->auditlog('internal_error', $internalError->getErrorid(),
                                    sprintf('internal error: %s', $status));
            }
        });

        return $this->redirectToRoute('jury_internal_error', ['errorId' => $internalError->getErrorid()]);
    }
}
