<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class AwardsControllerTest extends BaseTest
{
    protected $apiEndpoint = 'awards';

    protected static $skipMessageIDs = "Filtering on IDs not implemented in this endpoint.";

    /**
     * In the default test setup there are no judgings yet and one team.
     * This means that there's only the winner/gold award.
     */
    protected $expectedObjects = [
            'winner' => ["id" => "winner", "citation" => "Contest winner", "team_ids" => [2]],
    ];

    protected $expectedAbsent = ['bronze-medal', 'first-to-solve'];

    public function testListWithIds(): void {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithIdsNotArray(): void {
        static::markTestSkipped(static::$skipMessageIDs);
    }

    public function testListWithAbsentIds(): void {
        static::markTestSkipped(static::$skipMessageIDs);
    }
}
