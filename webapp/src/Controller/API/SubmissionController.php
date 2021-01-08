<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/contests/{cid}/submissions")
 * @OA\Tag(name="Submissions")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="404", ref="#/components/schemas/NotFound")
 * @OA\Response(response="401", ref="#/components/schemas/Unauthorized")
 */
class SubmissionController extends AbstractRestController
{
    /**
     * @var SubmissionService
     */
    protected $submissionService;

    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        SubmissionService $submissionService
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
        $this->submissionService = $submissionService;
    }

    /**
     * Get all the submissions for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the submissions for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             allOf={
     *                 @OA\Schema(ref=@Model(type=Submission::class)),
     *                 @OA\Schema(ref="#/components/schemas/Files")
     *             }
     *         )
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(ref="#/components/parameters/strict")
     * @OA\Parameter(
     *     name="language_id",
     *     in="query",
     *     description="Only show submissions for the given language",
     *     @OA\Schema(type="string")
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request)
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given submission for this contest
     * @param Request $request
     * @param string  $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given submission for this contest",
     *     @OA\JsonContent(
     *         allOf={
     *             @OA\Schema(ref=@Model(type=Submission::class)),
     *             @OA\Schema(ref="#/components/schemas/Files")
     *         }
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id)
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a submission to this contest
     * @param Request $request
     * @return int
     * @Rest\Post("")
     * @OA\Post()
     * @IsGranted("ROLE_TEAM", message="You need to have the Team Member role to add a submission")
     * Uploading an array of files in swagger is not supported, see
     * @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"problem","language","code"},
     *             @OA\Property(
     *                 property="problem",
     *                 description="The problem to submit a solution for",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="language",
     *                 description="The language to submit a solution in",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="code",
     *                 type="array",
     *                 description="The file(s) to submit",
     *                 @OA\Items(type="string", format="binary")
     *             ),
     *             @OA\Property(
     *                 property="entry_point",
     *                 type="string",
     *                 description="The entry point for the submission. Required for languages requiring an entry point",
     *             )
     *         )
     *     )
     * )
     * @OA\Response(
     *     response="200",
     *     description="When submitting was successful",
     *     @OA\JsonContent(type="integer", description="The ID of the submitted solution")
     * )
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function addSubmissionAction(Request $request)
    {
        $required = [
            'problem',
            'language'
        ];

        foreach ($required as $argument) {
            if (!$request->request->has($argument)) {
                throw new BadRequestHttpException(
                    sprintf("Argument '%s' is mandatory", $argument));
            }
        }

        if (!$this->dj->getUser()->getTeam()) {
            throw new BadRequestHttpException(sprintf('User does not belong to a team'));
        }

        // Load the problem
        /** @var ContestProblem $problem */
        $problem = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->join('cp.problem', 'p')
            ->join('cp.contest', 'c')
            ->select('cp, c')
            ->andWhere(sprintf('p.%s = :problem',
                               $this->eventLogService->externalIdFieldForEntity(Problem::class) ?? 'probid'))
            ->andWhere('cp.contest = :contest')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter(':problem', $request->request->get('problem'))
            ->setParameter(':contest', $this->getContestId($request))
            ->getQuery()
            ->getOneOrNullResult();

        if ($problem === null) {
            throw new BadRequestHttpException(
                sprintf("Problem %s not found or or not submittable", $request->request->get('problem')));
        }

        // Load the language
        /** @var Language $language */
        $language = $this->em->createQueryBuilder()
            ->from(Language::class, 'lang')
            ->select('lang')
            ->andWhere(sprintf('lang.%s = :language',
                               $this->eventLogService->externalIdFieldForEntity(Language::class) ?? 'langid'))
            ->andWhere('lang.allowSubmit = 1')
            ->setParameter(':language', $request->request->get('language'))
            ->getQuery()
            ->getOneOrNullResult();

        if ($language === null) {
            throw new BadRequestHttpException(
                sprintf("Language %s not found or or not submittable", $request->request->get('language')));
        }

        // Determine the entry point
        $entryPoint = null;
        if ($language->getRequireEntryPoint()) {
            if (!$request->request->get('entry_point')) {
                $entryPointDescription = $language->getEntryPointDescription() ?: 'Entry point';
                throw new BadRequestHttpException(sprintf('%s required, but not specified.', $entryPointDescription));
            }
            $entryPoint = $request->request->get('entry_point');
        }

        // Get the files we want to submit
        $files = $request->files->get('code') ?: [];
        if (!is_array($files)) {
            $files = [$files];
        }

        // Now submit the solution
        $team       = $this->dj->getUser()->getTeam();
        $submission = $this->submissionService->submitSolution(
            $team, $problem, $problem->getContest(), $language,
            $files, null, null, $entryPoint, null, null, $message
        );
        if (!$submission) {
            throw new BadRequestHttpException($message);
        }

        return $submission->getSubmitid();
    }

    /**
     * Get the files for the given submission as a ZIP archive
     * @Rest\Get("/{id}/files", name="submission_files")
     * @IsGranted("ROLE_API_SOURCE_READER")
     * @param Request $request
     * @param string  $id
     * @return Response|StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @OA\Response(
     *     response="200",
     *     description="The files for the submission as a ZIP archive",
     *     @OA\MediaType(mediaType="application/zip")
     * )
     * @OA\Response(
     *     response="500",
     *     description="An error occurred while creating the ZIP file"
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function getSubmissionFilesAction(Request $request, string $id)
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->join('s.files', 'f')
            ->select('s, f')
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter(':id', $id);

        /** @var Submission[] $submissions */
        $submissions = $queryBuilder->getQuery()->getResult();

        if (empty($submissions)) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found', $id));
        }

        $submission = reset($submissions);

        return $this->submissionService->getSubmissionZipResponse($submission);
    }

    /**
     * Get the source code of all the files for the given submission
     * @Rest\Get("/{id}/source-code")
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')")
     * @param Request $request
     * @param string  $id
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @OA\Response(
     *     response="200",
     *     description="The files for the submission",
     *     @OA\JsonContent(ref="#/components/schemas/SourceCodeList")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function getSubmissionSourceCodeAction(Request $request, string $id)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(SubmissionFile::class, 'f')
            ->join('f.submission', 's')
            ->select('f, s')
            ->andWhere('s.contest = :cid')
            ->andWhere('s.submitid = :submitid')
            ->setParameter(':cid', $this->getContestId($request))
            ->setParameter(':submitid', $id)
            ->orderBy('f.ranknumber');

        /** @var SubmissionFile[] $files */
        $files = $queryBuilder->getQuery()->getResult();

        if (empty($files)) {
            throw new NotFoundHttpException(sprintf('Source code for submission with ID \'%s\' not found', $id));
        }

        $result = [];
        foreach ($files as $file) {
            $result[]   = [
                'id' => (string)$file->getSubmitfileid(),
                'submission_id' => (string)$file->getSubmission()->getSubmitid(),
                'filename' => $file->getFilename(),
                'source' => base64_encode($file->getSourcecode()),
            ];
        }
        return $result;
    }

    /**
     * Get the query builder to use for request for this REST endpoint
     * @param Request $request
     * @return QueryBuilder
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $cid          = $this->getContestId($request);
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.contest', 'c')
            ->join('s.team', 't')
            ->select('s')
            ->andWhere('s.valid = 1')
            ->andWhere('s.contest = :cid')
            ->andWhere('t.enabled = 1')
            ->setParameter(':cid', $cid)
            ->orderBy('s.submitid');

        if ($request->query->has('language_id')) {
            $queryBuilder
                ->andWhere('s.language = :langid')
                ->setParameter(':langid', $request->query->get('language_id'));
        }

        // If an ID has not been given directly, only show submissions before contest end
        // This allows us to use eventlog on too-late submissions while not exposing them in the API directly
        if (!$request->attributes->has('id') && !$request->query->has('ids')) {
            $queryBuilder->andWhere('s.submittime < c.endtime');
        }

        if (!$this->dj->checkrole('api_reader') &&
            !$this->dj->checkrole('judgehost'))
        {
            $queryBuilder
                ->join('t.category', 'cat');
            if ($this->dj->checkrole('team')) {
                $queryBuilder
                    ->andWhere('cat.visible = 1 OR s.team = :team')
                    ->setParameter('team', $this->dj->getUser()->getTeam());
            } else {
                // Hide all submissions made by non public teams
                $queryBuilder->andWhere('cat.visible = 1');
            }
        }

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    protected function getIdField(): string
    {
        return sprintf('s.%s', $this->eventLogService->externalIdFieldForEntity(Submission::class) ?? 'submitid');
    }
}
