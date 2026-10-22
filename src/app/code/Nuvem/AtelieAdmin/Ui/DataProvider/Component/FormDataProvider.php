<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Ui\DataProvider\Component;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;

class FormDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    private mixed $filteredId = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly RequestInterface $request,
        private readonly DataPersistorInterface $dataPersistor,
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
        if (
            $filter->getField() !== $this->getPrimaryFieldName()
            || !in_array($filter->getConditionType(), [null, 'eq'], true)
        ) {
            throw new LocalizedException(
                __('O formulário recebeu um filtro não suportado.')
            );
        }

        $this->filteredId = $filter->getValue();
        $this->loadedData = null;
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $rawId = $this->filteredId
            ?? $this->request->getParam($this->getRequestFieldName());

        $componentId = null;

        if ($rawId !== null && $rawId !== '') {
            $validatedId = is_scalar($rawId)
                ? filter_var(
                    $rawId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                )
                : false;

            if ($validatedId === false) {
                throw new LocalizedException(
                    __('O identificador do componente é inválido.')
                );
            }

            $componentId = (int) $validatedId;
        }

        $values = [
            'componente_id' => '',
            'codigo' => '',
            'nome' => '',
            'grupo_id' => '',
            'cor_hex' => '#FFFFFF',
            'acabamento' => '',
            'preco_adicional' => '0.00',
            'ordem' => 0,
            'ativo' => 1,
            'padrao' => 0,
        ];

        if ($componentId !== null) {
            $component = $this->componentRepository->getById(
                $componentId
            );

            $values = [
                'componente_id' => $component->getComponenteId(),
                'codigo' => $component->getCodigo(),
                'nome' => $component->getNome(),
                'grupo_id' => $component->getGrupoId(),
                'cor_hex' => $component->getCorHex(),
                'acabamento' => $component->getAcabamento(),
                'preco_adicional' => $component->getPrecoAdicional(),
                'ordem' => $component->getOrdem(),
                'ativo' => (int) $component->isAtivo(),
                'padrao' => (int) $component->isPadrao(),
            ];
        }

        $persisted = $this->dataPersistor->get(
            'nuvem_atelie_component'
        );

        if (is_array($persisted) && $persisted !== []) {
            $persistedId = $persisted['componente_id'] ?? null;

            $sameComponent = $componentId === null
                ? ($persistedId === null || $persistedId === '')
                : (
                    is_scalar($persistedId)
                    && (string) $persistedId === (string) $componentId
                );

            if ($sameComponent) {
                $values = array_replace(
                    $values,
                    array_intersect_key($persisted, $values)
                );
            }

            $this->dataPersistor->clear('nuvem_atelie_component');
        }

        $this->loadedData = [
            $componentId ?? '' => $values,
        ];

        return $this->loadedData;
    }

    public function count(): int
    {
        return count($this->getData());
    }
}