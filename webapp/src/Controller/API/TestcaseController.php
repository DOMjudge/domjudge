<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Judging;
use App\Entity\JudgingRun;
use App\Entity\Testcase;
use App\Entity\TestcaseContent;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/api/v4/testcases", defaults={ "_format" = "json" })
 * @Rest\Prefix("/api/testcases")
 * @Rest\NamePrefix("testcase_")
 * @SWG\Tag(name="Testcases")
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
     * Get the next to judge testcase for the given judging ID
     * @param string $id
     * @return array|string|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @IsGranted({"ROLE_JURY", "ROLE_JUDGEHOST"})
     * @Rest\Get("/next-to-judge/{id}")
     * @SWG\Parameter(ref="#/parameters/id")
     * @SWG\Response(
     *     response="200",
     *     description="Information about the next testcase to run",
     *     @SWG\Schema(ref=@Model(type=Testcase::class))
     * )
     */
    public function getNextToJudgeAction(string $id)
    {
        // First, check if the judging has an endtime, because then we are done
        /** @var Judging $judging */
        $judging = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->leftJoin('j.runs', 'jr')
            ->select('j, s')
            ->andWhere('j.judgingid = :judgingid')
            ->setParameter(':judgingid', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$judging) {
            throw new NotFoundHttpException(sprintf('Judging with ID \'%s\' not found', $id));
        }

        if ($judging->getEndtime()) {
            return '';
        }

        $queryBuilder = $this->em->createQueryBuilder()
            ->from(Testcase::class, 't')
            ->select('t')
            ->andWhere('t.probid = :probid')
            ->setParameter(':probid', $judging->getSubmission()->getProbid())
            ->orderBy('t.rank')
            ->setMaxResults(1);

        if (!$judging->getRuns()->isEmpty()) {
            $testcasesToSkip = [];
            /** @var JudgingRun $run */
            foreach ($judging->getRuns() as $run) {
                $testcasesToSkip[] = $run->getTestcaseid();
            }

            $queryBuilder
                ->andWhere('t.testcaseid NOT IN (:testcasesToSkip)')
                ->setParameter(':testcasesToSkip', $testcasesToSkip);
        }

        /** @var Testcase $testcase */
        $testcase = $queryBuilder->getQuery()->getOneOrNullResult();

        // Would probably never be empty, because then endtime would also have been set. We cope with it anyway for now.
        if (!$testcase) {
            return null;
        }

        return $testcase;
    }

    /**
     * Get the input or output file for the given testcase
     * @param string $id
     * @param string $type
     * @return string
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @IsGranted({"ROLE_JURY", "ROLE_JUDGEHOST"})
     * @Rest\Get("/{id}/file/{type}")
     * @SWG\Parameter(ref="#/parameters/id")
     * @SWG\Parameter(
     *     name="type",
     *     type="string",
     *     in="path",
     *     enum={"input", "output"},
     *     description="Type of file to get",
     *     required=true
     * )
     * @SWG\Response(
     *     response="200",
     *     description="Information about the file of the given testcase",
     *     @SWG\Schema(type="string", description="Base64-encoded file contents")
     * )
     */
    public function getFileAction(string $id, string $type)
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
