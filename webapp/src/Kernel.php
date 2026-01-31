<?php declare(strict_types=1);

namespace App;

use App\DependencyInjection\Compiler\SetDocLinksPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SetDocLinksPass());
    }
}
