<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Security\UsernamePasswordFormAuthenticationListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ChangeMainFormAuthenticationListenerPass
 * @package App\DependencyInjection\Compiler
 */
class ChangeMainFormAuthenticationListenerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        // Replace default form authentication listener with our own
        $definition = $container->getDefinition('security.authentication.listener.form.main');
        $definition->setClass(UsernamePasswordFormAuthenticationListener::class);
        $container->setDefinition('security.authentication.listener.form.main', $definition);
    }
}
