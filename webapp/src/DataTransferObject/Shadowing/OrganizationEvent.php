<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

class OrganizationEvent implements EventData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $icpcId,
        public readonly ?string $formalName,
        public readonly ?string $country,
        public readonly ?string $url,
        public readonly ?string $twitterHashtag,
        public readonly ?string $twitterAccount,
    ) {}
}
