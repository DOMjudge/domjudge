<?php declare(strict_types=1);

namespace App\Serializer;

use App\DataTransferObject\FileWithName;
use App\DataTransferObject\ImageFile;
use App\Entity\Contest;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Utils\Utils;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ContestVisitor implements EventSubscriberInterface
{
    public function __construct(
        protected readonly ConfigurationService $config,
        protected readonly DOMJudgeService $dj,
        protected readonly EventLogService $eventLogService
    ) {}

    /**
     * @return array<array{event: string, class: string, format: string, method: string}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event' => Events::PRE_SERIALIZE,
                'class' => Contest::class,
                'format' => 'json',
                'method' => 'onPreSerialize',
            ],
        ];
    }

    public function onPreSerialize(ObjectEvent $event): void
    {
        /** @var Contest $contest */
        $contest = $event->getObject();

        $property = new StaticPropertyMetadata(
            Contest::class,
            'penalty_time',
            null
        );
        $contest->setPenaltyTimeForApi((int)$this->config->get('penalty_time'));

        $id = $contest->getApiId($this->eventLogService);

        // Banner
        if ($banner = $this->dj->assetPath($id, 'contest', true)) {
            $imageSize = Utils::getImageSize($banner);
            $parts = explode('.', $banner);
            $extension = $parts[count($parts) - 1];

            $route = $this->dj->apiRelativeUrl(
                'v4_contest_banner', ['cid' => $id]
            );
            $contest->setBannerForApi(new ImageFile(
                href: $route,
                mime: mime_content_type($banner),
                filename: 'banner.' . $extension,
                width: $imageSize[0],
                height: $imageSize[1],
            ));
        } else {
            $contest->setBannerForApi();
        }

        $hasAccess = $this->dj->checkrole('jury') ||
            $this->dj->checkrole('api_reader') ||
            $contest->getFreezeData()->started();

        // Problem statement
        if ($contest->getContestTextType() && $hasAccess) {
            $route = $this->dj->apiRelativeUrl(
                'v4_contest_text',
                [
                    'cid' => $contest->getApiId($this->eventLogService),
                ]
            );
            $mimeType = match ($contest->getContestTextType()) {
                'pdf' => 'application/pdf',
                'html' => 'text/html',
                'txt' => 'text/plain',
                default => throw new BadRequestHttpException(sprintf('Contest c%d text has unknown type', $contest->getCid())),
            };
            $contest->setTextForApi(new FileWithName(
                $route,
                $mimeType,
                'text.' . $contest->getContestTextType()
            ));
        } else {
            $contest->setTextForApi();
        }
    }
}
