<?php declare(strict_types=1);

namespace App\Serializer;

use App\DataTransferObject\ImageFile;
use App\Entity\TeamAffiliation;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class TeamAffiliationVisitor implements EventSubscriberInterface
{
    public function __construct(
        protected DOMJudgeService      $dj,
        protected ConfigurationService $config,
        protected RequestStack         $requestStack,
    ) {}

    /**
     * @return array<array{event: string, class: string, format: string, method: string}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::PRE_SERIALIZE,
                'class' => TeamAffiliation::class,
                'format' => 'json',
                'method' => 'onPreSerialize',
            ],
        ];
    }

    public function onPreSerialize(ObjectEvent $event): void
    {
        /** @var TeamAffiliation $affiliation */
        $affiliation = $event->getObject();

        $id = $affiliation->getExternalid();

        // Country flag
        if ($this->config->get('show_flags') && $affiliation->getCountry()) {
            $countryFlags = [];
            // Mapping from API URL size to viewbox size of SVG's
            $countryFlagSizes = [
                '4x3' => [640, 480],
                '1x1' => [512, 512],
            ];

            foreach ($countryFlagSizes as $size => $viewBoxSize) {
                $route = $this->dj->apiRelativeUrl(
                    'v4_app_api_generalinfo_countryflag', [
                        'countryCode' => $affiliation->getCountry(),
                        'size' => $size,
                    ]
                );
                $countryFlags[] = new ImageFile(
                    href: $route,
                    mime: 'image/svg+xml',
                    filename: 'country-flag-' . $size . '.svg',
                    width: $viewBoxSize[0],
                    height: $viewBoxSize[1],
                );
            }

            $affiliation->setCountryFlagForApi($countryFlags);
        } else {
            $affiliation->setCountryFlagForApi();
        }

        // Affiliation logo
        if ($affiliationLogo = $this->dj->assetPath((string)$id, 'affiliation', true)) {
            $imageSize = Utils::getImageSize($affiliationLogo);
            $parts = explode('.', $affiliationLogo);
            $extension = $parts[count($parts) - 1];

            if ($cid = $this->requestStack->getCurrentRequest()->attributes->get('cid')) {
                $route = $this->dj->apiRelativeUrl(
                    'v4_organization_logo',
                    [
                        'cid' => $cid,
                        'id' => $id,
                    ]
                );
            } else {
                $route = $this->dj->apiRelativeUrl('v4_no_contest_organization_logo', ['id' => $id]);
            }
            $affiliation->setLogoForApi(new ImageFile(
                href: $route,
                mime: mime_content_type($affiliationLogo),
                filename: 'logo.' . $extension,
                width: $imageSize[0],
                height: $imageSize[1],
            ));
        } else {
            $affiliation->setLogoForApi();
        }
    }
}
