<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\SampleSubmissionsFixture;

class JudgementControllerTest extends BaseTestCase
{
    // Leave $apiEndpoint null so the inherited list/single boilerplate tests skip themselves.
    // The judgements endpoint has its own specific shape and is exercised by the dedicated tests below.
    protected ?string $apiEndpoint = null;

    protected ?string $apiUser = 'admin';

    protected static array $fixtures = [
        SampleSubmissionsFixture::class,
    ];

    public function testSimplifiedJudgementTypeIdMatchesJudgementType(): void
    {
        $contestId = $this->getDemoContestId();

        $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements", 200, $this->apiUser);
        static::assertNotEmpty($response, 'expected at least one judgement in demo data');

        $checkedAtLeastOne = false;
        foreach ($response as $judgement) {
            $type = $judgement['judgement_type_id'] ?? null;
            static::assertArrayHasKey('simplified_judgement_type_id', $judgement);
            $simplified = $judgement['simplified_judgement_type_id'];

            if ($type === null) {
                static::assertNull($simplified, 'simplified should be null when judgement_type_id is null');
                continue;
            }

            $checkedAtLeastOne = true;
            $expected = match ($type) {
                'AC'    => 'AC',
                'CE'    => 'CE',
                default => 'RE',
            };
            static::assertSame($expected, $simplified, "judgement_type_id=$type should map to $expected");
        }
        static::assertTrue($checkedAtLeastOne, 'expected at least one completed judgement to check');
    }

    public function testSimplifiedJudgementTypeIdAbsentInOlderApiVersionStrict(): void
    {
        $this->withChangedConfiguration('ccs_api_version', '2023-06', function (): void {
            $contestId = $this->getDemoContestId();

            $response = $this->verifyApiJsonResponse('GET', "/contests/$contestId/judgements?strict=1", 200, $this->apiUser);
            static::assertNotEmpty($response, 'expected at least one judgement in demo data');

            foreach ($response as $judgement) {
                static::assertArrayNotHasKey(
                    'simplified_judgement_type_id',
                    $judgement,
                    'simplified_judgement_type_id must not appear in strict pre-2026-01 responses'
                );
            }
        });
    }
}
