<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

class ProblemControllerTest extends BaseTest
{
    /* This tests with the anonymous user;
       for tests with the admin user see ProblemControllerAdminTest.
     */

    protected $apiEndpoint = 'problems';

    protected $expectedObjects = [
      3 => [
        "ordinal" => 0,
        "id" => "3",
        "short_name" => "boolfind",
        "label" => "boolfind",
        "time_limit" => 5,
        "externalid" => "boolfind",
        "name" => "Boolean switch search",
        "rgb" => "#008000",
        "color" => "green",
      ],
      2 => [
        "ordinal" => 1,
        "id" => "2",
        "short_name" => "fltcmp",
        "label" => "fltcmp",
        "time_limit" => 5,
        "externalid" => "fltcmp",
        "name" => "Float special compare test",
        "rgb" => "#CD5C5C",
        "color" => "indianred",
      ],
      1 => [
        "ordinal" => 2,
        "id" => "1",
        "short_name" => "hello",
        "label" => "hello",
        "time_limit" => 5,
        "externalid" => "hello",
        "name" => "Hello World",
        "rgb" => "#87CEEB",
        "color" => "skyblue",
      ],
    ];

    protected $expectedAbsent = ['4242', 'nonexistent'];

}
