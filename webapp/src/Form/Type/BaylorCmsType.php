<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class BaylorCmsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('contest_id', TextType::class, [
            'label' => 'Contest ID',
        ]);
        $builder->add('access_token', TextType::class);
        $builder->add('fetch_teams', SubmitType::class);
        $builder->add('upload_standings', SubmitType::class);
    }
}
