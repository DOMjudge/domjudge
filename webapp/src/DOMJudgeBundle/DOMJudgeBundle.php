<?php declare(strict_types=1);

namespace DOMJudgeBundle;

use DOMJudgeBundle\DependencyInjection\Compiler\ChangeMainFormAuthenticationListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class DOMJudgeBundle
 * @package DOMJudgeBundle
 */
class DOMJudgeBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // Add compiler pass that replaces the default form authentication listener
        $container->addCompilerPass(new ChangeMainFormAuthenticationListenerPass());
    }
}
