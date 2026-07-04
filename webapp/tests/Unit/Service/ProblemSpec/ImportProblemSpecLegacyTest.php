<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\ProblemSpec;

class ImportProblemSpecLegacyTest extends ImportProblemSpecBaseTestCase
{
    protected array $problemSpecVersion = ['legacy'];
    protected const string PROBLEMSPEC_VERSION = 'legacy';
}
