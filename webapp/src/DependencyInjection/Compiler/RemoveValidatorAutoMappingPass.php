<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * We do not use validator auto mapping, but the property info loader still
 * extracts the types of all properties of every validated class. In debug mode
 * that is not cached and quite slow, so remove it.
 */
class RemoveValidatorAutoMappingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $id = 'validator.property_info_loader';
        if (!$container->hasDefinition($id)) {
            return;
        }

        // The validator builder references the loader, so remove that too.
        if ($container->hasDefinition('validator.builder')) {
            $builder = $container->getDefinition('validator.builder');
            $calls   = [];
            foreach ($builder->getMethodCalls() as $call) {
                [, $arguments] = $call;
                foreach ($arguments as $argument) {
                    if (($argument instanceof Reference && (string)$argument === $id) || $this->containsReference($argument, $id)) {
                        continue 2;
                    }
                }
                $calls[] = $call;
            }
            $builder->setMethodCalls($calls);
        }

        $container->removeDefinition($id);
    }

    private function containsReference(mixed $argument, string $id): bool
    {
        if ($argument instanceof Reference) {
            return (string)$argument === $id;
        }
        if (is_array($argument)) {
            foreach ($argument as $item) {
                if ($this->containsReference($item, $id)) {
                    return true;
                }
            }
        }

        return false;
    }
}
