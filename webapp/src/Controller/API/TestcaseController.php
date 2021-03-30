<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/testcases")
 * @OA\Tag(name="Testcases")
 */
class TestcaseController extends AbstractFOSRestController
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * TestcaseController constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Get the input or output file for the given testcase
     * @throws NonUniqueResultException
     * @Security("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')")
     * @Rest\Get("/{id}/file/{type}")
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(
     *     name="type",
     *     in="path",
     *     description="Type of file to get",
     *     required=true,
     *     @OA\Schema(type="string", enum={"input", "output"})
     * )
     * @OA\Response(
     *     response="200",
     *     description="Information about the file of the given testcase",
     *     @OA\JsonContent(type="string", description="Base64-encoded file contents")
     * )
     */
    public function getFileAction(string $id, string $type): string
    {
        if (!in_array($type, ['input', 'output'])) {
            throw new BadRequestHttpException('Only \'input\' or \'output\' file allowed');
        }

        /** @var TestcaseContent|null $testcaseContent */
        $testcaseContent = $this->em->createQueryBuilder()
            ->from(TestcaseContent::class, 'tcc')
            ->select('tcc')
            ->andWhere('tcc.testcase = :id')
            ->setParameter(':id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($testcaseContent === null) {
            throw new NotFoundHttpException(sprintf('Cannot find testcase \'%s\'', $id));
        }

        $contents = $type === 'input'
            ? $testcaseContent->getInput()
            : $testcaseContent->getOutput();

        if ($contents === null) {
            throw new NotFoundHttpException(sprintf('Cannot find the ' . $type . ' of testcase \'%s\'', $id));
        }

        return base64_encode($contents);
    }
}
