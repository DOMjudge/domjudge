<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Service\EventLogService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Base class that can be used to automatically add an external ID field to forms that need them.
 */
class AbstractExternalIdEntityType extends AbstractType
{
    public function __construct(protected readonly EventLogService $eventLogService)
    {
    }

    /**
     * Add an external ID field if the given entity class needs it.
     */
    protected function addExternalIdField(FormBuilderInterface $builder, string $entity): void
    {
        $builder->add('externalid', TextType::class, [
            'label' => 'ID',
            'help' => 'Leave empty to generate automatically.',
            'required' => false,
            'empty_data' => '',
        ]);
    }
}
