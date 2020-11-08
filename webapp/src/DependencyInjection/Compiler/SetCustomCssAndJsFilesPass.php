<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SetCustomCssAndJsFilesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $projectDir = $container->getParameter('kernel.project_dir');

        $container->setParameter(
            'domjudge.custom_css_files',
            $this->getCustomFiles($projectDir, 'css')
        );
        $container->setParameter(
            'domjudge.custom_js_files',
            $this->getCustomFiles($projectDir, 'js')
        );
    }

    protected function getCustomFiles(string $projectDir, string $type)
    {
        $customDir = sprintf('%s/public/%s/custom', $projectDir, $type);
        if (!is_dir($customDir)) {
            return [];
        }

        $results = [];
        foreach (scandir($customDir) as $file) {
            if (strpos($file, '.' . $type) !== false) {
                $results[] = sprintf('%s/custom/%s', $type, $file);
            }
        }

        return $results;
    }
}
