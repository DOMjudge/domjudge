<?php declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Entities that have assets should implement this interface.
 */
interface AssetEntityInterface
{
    /**
     * @return string[]
     */
    public function getAssetProperties(): array;

    public function getAssetFile(string $property): ?UploadedFile;

    public function isClearAsset(string $property): ?bool;
}
