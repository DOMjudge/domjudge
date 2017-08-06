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

  /**
   * @Get("/version")
   */
  public function getVersionAction() {
    $data = ['version'=>'2.0'];
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
