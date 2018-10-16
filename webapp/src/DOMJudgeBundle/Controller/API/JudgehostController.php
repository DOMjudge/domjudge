<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\InternalError;
use DOMJudgeBundle\Entity\Judgehost;
use DOMJudgeBundle\Entity\Judging;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Utils\Utils;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @Rest\Route("/api/v4/judgehosts", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/judgehosts")
 * @Rest\NamePrefix("judgehost_")
 * @SWG\Tag(name="Judgehosts")
 */
class JudgehostController extends FOSRestController
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
     * JudgehostController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService $DOMJudgeService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * Get judgehosts
     * @Rest\Get("")
     * @Security("has_role('ROLE_JURY')")
     * @SWG\Response(
     *     response="200",
     *     description="The judgehosts",
     *     @Model(type=Judgehost::class)
     * )
     * @SWG\Parameter(
     *     name="hostname",
     *     in="query",
     *     type="string",
     *     description="Only show the judgehost with the given hostname"
     * )
     * @param Request $request
     * @return array
     */
    public function getJudgehostsAction(Request $request)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judgehost', 'j')
            ->select('j');

        if ($request->query->has('hostname')) {
            $queryBuilder
                ->where('j.hostname = :hostname')
                ->setParameter(':hostname', $request->query->get('hostname'));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Internal error reporting (back from judgehost)
     *
     * @Rest\Post("/internal-error")
     * @Security("has_role('ROLE_JUDGEHOST')")
     * @SWG\Response(
     *     response="200",
     *     description="The ID of the created internal error",
     *     @SWG\Schema(type="integer")
     * )
     * @SWG\Parameter(
     *     name="description",
     *     in="formData",
     *     type="string",
     *     description="The description of the internal error",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="judgehostlog",
     *     in="formData",
     *     type="string",
     *     description="The log of the judgehost",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="disabled",
     *     in="formData",
     *     type="string",
     *     description="The object to disable in JSON format",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="cid",
     *     in="formData",
     *     type="integer",
     *     description="The contest ID associated with this internal error",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="judgingid",
     *     in="formData",
     *     type="integer",
     *     description="The ID of the judging that was being worked on",
     *     required=false
     * )
     * @param Request $request
     * @return int|string
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function internalErrorAction(Request $request)
    {
        $required = ['description', 'judgehostlog', 'disabled'];
        foreach ($required as $argument) {
            if (!$request->request->has($argument)) {
                throw new BadRequestHttpException(sprintf('Argument \'%s\' is mandatory', $argument));
            }
        }
        $description  = $request->request->get('description');
        $judgehostlog = $request->request->get('judgehostlog');
        $disabled     = $request->request->get('disabled');

        // Both cid and judgingid are allowed to be NULL.
        $cid       = $request->request->get('cid');
        $judgingId = $request->request->get('judgingid');

        // Group together duplicate internal errors
        // Note that it may be good to be able to ignore fields here, e.g. judgingid with compile errors
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:InternalError', 'e')
            ->select('e')
            ->where('e.description = :description')
            ->andWhere('e.disabled = :disabled')
            ->andWhere('e.status = :status')
            ->setParameter(':description', $description)
            ->setParameter(':disabled', $disabled)
            ->setParameter(':status', 'open')
            ->setMaxResults(1);

        if ($cid) {
            $queryBuilder
                ->andWhere('e.cid = :cid')
                ->setParameter(':cid', $cid);
        }

        /** @var InternalError $error */
        $error = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($error) {
            // FIXME: in some cases it makes sense to extend the known information, e.g. the judgehostlog
            return $error->getErrorid();
        }

        $error = new InternalError();
        $error
            ->setJudgingid($judgingId)
            ->setCid($cid)
            ->setDescription($description)
            ->setJudgehostlog($judgehostlog)
            ->setTime(Utils::now())
            ->setDisabled($disabled);

        $this->entityManager->persist($error);
        $this->entityManager->flush();

        $disabled = $this->DOMJudgeService->jsonDecode($disabled);

        $this->DOMJudgeService->setInternalError($disabled, $cid, false);

        if (in_array($disabled['kind'], ['problem', 'language', 'judgehost']) && $judgingId) {
            // give back judging if we have to
            $this->giveBackJudging((int)$judgingId);
        }

        return $error->getErrorid();
    }

    /**
     * Give back the judging with the given judging ID
     * @param int $judgingId
     */
    protected function giveBackJudging(int $judgingId)
    {
        /** @var Judging $judging */
        $judging = $this->entityManager->getRepository(Judging::class)->find($judgingId);
        if ($judging) {
            $this->entityManager->transactional(function () use ($judging) {
                $judging
                    ->setValid(false)
                    ->setRejudgingid(null);

                $judging->getSubmission()->setJudgehost(null);
            });

            $this->DOMJudgeService->auditlog('judging', $judgingId, 'given back', null, $judging->getJudgehost()->getHostname(), $judging->getCid());
        }
    }
}
