<?php

namespace DOMjudge\MainBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfirmType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('nevermind', SubmitType::class, array(
				'label' => 'Never mind...',
			))
			->add('yesimsure', SubmitType::class, array(
				'label' => 'Yes I\'m sure!',
			));
	}

}
