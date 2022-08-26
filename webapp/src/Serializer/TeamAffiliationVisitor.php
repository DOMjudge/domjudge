<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;

class TeamAffiliationVisitor implements EventSubscriberInterface
{
    protected DOMJudgeService $dj;
    protected ConfigurationService $config;
    protected EventLogService $eventLogService;
    protected RequestStack $requestStack;

    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EventLogService $eventLogService,
        RequestStack $requestStack
    ) {
        $this->dj = $dj;
        $this->config = $config;
        $this->eventLogService = $eventLogService;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
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

    public function onPostSerialize(ObjectEvent $event): void
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var TeamAffiliation $affiliation */
        $affiliation = $event->getObject();

        $id = $affiliation->getApiId($this->eventLogService);

        // Country flag
        if ($this->config->get('show_flags') && $affiliation->getCountry()) {
            $countryFlags = [];
            // Mapping from API URL size to viewbox size of SVG's
            $countryFlagSizes = [
                '4x3' => [640, 480],
                '1x1' => [512, 512],
            ];

            foreach ($countryFlagSizes as $size => $viewBoxSize) {
                $alpha3code = strtoupper($affiliation->getCountry());
                $alpha2code = strtolower(Countries::getAlpha2Code($alpha3code));
                $flagFile   = sprintf('%s/public/flags/%s/%s.svg', $this->dj->getDomjudgeWebappDir(), $size, $alpha2code);
                $route      = $this->dj->apiRelativeUrl(
                    'v4_app_api_generalinfo_countryflag', ['countryCode' => $affiliation->getCountry(), 'size' => $size]
                );
                $countryFlags[] = [
                    'href'     => $route,
                    'mime'     => 'image/svg+xml',
                    'width'    => $viewBoxSize[0],
                    'height'   => $viewBoxSize[1],
                    'filename' => 'country-flag-' . $size . '.svg',
                ];
            }

            $property = new StaticPropertyMetadata(
                TeamAffiliation::class,
                'country_flag',
                null
            );
            $visitor->visitProperty($property, $countryFlags);
        }

        // Affiliation logo
        if ($affiliationLogo = $this->dj->assetPath((string)$id, 'affiliation', true)) {
            $imageSize = Utils::getImageSize($affiliationLogo);
            $parts     = explode('.', $affiliationLogo);
            $extension = $parts[count($parts) - 1];

            $route = $this->dj->apiRelativeUrl(
                'v4_organization_logo',
                [
                    'cid' => $this->requestStack->getCurrentRequest()->attributes->get('cid'),
                    'id'  => $id,
                ]
            );
            $property = new StaticPropertyMetadata(
                TeamAffiliation::class,
                'logo',
                null
            );
            $visitor->visitProperty($property, [
                [
                    'href'     => $route,
                    'mime'     => mime_content_type($affiliationLogo),
                    'width'    => $imageSize[0],
                    'height'   => $imageSize[1],
                    'filename' => 'logo.' . $extension,
                ]
            ]);
        }
    }
}
