<?php

namespace DOMjudge\JuryBundle\Resources\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubmissionsFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$filters = array('newest', 'unverified', 'unjudged', 'all');
		foreach ($filters as $filter) {
			$builder
				->add($filter, SubmitType::class, array(
					'disabled' => $options['currently_disabled'] == $filter,
				));
		}
		
		$builder->setMethod('GET');
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		parent::configureOptions($resolver);
		$resolver
			->setDefaults(
				array(
					'csrf_protection' => false,
				)
			)
			->setRequired('currently_disabled');
	}
}
