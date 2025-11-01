<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class ApiInfo
{
    public function __construct(
        public readonly ?string $version,
        public readonly ?string $versionUrl,
        public readonly ?string $name,
        public readonly ?ApiInfoProvider $provider,
        #[Serializer\Exclude(if: '!object.domjudge')]
        public readonly ?DomJudgeApiInfo $domjudge,
    ) {}
}
