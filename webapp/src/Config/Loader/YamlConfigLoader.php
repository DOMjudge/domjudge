<?php declare(strict_types=1);

namespace App\Config\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * Class that loads a YAML file.
 *
 * @see https://symfony.com/doc/current/components/config/resources.html#resource-loaders
 */
class YamlConfigLoader extends FileLoader
{
    public function load(mixed $resource, ?string $type = null): mixed
    {
        return Yaml::parse(file_get_contents($resource));
    }

    public function supports($resource, ?string $type = null): bool
    {
        return is_string($resource) &&
            pathinfo($resource, PATHINFO_EXTENSION) === 'yaml';
    }
}
