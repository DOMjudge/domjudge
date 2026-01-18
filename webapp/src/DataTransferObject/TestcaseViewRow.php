<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\Testcase;
use App\Entity\TestcaseGroup;

class TestcaseViewRow
{
    public const TYPE_GROUP     = 'group';
    public const TYPE_TESTCASE  = 'testcase';
    public const TYPE_NO_GROUP  = 'no_group';

    public function __construct(
        public readonly string $type,
        public readonly ?Testcase $testcase = null,
        public readonly ?TestcaseGroup $group = null,
        public readonly int $level = 0,
        public readonly ?int $inputSize = null,
        public readonly ?int $outputSize = null,
        public readonly ?int $imageSize = null,
    ) {}
}
