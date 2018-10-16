<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\Request;
use DOMJudgeBundle\Entity\Judgehost;

/**
 * @Rest\Route("/api/v4/judgehosts", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/judgehosts")
 * @Rest\NamePrefix("judgehost_")
 * @SWG\Tag(name="Judgehosts")
 */
class JudgehostController extends FOSRestController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * JudgehostController constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get judgehosts
     * @Rest\Get("")
     * @Security("has_role('ROLE_JURY')")
     * @SWG\Response(
     *     response="200",
     *     description="The judgehosts",
     *     @Model(type=Judgehost::class)
     * )
     * @SWG\Parameter(
     *     name="hostname",
     *     in="query",
     *     type="string",
     *     description="Only show the judgehost with the given hostname"
     * )
     * @param Request $request
     * @return array
     */
    public function getJudgehostsAction(Request $request)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Judgehost', 'j')
            ->select('j');

        if ($request->query->has('hostname')) {
            $queryBuilder
                ->where('j.hostname = :hostname')
                ->setParameter(':hostname', $request->query->get('hostname'));
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
