<?php declare(strict_types=1);

namespace App\DataTransferObject;

readonly class DomJudgeApiInfo
{
    public function __construct(
        public int    $apiversion,
        public string $version,
        public string $environment,
        public string $docUrl,
    ) {}
}
