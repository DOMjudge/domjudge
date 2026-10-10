<?php declare(strict_types=1);

namespace App\Form\Type;

use App\DataTransferObject\ConfigurationSpecification;
use App\Service\ConfigurationService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigurationType extends AbstractType
{
    public function __construct(protected readonly ConfigurationService $config) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keyValueFields = [];
        foreach ($this->config->getConfigSpecification() as $name => $spec) {
            $spec = $this->config->addOptions($spec);
            [$type, $fieldOptions] = $this->fieldTypeAndOptions($spec);
            $builder->add($name, $type, $fieldOptions + [
                'label' => ucfirst(str_replace('_', ' ', $name)),
                'help' => $spec->description,
                'required' => false,
            ]);
            if ($spec->type === 'array_keyval') {
                $keyValueFields[] = $name;
            }
        }

        $builder->add('save', SubmitType::class, ['label' => 'Save all changes']);

        // A CollectionType needs a list, but key/value configs are stored as a map.
        $builder->addModelTransformer(new CallbackTransformer(
            function (?array $data) use ($keyValueFields): ?array {
                foreach ($keyValueFields as $name) {
                    $rows = [];
                    foreach ($data[$name] ?? [] as $key => $val) {
                        $rows[] = ['key' => $key, 'val' => $val];
                    }
                    $data[$name] = $rows;
                }
                return $data;
            },
            function (?array $data) use ($keyValueFields): ?array {
                foreach ($keyValueFields as $name) {
                    $map = [];
                    foreach ($data[$name] ?? [] as $row) {
                        $map[$row['key'] ?? ''] = $row['val'];
                    }
                    $data[$name] = $map;
                }
                return $data;
            }
        ));
    }

    public function getBlockPrefix(): string
    {
        return 'config';
    }

    /**
     * @param array<int|string, string> $labels Labels indexed by value
     * @return array<string, mixed>
     */
    public static function choiceOptions(array $labels): array
    {
        // Labels are not unique (e.g. executable descriptions), so don't use them as keys.
        return [
            'choices' => array_keys($labels),
            'choice_label' => fn(int|string $value): string => $labels[$value],
            'placeholder' => false,
        ];
    }

    /**
     * @return array{class-string, array<string, mixed>}
     */
    private function fieldTypeAndOptions(ConfigurationSpecification $spec): array
    {
        $collectionOptions = [
            'entry_options' => ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
        ];

        return match ($spec->type) {
            'bool' => [CheckboxType::class, ['label_attr' => ['class' => 'checkbox-switch']]],
            'int', 'string', 'enum' => match (true) {
                $spec->options !== null => [ChoiceType::class, self::choiceOptions($spec->options)],
                $spec->type === 'int' => [IntegerType::class, []],
                default => [TextType::class, ['empty_data' => '']],
            },
            'array_val' => $spec->options !== null
                ? [ChoiceType::class, self::choiceOptions(array_combine($spec->options, $spec->options)) + ['multiple' => true]]
                : [CollectionType::class, $collectionOptions + ['entry_type' => TextType::class]],
            'array_keyval' => [CollectionType::class, [
                'entry_type' => ConfigKeyValueType::class,
                'entry_options' => [
                    'label' => false,
                    'key_options' => $spec->keyOptions,
                    'val_options' => $spec->valueOptions,
                    'key_placeholder' => $spec->keyPlaceholder,
                    'val_placeholder' => $spec->valuePlaceholder,
                    'val_type' => $spec->valueType,
                ],
            ] + $collectionOptions],
            default => [TextType::class, []],
        };
    }
}
