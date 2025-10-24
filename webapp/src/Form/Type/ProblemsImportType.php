<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ProblemsImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('contest', EntityType::class, [
            'class' => Contest::class,
            'required' => true,
            'choice_value' => 'externalid',
            'choice_label' => fn(Contest $contest) => sprintf(
                '%s: %s - %s', $contest->getExternalid(), $contest->getShortname(), $contest->getName()
            ),
        ]);
        $builder->add('file', FileType::class, [
            'required' => true,
        ]);
        $builder->add('import', SubmitType::class, ['icon' => 'fa-upload']);
    }
}
