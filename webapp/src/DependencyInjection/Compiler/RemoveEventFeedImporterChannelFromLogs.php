<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RemoveEventFeedImporterChannelFromLogs implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // Remove all handlers from the event feed importer logger except the event feed importer handler
        // This will make it such that event feed importer log entries only appear in the event feed importer log
        $container->getDefinition('monolog.logger.event-feed-importer')
            ->removeMethodCall('pushHandler')
            ->addMethodCall('pushHandler', [new Reference('monolog.handler.event_feed_importer')]);
    }
}
