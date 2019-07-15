<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class PrintType extends AbstractType
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
        $languages       = $this->em->getRepository(Language::class)->findAll();
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
