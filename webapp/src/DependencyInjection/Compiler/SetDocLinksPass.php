<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Config\OptionalFileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

class SetDocLinksPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $etcDir = $container->getParameter('domjudge.etcdir');
        $docsFile = sprintf('%s/docs.yaml', $etcDir);
        if (file_exists($docsFile)) {
            $docs = Yaml::parseFile($docsFile);
        } else {
            $docs = [];
        }
        $container->setParameter('domjudge.doc_links', $docs);
        $container->addResource(new OptionalFileResource($docsFile));
    }
}
