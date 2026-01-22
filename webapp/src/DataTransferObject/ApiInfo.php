<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

readonly class ApiInfo
{
    public function __construct(
        public ?string          $version,
        public ?string          $versionUrl,
        public ?string          $name,
        public ?ApiInfoProvider $provider,
        #[Serializer\Exclude(if: '!object.domjudge')]
        public ?DomJudgeApiInfo $domjudge,
    ) {}
}
