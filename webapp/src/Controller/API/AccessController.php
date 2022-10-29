<?php declare(strict_types=1);

namespace App\Controller\API;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Rest\Route("/contests/{cid}/access")
 * @OA\Tag(name="Access")
 * @OA\Parameter(ref="#/components/parameters/cid")
 * @OA\Response(response="400", ref="#/components/responses/InvalidResponse")
 * @OA\Response(response="401", ref="#/components/responses/Unauthenticated")
 * @OA\Response(response="403", ref="#/components/responses/Unauthorized")
 * @OA\Response(response="404", ref="#/components/responses/NotFound")
 */
class AccessController extends AbstractRestController
{
    /**
     * Get access information
     * @Rest\Get("")
     * @IsGranted("ROLE_API_READER")
     * @OA\Response(
     *     response="200",
     *     description="Access information for the given contest",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="capabilities", type="array", @OA\Items(type="string")),
     *         @OA\Property(
     *             property="endpoints",
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="properties", type="array", @OA\Items(type="string")),
     *             )
     *         )
     *     )
     * )
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getStatusAction(Request $request): array
    {
        // Get the contest ID to make sure the contest exists
        $this->getContestId($request);

        // For now we hardcode the access response since we only do it for API readers/writers
        // But later we might want to do more fancy stuff like auto detect the access
        $organizationProperties = [
            'id',
            'icpc_id',
            'name',
            'formal_name',
        ];

        // Add country data to organizations if supported
        if ($this->config->get('show_flags')) {
            $organizationProperties[] = 'country';
            $organizationProperties[] = 'country_flag';
        }

        $submissionsProperties = [
            'id',
            'language_id',
            'problem_id',
            'team_id',
            'time',
            'contest_time',
            'entry_point',
        ];

        // Add files to submissions if allowed
        if ($this->dj->checkrole('api_source_reader')) {
            $submissionsProperties[] = 'files';
        }

        $capabilities = [];

        // Add capabilities
        if ($this->dj->checkrole('api_writer')) {
            $capabilities[] = 'contest_start';
        }
        if ($this->dj->checkrole('team') && $this->dj->getUser()->getTeam()) {
            $capabilities[] = 'team_submit';
            $capabilities[] = 'team_clar';
        }
        if ($this->dj->checkrole('api_writer')) {
            $capabilities[] = 'proxy_submit';
            $capabilities[] = 'proxy_clar';
            $capabilities[] = 'admin_submit';
            $capabilities[] = 'admin_clar';
        }

        return [
            'capabilities' => $capabilities,
            'endpoints'    => [
                [
                    'type'       => 'contest',
                    'properties' => [
                        'id',
                        'name',
                        'formal_name',
                        'start_time',
                        'duration',
                        'scoreboard_freeze_duration',
                        'penalty_time',
                    ],
                ],
                [
                    'type'       => 'judgement-types',
                    'properties' => [
                        'id',
                        'name',
                        'penalty',
                        'solved',
                    ],
                ],
                [
                    'type'       => 'languages',
                    'properties' => [
                        'id',
                        'name',
                        'entry_point_required',
                        'entry_point_name',
                        'extensions',
                        // TODO: we don't support these yet
//                        'compiler.command',
//                        'runner.command',
                    ],
                ],
                [
                    'type'       => 'problems',
                    'properties' => [
                        'id',
                        'label',
                        'name',
                        'ordinal',
                        'rgb',
                        'color',
                        'time_limit',
                        'test_data_count',
                        'statement',
                    ],
                ],
                [
                    'type'       => 'groups',
                    'properties' => [
                        'id',
                        'icpc_id',
                        'name',
                        'hidden',
                    ],
                ],
                [
                    'type'       => 'organizations',
                    'properties' => $organizationProperties,
                ],
                [
                    'type'       => 'teams',
                    'properties' => [
                        'id',
                        'icpc_id',
                        'name',
                        'display_name',
                        'organization_id',
                        'group_ids',
                    ]
                ],
                [
                    'type'       => 'state',
                    'properties' => [
                        'started',
                        'frozen',
                        'ended',
                        'thawed',
                        'finalized',
                        'end_of_updates',
                    ],
                ],
                [
                    'type'       => 'submissions',
                    'properties' => $submissionsProperties,
                    ],
                [
                    'type'       => 'judgements',
                    'properties' => [
                        'id',
                        'submission_id',
                        'judgement_type_id',
                        'start_time',
                        'start_contest_time',
                        'end_time',
                        'end_contest_time',
                        'max_run_time',
                    ],
                ],
                [
                    'type'       => 'runs',
                    'properties' => [
                        'id',
                        'judgement_id',
                        'ordinal',
                        'judgement_type_id',
                        'time',
                        'contest_time',
                        'run_time',
                    ],
                ],
                [
                    'type'       => 'awards',
                    'properties' => [
                        'id',
                        'citation',
                        'team_ids',
                    ],
                ],
            ],
        ];
    }

    protected function getQueryBuilder(Request $request): QueryBuilder
    {
        throw new Exception('Not implemented');
    }

    protected function getIdField(): string
    {
        throw new Exception('Not implemented');
    }
}
