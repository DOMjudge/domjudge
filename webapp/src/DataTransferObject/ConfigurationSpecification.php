<?php declare(strict_types=1);

namespace App\DataTransferObject;

use BackedEnum;

class ConfigurationSpecification
{
    /**
     * @param class-string<BackedEnum>       $enumClass
     * @param array<int|string, string>|null $options
     * @param array<string, string>|null     $keyOptions
     * @param array<string, string>|null     $valueOptions
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $public,
        public readonly string $description,
        public readonly string $category,
        public readonly mixed $defaultValue,
        public readonly ?string $regex = null,
        public readonly ?string $keyPlaceholder = null,
        public readonly ?string $valuePlaceholder = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $docdescription = null,
        public readonly ?string $enumClass = null,
        public ?array $options = null,
        public ?array $keyOptions = null,
        public ?array $valueOptions = null,
    ) {}

    /**
     * @param array{name: string, type: string, public: bool,
     *                description: string, category: string, default_value: mixed|mixed[],
     *                regex?: string, key_placeholder?: string, value_placeholder?: string,
     *                error_message?: string, docdescription?: string, enum_class?: string,
     *                options?: array<int|string, string>, key_options?: array<string, string>,
     *                value_options?: array<string, string>} $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            $array['name'],
            $array['type'],
            $array['public'],
            $array['description'],
            $array['category'],
            $array['default_value'],
            $array['regex'] ?? null,
            $array['key_placeholder'] ?? null,
            $array['value_placeholder'] ?? null,
            $array['error_message'] ?? null,
            $array['docdescription'] ?? null,
            $array['enum_class'] ?? null,
            $array['options'] ?? null,
            $array['key_options'] ?? null,
            $array['value_options'] ?? null,
        );
    }
}
