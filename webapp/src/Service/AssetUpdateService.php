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
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLog;

    public function __construct(DOMJudgeService $dj, EventLogService $eventLog)
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
            $assetPath = $this->dj->fullAssetPath($entity, $assetProperty, $this->eventLog->externalIdFieldForEntity($entity) !== null);
            if ($entity->isClearAsset($assetProperty) && file_exists($assetPath)) {
                unlink($assetPath);
            } elseif ($entity->getAssetFile($assetProperty)) {
                $fs->copy($entity->getAssetFile($assetProperty)->getRealPath(), $assetPath);
            }
        }
    }
}
