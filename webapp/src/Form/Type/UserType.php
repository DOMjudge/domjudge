<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * UserType constructor.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Team[] $teams */
        $teams = $this->em->createQueryBuilder()
            ->from(Team::class, 't', 't.teamid')
            ->select('t')
            ->getQuery()
            ->getResult();
        uasort($teams, function(Team $a, Team $b) {
            return $a->getEffectiveName() <=> $b->getEffectiveName();
        });

        $builder->add('username', TextType::class);
        $builder->add('name', TextType::class, [
            'label' => 'Full name',
        ]);
        $builder->add('email', EmailType::class, [
            'required' => false,
        ]);
        $builder->add('plainPassword', PasswordType::class, [
            'required' => false,
            'label' => 'Password',
        ]);
        $builder->add('ipAddress', TextType::class, [
            'required' => false,
            'label' => 'IP address',
        ]);
        $builder->add('enabled', ChoiceType::class, [
            'expanded' => true,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
        ]);
        $builder->add('team', ChoiceType::class, [
            'choice_label' => 'effective_name',
            'required' => false,
            'placeholder' => '-- no team --',
            'choices' => $teams,
        ]);
        $builder->add('user_roles', EntityType::class, [
            'label' => 'Roles',
            'class' => Role::class,
            'choice_label' => 'description',
            'required' => false,
            'multiple' => true,
            'expanded' => true,
        ]);
        $builder->add('save', SubmitType::class);

        // Remove ID field when doing an edit
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var User|null $user */
            $user = $event->getData();
            $form = $event->getForm();

            if ($user && $user->getUserid() !== null) {
                $form->remove('username');
            }

            $set = $user->getPassword() ? 'set' : 'not set';
            $form->add('plainPassword', PasswordType::class, [
                'required' => false,
                'label' => 'Password',
                'help' => sprintf('Currently %s - fill to change. Any current login session of the user will be terminated.', $set),
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
