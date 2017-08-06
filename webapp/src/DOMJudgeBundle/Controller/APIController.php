<?php
namespace DOMJudgeBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;

use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Head;
use FOS\RestBundle\Controller\Annotations\Delete;



use DOMJudgeBundle\Entity\Contest;

/**
 * @Route("/api", defaults={ "_format" = "json" })
 */
class APIController extends FOSRestController {

	public $apiVersion = 4;
	// FIXME: get DOMjudge version from central location
	public $domjudgeVersion = "5.2.0DEV";

  /**
   * @Get("/version")
   */
  public function getVersionAction() {
	$data = ['api_version' => $this->apiVersion];
	return $data;
  }

  /**
   * @Get("/info")
   */
  public function getInfoAction() {
	  $data = [
		'api_version' => $this->apiVersion,
		'domjudge_version' => $this->domjudgeVersion,
	];
	return $data;
  }

  /**
   * @Get("/contests")
   * @View(serializerGroups={"details","problems"})
   */
  public function getContestsAction() {
	$em = $this->getDoctrine()->getManager();
	$data = $em->getRepository(Contest::class)->findAll();

	return $data;
  }
}
