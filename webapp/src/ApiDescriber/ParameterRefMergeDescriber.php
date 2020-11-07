<?php declare(strict_types=1);

namespace App\ApiDescriber;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use const OpenApi\Annotations\UNDEFINED;

/**
 * Class ParameterRefMergeDescriber
 *
 * @package App\ApiDescriber
 */
class ParameterRefMergeDescriber implements DescriberInterface
{
    /**
     * @inheritdoc
     */
    public function describe(OpenApi $api)
    {
        // This method removes parameters that also have a ref version as they will be duplicated otherwise.
        // It can be deleted when https://github.com/nelmio/NelmioApiDocBundle/issues/1407 is fixed

        $apiParameters = [];
        foreach ($api->components->parameters as $parameter) {
            $apiParameters[$parameter->parameter] = $parameter;
        }

        foreach ($api->paths as $path) {
            /** @var Operation[] $operations */
            $operations = array_filter([
                $path->get,
                $path->post,
                $path->delete,
                $path->options,
                $path->head,
                $path->patch
            ]);
            foreach ($operations as $operation) {
                if ($operation === UNDEFINED || $operation->parameters === UNDEFINED) {
                    continue;
                }
                $parametersToRemove = [];
                foreach ($operation->parameters as $parameter) {
                    if ($parameter->ref !== UNDEFINED) {
                        $ref = substr($parameter->ref, 24);
                        if (isset($apiParameters[$ref])) {
                            $refParameter = $apiParameters[$ref];
                            foreach ($operation->parameters as $operationParameter) {
                                if ($operationParameter->in === $refParameter->in && $operationParameter->name === $refParameter->name) {
                                    $parametersToRemove[] = $operationParameter;
                                }
                            }
                        }
                    }
                }
                $newParameters = [];
                foreach ($operation->parameters as $parameter) {
                    if (!in_array($parameter, $parametersToRemove, true)) {
                        $newParameters[] = $parameter;
                    }
                }
                $operation->parameters = $newParameters;
            }
        }
    }
}
