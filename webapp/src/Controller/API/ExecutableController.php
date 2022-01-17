<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use ZipArchive;

/**
 * @Rest\Route("/executables")
 * @OA\Tag(name="Executables")
 */
class ExecutableController extends AbstractFOSRestController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var DOMJudgeService
     */
    protected $dj;

    public function __construct(
        EntityManagerInterface $em,
        DOMJudgeService $dj
    )
    {
        $this->em = $em;
        $this->dj = $dj;
    }

    /**
     * Get the executable with the given ID
     * @return array|string|null
     * @throws NonUniqueResultException
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')")
     * @Rest\Get("/{id}")
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Response(
     *     response="200",
     *     description="Information about the requested executable",
     *     @OA\JsonContent(type="string", description="Base64-encoded executable contents")
     * )
     */
    public function singleAction(string $id)
    {
        /** @var Executable|null $executable */
        $executable = $this->em->createQueryBuilder()
            ->from(Executable::class, 'e')
            ->select('e')
            ->andWhere('e.execid = :id')
            ->setParameter(':id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($executable === null) {
            throw new NotFoundHttpException(sprintf('Cannot find executable \'%s\'', $id));
        }

        return base64_encode($executable->getZipfileContent($this->dj->getDOMjudgeTmpDir()));
    }
}
