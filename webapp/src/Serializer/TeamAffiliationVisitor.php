<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\TeamAffiliation;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Exception;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\HttpFoundation\RequestStack;

class TeamAffiliationVisitor implements EventSubscriberInterface
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(
        DOMJudgeService $dj,
        EventLogService $eventLogService,
        RequestStack $requestStack
    ) {
        $this->dj = $dj;
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

    /**
     * @param ObjectEvent $event
     *
     * @throws Exception
     */
    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        /** @var TeamAffiliation $affiliation */
        $affiliation = $event->getObject();

        $idField = sprintf('get%s', ucfirst($this->eventLogService->externalIdFieldForEntity(TeamAffiliation::class) ?? 'affilid'));
        $id = call_user_func([$affiliation, $idField]);

        // Country flag
        if ($affiliation->getCountry()) {
            $countryFlags = [];
            // Mapping from API URL size to viewbox size of SVG's
            $countryFlagSizes = [
                '4x3' => [640, 480],
                '1x1' => [512, 512],
            ];

            foreach ($countryFlagSizes as $size => $viewBoxSize) {
                $route = $this->dj->apiRelativeUrl(
                    'v4_app_api_generalinfo_countryflag', ['countryCode' => $affiliation->getCountry(), 'size' => $size]
                );
                $countryFlags[] = [
                    'href'   => $route,
                    'mime'   => 'image/svg+xml',
                    'width'  => $viewBoxSize[0],
                    'height' => $viewBoxSize[1],
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
            $imageSize = getimagesize($affiliationLogo);

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
            $visitor->visitProperty($property, [['href' => $route, 'mime' => 'image/png', 'width' => $imageSize[0], 'height' => $imageSize[1]]]);
        }
    }
}
