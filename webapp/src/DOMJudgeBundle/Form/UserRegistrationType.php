<?php declare(strict_types=1);
namespace DOMJudgeBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

use DOMJudgeBundle\Validator\Constraints;

class UserRegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('username', TextType::class, [
            'error_bubbling' => true,
            'label' => 'Username',
            'constraints' => new Constraints\UserRegistration()
        ])
        ->add('plainPassword', RepeatedType::class, array(
            'type' => PasswordType::class,
            'error_bubbling' => true,
            'invalid_message' => 'The password fields must match.',
            'first_options'  => array('label' => 'Password'),
            'second_options' => array('label' => 'Repeat Password'),
            'mapped' => false,
        ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        'data_class' => 'DOMJudgeBundle\Entity\User',
      ));
    }
}
