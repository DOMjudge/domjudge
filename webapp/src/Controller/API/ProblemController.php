<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Helpers\ContestProblemWrapper;
use App\Helpers\OrdinalArray;
use App\Service\ConfigurationService;
use App\Service\DOMjudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Service\ImportProblemService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;

/**
 * @Rest\Route("/contests/{cid}/problems")
 * @OA\Tag(name="Problems")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 * @OA\Response(response="401", ref="#/components/responses/Unauthorized")
 */
class ProblemController extends AbstractRestController implements QueryObjectTransformer
{
    /**
     * @var ImportProblemService
     */
    protected $importProblemService;

    /**
     * @var ImportExportService
     */
    protected $importExportService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMjudgeService $DOMjudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ImportProblemService $importProblemService,
        ImportExportService $importExportService
    ) {
        parent::__construct($entityManager, $DOMjudgeService, $config, $eventLogService);
        $this->importProblemService = $importProblemService;
        $this->importExportService = $importExportService;
    }

    /**
     * Add one or more problems.
     * @Rest\Post("/add-data")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Post()
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="data",
     *                 type="string",
     *                 format="binary",
     *                 description="The problems.yaml or problems.json file to import."
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="Returns the API ID's of the added problems.",
     * )
     * @throws BadRequestHttpException
     */
    public function addProblemsAction(Request $request) : array
    {
        // Note we use /add-data as URL here since we already have a route listening
        // on POST /, which is to add a problem ZIP.

        $contestId = $this->getContestId($request);
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);

        /** @var UploadedFile $file */
        $file = $request->files->get('data') ?: [];
        // Note: we read the JSON as YAML, since any JSON is also YAML and this allows us
        // to import files with YAML inside them that match the JSON format
        $data = Yaml::parseFile($file->getRealPath(), Yaml::PARSE_DATETIME);
        if ($this->importExportService->importProblemsData($contest, $data, $ids)) {
            return $ids;
        }
        throw new BadRequestHttpException("Error while adding problems");
    }

    /**
     * Get all the problems for this contest
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the problems for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/ContestProblem")
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function listAction(Request $request): Response
    {
        // Make sure we clear the entity manager class, for when this method is called multiple times by internal requests
        $this->em->clear();
        // This method is overwritten, because we need to add ordinal values
        $queryBuilder = $this->getQueryBuilder($request);

        $objects = $queryBuilder
            ->getQuery()
            ->getResult();

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
                /** @var ContestProblemWrapper|ContestProblem $contestProblem */
                $contestProblem = $item->getItem();
                if ($contestProblem instanceof ContestProblemWrapper) {
                    $contestProblem = $contestProblem->getContestProblem();
                }
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
     * Add a problem to this contest.
     * @Rest\Post("")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Post()
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"zip"},
     *             @OA\Property(
     *                 property="zip",
     *                 type="string",
     *                 format="binary",
     *                 description="The problem archive to import"
     *             ),
     *             @OA\Property(
     *                 property="problem",
     *                 description="Optional: problem id to update.",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="Returns the ID of the imported problem and any messages produced",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="problem_id", type="integer", description="The ID of the imported problem"),
     *         @OA\Property(property="messages", type="array",
     *             @OA\Items(type="string", description="Messages produced while adding problems")
     *         )
     *     )
     * )
     * @throws NonUniqueResultException
     */
    public function addProblemAction(Request $request) : array
    {
        $file     = $request->files->get('zip');
        if (empty($file)) {
            throw new BadRequestHttpException('ZIP file missing');
        }

        $contestId = $this->getContestId($request);
        /** @var Contest $contest */
        $contest     = $this->em->getRepository(Contest::class)->find($contestId);
        $allMessages = [];

        // Only timeout after 2 minutes, since importing may take a while.
        set_time_limit(120);

        $probId = $request->request->get('problem');
        $problem = null;
        if (!empty($probId)) {
            $problem = $this->em->createQueryBuilder()
                ->from(Problem::class, 'p')
                ->select('p')
                ->andWhere(sprintf('%s = :id', $this->getIdField()))
                ->setParameter(':id', $probId)
                ->getQuery()
                ->getOneOrNullResult();
            if (empty($problem)) {
                throw new BadRequestHttpException('Specified \'problem\' does not exist.');
            }
        }
        $errors = [];
        $zip = null;
        try {
            $zip         = $this->dj->openZipFile($file->getRealPath());
            $clientName  = $file->getClientOriginalName();
            $messages    = [];
            $newProblem  = $this->importProblemService->importZippedProblem(
                $zip, $clientName, $problem, $contest, $messages
            );
            $allMessages = array_merge($allMessages, $messages);
            if ($newProblem) {
                $this->dj->auditlog('problem', $newProblem->getProbid(), 'upload zip', $clientName);
                $probId = $newProblem->getApiId($this->eventLogService);
            } else {
                $errors = array_merge($errors, $messages);
            }
        } catch (Exception $e) {
            $allMessages[] = $e->getMessage();
        } finally {
            if ($zip) {
                $zip->close();
            }
        }
        if (!empty($errors)) {
            throw new BadRequestHttpException(json_encode($errors));
        }
        return [
            'problem_id' => $probId,
            'messages' => $allMessages,
        ];
    }

    /**
     * Get the given problem for this contest
     * @throws NonUniqueResultException
     * @throws Exception
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given problem for this contest",
     *     @OA\JsonContent(ref="#/components/schemas/ContestProblem")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id) : Response
    {
        $ordinalArray = new OrdinalArray($this->listActionHelper($request));

        $object = null;
        foreach ($ordinalArray->getItems() as $item) {
            /** @var ContestProblemWrapper|ContestProblem $contestProblem */
            $contestProblem = $item->getItem();
            if ($contestProblem instanceof ContestProblemWrapper) {
                $contestProblem = $contestProblem->getContestProblem();
            }
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

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $contestId = $this->getContestId($request);
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->join('cp.problem', 'p')
            ->leftJoin('p.testcases', 'tc')
            ->select('cp, partial p.{probid,externalid,name,timelimit,memlimit}, COUNT(tc.testcaseid) AS testdatacount')
            ->andWhere('cp.contest = :cid')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter(':cid', $contestId)
            ->orderBy('cp.shortname')
            ->groupBy('cp.problem');

        // For non-API-reader users, only expose the problems after the contest has started
        if (!$this->dj->checkrole('api_reader') && $contest->getStartTimeObject()->getTimestamp() > time()) {
            $queryBuilder->andWhere('1 = 0');
        }

        return $queryBuilder;
    }

    /**
     * @throws Exception
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
        if ($this->dj->checkrole('jury')) {
            return new ContestProblemWrapper($problem, $testDataCount);
        } else {
            return $problem;
        }
    }
}
