<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\Utils\CcsApiVersion;
use JMS\Serializer\Annotation as Serializer;

class ImageFile extends FileWithName
{
    /**
     * @param list<ImageTag>|null $tags
     */
    public function __construct(
        string $href,
        string $mime,
        string $filename,
        public readonly int $width,
        public readonly int $height,
        #[Serializer\Exclude]
        public readonly ?array $tags = null,
    ) {
        parent::__construct($href, $mime, $filename);
    }

    /**
     * Not in the nonstrict group, since the event feed only strips top-level properties for strict clients.
     *
     * @return list<string>|null
     */
    #[Serializer\Groups([CcsApiVersion::Format_2026_01->value])]
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('tags')]
    #[Serializer\Type('array<string>')]
    #[Serializer\Exclude(if: 'object.tags === null')]
    public function getApiTags(): ?array
    {
        if ($this->tags === null) {
            return null;
        }

        return array_map(fn (ImageTag $tag) => $tag->value, $this->tags);
    }
}
