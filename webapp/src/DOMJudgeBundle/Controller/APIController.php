<?php declare(strict_types=1);
namespace DOMJudgeBundle\Controller;

use DOMJudgeBundle\Service\DOMJudgeService;
use FOS\RestBundle\Controller\FOSRestController;

use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Head;
use FOS\RestBundle\Controller\Annotations\Delete;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\Event;
use DOMJudgeBundle\Utils\Utils;

/**
 * @Route("/api", defaults={ "_format" = "json" })
 */
class APIController extends FOSRestController
{
    public $apiVersion = 4;

    /**
     * @var DOMJudgeService
     */
    private $DOMJudgeService;

    public function __construct(DOMJudgeService $DOMJudgeService)
    {
        $this->DOMJudgeService = $DOMJudgeService;
    }

    /**
     * @Get("/")
     */
    public function getCurrentActiveContestAction()
    {
        $contests = $this->getContestsAction();
        if (count($contests) == 0) {
            return null;
        } else {
            return $contests[0];
        }
    }

    /**
     * @Get("/version")
     */
    public function getVersionAction()
    {
        $data = ['api_version' => $this->apiVersion];
        return $data;
    }

    /**
     * @Get("/info")
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
