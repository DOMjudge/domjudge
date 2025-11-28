<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineNativeLazyObjectsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (PHP_VERSION_ID < 80400) {
            return;
        }

        $configurations = [
            'doctrine.orm.configuration',
            'doctrine.orm.default_configuration',
        ];

        foreach ($configurations as $configuration) {
            if (!$container->hasDefinition($configuration)) {
                continue;
            }

            $definition = $container->getDefinition($configuration);
            $definition->addMethodCall('enableNativeLazyObjects', [true]);
        }
    }
}
