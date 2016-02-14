<?php

namespace DOMjudge\JuryBundle\Form\Type;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('name', TextType::class, array(
				'label' => 'Team name',
				'required' => true,
			))
			->add('category', EntityType::class, array(
				'class' => 'DOMjudge\MainBundle\Entity\TeamCategory',
				'choice_label' => 'name',
			))
			->add('members', TextareaType::class, array(
				'label' => 'Members',
				'required' => false,
				'attr' => array(
					'rows' => 3,
					'cols' => 40,
				),
			))
			->add('affiliation', EntityType::class, array(
				'class' => 'DOMjudge\MainBundle\Entity\TeamAffiliation',
				'choice_label' => 'name',
				'required' => false,
				'placeholder' => '- None',
			))
			->add('penalty', IntegerType::class, array(
				'label' => 'Penalty time',
			))
			->add('room', TextType::class, array(
				'label' => 'Location',
				'required' => false,
			))
			->add('comments', TextareaType::class, array(
				'label' => 'Comments',
				'required' => false,
				'attr' => array(
					'rows' => 10,
					'cols' => 40,
				),
			))
			->add('enabled', ChoiceType::class, array(
				'choices' => array(
					'yes' => true,
					'no' => false,
				),
				'choices_as_values' => true,
				'expanded' => true,
				'required' => false,
			))
			->add('save', SubmitType::class, array(
				'label' => 'Save',
			));
		
		// TODO: subform for user create, based on an option which can be set using configureOptions
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(
			array(
				'data_class' => 'DOMJudge\MainBundle\Entity\Team',
			)
		);
	}

}
