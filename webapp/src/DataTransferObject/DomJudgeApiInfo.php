<?php declare(strict_types=1);

namespace App\DataTransferObject;

class DomJudgeApiInfo
{
    public function __construct(
        public readonly int $apiversion,
        public readonly string $version,
        public readonly string $environment,
        public readonly string $docUrl,
    ) {}
}
