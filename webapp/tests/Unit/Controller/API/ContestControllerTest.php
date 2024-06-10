<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Entity\Contest;

class ContestControllerTest extends BaseTestCase
{
    protected ?string $apiEndpoint = 'contests';

    protected array $expectedObjects = [
        'demo' => [
            'formal_name'                => 'Demo contest',
            'penalty_time'               => 20,
            // 'start_time'                 => '2021-01-01T11:00:00+00:00',
            // 'end_time'                   => '2024-01-01T16:00:00+00:00',
            'duration'                   => '5:00:00.000',
            'scoreboard_freeze_duration' => '1:00:00.000',
            'id'                         => 'demo',
            'name'                       => 'Demo contest',
            'shortname'                  => 'demo',
            'banner'                     => null,
        ],
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    protected ?string $objectClassForExternalId = Contest::class;
}
