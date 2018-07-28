<?php

namespace DOMJudgeBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFile;
use DOMJudgeBundle\Entity\Team;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class FileController extends Controller
{
    /**
     * @Route("/api/contests/{cid}/submissions/{sid}/files", name="submission_file")
     * @Route("/api/v4/contests/{cid}/submissions/{sid}/files", name="submission_file_v4")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function submissionFiles(Contest $contest, Submission $submission)
    {
        if ($submission->getCid() != $contest->getCid()) {
            return new Response("Submission s" . $submission->getSubmitid() .
                                " not found in contest '" . $contest->getCid() . "'.", 404);
        }

        $files = $submission->getFiles();
        $zip = new \ZipArchive;
        if (!($tmpfname = tempnam($this->getParameter('domjudge.tmpdir'), "submission_file-"))) {
            return new Response("Could not create temporary file.", 500);
        }

        $res = $zip->open($tmpfname, \ZipArchive::OVERWRITE);
        if ($res !== true) {
            return new Response("Could not create temporary zip file.", 500);
        }
        foreach ($files as $file) {
            $zip->addFromString($file->getFilename(), stream_get_contents($file->getSourcecode()));
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
}
