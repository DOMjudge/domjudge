<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contest;
use App\Entity\JudgehostRestriction;
use App\Entity\Language;
use App\Entity\Problem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class JudgehostRestrictionType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'constraints' => [new NotBlank()],
        ]);

        // Note that we can not use the normal EntityType form type, because these are not Doctrine associations

        $contests       = $this->em->getRepository(Contest::class)->findAll();
        $contestChoices = [];
        foreach ($contests as $contest) {
            $contestChoices[$contest->getName()] = $contest->getCid();
        }
        $builder->add('contests', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => $contestChoices,
        ]);

        $problems       = $this->em->getRepository(Problem::class)->findAll();
        $problemChoices = [];
        foreach ($problems as $problem) {
            $problemChoices[$problem->getName()] = $problem->getProbid();
        }
        $builder->add('problems', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => $problemChoices,
        ]);

        $languages       = $this->em->getRepository(Language::class)->findAll();
        $languageChoices = [];
        foreach ($languages as $language) {
            $languageChoices[$language->getName()] = $language->getLangid();
        }
        $builder->add('languages', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => $languageChoices,
        ]);
        $builder->add('rejudge_own', ChoiceType::class, [
            'expanded' => true,
            'choices' => ['Yes' => true, 'No' => false],
            'label' => 'Allow rejudge on same judgehost',
        ]);
        $builder->add('save', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => JudgehostRestriction::class]);
    }
}
