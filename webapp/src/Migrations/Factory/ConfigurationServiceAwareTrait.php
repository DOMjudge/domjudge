<?php declare(strict_types=1);

namespace App\Migrations\Factory;

use App\Service\ConfigurationService;

trait ConfigurationServiceAwareTrait
{
    private ConfigurationService $configurationService;

    public function setConfigurationService(ConfigurationService $configurationService): void
    {
        $this->configurationService = $configurationService;
    }
}
