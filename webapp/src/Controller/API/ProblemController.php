<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Helpers\ContestProblemWrapper;
use App\Helpers\OrdinalArray;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\ImportExportService;
use App\Service\ImportProblemService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @Rest\Route("/contests/{cid}/problems")
 * @OA\Tag(name="Problems")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Parameter(ref="#/components/parameters/strict")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class ProblemController extends AbstractRestController implements QueryObjectTransformer
{
    protected ImportProblemService $importProblemService;
    protected ImportExportService $importExportService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $DOMJudgeService,
        ConfigurationService $config,
        EventLogService $eventLogService,
        ImportProblemService $importProblemService,
        ImportExportService $importExportService
    ) {
        parent::__construct($entityManager, $DOMJudgeService, $config, $eventLogService);
        $this->importProblemService = $importProblemService;
        $this->importExportService = $importExportService;
    }

    /**
     * Add one or more problems.
     * @Rest\Post("/add-data")
     * @IsGranted("ROLE_ADMIN")
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
     *
     * @throws BadRequestHttpException
     * @throws NonUniqueResultException
     */
    public function addProblemsAction(Request $request): array
    {
        // Note we use /add-data as URL here since we already have a route listening
        // on POST /, which is to add a problem ZIP.

        $contestId = $this->getContestId($request);
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);

        if ($contest->isLocked()) {
            $contestUrl = $this->generateUrl('jury_contest', ['contestId' => $contestId], UrlGeneratorInterface::ABSOLUTE_URL);
            throw new AccessDeniedHttpException('Contest is locked, go to ' . $contestUrl . ' to unlock it.');
        }

        /** @var UploadedFile $file */
        if (!$file = $request->files->get('data')) {
            throw new BadRequestHttpException("Data field is missing.");
        }
        // Note: we read the JSON as YAML, since any JSON is also YAML and this allows us
        // to import files with YAML inside them that match the JSON format
        $data = Yaml::parseFile($file->getRealPath(), Yaml::PARSE_DATETIME);
        if ($this->importExportService->importProblemsData($contest, $data, $ids)) {
            return $ids;
        }
        throw new BadRequestHttpException("Error while adding problems");
    }

    /**
     * Get all the problems for this contest.
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
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        // Make sure we clear the entity manager class, for when this method is called multiple times
        // by internal requests.
        $this->em->clear();
        // This method is overwritten, because we need to add ordinal values.
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
            $ids = $request->query->all('ids');
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
    public function addProblemAction(Request $request): array
    {
        $contestId = $this->getContestId($request);
        /** @var Contest $contest */
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if ($contest->isLocked()) {
            $contestUrl = $this->generateUrl('jury_contest', ['contestId' => $contestId], UrlGeneratorInterface::ABSOLUTE_URL);
            throw new AccessDeniedHttpException('Contest is locked, go to ' . $contestUrl . ' to unlock it.');
        }
        return $this->importProblemService->importProblemFromRequest($request, $contestId);
    }

    /**
     * Unlink a problem from this contest.
     * @Rest\Delete("/{id}")
     * @IsGranted("ROLE_ADMIN")
     * @OA\Response(response="204", description="Problem unlinked from contest succeeded")
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function unlinkProblemAction(Request $request, string $id): Response
    {
        $problem = $this->em->createQueryBuilder()
                            ->from(Problem::class, 'p')
                            ->select('p')
                            ->andWhere(sprintf('%s = :id', $this->getIdField()))
                            ->setParameter('id', $id)
                            ->getQuery()
                            ->getOneOrNullResult();

        if (empty($problem)) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        $cid = $this->getContestId($request);

        /** @var ContestProblem|null $contestProblem */
        $contestProblem = $this->em->createQueryBuilder()
                                   ->from(ContestProblem::class, 'cp')
                                   ->select('cp')
                                   ->andWhere('cp.contest = :contest')
                                   ->andWhere('cp.problem = :problem')
                                   ->setParameter('contest', $cid)
                                   ->setParameter('problem', $problem)
                                   ->getQuery()
                                   ->getOneOrNullResult();

        if (empty($contestProblem)) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }
        $contest = $contestProblem->getContest();
        if ($contest->isLocked()) {
            $contestUrl = $this->generateUrl('jury_contest', ['contestId' => $contest->getCid()], UrlGeneratorInterface::ABSOLUTE_URL);
            throw new AccessDeniedHttpException('Contest is locked, go to ' . $contestUrl . ' to unlock it.');
        }

        $this->em->remove($contestProblem);
        $id = [$contestProblem->getCid(), $contestProblem->getProbid()];
        $this->dj->auditlog('contest_problem', implode(', ', $id), 'deleted');
        $this->eventLogService->log('problem', $contestProblem->getProbid(),
                                    EventLogService::ACTION_DELETE, $cid,
                                    null, null, false);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Link an existing problem to this contest.
     * @Rest\Put("/{id}")
     * @IsGranted("ROLE_ADMIN")
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(ref="#/components/schemas/ContestProblemPut")
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="Returns the linked problem for this contest",
     *     @OA\JsonContent(ref="#/components/schemas/ContestProblem")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function linkProblemAction(Request $request, string $id): Response
    {
        $required = ['label'];
        foreach ($required as $argument) {
            if (!$request->request->has($argument)) {
                throw new BadRequestHttpException(sprintf("Argument '%s' is mandatory.", $argument));
            }
        }

        $problem = $this->em->createQueryBuilder()
                            ->from(Problem::class, 'p')
                            ->select('p')
                            ->andWhere(sprintf('%s = :id', $this->getIdField()))
                            ->setParameter('id', $id)
                            ->getQuery()
                            ->getOneOrNullResult();

        if (empty($problem)) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        $cid = $this->getContestId($request);
        $contest = $this->em->getRepository(Contest::class)->find($cid);
        if ($contest->isLocked()) {
            $contestUrl = $this->generateUrl('jury_contest', ['contestId' => $contest->getCid()], UrlGeneratorInterface::ABSOLUTE_URL);
            throw new AccessDeniedHttpException('Contest is locked, go to ' . $contestUrl . ' to unlock it.');
        }

        /** @var ContestProblem|null $contestProblem */
        $contestProblem = $this->em->createQueryBuilder()
                                   ->from(ContestProblem::class, 'cp')
                                   ->select('cp')
                                   ->andWhere('cp.contest = :contest')
                                   ->andWhere('cp.problem = :problem')
                                   ->setParameter('contest', $cid)
                                   ->setParameter('problem', $problem)
                                   ->getQuery()
                                   ->getOneOrNullResult();

        if (!empty($contestProblem)) {
            throw new BadRequestHttpException('Problem already linked to contest');
        }

        $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));

        $lazyEvalResults = null;
        if ($request->request->has('lazy_eval_results')) {
            $lazyEvalResults = $request->request->getBoolean('lazy_eval_results');
        }

        $contestProblem = (new ContestProblem())
            ->setContest($contest)
            ->setProblem($problem)
            ->setShortname($request->request->get('label'))
            ->setColor($request->request->get('rgb') ?? $request->request->get('color'))
            ->setPoints($request->request->getInt('points', 1))
            ->setLazyEvalResults($lazyEvalResults);

        $this->em->persist($contestProblem);
        $this->em->flush();

        $fullId = [$contestProblem->getCid(), $contestProblem->getProbid()];
        $this->dj->auditlog('contest_problem', implode(', ', $fullId), 'added');
        $this->eventLogService->log('problem', $contestProblem->getProbid(),
                                    EventLogService::ACTION_CREATE, $cid,
                                    null, null, false);

        return $this->singleAction($request, $id);
    }

    /**
     * Get the given problem for this contest.
     * @throws NonUniqueResultException
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given problem for this contest",
     *     @OA\JsonContent(ref="#/components/schemas/ContestProblem")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function singleAction(Request $request, string $id): Response
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

    /**
     * Get the statement for given problem for this contest.
     * @throws NonUniqueResultException
     * @Rest\Get("/{id}/statement")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given problem statement for this contest",
     *     @OA\MediaType(mediaType="application/pdf")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function statementAction(Request $request, string $id): Response
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->addSelect('partial p.{probid,problemtext}')
            ->setParameter('id', $id)
            ->andWhere(sprintf('%s = :id', $this->getIdField()));

        // Get the one result; we know it's only one since we filter on ID
        $contestProblemData = $queryBuilder->getQuery()->getOneOrNullResult();

        if (empty($contestProblemData)) {
            throw new NotFoundHttpException(sprintf('Problem with ID \'%s\' not found', $id));
        }

        // The result contains the contest problem as well as the test data
        // count which should not be disclosed to the contestants; so get only
        // the problem.
        /** @var ContestProblem $contestProblem */
        $contestProblem = $contestProblemData[0];

        if ($contestProblem->getProblem()->getProblemtextType() !== 'pdf') {
            throw new NotFoundHttpException(sprintf('Problem with ID \'%s\' has no PDF statement', $id));
        }

        return $contestProblem->getProblem()->getProblemTextStreamedResponse();
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
            ->select('cp, partial p.{probid,externalid,name,timelimit,memlimit,problemtext_type}, COUNT(tc.testcaseid) AS testdatacount')
            ->andWhere('cp.contest = :cid')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter('cid', $contestId)
            ->orderBy('cp.shortname')
            ->groupBy('cp.problem');

        // For non-API-reader users, only expose the problems after the contest has started.
        if (!$this->dj->checkrole('api_reader') && $contest->getStartTimeObject()->getTimestamp() > time()) {
            $queryBuilder->andWhere('1 = 0');
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return sprintf('p.%s', $this->eventLogService->externalIdFieldForEntity(Problem::class) ?? 'probid');
    }

    /**
     * Transform the given object before returning it from the API.
     * @param array $object
     * @return ContestProblem|ContestProblemWrapper
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
