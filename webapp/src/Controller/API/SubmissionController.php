<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionFile;
use App\Entity\Team;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * @Rest\Route("/")
 * @OA\Tag(name="Submissions")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class SubmissionController extends AbstractRestController
{
    protected SubmissionService $submissionService;

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
     * Get all the submissions for this contest.
     * @Rest\Get("submissions")
     * @Rest\Get("contests/{cid}/submissions")
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
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given submission for this contest.
     * @throws NonUniqueResultException
     * @Rest\Get("submissions/{id}")
     * @Rest\Get("contests/{cid}/submissions/{id}")
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
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a submission to this contest.
     * @Rest\Post("contests/{cid}/submissions")
     * @Rest\Put("contests/{cid}/submissions/{id}")
     * @Security("is_granted('ROLE_TEAM') or is_granted('ROLE_API_WRITER')", message="You need to have the Team Member role to add a submission")
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
     *     ),
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             required={"problem_id","language_id","files"},
     *             @OA\Property(
     *                 property="problem_id",
     *                 description="The problem ID to submit a solution for",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="language_id",
     *                 description="The language to submit a solution in",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="team_id",
     *                 description="The team to submit a solution for. Only used when adding a submission as admin",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="time",
     *                 description="The time to use for the submission. Only used when adding a submission as admin",
     *                 type="string",
     *                 format="date-time"
     *             ),
     *             @OA\Property(
     *                 property="id",
     *                 description="The ID to use for the submission. Only used when adding a submission as admin and only allowed with PUT",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="files",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=1,
     *                 description="The base64 encoded ZIP file to submit",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"data"},
     *                     @OA\Property(property="data", type="string", description="The base64 encoded ZIP archive")
     *                 )
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
     *     @OA\JsonContent(
     *         allOf={
     *             @OA\Schema(ref=@Model(type=Submission::class)),
     *             @OA\Schema(ref="#/components/schemas/Files")
     *         }
     *     )
     * )
     * @throws NonUniqueResultException
     */
    public function addSubmissionAction(Request $request, ?string $id): Response
    {
        $required = [
            'problem'  => ['problem', 'problem_id'],
            'language' => ['language', 'language_id'],
        ];
        $data = [];

        foreach ($required as $field => $requiredList) {
            $hasAny = false;
            foreach ($requiredList as $argument) {
                if ($request->request->has($argument)) {
                    $data[$field] = $request->request->get($argument);
                    $hasAny       = true;
                }
            }
            if (!$hasAny) {
                $requiredListQuoted = array_map(fn($item) => "'$item'", $requiredList);
                throw new BadRequestHttpException(
                    sprintf("One of the arguments %s is mandatory.", implode(', ', $requiredListQuoted)));
            }
        }

        // By default, use the user and team of the user.
        $user = $this->dj->getUser();
        $team = $user->getTeam();
        if ($teamId = $request->request->get('team_id')) {
            $idField = $this->eventLogService->externalIdFieldForEntity(Team::class) ?? 'teamid';
            $method  = sprintf('get%s', ucfirst($idField));

            // If the user is an admin or API writer, allow it to specify the team.
            if ($this->isGranted('ROLE_API_WRITER')) {
                /** @var Contest $contest */
                $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
                /** @var Team $team */
                $team = $this->dj->loadTeam($idField, $teamId, $contest);
                $user = $team->getUsers()->first() ?: null;
            } elseif (!$team) {
                throw new BadRequestHttpException('User does not belong to a team.');
            } elseif ((string)call_user_func([$team, $method]) !== (string)$teamId) {
                throw new BadRequestHttpException('Can not submit for a different team.');
            }
        } elseif (!$team) {
            throw new BadRequestHttpException('User does not belong to a team.');
        }

        if ($userId = $request->request->get('user_id')) {
            // If the current user is an admin or API writer, allow it to specify the user.
            if ($this->isGranted('ROLE_API_WRITER')) {
                // Load the user.
                /** @var User|null $user */
                $user = $this->em->getRepository(User::class)->find($userId);

                if (!$user) {
                    throw new BadRequestHttpException("User not found.");
                }
                if (!$user->getEnabled()) {
                    throw new BadRequestHttpException("User not enabled.");
                }
                if (!$user->getTeam()) {
                    throw new BadRequestHttpException("User not linked to a team.");
                }
                if ($user->getTeam()->getTeamid() !== $team->getTeamid()) {
                    throw new BadRequestHttpException("User not linked to provided team.");
                }
            } elseif ($user->getUserid() !== (int)$userId) {
                throw new BadRequestHttpException('Can not submit for a different user.');
            }
        }

        // Load the problem.
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
            ->setParameter('problem', $data['problem'])
            ->setParameter('contest', $this->getContestId($request))
            ->getQuery()
            ->getOneOrNullResult();

        if ($problem === null) {
            throw new BadRequestHttpException(
                sprintf("Problem '%s' not found or not submittable.", $data['problem']));
        }

        // Load the language.
        /** @var Language $language */
        $language = $this->em->createQueryBuilder()
            ->from(Language::class, 'lang')
            ->select('lang')
            ->andWhere(sprintf('lang.%s = :language',
                               $this->eventLogService->externalIdFieldForEntity(Language::class) ?? 'langid'))
            ->andWhere('lang.allowSubmit = 1')
            ->setParameter('language', $data['language'])
            ->getQuery()
            ->getOneOrNullResult();

        if ($language === null) {
            throw new BadRequestHttpException(
                sprintf("Language '%s' not found or not submittable.", $data['language']));
        }

        // Determine the entry point.
        $entryPoint = null;
        if ($language->getRequireEntryPoint()) {
            if (!$request->request->get('entry_point')) {
                $entryPointDescription = $language->getEntryPointDescription() ?: 'Entry point';
                throw new BadRequestHttpException(sprintf('%s required, but not specified.', $entryPointDescription));
            }
            $entryPoint = $request->request->get('entry_point');
        }

        $time = null;
        if ($timeString = $request->request->get('time')) {
            if ($this->isGranted('ROLE_API_WRITER')) {
                try {
                    $time = Utils::toEpochFloat($timeString);
                } catch (Exception $e) {
                    throw new BadRequestHttpException(sprintf("Can not parse time '%s'.", $timeString));
                }
            } else {
                throw new BadRequestHttpException('A team can not assign time.');
            }
        }

        if ($submissionId = $request->request->get('id')) {
            if ($request->isMethod('POST')) {
                throw new BadRequestHttpException('Passing an ID is not supported for POST.');
            } elseif ($id !== $submissionId) {
                throw new BadRequestHttpException('ID does not match URI.');
            } elseif ($this->isGranted('ROLE_API_WRITER')) {
                if (preg_match(DOMJudgeService::EXTERNAL_IDENTIFIER_REGEX, $submissionId) !== 1) {
                    throw new BadRequestHttpException(sprintf("ID '%s' is not valid.", $submissionId));
                }

                // Check if we already have a submission with this ID.
                $existingSubmission = $this->em->createQueryBuilder()
                    ->from(Submission::class, 's')
                    ->select('s')
                    ->andWhere('(s.externalid IS NULL AND s.submitid = :submitid) OR s.externalid = :submitid')
                    ->andWhere('s.contest = :contest')
                    ->setParameter('submitid', $submissionId)
                    ->setParameter('contest', $problem->getContest())
                    ->getQuery()
                    ->getOneOrNullResult();
                if ($existingSubmission !== null) {
                    throw new BadRequestHttpException(sprintf("Submission with ID '%s' already exists.", $submissionId));
                }
            } else {
                throw new BadRequestHttpException('A team can not assign id.');
            }
        }

        $tempFiles = [];

        if ($request->request->has('files')) {
            // CCS spec format, files are a ZIP, get them and transform them into a file object.
            $filesList = $request->request->all('files');
            if (count($filesList) !== 1 || !isset($filesList[0]['data'])) {
                throw new BadRequestHttpException("The 'files' attribute must be an array with a single item, containing an object with a base64 encoded data field.");
            }

            if (isset($filesList[0]['mime']) && $filesList[0]['mime'] !== 'application/zip') {
                throw new BadRequestHttpException("The 'files[0].mime' attribute must be application/zip if provided.");
            }

            $data        = $filesList[0]['data'];
            $decodedData = base64_decode($data, true);
            if ($decodedData === false) {
                throw new BadRequestHttpException("The 'files[0].data' attribute is not base64 encoded.");
            }

            $tmpDir = $this->dj->getDomjudgeTmpDir();

            // Now write the data to a temporary ZIP file.
            if (!($tempZipFile = tempnam($tmpDir, 'submission_zip-'))) {
                throw new ServiceUnavailableHttpException(null,
                    sprintf('Could not create temporary file in directory %s', $tmpDir));
            }

            if (file_put_contents($tempZipFile, $decodedData) === false) {
                throw new ServiceUnavailableHttpException(
                    null,
                    sprintf("Could not write ZIP to temporary file '%s'.", $tempZipFile)
                );
            }

            $zip = $this->dj->openZipFile($tempZipFile);

            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name   = $zip->getNameIndex($i);
                $source = $zip->getFromIndex($i);

                if (!($tempFileName = tempnam($tmpDir, 'submission-'))) {
                    throw new ServiceUnavailableHttpException(null,
                        sprintf('Could not create temporary file in directory %s', $tmpDir));
                }
                if (file_put_contents($tempFileName, $source) === false) {
                    throw new ServiceUnavailableHttpException(
                        null,
                        sprintf("Could not write to temporary file '%s'.", $tempFileName)
                    );
                }
                $files[]     = new UploadedFile($tempFileName, $name, null, null, true);
                $tempFiles[] = $tempFileName;
            }

            $zip->close();
            unlink($tempZipFile);
        } else {
            // Files are uploaded as actual files, get them.
            $files = $request->files->get('code') ?: [];
            if (!is_array($files)) {
                $files = [$files];
            }
        }

        // Now submit the solution.
        $submission = $this->submissionService->submitSolution(
            $team, $user, $problem, $problem->getContest(), $language,
            $files, 'API', null, null, $entryPoint, $submissionId, $time, $message
        );

        // Clean up temporary if needed.
        foreach ($tempFiles as $tempFile) {
            unlink($tempFile);
        }

        if (!$submission) {
            throw new BadRequestHttpException($message);
        }

        return $this->renderData($request, $submission);
    }

    /**
     * Get the files for the given submission as a ZIP archive.
     * @Rest\Get("contests/{cid}/submissions/{id}/files", name="submission_files")
     * @Rest\Get("submissions/{id}/files", name="submission_files_root")
     * @IsGranted("ROLE_API_SOURCE_READER")
     * @throws NonUniqueResultException
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
    public function getSubmissionFilesAction(Request $request, string $id): Response
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->join('s.files', 'f')
            ->select('s, f')
            ->setParameter('id', $id);

        $idField = $this->getIdField();
        if ($idField === 's.submitid') {
            $queryBuilder->andWhere('(s.externalid IS NULL AND s.submitid = :id) OR s.externalid = :id');
        } else {
            $queryBuilder->andWhere(sprintf('%s = :id', $idField));
        }

        /** @var Submission[] $submissions */
        $submissions = $queryBuilder->getQuery()->getResult();

        if (empty($submissions)) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found', $id));
        }

        $submission = reset($submissions);

        return $this->submissionService->getSubmissionZipResponse($submission);
    }

    /**
     * Get the source code of all the files for the given submission.
     * @Rest\Get("contests/{cid}/submissions/{id}/source-code")
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')")
     * @throws NonUniqueResultException
     * @OA\Response(
     *     response="200",
     *     description="The files for the submission",
     *     @OA\JsonContent(ref="#/components/schemas/SourceCodeList")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     */
    public function getSubmissionSourceCodeAction(Request $request, string $id): array
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(SubmissionFile::class, 'f')
            ->join('f.submission', 's')
            ->select('f, s')
            ->andWhere('s.contest = :cid')
            ->andWhere('s.submitid = :submitid')
            ->setParameter('cid', $this->getContestId($request))
            ->setParameter('submitid', $id)
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
     * @throws NonUniqueResultException
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.contest', 'c')
            ->join('s.team', 't')
            ->select('s')
            ->andWhere('s.valid = 1')
            ->andWhere('t.enabled = 1')
            ->orderBy('s.submitid');
        if ($request->attributes->has('cid')) {
            $cid = $this->getContestId($request);
            $queryBuilder
                ->andWhere('s.contest = :cid')
                ->setParameter('cid', $cid);
        }


        if ($request->query->has('language_id')) {
            $queryBuilder
                ->andWhere('s.language = :langid')
                ->setParameter('langid', $request->query->get('language_id'));
        }

        // If an ID has not been given directly, only show submissions before contest end.
        // This allows us to use eventlog on too-late submissions while not exposing them in the API directly.
        if (!$request->attributes->has('id') && !$request->query->has('ids') && !$this->dj->checkrole('admin')) {
            $queryBuilder->andWhere('s.submittime < c.endtime');
        }

        if (!$this->dj->checkrole('api_reader') &&
            !$this->dj->checkrole('judgehost')) {
            $queryBuilder
                ->join('t.category', 'cat');
            if ($this->dj->checkrole('team')) {
                $queryBuilder
                    ->andWhere('cat.visible = 1 OR s.team = :team')
                    ->setParameter('team', $this->dj->getUser()->getTeam());
            } else {
                // Hide all submissions made by non-public teams.
                $queryBuilder->andWhere('cat.visible = 1');
            }
        }

        return $queryBuilder;
    }

    protected function getIdField(): string
    {
        return sprintf('s.%s', $this->eventLogService->externalIdFieldForEntity(Submission::class) ?? 'submitid');
    }
}
