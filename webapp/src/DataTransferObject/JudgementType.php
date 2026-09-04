<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Controller\API\AbstractRestController as ARC;
use App\Utils\CcsApiVersion;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;

readonly class JudgementType
{
    public function __construct(
        public string  $id,
        public string  $name,
        public bool    $penalty,
        public bool    $solved,
        #[OA\Property(nullable: true)]
        #[Serializer\Exclude(if: 'object.simplifiedJudgementTypeId === null')]
        #[Serializer\Groups([ARC::GROUP_NONSTRICT, CcsApiVersion::Format_2026_01->value])]
        #[Serializer\SerializedName('simplified_judgement_type_id')]
        public ?string $simplifiedJudgementTypeId = null,
    ) {}
}
