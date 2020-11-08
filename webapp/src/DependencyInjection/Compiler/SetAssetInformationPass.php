<?php declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SetAssetInformationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $projectDir = $container->getParameter('kernel.project_dir');

        $container->setParameter(
            'domjudge.custom_css_files',
            $this->getAssetFiles($projectDir, 'css/custom', 'css')
        );
        $container->setParameter(
            'domjudge.custom_js_files',
            $this->getAssetFiles($projectDir, 'js/custom', 'js')
        );
        $container->setParameter(
            'domjudge.affiliations_logos',
            $this->getAssetFiles($projectDir, 'images/affiliations', 'png')
        );
        $container->setParameter(
            'domjudge.team_images',
            $this->getAssetFiles($projectDir, 'images/teams', 'jpg')
        );
        $container->setParameter(
            'domjudge.banner_exists',
            $this->assetExists($projectDir, 'images/banner.png')
        );
    }

    protected function getAssetFiles(string $projectDir, string $path, string $extension)
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
     * @param string $asset
     * @return bool
     */
    protected function assetExists(string $projectDir, string $asset): bool
    {
        $webDir = realpath(sprintf('%s/public', $projectDir));
        return is_readable($webDir . '/' . $asset);
    }
}
