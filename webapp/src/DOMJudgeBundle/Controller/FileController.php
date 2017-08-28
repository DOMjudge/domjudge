<?php

namespace DOMJudgeBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use DOMJudgeBundle\Entity\Submission;
use DOMJudgeBundle\Entity\SubmissionFile;
use DOMJudgeBundle\Entity\Team;

class FileController extends Controller
{
	/**
	 *@Route("/api/submissions/{sid}/files", name="submission_file")
	 */
	public function submissionFiles(Submission $sid)
	{
		$files = $sid->getFiles();

		$zip = new \ZipArchive;
		if ( !($tmpfname = tempnam($this->getParameter('domjudge.tmpdir'), "submission_file-")) ) {
			error("Could not create temporary file.");
		}

		$res = $zip->open($tmpfname, \ZipArchive::OVERWRITE);
		if ( $res !== TRUE ) {
			error("Could not create temporary zip file.");
		}
		foreach ($files as $file) {
			$zip->addFromString($file->getFilename(), stream_get_contents($file->getSourcecode()));
		}
		$zip->close();

		$filename = 's' . $sid->getSubmitid() . '.zip';

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
		$response->headers->set('Accept-Ranges','bytes');

		return $response;
	}
}
