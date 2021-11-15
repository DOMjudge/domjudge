<?php declare(strict_types=1);

namespace App\Service;

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
     * The entities and asset fields supported by this subscriber
     *
     * The entities are assumed to have a property called <asset>File and clear<Asset>,
     * where <asset> is the name of the asset in this array and <Asset> is the same but
     * with the first letter uppercased. <asset>File should be an `UploadedFile` and
     * clear<Asset> should be a boolean.
     */
    protected const SUPPORTED_ASSETS = [
        Team::class            => ['photo'],
        TeamAffiliation::class => ['logo'],
        Contest::class         => ['banner'],
    ];

    protected function getAssetFields($entity): array
    {
        $class = get_class($entity);
        if (strpos($class, 'Proxies\\__CG__\\') === 0) {
            $class = substr($class, strlen('Proxies\\__CG__\\'));
        }
        return static::SUPPORTED_ASSETS[$class] ?? [];
    }

    /**
     * Update assets for the given entity
     */
    public function updateAssets(BaseApiEntity &$entity): void
    {
        $assetFields = $this->getAssetFields($entity);
        if (!$assetFields) {
            return;
        }

        $fs = new Filesystem();

        foreach ($assetFields as $assetField) {
            $assetPath = $this->dj->fullAssetPath($entity, $assetField, $this->eventLog->externalIdFieldForEntity($entity) !== null);
            $clearMethod = sprintf('isClear%s', ucfirst($assetField));
            $shouldClear = call_user_func([$entity, $clearMethod]);
            $fileMethod = sprintf('get%sFile', ucfirst($assetField));
            /** @var UploadedFile|null $file */
            $file = call_user_func([$entity, $fileMethod]);
            if ($shouldClear && file_exists($assetPath)) {
                unlink($assetPath);
            } elseif ($file) {
                $fs->copy($file->getRealPath(), $assetPath);
            }
        }
    }
}
