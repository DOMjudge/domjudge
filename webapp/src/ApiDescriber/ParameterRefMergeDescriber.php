<?php declare(strict_types=1);

namespace App\ApiDescriber;

use EXSyst\Component\Swagger\Operation;
use EXSyst\Component\Swagger\Parameter;
use EXSyst\Component\Swagger\Path;
use EXSyst\Component\Swagger\Swagger;
use Nelmio\ApiDocBundle\Describer\DescriberInterface;

/**
 * Class ParameterRefMergeDescriber
 * @package App\ApiDescriber
 */
class ParameterRefMergeDescriber implements DescriberInterface
{
    /**
     * @inheritdoc
     */
    public function describe(Swagger $api)
    {
        // This method removes paramaters that also have a ref version as they will be duplicated otherwise.
        // It can be deleted when https://github.com/nelmio/NelmioApiDocBundle/issues/1407 is fixed

        /** @var Path $path */
        foreach ($api->getPaths() as $path) {
            /** @var Operation $operation */
            foreach ($path->getOperations() as $operation) {
                $parametersToRemove = [];
                /** @var Parameter $parameter */
                foreach ($operation->getParameters() as $parameter) {
                    if ($parameter->getRef()) {
                        $ref = substr($parameter->getRef(), 13);
                        if (isset($api->getParameters()[$ref])) {
                            /** @var Parameter $refParameter */
                            $refParameter   = $api->getParameters()[$ref];
                            $refParameterId = sprintf('%s/%s', $refParameter->getName(), $refParameter->getIn());
                            if ($operation->getParameters()->has($refParameterId)) {
                                $parametersToRemove[] = $operation->getParameters()->get($refParameterId);
                            }
                        }
                    }
                }
                foreach ($parametersToRemove as $parameterToRemove) {
                    $operation->getParameters()->remove($parameterToRemove);
                }
            }
        }
    }
}
