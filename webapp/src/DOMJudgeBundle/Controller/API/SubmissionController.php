<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFile;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/api/v4/contests/{cid}/submissions", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/contests/{cid}/submissions")
 * @Rest\NamePrefix("submission_")
 * @SWG\Tag(name="Submissions")
 * @SWG\Parameter(ref="#/parameters/cid")
 * @SWG\Response(response="404", ref="#/definitions/NotFound")
 * @SWG\Response(response="401", ref="#/definitions/Unauthorized")
 */
class SubmissionController extends AbstractRestController
{
    /**
     * Get all the submissions for this contest
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Rest\Get("")
     * @SWG\Response(
     *     response="200",
     *     description="Returns all the submissions for this contest",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             allOf={
     *                 @SWG\Schema(ref=@Model(type=Submission::class)),
     *                 @SWG\Schema(ref="#/definitions/Files")
     *             }
     *         )
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/idlist")
     * @SWG\Parameter(
     *     name="language_id",
     *     in="query",
     *     type="string",
     *     description="Only show submissions for the given language"
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
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Rest\Get("/{id}")
     * @SWG\Response(
     *     response="200",
     *     description="Returns the given submission for this contest",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Submission::class)),
     *             @SWG\Schema(ref="#/definitions/Files")
     *         }
     *     )
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     */
    public function singleAction(Request $request, string $id)
    {
        return parent::performSingleAction($request, $id);
    }

    /**
     * Get the files for the given submission as a ZIP archive
     * @Rest\Get("/{id}/files", name="submission_files")
     * @SWG\Get(produces={"application/zip"})
     * @Security("has_role('ROLE_ADMIN')")
     * @param Request $request
     * @param string $id
     * @return Response|StreamedResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @SWG\Response(
     *     response="200",
     *     description="The files for the submission as a ZIP archive"
     * )
     * @SWG\Response(
     *     response="500",
     *     description="An error occurred while creating the ZIP file"
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     */
    public function getSubmissionFilesAction(Request $request, string $id)
    {
        $queryBuilder = $this->getQueryBuilder($request)
            ->join('f.submission_file_source_code', 'sc')
            ->select('s, f, sc')
            ->andWhere(sprintf('%s = :id', $this->getIdField()))
            ->setParameter(':id', $id);

        /** @var Submission[] $submissions */
        $submissions = $queryBuilder->getQuery()->getResult();

        if (empty($submissions)) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found', $id));
        }

        $submission = reset($submissions);

        /** @var SubmissionFile[] $files */
        $files = $submission->getFiles();
        $zip   = new \ZipArchive;
        if (!($tmpfname = tempnam($this->getParameter('domjudge.tmpdir'), "submission_file-"))) {
            return new Response("Could not create temporary file.", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $res = $zip->open($tmpfname, \ZipArchive::OVERWRITE);
        if ($res !== true) {
            return new Response("Could not create temporary zip file.", Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        foreach ($files as $file) {
            $zip->addFromString($file->getFilename(),
                                stream_get_contents($file->getSubmissionFileSourceCode()->getSourcecode()));
        }
        $zip->close();

        $filename = 's' . $submission->getSubmitid() . '.zip';

        $response = new StreamedResponse();
        $response->setCallback(function () use ($tmpfname) {
            $fp = fopen($tmpfname, 'rb');
            fpassthru($fp);
            unlink($tmpfname);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Content-Length', filesize($tmpfname));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Connection', 'Keep-Alive');
        $response->headers->set('Accept-Ranges', 'bytes');

        return $response;
    }

    /**
     * Get the source code of all the files for the given submission
     * @Rest\Get("/{id}/source-code")
     * @Security("has_role('ROLE_JUDGEHOST') or has_role('ROLE_JURY')")
     * @param Request $request
     * @param string $id
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @SWG\Response(
     *     response="200",
     *     description="The files for the submission",
     *     @SWG\Schema(ref="#/definitions/SourceCodeList")
     * )
     * @SWG\Parameter(ref="#/parameters/id")
     */
    public function getSubmissionSourceCodeAction(Request $request, string $id)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:SubmissionFile', 'f')
            ->join('f.submission_file_source_code', 'sc')
            ->join('f.submission', 's')
            ->select('f, sc, s')
            ->andWhere('s.cid = :cid')
            ->andWhere('s.submitid = :submitid')
            ->setParameter(':cid', $this->getContestId($request))
            ->setParameter(':submitid', $id)
            ->orderBy('f.rank');

        /** @var SubmissionFile[] $files */
        $files = $queryBuilder->getQuery()->getResult();

        if (empty($files)) {
            throw new NotFoundHttpException(sprintf('Source code for submission with ID \'%s\' not found', $id));
        }

        $result = [];
        foreach ($files as $file) {
            $sourceCode = $file->getSubmissionFileSourceCode();
            $result[]   = [
                'id' => (string)$file->getSubmitfileid(),
                'submission_id' => (string)$file->getSubmitid(),
                'filename' => $file->getFilename(),
                'source' => base64_encode(stream_get_contents($sourceCode->getSourcecode())),
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
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->join('s.files', 'f')
            ->join('s.problem', 'p')
            ->join('s.contest', 'c')
            ->join('s.language', 'lang')
            ->join('s.team', 'team')
            ->select('s, f, team, lang, p')
            ->andWhere('s.valid = 1')
            ->andWhere('s.cid = :cid')
            ->setParameter(':cid', $cid)
            ->orderBy('s.submitid');

        if ($request->query->has('language_id')) {
            $queryBuilder
                ->andWhere('s.langid = :langid')
                ->setParameter(':langid', $request->query->get('language_id'));
        }

        // If an ID has not been given directly, only show submissions before contest end
        // This allows us to use eventlog on too-late submissions while not exposing them in the API directly
        if (!$request->attributes->has('id') && !$request->query->has('ids')) {
            $queryBuilder->andWhere('s.submittime < c.endtime');
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
