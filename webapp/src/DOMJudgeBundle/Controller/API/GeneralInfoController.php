<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Swagger\Annotations as SWG;

/**
 * @Rest\Route("/api/v4", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api")
 * @Rest\NamePrefix("general_")
 * @SWG\Tag(name="General")
 */
class GeneralInfoController extends FOSRestController
{
    protected $apiVersion = 4;

    /**
     * Get the current API version
     * @Rest\Get("/version")
     * @SWG\Response(
     *     response="200",
     *     description="The current API version information",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="api_version", type="integer")
     *     )
     * )
     */
    public function getVersionAction()
    {
        $data = ['api_version' => $this->apiVersion];
        return $data;
    }

    /**
     * Get information about the API and DOMjudge
     * @Rest\Get("/info")
     * @SWG\Response(
     *     response="200",
     *     description="Information about the API and DOMjudge",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="api_version", type="integer"),
     *         @SWG\Property(property="domjudge_version", type="string")
     *     )
     * )
     */
    public function getInfoAction()
    {
        $data = [
            'api_version' => $this->apiVersion,
            'domjudge_version' => $this->getParameter('domjudge.version'),
        ];
        return $data;
    }
}
