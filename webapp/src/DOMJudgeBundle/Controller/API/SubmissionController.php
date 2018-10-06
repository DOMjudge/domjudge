<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\QueryBuilder;
use DOMJudgeBundle\Entity\SubmissionFile;
use DOMJudgeBundle\Entity\SubmissionFileSourceCode;
use DOMJudgeBundle\Entity\Submission;
use FOS\RestBundle\Controller\Annotations as Rest;
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
     * Get the files for the given submission as a ZIP archive
     * @Rest\Get("/{id}/files")
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
            ->setParameter(':id', $id)
            ->setMaxResults(1);

        /** @var Submission $submission */
        $submission = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($submission === null) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found', $id));
        }

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
            $zip->addFromString($file->getFilename(), stream_get_contents($file->getSubmissionFileSourceCode()->getSourcecode()));
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
     * Get the query builder to use for request for this REST endpoint
     * @param Request $request
     * @return QueryBuilder
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Submission', 's')
            ->join('s.files', 'f')
            ->select('s, f')
            ->where('s.cid = :cid')
            ->setParameter(':cid', $this->getContestId($request));
    }

    /**
     * Return the field used as ID in requests
     * @return string
     */
    protected function getIdField(): string
    {
        return 's.submitid';
    }
}
