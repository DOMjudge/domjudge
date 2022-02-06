<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ICPCCmsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('contest_id', TextType::class, [
            'label' => 'Contest ID',
            'help' => 'Create a "Web Services Token" with appropriate rights in the "Export" section for your contest at <a
                            href="https://icpc.global/login" target="_blank">https://icpc.global/login</a>.
                    You can find the Contest ID (e.g. <code>Southwestern-Europe-2014</code>) in the URL.',
            'help_html' => true,
        ]);
        $builder->add('access_token', TextType::class);
        $builder->add('fetch_teams', SubmitType::class, ['label' => 'Import', 'icon' => 'fa-upload']);
        // $builder->add('upload_standings', SubmitType::class);
    }
}
