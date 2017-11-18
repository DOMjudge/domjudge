<?php

namespace DOMJudgeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class DOMJudgeBundle extends Bundle
{
	public function getContainerExtension() {
		// We overwrite this method because we want the extension to be 'domjudge' and normally it would be dom_judge
		if (null === $this->extension) {
			$this->extension = $this->createContainerExtension();
		}

		return $this->extension;
	}
}
