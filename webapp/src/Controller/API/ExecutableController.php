<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Executable;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/executables")
 * @OA\Tag(name="Executables")
 */
class ExecutableController extends AbstractFOSRestController
{
    protected EntityManagerInterface $em;
    protected DOMJudgeService $dj;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj
    ) {
        $this->em = $em;
        $this->dj = $dj;
    }

    /**
     * Get the executable with the given ID.
     * @throws NonUniqueResultException
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')")
     * @Rest\Get("/{id}")
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Response(
     *     response="200",
     *     description="Information about the requested executable",
     *     @OA\JsonContent(type="string", description="Base64-encoded executable contents")
     * )
     * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
     * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
     * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
     */
    public function singleAction(string $id): string
    {
        /** @var Executable|null $executable */
        $executable = $this->em->createQueryBuilder()
            ->from(Executable::class, 'e')
            ->select('e')
            ->andWhere('e.execid = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($executable === null) {
            throw new NotFoundHttpException(sprintf('Cannot find executable \'%s\'', $id));
        }

        return base64_encode($executable->getZipfileContent($this->dj->getDomjudgeTmpDir()));
    }
}
