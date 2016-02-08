<?php

namespace DOMjudge\MainBundle;

use Symfony\Component\HttpKernel\KernelInterface;

class ExecutionPrinter
{
	/**
	 * @var KernelInterface
	 */
	private $kernel;

	public function __construct(KernelInterface $kernel)
	{
		$this->kernel = $kernel;
	}
	
	public function getTotalTime() {
		$now = microtime(true);
		$taken = $now - $this->kernel->getStartTime();
		return sprintf('<p>Execution took: %d ms</p>', round($taken * 1000));
	}
}
