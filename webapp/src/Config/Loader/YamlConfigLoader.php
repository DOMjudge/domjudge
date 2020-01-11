<?php declare(strict_types=1);

namespace App\Config\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlConfigLoader
 *
 * Class that loads a YAML file
 *
 * @see     https://symfony.com/doc/current/components/config/resources.html#resource-loaders
 *
 * @package App\Config\Loader
 */
class YamlConfigLoader extends FileLoader
{
    /**
     * @inheritDoc
     */
    public function load($resource, $type = null)
    {
        return Yaml::parse(file_get_contents($resource));
    }

    /**
     * @inheritDoc
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) &&
            pathinfo($resource, PATHINFO_EXTENSION) === 'yaml';
    }
}
