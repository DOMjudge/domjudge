<?php declare(strict_types=1);

namespace App\Controller\API;

use App\DataTransferObject\Access;
use App\DataTransferObject\AccessEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Rest\Route('/contests/{cid}/access')]
#[OA\Tag(name: 'Access')]
#[OA\Parameter(ref: '#/components/parameters/cid')]
#[OA\Parameter(ref: '#/components/parameters/strict')]
#[OA\Response(ref: '#/components/responses/InvalidResponse', response: 400)]
#[OA\Response(ref: '#/components/responses/Unauthenticated', response: 401)]
#[OA\Response(ref: '#/components/responses/Unauthorized', response: 403)]
#[OA\Response(ref: '#/components/responses/NotFound', response: 404)]
class AccessController extends AbstractApiController
{
    /**
     * Get access information
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     *
     * @return Access
     */
    #[IsGranted('ROLE_API_READER')]
    #[Rest\Get('')]
    #[OA\Response(
        response: 200,
        description: 'Access information for the given contest',
        content: new OA\JsonContent(ref: new Model(type: Access::class))
    )]
    public function getStatusAction(Request $request): Access
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
            $capabilities[] = 'contest_thaw';
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

        return new Access(
            capabilities: $capabilities,
            endpoints: [
                new AccessEndpoint(
                    type: 'contest',
                    properties: [
                        'id',
                        'name',
                        'formal_name',
                        'start_time',
                        'duration',
                        'scoreboard_freeze_duration',
                        'penalty_time',
                    ],
                ),
                new AccessEndpoint(
                    type: 'judgement-types',
                    properties: [
                        'id',
                        'name',
                        'penalty',
                        'solved',
                    ],
                ),
                new AccessEndpoint(
                    type: 'languages',
                    properties: [
                        'id',
                        'name',
                        'entry_point_required',
                        'entry_point_name',
                        'extensions',
                        // TODO: we don't support these yet
//                        'compiler.command',
//                        'runner.command',
                    ],
                ),
                new AccessEndpoint(
                    type: 'problems',
                    properties: [
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
                ),
                new AccessEndpoint(
                    type: 'groups',
                    properties: [
                        'id',
                        'icpc_id',
                        'name',
                        'hidden',
                    ],
                ),
                new AccessEndpoint(
                    type: 'organizations',
                    properties: $organizationProperties,
                ),
                new AccessEndpoint(
                    type: 'teams',
                    properties: [
                        'id',
                        'icpc_id',
                        'name',
                        'display_name',
                        'organization_id',
                        'group_ids',
                    ]
                ),
                new AccessEndpoint(
                    type: 'state',
                    properties: [
                        'started',
                        'frozen',
                        'ended',
                        'thawed',
                        'finalized',
                        'end_of_updates',
                    ],
                ),
                new AccessEndpoint(
                    type: 'submissions',
                    properties: $submissionsProperties,
                ),
                new AccessEndpoint(
                    type: 'judgements',
                    properties: [
                        'id',
                        'submission_id',
                        'judgement_type_id',
                        'start_time',
                        'start_contest_time',
                        'end_time',
                        'end_contest_time',
                        'max_run_time',
                    ],
                ),
                new AccessEndpoint(
                    type: 'runs',
                    properties: [
                        'id',
                        'judgement_id',
                        'ordinal',
                        'judgement_type_id',
                        'time',
                        'contest_time',
                        'run_time',
                    ],
                ),
                new AccessEndpoint(
                    type: 'awards',
                    properties: [
                        'id',
                        'citation',
                        'team_ids',
                    ],
                ),
            ],
        );
    }
}
