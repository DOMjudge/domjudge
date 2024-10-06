<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\JudgementType;
use Doctrine\ORM\NonUniqueResultException;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Rest\Route('/contests/{cid}/judgement-types')]
#[OA\Tag(name: 'Judgement types')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
class JudgementTypeController extends AbstractApiController
{
    /**
     * Get all the judgement types for this contest.
     *
     * @throws NonUniqueResultException
     * @return JudgementType[]
     */
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Returns all the judgement types for this contest',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: JudgementType::class))
        )
    )]
    #[OA\Parameter(ref: '#/components/parameters/idlist')]
    public function listAction(Request $request): array
    {
        // Call getContestId to make sure we have an active contest.
        $this->getContestId($request);
        $ids = null;
        if ($request->query->has('ids')) {
            $ids = $request->query->all('ids');
            $ids = array_unique($ids);
        }

        $judgementTypes = $this->getJudgementTypes($ids);

        if (isset($ids) && count($judgementTypes) !== count($ids)) {
            throw new NotFoundHttpException('One or more objects not found');
        }

        return $judgementTypes;
    }

    /**
     * Get the given judgement type for this contest.
     * @throws NonUniqueResultException
     */
    #[Rest\Get('/{id}')]
    #[OA\Response(
        response: 200,
        description: 'Returns the given judgement type for this contest',
        content: new OA\JsonContent(ref: new Model(type: JudgementType::class))
    )]
    #[OA\Parameter(ref: '#/components/parameters/id')]
    public function singleAction(Request $request, string $id): JudgementType
    {
        // Call getContestId to make sure we have an active contest.
        $this->getContestId($request);
        $judgementTypes = $this->getJudgementTypes([$id]);

        if (empty($judgementTypes)) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        return reset($judgementTypes);
    }

    /**
     * Get the judgement types, optionally filtered on the given IDs.
     *
     * @param string[]|null $filteredOn
     *
     * @return JudgementType[]
     */
    protected function getJudgementTypes(array $filteredOn = null): array
    {
        $verdicts = $this->dj->getVerdicts(mergeExternal: true);

        $result = [];
        foreach ($verdicts as $name => $label) {
            $penalty = true;
            $solved  = false;
            if ($name == 'correct') {
                $penalty = false;
                $solved  = true;
            }
            if ($name == 'compiler-error') {
                $penalty = $this->config->get('compile_penalty');
            }
            if ($filteredOn !== null && !in_array($label, $filteredOn)) {
                continue;
            }
            $result[] = new JudgementType(
                id: $label,
                name: str_replace('-', ' ', $name),
                penalty: (bool)$penalty,
                solved: $solved,
            );
        }
        return $result;
    }
}
