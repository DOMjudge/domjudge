<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\Judging;
use JMS\Serializer\Annotation as Serializer;

class JudgingWrapper
{
    public function __construct(
        #[Serializer\Inline]
        protected readonly Judging $judging,
        #[Serializer\SerializedName('judgement_type_id')]
        protected readonly ?string $judgementTypeId = null
    ) {}
}
