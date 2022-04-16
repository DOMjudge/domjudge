<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AssetEntityInterface;
use Symfony\Component\Filesystem\Filesystem;

class AssetUpdateService
{
    protected DOMJudgeService $dj;
    protected EventLogService $eventLog;

    public function __construct(DOMJudgeService $dj, EventLogService $eventLog)
    {
        $this->dj = $dj;
        $this->eventLog = $eventLog;
    }

    /**
     * Update assets for the given entity.
     */
    public function updateAssets(AssetEntityInterface &$entity): void
    {
        $fs = new Filesystem();

        foreach ($entity->getAssetProperties() as $assetProperty) {
            $assetPaths = [];
            foreach (DOMJudgeService::MIMETYPE_TO_EXTENSION as $mimetype => $extension) {
                $assetPaths[$mimetype] = $this->dj->fullAssetPath($entity, $assetProperty, $this->eventLog->externalIdFieldForEntity($entity) !== null, $extension);
            }
            if ($entity->isClearAsset($assetProperty)) {
                foreach ($assetPaths as $assetPath) {
                    if (file_exists($assetPath)) {
                        unlink($assetPath);
                    }
                }
            } elseif ($entity->getAssetFile($assetProperty)) {
                $assetPath = $assetPaths[$entity->getAssetFile($assetProperty)->getMimeType()];
                $fs->copy($entity->getAssetFile($assetProperty)->getRealPath(), $assetPath);
            }
        }
    }
}
