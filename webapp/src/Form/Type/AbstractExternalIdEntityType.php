<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Service\EventLogService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Class AbstractExternalIdEntityType
 *
 * Base class that can be used to automatically add an external ID field to forms that need them
 *
 * @package App\Form\Type
 */
class AbstractExternalIdEntityType extends AbstractType
{
    /**
     * @var EventLogService
     */
    protected $eventLogService;

    /**
     * AbstractExternalIdEntityType constructor.
     * @param EventLogService $eventLogService
     */
    public function __construct(EventLogService $eventLogService)
    {
        $this->eventLogService = $eventLogService;
    }

    /**
     * Add an external ID field if the given entity class needs it
     * @param FormBuilderInterface $builder
     * @param string               $entity
     * @throws \Exception
     */
    protected function addExternalIdField(FormBuilderInterface $builder, $entity)
    {
        if ($this->eventLogService->externalIdFieldForEntity($entity) !== null) {
            $builder->add('externalid', TextType::class, [
                'label' => 'External ID',
                'required' => false,
                'constraints' => [
                    new Regex(
                        [
                            'pattern' => '/^[a-zA-Z0-9_-]+$/i',
                            'message' => 'Only letters, numbers, dashes and underscores are allowed',
                        ]
                    ),
                    new NotBlank(),
                ]
            ]);
        }
    }
}
