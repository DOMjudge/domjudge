<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class JudgementTypesControllerTest extends BaseTest
{
    protected ?string $apiEndpoint = 'judgement-types';

    protected array $expectedObjects = [
        'AC' => [
            'id'        => 'AC',
            'name'      => 'correct',
            'penalty'   => false,
            'solved'    => true,
        ],
        'WA' => [
            'id'        => 'WA',
            'name'      => 'wrong answer',
            'penalty'   => true,
            'solved'    => false,
        ],
        'CE' => [
            'id'        => 'CE',
            'name'      => 'compiler error',
            'penalty'   => false,
            'solved'    => false,
        ],
    ];

    protected array $expectedAbsent = ['nonexistent'];

    public function testJudgementTypeChangedPenalty(): void
    {
        $this->withChangedConfiguration('compile_penalty', true, function () {
            $contestId = $this->getDemoContestId();
            $endpointId = $this->apiEndpoint;

            $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$endpointId/CE", 200);

            $expected = ["id" => "CE", "name" => "compiler error", "penalty" => true, "solved" => false];
            static::assertEquals($expected, $response);
        });
    }
}
