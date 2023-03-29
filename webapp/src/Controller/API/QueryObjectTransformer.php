<?php declare(strict_types=1);

namespace App\Controller\API;

/**
 * Classes that implement this interface (and inherit from AbstractRestController) will allow you to modify
 * objects returned from the API. AbstractRestController will call `transformObject` before returning the objects.
 */
interface QueryObjectTransformer
{
    /**
     * Transform the given object before returning it from the API.
     * @param mixed $object
     * @return mixed
     */
    public function transformObject($object);
}
