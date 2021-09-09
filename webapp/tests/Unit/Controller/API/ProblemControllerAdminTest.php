<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class ProblemControllerAdminTest extends ProblemControllerTest
{
    protected $apiUser = 'admin';

    protected function setUp(): void
    {
        // When queried as admin, extra information is returned about each problem
        $this->expectedObjects[1]['test_data_count'] = 1;
        $this->expectedObjects[2]['test_data_count'] = 3;
        $this->expectedObjects[3]['test_data_count'] = 1;
        parent::setUp();
    }
}
