<?php

namespace DOMJudgeBundle\Request\ParamConverter;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiEntityParamConverter implements ParamConverterInterface {
	/**
	 * @var ManagerRegistry
	 */
	protected $registry;

	/**
	 * @var array
	 */
	protected $endpoints;

	/**
	 * @var bool
	 */
	protected $externalIdsEnabled;

	/**
	 * ApiEntityParamConverter constructor.
	 * @param ManagerRegistry $registry
	 * @param array $endpoints
	 * @param bool $externalIdsEnabled
	 */
	public function __construct(ManagerRegistry $registry, array $endpoints, $externalIdsEnabled) {
		$this->registry = $registry;
		$this->endpoints = $endpoints;
		$this->externalIdsEnabled = $externalIdsEnabled;
	}

	/**
	 * Stores the object in the request.
	 *
	 * @param Request $request The request
	 * @param ParamConverter $configuration Contains the name, class and options of the object
	 *
	 * @return bool True if the object has been successfully set, else false
	 */
	public function apply(Request $request, ParamConverter $configuration) {
		$name = $configuration->getName();
		$class = $configuration->getClass();

		// Determine the endpoint to use
		$endpoint = str_replace('_', '-', Inflector::tableize(Inflector::pluralize($name)));

		if (!isset($this->endpoints[$endpoint])) {
			return false;
		}

		$endpointData = $this->endpoints[$endpoint];

		try {
			$repo = $this->registry->getManagerForClass($class)->getRepository($class);
			if ($this->externalIdsEnabled) {
				$criteria = [$endpointData['externalid'] => $request->attributes->get('id')];
			} else {
				$criteria = [$endpointData['primarykey'] => $request->attributes->get('id')];
			}

			// Check if the endpoint requires a contest ID
			if (isset($endpointData['contestid'])) {
				// It does, the request should have one
				if (!$request->attributes->has('cid')) {
					throw new \InvalidArgumentException(sprintf('Endpoint %s requires a {cid} request attribute', $endpoint));
				}

				// Now add the attribute
				$criteria[$endpointData['contestid']] = $request->attributes->get('cid');
			}

			$object = $repo->findOneBy($criteria);

			$request->attributes->set($name, $object);

			return true;
		} catch (NoResultException $e) {
			return false;
		}
	}

	/**
	 * Checks if the object is supported.
	 *
	 * @param ParamConverter $configuration Should be an instance of ParamConverter
	 *
	 * @return bool True if the object is supported, else false
	 */
	public function supports(ParamConverter $configuration) {
		return !$this->registry->getManagerForClass($configuration->getClass())->getMetadataFactory()->isTransient($configuration->getClass());
	}
}
