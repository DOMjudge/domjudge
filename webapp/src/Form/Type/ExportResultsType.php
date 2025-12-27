<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\TeamCategory;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class ExportResultsType extends AbstractType
{
    public function __construct(protected readonly EntityManagerInterface $em) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TeamCategory[] $teamCategories */
        $teamCategories = $this->em->createQueryBuilder()
            ->from(TeamCategory::class, 'c', 'c.categoryid')
            ->select('c.sortorder, c.name')
            ->where('c.visible = 1')
            ->andWhere('BIT_AND(c.types, :scoring) = :scoring')
            ->setParameter('scoring', TeamCategory::TYPE_SCORING)
            ->orderBy('c.sortorder')
            ->getQuery()
            ->getResult();
        $sortOrders = [];
        foreach ($teamCategories as $teamCategory) {
            $sortOrder = $teamCategory['sortorder'];
            if (!array_key_exists($sortOrder, $sortOrders)) {
                $sortOrders[$sortOrder] = new stdClass();
                $sortOrders[$sortOrder]->sort_order = $sortOrder;
                $sortOrders[$sortOrder]->categories = [];
            }
            $sortOrders[$sortOrder]->categories[] = $teamCategory['name'];
        }

        $builder->add('sortorder', ChoiceType::class, [
            'choices' => $sortOrders,
            'group_by' => null,
            'choice_label' => fn(stdClass $sortOrder) => sprintf(
                '%d with %d %s',
                $sortOrder->sort_order,
                count($sortOrder->categories),
                count($sortOrder->categories) === 1 ? 'category' : 'categories',
            ),
            'choice_value' => 'sort_order',
            'choice_attr' => fn(stdClass $sortOrder) => [
                'data-categories' => json_encode($sortOrder->categories),
            ],
            'label' => 'Sort order',
            'help' => '[will be replaced by categories]',
        ]);
        $builder->add('individually_ranked', ChoiceType::class, [
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'label' => 'Individually ranked?',
        ]);
        $builder->add('honors', ChoiceType::class, [
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'label' => 'Honors?',
        ]);
        $builder->add('format', ChoiceType::class, [
            'choices' => [
                'HTML (display inline)' => 'html_inline',
                'HTML (download)' => 'html_download',
                'TSV' => 'tsv',
            ],
            'label' => 'Format',
        ]);
        $builder->add('export', SubmitType::class, ['icon' => 'fa-download']);
    }
}
