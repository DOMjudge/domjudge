<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ContestExportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('contest', EntityType::class, [
            'class' => Contest::class,
            'choice_label' => fn(Contest $contest) => sprintf(
                'c%d: %s - %s', $contest->getCid(), $contest->getShortname(), $contest->getName()
            ),
        ]);
        $builder->add('export', SubmitType::class, ['icon' => 'fa-download']);
    }
}
