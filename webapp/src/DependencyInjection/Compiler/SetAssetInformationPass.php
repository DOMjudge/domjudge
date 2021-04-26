<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SetAssetInformationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $this->registerDirectoryAssetParameter(
            $container,
            'domjudge.custom_css_files',
            'css/custom',
            'css'
        );
        $this->registerDirectoryAssetParameter(
            $container,
            'domjudge.custom_js_files',
            'js/custom',
            'js'
        );
        $this->registerDirectoryAssetParameter(
            $container,
            'domjudge.affiliations_logos',
            'images/affiliations',
            'png'
        );
        $this->registerDirectoryAssetParameter(
            $container,
            'domjudge.team_images',
            'images/teams',
            'jpg'
        );
        $this->registerFileAssetParameter(
            $container,
            'domjudge.banner_exists',
            'images/banner.png'
        );
    }

    /**
     * Register the directory asset from the given path in the container using the given parameter name
     */
    protected function registerDirectoryAssetParameter(ContainerBuilder $container, string $paramName, string $path, string $extension): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $container->setParameter($paramName, $this->getAssetFiles($projectDir, $path, $extension));
        $container->addResource(new DirectoryResource(sprintf('%s/public/%s', $projectDir, $path), sprintf('/^.*\.%s$/', $extension)));
    }

    /**
     * Register the existence of the file asset from the given path in the container using the given parameter name
     */
    protected function registerFileAssetParameter(ContainerBuilder $container, string $paramName, string $path): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $container->setParameter(
            $paramName,
            $this->assetExists($projectDir, $path)
        );
        $container->addResource(new FileExistenceResource(sprintf('%s/public/%s', $projectDir, $path)));
    }

    /**
     * Get asset files in the given directory with the given extension
     */
    protected function getAssetFiles(string $projectDir, string $path, string $extension): array
    {
        $customDir = sprintf('%s/public/%s', $projectDir, $path);
        if (!is_dir($customDir)) {
            return [];
        }

        $results = [];
        foreach (scandir($customDir) as $file) {
            if (strpos($file, '.' . $extension) !== false) {
                $results[] = $file;
            }
        }

        return $results;
    }

    /**
     * Determine whether the given asset exists
     */
    protected function assetExists(string $projectDir, string $asset): bool
    {
        $webDir = realpath(sprintf('%s/public', $projectDir));
        return is_readable($webDir . '/' . $asset);
    }
}
