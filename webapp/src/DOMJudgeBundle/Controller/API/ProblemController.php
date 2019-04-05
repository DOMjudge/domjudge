<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\ContestProblem;
use DOMJudgeBundle\Entity\Problem;
use DOMJudgeBundle\Helpers\ContestProblemWrapper;
use DOMJudgeBundle\Helpers\OrdinalArray;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\EventLogService;
use DOMJudgeBundle\Service\ImportProblemService;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/api/v4/contests/{cid}/problems", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/problems")
 * @Rest\NamePrefix("problems_")
 * @SWG\Tag(name="Problems")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class ProblemController extends AbstractRestController implements QueryObjectTransformer
{
    /**
     * @var ImportProblemService
     */
    protected $importProblemService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        EventLogService $eventLogService,
        ImportProblemService $importProblemService
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $eventLogService);
        $this->importProblemService = $importProblemService;
    }

    /**
     * Get all the problems for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the problems for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref="#/definitions/ContestProblem")
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function listAction(Request $request)
    {
        // Make sure we clear the entity manager class, for when this method is called multiple times by internal requests
        $this->entityManager->clear();
        // This method is overwritten, because we need to add ordinal values
        $queryBuilder = $this->getQueryBuilder($request);

        $objects = $queryBuilder
            ->getQuery()
            ->getResult();

        if (isset($ids) && count($objects) !== count($ids)) {
            throw new NotFoundHttpException('One or more objects not found');
        }

        if (empty($objects)) {
            return $this->renderData($request, []);
        }

        $objects = array_map([$this, 'transformObject'], $objects);

        $ordinalArray = new OrdinalArray($objects);
        $objects      = $ordinalArray->getItems();

        if ($request->query->has('ids')) {
            $ids = $request->query->get('ids', []);
            if (!is_array($ids)) {
                throw new BadRequestHttpException('\'ids\' should be an array of ID\'s to fetch');
            }

            $ids = array_unique($ids);

            $objects = [];
            foreach ($ordinalArray->getItems() as $item) {
                /** @var ContestProblemWrapper $contestProblemWrapper */
                $contestProblemWrapper = $item->getItem();
                $contestProblem        = $contestProblemWrapper->getContestProblem();
                $probid                = $this->getIdField() === 'p.probid' ? $contestProblem->getProbid() : $contestProblem->getExternalId();
                if (in_array($probid, $ids)) {
                    $objects[] = $item;
                }
            }

            if (count($objects) !== count($ids)) {
                throw new NotFoundHttpException('One or more objects not found');
            }
        }

        return $this->renderData($request, $objects);
    }

    /**
     * Add one or more problems to this contest.
     * @param Request $request
     * @return array
     * @Rest\Post("")
     * @Security("has_role('ROLE_ADMIN')")
     * @SWG\Post(consumes={"multipart/form-data"})
     * @SWG\Parameter(
     *     name="zip[]",
     *     in="formData",
     *     type="file",
     *     required=true,
     *     description="The problem archives to import"
     * )
     * @SWG\Parameter(
     *     name="problem",
     *     in="formData",
     *     type="string",
     *     description="Optional: problem id to update."
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Returns the IDs of the just imported problems",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(type="integer", description="The IDs of the imported problems")
     *     )
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function addProblemAction(Request $request)
    {
        $files     = $request->files->get('zip') ?: [];
        $contestId = $this->getContestId($request);
        /** @var Contest $contest */
        $contest     = $this->entityManager->getRepository(Contest::class)->find($contestId);
        $allMessages = [];
        $probIds     = [];

        $probId = $request->request->get('problem');
        $problem = null;
        if (!empty($probId)) {
            if (sizeof($files) != 1) {
                throw new BadRequestHttpException('Can only take one problem zip if \'problem\' is set.');
            }
            $problem = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Problem', 'p')
                ->select('p')
                ->andWhere(sprintf('%s = :id', $this->getIdField()))
                ->setParameter(':id', $probId)
                ->getQuery()
                ->getOneOrNullResult();
            if (empty($problem)) {
                throw new BadRequestHttpException('Specified \'problem\' does not exist.');
            }
        }
        /** @var UploadedFile $file */
        foreach ($files as $file) {
            $zip = null;
            try {
                $zip         = $this->DOMJudgeService->openZipFile($file->getRealPath());
                $clientName  = $file->getClientOriginalName();
                $messages    = [];
                $newProblem  = $this->importProblemService->importZippedProblem($zip, $clientName, null, $contest,
                                                                                $messages);
                $allMessages = array_merge($allMessages, $messages);
                if ($newProblem) {
                    $this->DOMJudgeService->auditlog('problem', $newProblem->getProbid(), 'upload zip', $clientName);
                    $probIds[] = $newProblem->getProbid();
                }
            } catch (\Exception $e) {
                dump($e);
            } finally {
                if ($zip) {
                    $zip->close();
                }
            }
        }
        dump($allMessages);
        return $probIds;
    }

    /**
     * Get the given problem for this contest
     * @param Request $request
     * @param string  $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given problem for this contest",
     *     ref="#/definitions/ContestProblem"
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     */
    public function singleAction(Request $request, string $id)
    {
        // Make sure we clear the entity manager class, for when this method is called multiple times by internal requests
        $this->entityManager->clear();
        // This method is overwritten, because we need to add ordinal values
        $queryBuilder = $this->getQueryBuilder($request);

        if ($request->query->has('ids')) {
            $ids = $request->query->get('ids', []);
            if (!is_array($ids)) {
                throw new BadRequestHttpException('\'ids\' should be an array of ID\'s to fetch');
            }

            $ids = array_unique($ids);

            $queryBuilder
                ->andWhere(sprintf('%s IN (:ids)', $this->getIdField()))
                ->setParameter(':ids', $ids);
        }

        $objects = $queryBuilder
            ->getQuery()
            ->getResult();

        if (isset($ids) && count($objects) !== count($ids)) {
            throw new NotFoundHttpException('One or more objects not found');
        }

        $objects = array_map([$this, 'transformObject'], $objects);

        $ordinalArray = new OrdinalArray($objects);

        $object = null;
        foreach ($ordinalArray->getItems() as $item) {
            /** @var ContestProblemWrapper $contestProblemWrapper */
            $contestProblemWrapper = $item->getItem();
            $contestProblem        = $contestProblemWrapper->getContestProblem();
            $probid                = $this->getIdField() === 'p.probid' ? $contestProblem->getProbid() : $contestProblem->getExternalId();
            if ($probid == $id) {
                $object = $item;
                break;
            }
        }

        if ($object === null) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        return $this->renderData($request, $object);
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $contestId = $this->getContestId($request);
        /** @var Contest $contest */
        $contest = $this->entityManager->getRepository(Contest::class)->find($contestId);

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:ContestProblem', 'cp')
            ->join('cp.problem', 'p')
            ->leftJoin('p.testcases', 'tc')
            ->select('cp, p, COUNT(tc.testcaseid) AS testdatacount')
            ->andWhere('cp.cid = :cid')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter(':cid', $contestId)
            ->orderBy('cp.shortname')
            ->groupBy('cp.probid');

        // For non-API-reader users, only expose the problems after the contest has started
        if (!$this->DOMJudgeService->checkrole('api_reader') && $contest->getStartTimeObject()->getTimestamp() > time()) {
            $queryBuilder->andWhere('1 = 0');
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getIdField(): string
    {
        return sprintf('p.%s', $this->eventLogService->externalIdFieldForEntity(Problem::class) ?? 'probid');
    }

    /**
     * Transform the given object before returning it from the API
     * @param mixed $object
     * @return mixed
     */
    public function transformObject($object)
    {
        /** @var ContestProblem $problem */
        $problem       = $object[0];
        $testDataCount = (int)$object['testdatacount'];
        return new ContestProblemWrapper($problem, $testDataCount);
    }
}
