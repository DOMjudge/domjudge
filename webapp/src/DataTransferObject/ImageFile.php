<?php declare(strict_types=1);

namespace App\DataTransferObject;

use JMS\Serializer\Annotation as Serializer;

class ImageFile extends FileWithName
{
    /**
     * @param list<ImageTag>|null $tag
     */
    public function __construct(
        string $href,
        string $mime,
        string $filename,
        public readonly int $width,
        public readonly int $height,
        #[Serializer\Exclude]
        public readonly ?array $tag = null,
    ) {
        parent::__construct($href, $mime, $filename);
    }

    /**
     * @return list<string>|null
     */
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('tag')]
    #[Serializer\Type('array<string>')]
    #[Serializer\Exclude(if: 'object.tag === null')]
    public function getApiTag(): ?array
    {
        if ($this->tag === null) {
            return null;
        }

        return array_map(fn (ImageTag $tag) => $tag->value, $this->tag);
    }
}
