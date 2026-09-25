<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class JudgementTypesControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'judgement-types';

    protected array $expectedObjects = [
        'AC' => [
            'id'                           => 'AC',
            'name'                         => 'correct',
            'penalty'                      => false,
            'solved'                       => true,
            'simplified_judgement_type_id' => 'AC',
        ],
        'WA' => [
            'id'                           => 'WA',
            'name'                         => 'wrong answer',
            'penalty'                      => true,
            'solved'                       => false,
            'simplified_judgement_type_id' => 'RE',
        ],
        'CE' => [
            'id'                           => 'CE',
            'name'                         => 'compiler error',
            'penalty'                      => false,
            'solved'                       => false,
            'simplified_judgement_type_id' => 'CE',
        ],
        'RE' => [
            'id'      => 'RE',
            'name'    => 'Rejected',
            'penalty' => true,
            'solved'  => false,
        ],
    ];

    protected array $expectedAbsent = ['nonexistent'];

    public function testJudgementTypeChangedPenalty(): void
    {
        $this->withChangedConfiguration('compile_penalty', true, function (): void {
            $contestId = $this->getDemoContestId();
            $endpointId = $this->apiEndpoint;

            $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$endpointId/CE", 200);

            $expected = [
                "id"                           => "CE",
                "name"                         => "compiler error",
                "penalty"                      => true,
                "solved"                       => false,
                "simplified_judgement_type_id" => "CE",
            ];
            static::assertEquals($expected, $response);
        });
    }

    public function testSimplifiedRejectedJudgementType(): void
    {
        $contestId = $this->getDemoContestId();
        $endpointId = $this->apiEndpoint;

        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$endpointId/RE", 200);

        // The synthetic Rejected type must not carry a simplified_judgement_type_id of its own.
        $expected = [
            "id"      => "RE",
            "name"    => "Rejected",
            "penalty" => true,
            "solved"  => false,
        ];
        static::assertEquals($expected, $response);
    }

    public function testOlderApiVersionHasNoSimplifiedFieldAndNoRejected(): void
    {
        $this->withChangedConfiguration('ccs_api_version', '2023-06', function (): void {
            $contestId = $this->getDemoContestId();
            $endpointId = $this->apiEndpoint;

            // Synthetic RE entry is gated by version, not by strict mode, so it should be absent in any 2023-06 response.
            $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$endpointId", 200);
            $ids = array_column($response, 'id');
            static::assertNotContains('RE', $ids, 'Synthetic RE must not appear in pre-2026-01 responses');

            // simplified_judgement_type_id is a 2026-01 spec field, so strict-mode 2023-06 must not include it.
            $strictResponse = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$endpointId?strict=1", 200);
            foreach ($strictResponse as $type) {
                static::assertArrayNotHasKey(
                    'simplified_judgement_type_id',
                    $type,
                    'simplified_judgement_type_id must not appear in strict pre-2026-01 responses'
                );
            }
        });
    }
}
