<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class ContestControllerTest extends BaseTest
{
    protected $apiEndpoint = 'contests';

    protected $expectedObjects = [
        '2' => [
            'formal_name'                => 'Demo contest',
            'penalty_time'               => 20,
            'start_time'                 => '2020-01-01T11:00:00+00:00',
            'end_time'                   => '2023-01-01T16:00:00+00:00',
            'duration'                   => '26309:00:00.000',
            'scoreboard_freeze_duration' => '1:00:00.000',
            'id'                         => '2',
            'external_id'                => 'demo',
            'name'                       => 'Demo contest',
            'shortname'                  => 'demo',
            'banner'           => [
                [
                    'href'   => 'contests/2/banner.png',
                    'mime'   => 'image/png',
                    'width'  => 181,
                    'height' => 101
                ]
            ]
        ],
    ];

    protected $expectedAbsent = ['4242', 'nonexistent'];

    /**
     * @var string
     */
    protected $banner;

    protected function setUp(): void
    {
        // Make sure we have a contest banner by copying an existing file.
        $fileToCopy = __DIR__ . '/../../../../public/js/hv.png';
        $imagesDir = __DIR__ . '/../../../../public/images/';
        $this->banner = $imagesDir . 'banner.png';
        copy($fileToCopy, $this->banner);

        // Make sure we remove the test container, since we need to rebuild it for the images to work.
        $this->removeTestContainer();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Remove the image again
        unlink($this->banner);
        $this->removeTestContainer();
    }
}
