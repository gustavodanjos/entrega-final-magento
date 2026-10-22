<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Ui\DataProvider\Rule;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Nuvem\Atelie\Api\RuleRepositoryInterface;

class ListingDataProvider extends AbstractDataProvider
{
    private const FIELDS = [
        'regra_id',
        'codigo',
        'tipo',
        'origem_componente_id',
        'alvo_tipo',
        'alvo',
        'mensagem',
        'ativo',
    ];

    private array $filters = [];
    private array $orders = [];
    private int $currentPage = 1;
    private int $pageSize = 20;
    private ?array $loadedData = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );
    }

    public function addFilter(Filter $filter)
    {
        $this->validateField((string) $filter->getField());
        $this->filters[] = $filter;
        $this->loadedData = null;
    }

    public function addOrder($field, $direction)
    {
        $this->validateField((string) $field);

        $this->orders[$field] =
            strtoupper((string) $direction) === SortOrder::SORT_DESC
                ? SortOrder::SORT_DESC
                : SortOrder::SORT_ASC;

        $this->loadedData = null;
    }

    public function setLimit($offset, $size)
    {
        $this->currentPage = max(1, (int) $offset);
        $this->pageSize = max(1, min(200, (int) $size));
        $this->loadedData = null;
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        foreach ($this->filters as $filter) {
            $this->searchCriteriaBuilder->addFilters([$filter]);
        }

        $orders = $this->orders ?: [
            'regra_id' => SortOrder::SORT_DESC,
        ];

        foreach ($orders as $field => $direction) {
            $sortOrder = $this->sortOrderBuilder
                ->setField($field)
                ->setDirection($direction)
                ->create();

            $this->searchCriteriaBuilder->addSortOrder($sortOrder);
        }

        $criteria = $this->searchCriteriaBuilder
            ->setCurrentPage($this->currentPage)
            ->setPageSize($this->pageSize)
            ->create();

        $result = $this->ruleRepository->getList($criteria);
        $items = [];

        foreach ($result->getItems() as $rule) {
            $items[] = [
                'regra_id' => $rule->getRegraId(),
                'codigo' => $rule->getCodigo(),
                'tipo' => $rule->getTipo(),
                'origem_componente_id' => $rule->getOrigemComponenteId(),
                'alvo_tipo' => $rule->getAlvoTipo(),
                'alvo' => $rule->getAlvo(),
                'mensagem' => $rule->getMensagem(),
                'ativo' => (int) $rule->isAtivo(),
            ];
        }

        $this->loadedData = [
            'items' => $items,
            'totalRecords' => (int) $result->getTotalCount(),
        ];

        return $this->loadedData;
    }

    public function count(): int
    {
        return $this->getData()['totalRecords'];
    }

    private function validateField(string $field): void
    {
        if (!in_array($field, self::FIELDS, true)) {
            throw new LocalizedException(
                __('O campo de consulta informado é inválido.')
            );
        }
    }
}