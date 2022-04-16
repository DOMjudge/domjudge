<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Class AbstractExternalIdEntityType
 *
 * Base class that can be used to automatically add an external ID field to forms that need them.
 *
 * @package App\Form\Type
 */
class AbstractExternalIdEntityType extends AbstractType
{
    protected EventLogService $eventLogService;

    public function __construct(EventLogService $eventLogService)
    {
        $this->eventLogService = $eventLogService;
    }

    /**
     * Add an external ID field if the given entity class needs it.
     */
    protected function addExternalIdField(FormBuilderInterface $builder, string $entity): void
    {
        if ($this->eventLogService->externalIdFieldForEntity($entity) !== null) {
            $builder->add('externalid', TextType::class, [
                'label' => 'External ID',
                'required' => false,
                'constraints' => [
                    new Regex(
                        [
                            'pattern' => DOMJudgeService::EXTERNAL_IDENTIFIER_REGEX,
                            'message' => 'Only letters, numbers, dashes, underscores and dots are allowed',
                        ]
                    ),
                    new NotBlank(),
                ]
            ]);
        }
    }
}
