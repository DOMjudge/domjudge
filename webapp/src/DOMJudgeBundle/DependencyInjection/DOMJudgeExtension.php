<?php
// src/Acme/HelloBundle/DependencyInjection/AcmeHelloExtension.php
namespace DOMJudgeBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class DOMJudgeExtension extends Extension
{
	public function load(array $configs, ContainerBuilder $container)
	{
		$loader = new YamlFileLoader(
			$container,
			new FileLocator(__DIR__.'/../Resources/config')
		);
		$loader->load('services.yml');

		$configuration = new Configuration();

		$config = $this->processConfiguration($configuration, $configs);

		$endpoints = $config['api']['endpoints'];

		// Add defaults to mapping:
		foreach ($endpoints as $endpoint => $data) {
			if (!array_key_exists('url', $data)) {
				$endpoints[$endpoint]['url'] = '/' . $endpoint;
			}
			if ($data['tables'] === [null]) {
				$endpoints[$endpoint]['tables'] = array(preg_replace('/s$/', '', $endpoint));
			}
		}

		$container->setParameter('domjudge.api.endpoints', $endpoints);
	}

	public function getAlias()
	{
		return 'domjudge';
	}
}
