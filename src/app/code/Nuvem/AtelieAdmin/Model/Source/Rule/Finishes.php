<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Source\Rule;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;

class Finishes implements OptionSourceInterface
{
    public function __construct(
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function toOptionArray(): array
    {
        $criteria = $this->searchCriteriaBuilder->create();

        $components = $this->componentRepository
            ->getList($criteria)
            ->getItems();

        $finishes = [];

        foreach ($components as $component) {
            $finish = trim((string) $component->getAcabamento());

            if ($finish !== '') {
                $finishes[$finish] = $finish;
            }
        }

        natcasesort($finishes);

        $options = [
            [
                'value' => '',
                'label' => __('Selecione um acabamento'),
            ],
        ];

        foreach ($finishes as $finish) {
            $options[] = [
                'value' => $finish,
                'label' => $finish,
            ];
        }

        return $options;
    }
}