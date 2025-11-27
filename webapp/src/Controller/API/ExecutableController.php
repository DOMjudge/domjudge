<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Executable;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Rest\Route(path: '/executables')]
#[OA\Tag(name: 'Executables')]
class ExecutableController extends AbstractFOSRestController
{
    public function __construct(protected readonly EntityManagerInterface $em, protected readonly DOMJudgeService $dj)
    {
    }

    /**
     * Get the executable with the given ID.
     * @throws NonUniqueResultException
     */
    #[IsGranted(new Expression("is_granted('ROLE_JURY') or is_granted('ROLE_JUDGEHOST')"))]
    #[Rest\Get(path: '/{id}')]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    #[OA\Response(
        response: 200,
        description: 'Information about the requested executable',
        content: new OA\JsonContent(
            description: 'Base64-encoded executable contents',
            type: 'string')
    )]
    #[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
    #[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
    #[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
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
