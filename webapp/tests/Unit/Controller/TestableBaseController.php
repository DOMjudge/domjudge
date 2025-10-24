<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\BaseController;

/**
 * Concrete implementation of BaseController for testing protected methods.
 */
class TestableBaseController extends BaseController
{
    public function exposedGetPreviousAndNextObjectIds(
        string $entityClass,
        mixed $currentIdValue,
        string $idField = 'externalid',
        array $orderBy = ['e.externalid' => 'ASC'],
        bool $filterOnContest = false,
    ): array {
        return $this->getPreviousAndNextObjectIds($entityClass, $currentIdValue, $idField, $orderBy, $filterOnContest);
    }
}
