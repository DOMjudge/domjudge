<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\AddSubmission;
use App\DataTransferObject\SourceCode;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Language;
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
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractRestController<Submission, Submission>
 */
#[Rest\Route('/')]
#[OA\Tag(name: 'Submissions')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class SubmissionController extends AbstractRestController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        protected readonly SubmissionService $submissionService
    ) {
        parent::__construct($entityManager, $dj, $config, $eventLogService);
    }

    /**
     * Get all the submissions for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('submissions')]
    #[Rest\Get('contests/{cid}/submissions')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the submissions for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Submission::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    #[OA\Parameter(
        name: 'language_id',
        description: 'Only show submissions for the given language',
        in: 'query',
        schema: new OA\Schema(type: 'string')
    )]
    public function listAction(Request $request): Response
    {
        return parent::performListAction($request);
    }

    /**
     * Get the given submission for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('submissions/{id}')]
    #[Rest\Get('contests/{cid}/submissions/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given submission for this contest',
        content: new OA\JsonContent(ref: new Model(type: Submission::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): Response
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Add a submission to this contest.
     * @throws NonUniqueResultException
     */
    #[IsGranted(
        new Expression("is_granted('ROLE_TEAM') or is_granted('ROLE_API_WRITER')"),
        message: 'You need to have the Team Member role to add a submission'
    )]
    #[Rest\Post('contests/{cid}/submissions')]
    #[Rest\Put('contests/{cid}/submissions/{id}')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: new Model(type: AddSubmission::class))
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'When submitting was successful',
        content: new OA\JsonContent(ref: new Model(type: Submission::class))
    )]
    public function addSubmissionAction(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        AddSubmission $addSubmission,
        Request $request,
        ?string $id
    ): Response {
        $problemId = $addSubmission->problem ?? $addSubmission->problemId;
        $languageId = $addSubmission->language ?? $addSubmission->languageId;

        // By default, use the user and team of the user.
        $user = $this->dj->getUser();
        $team = $user->getTeam();
        $teamId = $addSubmission->teamId;
        if ($teamId) {
            // If the user is an admin or API writer, allow it to specify the team.
            if ($this->isGranted('ROLE_API_WRITER')) {
                /** @var Contest $contest */
                $contest = $this->em->getRepository(Contest::class)->find($this->getContestId($request));
                /** @var Team $team */
                $team = $this->dj->loadTeam($teamId, $contest);
                $user = $team->getUsers()->first() ?: null;
            } elseif (!$team) {
                throw new BadRequestHttpException('User does not belong to a team.');
            } elseif ($team->getExternalid() !== $teamId) {
                throw new BadRequestHttpException('Can not submit for a different team.');
            }
        } elseif (!$team) {
            throw new BadRequestHttpException('User does not belong to a team.');
        }

        if ($userId = $addSubmission->userId) {
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
        /** @var ContestProblem|null $problem */
        $problem = $this->em->createQueryBuilder()
            ->from(ContestProblem::class, 'cp')
            ->join('cp.problem', 'p')
            ->join('cp.contest', 'c')
            ->select('cp, c')
            ->andWhere('p.externalid = :problem')
            ->andWhere('cp.contest = :contest')
            ->andWhere('cp.allowSubmit = 1')
            ->setParameter('problem', $problemId)
            ->setParameter('contest', $this->getContestId($request))
            ->getQuery()
            ->getOneOrNullResult();

        if ($problem === null) {
            throw new BadRequestHttpException(
                sprintf("Problem '%s' not found or not submittable.", $problemId));
        }

        // Load the language.
        /** @var Language|null $language */
        $language = $this->em->createQueryBuilder()
            ->from(Language::class, 'lang')
            ->select('lang')
            ->andWhere('lang.externalid = :language')
            ->andWhere('lang.allowSubmit = 1')
            ->setParameter('language', $languageId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($language === null) {
            throw new BadRequestHttpException(
                sprintf("Language '%s' not found or not submittable.", $languageId));
        }

        // Determine the entry point.
        $entryPoint = null;
        if ($language->getRequireEntryPoint()) {
            if (!$addSubmission->entryPoint) {
                $entryPointDescription = $language->getEntryPointDescription() ?: 'Entry point';
                throw new BadRequestHttpException(sprintf('%s required, but not specified.', $entryPointDescription));
            }
            $entryPoint = $addSubmission->entryPoint;
        }

        $time = null;
        if ($timeString = $addSubmission->time) {
            if ($this->isGranted('ROLE_API_WRITER')) {
                try {
                    $time = Utils::toEpochFloat($timeString);
                } catch (Exception) {
                    throw new BadRequestHttpException(sprintf("Can not parse time '%s'.", $timeString));
                }
            } else {
                throw new BadRequestHttpException('A team can not assign time.');
            }
        }

        if ($submissionId = $addSubmission->id) {
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
                    ->andWhere('s.externalid = :submitid')
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

        if ($addSubmission->files) {
            // CCS spec format, files are a ZIP, get them and transform them into a file object.
            if (count($addSubmission->files) !== 1 || !isset($addSubmission->files[0]->data)) {
                throw new BadRequestHttpException("The 'files' attribute must be an array with a single item, containing an object with a base64 encoded data field.");
            }

            if ($addSubmission->files[0]->mime && $addSubmission->files[0]->mime !== 'application/zip') {
                throw new BadRequestHttpException("The 'files[0].mime' attribute must be application/zip if provided.");
            }

            $data        = $addSubmission->files[0]->data;
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
     * @throws NonUniqueResultException
     */
    #[IsGranted('ROLE_API_SOURCE_READER')]
    #[Rest\Get('contests/{cid}/submissions/{id}/files', name: 'submission_files')]
    #[Rest\Get('submissions/{id}/files', name: 'submission_files_root')]
    #[OA\Response(
        response: 200,
        description: 'The files for the submission as a ZIP archive',
        content: new OA\MediaType(mediaType: 'application/zip')
    )]
    #[OA\Response(response: 500, description: 'An error occurred while creating the ZIP file')]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function getSubmissionFilesAction(Request $request, string $id): Response
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->join('s.files', 'f')
            ->select('s, f')
            ->setParameter('id', $id);

        $queryBuilder->andWhere('s.externalid = :id');

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
     * @throws NonUniqueResultException
     * @return SourceCode[]
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')"))]
    #[Rest\Get('contests/{cid}/submissions/{id}/source-code')]
    #[OA\Response(
        response: 200,
        description: 'The files for the submission',
        content: new OA\JsonContent(ref: new Model(type: SourceCode::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
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
            $result[] = new SourceCode(
                id: (string)$file->getSubmitfileid(),
                submissionId: (string)$file->getSubmission()->getSubmitid(),
                filename: $file->getFilename(),
                source: base64_encode($file->getSourcecode()),
            );
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
        return 's.externalid';
    }
}
