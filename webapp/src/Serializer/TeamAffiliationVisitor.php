<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\Routing\RouterInterface;

class TeamAffiliationVisitor implements EventSubscriberInterface
{
    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var RouterInterface
     */
    protected $router;

    public function __construct(ConfigurationService $config, RouterInterface $router)
    {
        $this->config = $config;
        $this->router = $router;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event'  => Events::POST_SERIALIZE,
                'class'  => TeamAffiliation::class,
                'format' => 'json',
                'method' => 'onPostSerialize'
            ],
        ];
    }

    /**
     * @param ObjectEvent $event
     *
     * @throws \Exception
     */
    public function onPostSerialize(ObjectEvent $event)
    {
        if (!$this->config->get('show_flags')) {
            return;
        }

        /** @var TeamAffiliation $affiliation */
        $affiliation = $event->getObject();
        if (!$affiliation->getCountry()) {
            return;
        }

        $apiRootRoute = $this->router->generate('v4_api_root');
        $offset = substr($apiRootRoute, -1) === '/' ? 0 : 1;
        $countryFlags = [];
        // Mapping from API URL size to viewbox size of SVG's
        $countryFlagSizes = [
            '4x3' => [640, 480],
            '1x1' => [512, 512],
        ];
        foreach ($countryFlagSizes as $size => $viewBoxSize) {
            $countryFlagRoute = $this->router->generate(
                'v4_app_api_generalinfo_countryflag', ['countryCode' => $affiliation->getCountry(), 'size' => $size]
            );
            $relativeCountryFlagRoute = substr($countryFlagRoute, strlen($apiRootRoute) + $offset);
            $countryFlags[] = [
                'href'   => $relativeCountryFlagRoute,
                'mime'   => 'image/svg+xml',
                'width'  => $viewBoxSize[0],
                'height' => $viewBoxSize[1],
            ];
        }

        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        $property = new StaticPropertyMetadata(
            TeamAffiliation::class,
            'country_flag',
            null
        );
        $visitor->visitProperty($property, $countryFlags);
    }
}
