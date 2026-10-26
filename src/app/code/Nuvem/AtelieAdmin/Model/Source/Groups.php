<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Source;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Data\OptionSourceInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;

class Groups implements OptionSourceInterface
{
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    public function toOptionArray(): array
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField('ordem')
            ->setDirection(SortOrder::SORT_ASC)
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addSortOrder($sortOrder)
            ->create();

        $groups = $this->groupRepository
            ->getList($searchCriteria)
            ->getItems();

        $options = [
            [
                'value' => '',
                'label' => __('Selecione um grupo'),
            ],
        ];

        foreach ($groups as $group) {
            $label = (string) $group->getNome();

            if (!$group->isAtivo()) {
                $label .= ' ' . (string) __('(inativo)');
            }

            $options[] = [
                'value' => (string) $group->getGrupoId(),
                'label' => $label,
            ];
        }

        return $options;
    }
}