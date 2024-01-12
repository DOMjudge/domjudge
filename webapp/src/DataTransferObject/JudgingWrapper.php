<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\Judging;
use App\Utils\Utils;
use JMS\Serializer\Annotation as Serializer;

class JudgingWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected readonly Judging $judging,
        #[Serializer\Exclude]
        protected readonly ?float $maxRunTime = null,
        #[Serializer\SerializedName('judgement_type_id')]
        protected readonly ?string $judgementTypeId = null
    ) {}

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('max_run_time')]
    #[Serializer\Type('float')]
    public function getMaxRunTime(): ?float
    {
        return Utils::roundedFloat($this->maxRunTime);
    }
}
