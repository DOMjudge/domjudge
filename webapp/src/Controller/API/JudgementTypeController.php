<?php declare(strict_types=1);

namespace App\Controller\API;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * @Rest\Route("/contests/{cid}/judgement-types")
 * @OA\Tag(name="Judgement types")
 */
class JudgementTypeController extends AbstractRestController
{
    /**
     * Get all the judgement types for this contest
     * @param Request $request
     * @return array
     * @throws \Exception
     * @Rest\Get("")
     * @OA\Response(
     *     response="200",
     *     description="Returns all the judgement types for this contest",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/JudgementType")
     *     )
     * )
     * @OA\Parameter(ref="#/components/parameters/idlist")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function listAction(Request $request)
    {
        // Call getContestId to make sure we have an active contest
        $this->getContestId($request);
        $ids = null;
        if ($request->query->has('ids')) {
            $ids = $request->query->get('ids', []);
            if (!is_array($ids)) {
                throw new BadRequestHttpException('\'ids\' should be an array of ID\'s to fetch');
            }

            $ids = array_unique($ids);
        }

        $judgementTypes = $this->getJudgementTypes($ids);

        if (isset($ids) && count($judgementTypes) !== count($ids)) {
            throw new NotFoundHttpException('One or more objects not found');
        }

        return $judgementTypes;
    }

    /**
     * Get the given judgement type for this contest
     * @param Request $request
     * @param string $id
     * @return array
     * @throws NonUniqueResultException
     * @throws \Exception
     * @Rest\Get("/{id}")
     * @OA\Response(
     *     response="200",
     *     description="Returns the given judgement type for this contest",
     *     @OA\JsonContent(ref="#/components/schemas/JudgementType")
     * )
     * @OA\Parameter(ref="#/components/parameters/id")
     * @OA\Parameter(ref="#/components/parameters/strict")
     */
    public function singleAction(Request $request, string $id)
    {
        // Call getContestId to make sure we have an active contest
        $this->getContestId($request);
        $judgementTypes = $this->getJudgementTypes([$id]);

        if (empty($judgementTypes)) {
            throw new NotFoundHttpException(sprintf('Object with ID \'%s\' not found', $id));
        }

        return reset($judgementTypes);
    }

    /**
     * Get the judgement types, optionally filtered on the given ID's
     * @param string[]|null $filteredOn
     * @return array
     * @throws \Exception
     */
    protected function getJudgementTypes(array $filteredOn = null)
    {
        $verdictsConfig = $this->dj->getDomjudgeEtcDir() . '/verdicts.php';
        $verdicts       = include $verdictsConfig;

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
            $result[] = [
                'id' => (string)$label,
                'name' => str_replace('-', ' ', $name),
                'penalty' => (bool)$penalty,
                'solved' => (bool)$solved,
            ];
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    protected function getIdField(): string
    {
        throw new NotImplementedException();
    }
}
