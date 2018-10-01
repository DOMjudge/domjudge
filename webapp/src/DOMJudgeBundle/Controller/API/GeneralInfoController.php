<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Contest;
use DOMJudgeBundle\Entity\User;
use DOMJudgeBundle\Service\DOMJudgeService;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * GeneralInfoController constructor.
     * @param EntityManagerInterface $entityManager
     * @param DOMJudgeService $DOMJudgeService
     */
    public function __construct(EntityManagerInterface $entityManager, DOMJudgeService $DOMJudgeService)
    {
        $this->entityManager   = $entityManager;
        $this->DOMJudgeService = $DOMJudgeService;
    }

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

    /**
     * Get general status information
     * @Rest\Get("/status")
     * @Security("has_role('ROLE_JURY')")
     * @SWG\Response(
     *     response="200",
     *     description="General status information for the currently active contest",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="num_submissions", type="integer"),
     *         @SWG\Property(property="num_queued", type="integer"),
     *         @SWG\Property(property="num_judging", type="integer")
     *     )
     * )
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getStatusAction()
    {
        if ($this->DOMJudgeService->checkrole('jury')) {
            $onlyOfTeam = null;
        } elseif ($this->DOMJudgeService->checkrole('team') && $this->DOMJudgeService->getUser()->getTeamid()) {
            $onlyOfTeam = $this->DOMJudgeService->getUser()->getTeamid();
        } else {
            $onlyOfTeam = -1;
        }
        $contests = $this->DOMJudgeService->getCurrentContests(true, $onlyOfTeam);
        if (empty($contests)) {
            throw new BadRequestHttpException('No active contest');
        }

        /** @var Contest $contest */
        $contest = reset($contests);

        $result                    = [];
        $result['num_submissions'] = (int)$this->entityManager
            ->createQuery(
                'SELECT COUNT(s)
                FROM DOMJudgeBundle:Submission s
                WHERE s.cid = :cid')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        $result['num_queued']      = (int)$this->entityManager
            ->createQuery(
                'SELECT COUNT(s)
                FROM DOMJudgeBundle:Submission s
                LEFT JOIN DOMJudgeBundle:Judging j WITH (j.submitid = s.submitid AND j.valid != 0)
                WHERE s.cid = :cid
                AND j.result IS NULL
                AND s.valid = 1')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();
        $result['num_judging']     = (int)$this->entityManager
            ->createQuery(
                'SELECT COUNT(s)
                FROM DOMJudgeBundle:Submission s
                LEFT JOIN DOMJudgeBundle:Judging j WITH (j.submitid = s.submitid)
                WHERE s.cid = :cid
                AND j.result IS NULL
                AND j.valid = 1
                AND s.valid = 1')
            ->setParameter(':cid', $contest->getCid())
            ->getSingleScalarResult();

        return $result;
    }

    /**
     * Get information about the currently logged in user
     * @Rest\Get("/user")
     * @SWG\Response(
     *     response="200",
     *     description="Information about the logged in user",
     *     @Model(type=User::class)
     * )
     * @return \DOMJudgeBundle\Entity\User|null
     */
    public function getUserAction()
    {
        $user = $this->DOMJudgeService->getUser();
        if ($user === null) {
            return null;
        }

        return $user;
    }
}
