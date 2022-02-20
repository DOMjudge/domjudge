<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ExternalSourceWarning;
use App\Service\EventLogService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class ExternalSourceWarningsFilterType extends AbstractType
{
    protected EventLogService $eventLog;

    public function __construct(EventLogService $eventLog)
    {
        $this->eventLog = $eventLog;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $endPoints = array_keys($this->eventLog->apiEndpoints);
        sort($endPoints);
        $builder->add("entity-type", ChoiceType::class, [
            "multiple" => true,
            "label"    => "Filter on entity type(s)",
            "required" => false,
            "choices"  => array_combine($endPoints, $endPoints),
            "attr"     => ["data-filter-field" => "entity-type"],
        ]);
        $types = [
            ExternalSourceWarning::readableType(ExternalSourceWarning::TYPE_UNSUPORTED_ACTION)       => ExternalSourceWarning::TYPE_UNSUPORTED_ACTION,
            ExternalSourceWarning::readableType(ExternalSourceWarning::TYPE_DATA_MISMATCH)           => ExternalSourceWarning::TYPE_DATA_MISMATCH,
            ExternalSourceWarning::readableType(ExternalSourceWarning::TYPE_DEPENDENCY_MISSING)      => ExternalSourceWarning::TYPE_DEPENDENCY_MISSING,
            ExternalSourceWarning::readableType(ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND)        => ExternalSourceWarning::TYPE_ENTITY_NOT_FOUND,
            ExternalSourceWarning::readableType(ExternalSourceWarning::TYPE_ENTITY_SHOULD_NOT_EXIST) => ExternalSourceWarning::TYPE_ENTITY_SHOULD_NOT_EXIST,
            ExternalSourceWarning::readableType(ExternalSourceWarning::TYPE_SUBMISSION_ERROR)        => ExternalSourceWarning::TYPE_SUBMISSION_ERROR,
        ];
        asort($types);
        $builder->add("type", ChoiceType::class, [
            "multiple" => true,
            "label"    => "Filter on warning type(s)",
            "required" => false,
            "choices"  => $types,
            "attr"     => ["data-filter-field" => "type"],
        ]);

        $builder->add("clear", ButtonType::class, [
            "label" => "Clear all filters",
            "attr"  => ["class" => "btn-secondary"],
        ]);
    }
}
