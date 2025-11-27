<?php declare(strict_types=1);

namespace App\Migrations\Factory;

use App\Service\ConfigurationService;

interface ConfigurationServiceAwareInterface
{
    public function setConfigurationService(ConfigurationService $configurationService): void;
}
