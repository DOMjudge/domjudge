<?php declare(strict_types=1);

namespace App\NelmioApiDocBundle;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use Nelmio\ApiDocBundle\Describer\ExternalDocDescriber as BaseExternalDocDescriber;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDecorator(decorates: 'nelmio_api_doc.describers.config')]
class ExternalDocDescriber implements DescriberInterface
{
    public function __construct(
        #[AutowireDecorated]
        protected BaseExternalDocDescriber $decorated,
        protected RequestStack $requestStack,
    ) {}

    public function describe(OpenApi $api): void
    {
        // Inject the correct server for the API docs
        $request = $this->requestStack->getCurrentRequest();
        $this->decorated->describe($api);
        Util::merge($api->servers[0], ['url' => $request->getSchemeAndHttpHost(),], true);
    }
}
