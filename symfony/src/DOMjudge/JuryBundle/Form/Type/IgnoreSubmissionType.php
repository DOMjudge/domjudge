<?php

namespace DOMjudge\JuryBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IgnoreSubmissionType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('submission', HiddenType::class, array(
				'required' => true,
			))
			->add('ignore', HiddenType::class, array(
				'required' => true,
			))
			->add('submit', SubmitType::class, array(
				'label' => ($options['ignore'] ? '' : 'un') . 'IGNORE this submission',
			));
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		parent::configureOptions($resolver);
		$resolver->setRequired('ignore');
	}
}
