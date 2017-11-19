<?php

namespace DOMJudgeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface {
	/**
	 * Generates the configuration tree builder.
	 *
	 * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
	 */
	public function getConfigTreeBuilder() {
		$treeBuilder = new TreeBuilder();
		$rootNode = $treeBuilder->root('domjudge');

		$rootNode
			->children()
				->arrayNode('api')
					->children()
						->booleanNode('useexternalids')
							->info('Whether to use external ID\'s in the API. If enabled, all endpoints as configured by domjudge.api.endpoints will work with external ID\'s; otherwise will work with internal (database) ID\'s')
							->defaultFalse()
						->end()
						->arrayNode('endpoints')
							->info('Configuration for API endpoints')
							->normalizeKeys(false)
							->useAttributeAsKey('endpointname')
							->prototype('array')
								->children()
									->enumNode('type')
										->info('Type of endpoint')
										->defaultValue('configuration')
										->values(['configuration', 'live', 'aggregate'])
									->end()
									->scalarNode('primarykey')
										->info('Field from the database table to use as primary key')
										->defaultValue('id')
									->end()
									->scalarNode('url')
										->info('REST API URL of endpoint relative to baseurl, defaults to \'/<endpoint>\'')
									->end()
									->arrayNode('tables')
										->defaultValue([null])
										->info('Array of database table(s) associated to data, defaults to <endpoint> without \'s\'')
										->prototype('scalar')->end()
									->end()
									->scalarNode('externalid')
										->info('Field from the database table to use as external ID. If null, use database ID')
										->defaultValue('externalid')
									->end()
									->scalarNode('contestid')
										->info('If given, field from the database table to use to filter on contest ID')
									->end()
								->end()
							->end()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
