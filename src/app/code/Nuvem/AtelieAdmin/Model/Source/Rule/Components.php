<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Source\Rule;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\OptionSourceInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;

class Components implements OptionSourceInterface
{
    public function __construct(
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    public function toOptionArray(): array
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField('nome')
            ->setDirection(SortOrder::SORT_ASC)
            ->create();

        $criteria = $this->searchCriteriaBuilder
            ->addSortOrder($sortOrder)
            ->create();

        $components = $this->componentRepository
            ->getList($criteria)
            ->getItems();

        $options = [
            [
                'value' => '',
                'label' => __('Selecione um componente'),
            ],
        ];

        foreach ($components as $component) {
            $label = $component->getCodigo()
                . ' — '
                . $component->getNome();

            if (!$component->isAtivo()) {
                $label .= ' ' . (string) __('(inativo)');
            }

            $options[] = [
                'value' => $this->getOptionValue($component),
                'label' => $label,
            ];
        }

        return $options;
    }

    protected function getOptionValue(
        ComponentInterface $component
    ): string {
        return (string) $component->getComponenteId();
    }
}