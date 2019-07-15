<?php declare(strict_types=1);

namespace App;

use App\DependencyInjection\Compiler\ChangeMainFormAuthenticationListenerPass;
use Doctrine;
use FOS;
use JMS;
use Nelmio;
use Sensio;
use Symfony;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    /**
     * @inheritDoc
     */
    public function registerBundles()
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new FOS\RestBundle\FOSRestBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new Nelmio\ApiDocBundle\NelmioApiDocBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();

            if ('dev' === $this->getEnvironment()) {
                $bundles[] = new Symfony\Bundle\WebServerBundle\WebServerBundle();
            }
        }

        return $bundles;
    }

    /**
     * @inheritDoc
     */
    public function getProjectDir()
    {
        // We overwrite this, because Symfony assumes the root dir is the
        // place where the composer.json file is, but we do not have that
        return dirname(__DIR__);
    }

    /**
     * @inheritDoc
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('container.autowiring.strict_mode', true);
            $container->setParameter('container.dumper.inline_class_loader',
                                     true);

            $container->addObjectResource($this);
        });
        $loader->load($this->getProjectDir() . '/app/config/config_' . $this->getEnvironment() . '.yml');
    }

    protected function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ChangeMainFormAuthenticationListenerPass());
    }
}
