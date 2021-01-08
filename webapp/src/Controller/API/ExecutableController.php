<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Executable;
use App\Entity\ExecutableFile;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
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

    /**
     * ExecutableController constructor.
     * @param EntityManagerInterface $em
     * @param DOMJudgeService        $dj
     */
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
     * @param string $id
     * @return array|string|null
     * @throws \Doctrine\ORM\NonUniqueResultException
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

        // There's some code duplication with downloadAction in Jury/ExecutableController
        $zipArchive = new ZipArchive();
        if (!($tempzipFile = tempnam($this->dj->getDomjudgeTmpDir(), "/executable-"))) {
            throw new ServiceUnavailableHttpException(null, 'Failed to create temporary file');
        }
        $zipArchive->open($tempzipFile);

        /** @var ExecutableFile[] $files */
        $files = array_values($executable->getImmutableExecutable()->getFiles()->toArray());
        usort($files, function($a, $b)  { return $a->getRank() <=> $b->getRank(); });
        foreach ($files as $file) {
            $zipArchive->addFromString($file->getFilename(), $file->getFileContent());
        }
        $zipArchive->close();
        $zipFile = file_get_contents($tempzipFile);

        return base64_encode($zipFile);
    }
}
