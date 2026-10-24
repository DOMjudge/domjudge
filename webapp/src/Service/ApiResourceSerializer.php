<?php declare(strict_types=1);

namespace App\Service;

use App\Utils\CcsApiVersion;
use JMS\Serializer\ArrayTransformerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;

class ApiResourceSerializer
{
    public const GROUP_DEFAULT = 'Default';
    public const GROUP_NONSTRICT = 'Nonstrict';
    public const GROUP_RESTRICTED = 'Restricted';
    public const GROUP_RESTRICTED_NONSTRICT = 'RestrictedNonstrict';

    public function __construct(
        protected readonly SerializerInterface $serializer,
        protected readonly ConfigurationService $config,
        protected readonly AuthorizedUserService $authService,
    ) {}

    /**
     * @return string[]
     */
    public function getSerializationGroups(bool $strict = false, ?bool $restricted = null): array
    {
        $restricted ??= $this->authService->checkRole('api_reader');

        /** @var CcsApiVersion $ccsApiVersion */
        $ccsApiVersion = $this->config->get('ccs_api_version');
        $groups = [self::GROUP_DEFAULT, $ccsApiVersion->value];
        if (!$strict) {
            $groups[] = self::GROUP_NONSTRICT;
        }
        if ($restricted) {
            $groups[] = self::GROUP_RESTRICTED;
        }
        if (!$strict && $restricted) {
            $groups[] = self::GROUP_RESTRICTED_NONSTRICT;
        }

        return $groups;
    }

    public function createSerializationContext(bool $strict = false, ?bool $restricted = null): SerializationContext
    {
        return SerializationContext::create()
            ->setSerializeNull(true)
            ->setAttribute('authorized_user_service', $this->authService)
            ->setAttribute('config_service', $this->config)
            ->setGroups($this->getSerializationGroups($strict, $restricted));
    }

    public function serialize(mixed $data, bool $strict = false, ?bool $restricted = null): string
    {
        return $this->serializer->serialize(
            $data,
            'json',
            $this->createSerializationContext($strict, $restricted)
        );
    }

    /**
     * @return mixed[]
     */
    public function toArray(mixed $data, bool $strict = false, ?bool $restricted = null): array
    {
        if ($this->serializer instanceof ArrayTransformerInterface) {
            return $this->serializer->toArray(
                $data,
                $this->createSerializationContext($strict, $restricted)
            );
        }

        return json_decode($this->serialize($data, $strict, $restricted), true);
    }
}
