<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\ProblemSpec;

use App\Entity\Problem;
use App\Service\ImportProblemService;
use App\Tests\Unit\BaseTestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use ZipArchive;

class ImportProblemSpecDOMjudge extends BaseTestCase
{
    private array $problemSpecVersion = ['domjudge'];
}
