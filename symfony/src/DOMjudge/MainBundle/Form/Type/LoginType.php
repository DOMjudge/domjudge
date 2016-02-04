<?php

namespace DOMjudge\MainBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginType extends AbstractType
{

	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('username', TextType::class, array(
				'mapped' => false,
				'label' => 'Username',
			))
			->add('password', PasswordType::class, array(
				'mapped' => false,
				'label' => 'Password',
			))
			->add('login', SubmitType::class, array(
				'label' => 'Login',
			));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		parent::configureOptions($resolver);
		$resolver->setDefaults(
			array(
				'csrf_token_id' => 'authenticate',
				'csrf_protection' => true,
			)
		);
	}
}
