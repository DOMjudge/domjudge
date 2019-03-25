<?php declare(strict_types=1);

namespace DOMJudgeBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Entity\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class PrintType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $languages       = $this->entityManager->getRepository(Language::class)->findAll();
        $languageChoices = [];
        foreach ($languages as $language) {
            $languageChoices[$language->getName()] = $language->getLangid();
        }
        ksort($languageChoices, SORT_NATURAL | SORT_FLAG_CASE);
        $languageChoices = ['plain text' => ''] + $languageChoices;

        $builder
            ->add('code', BootstrapFileType::class, [
                'label' => 'Source file:',
                'attr' => [
                    'onchange' => 'detectLanguage(this.value)',
                ],
            ])
            ->add('langid', ChoiceType::class, [
                'label' => 'Language:',
                'required' => false,
                'choices' => $languageChoices,
            ])
            ->add('print', SubmitType::class, [
                'label' => 'Print code',
            ]);
    }
}
