<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AssetEntityInterface;
use App\Entity\BaseApiEntity;
use App\Entity\Contest;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetUpdateService
{
    /**
     * @var DOMjudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLog;

    public function __construct(DOMjudgeService $dj, EventLogService $eventLog)
    {
        $this->dj = $dj;
        $this->eventLog = $eventLog;
    }

    /**
     * Update assets for the given entity
     */
    public function updateAssets(AssetEntityInterface &$entity): void
    {
        $fs = new Filesystem();

        foreach ($entity->getAssetProperties() as $assetProperty) {
            $assetPaths = [];
            foreach (DOMjudgeService::MIMETYPE_TO_EXTENSION as $mimetype => $extension) {
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
