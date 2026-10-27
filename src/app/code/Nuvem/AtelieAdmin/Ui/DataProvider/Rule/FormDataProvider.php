<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Ui\DataProvider\Rule;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;

class FormDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    private mixed $filteredId = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly RuleRepositoryInterface $ruleRepository,
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

        $ruleId = null;

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
                    __('O identificador da regra é inválido.')
                );
            }

            $ruleId = (int) $validatedId;
        }

        $values = [
            'regra_id' => '',
            'codigo' => '',
            'tipo' => RuleInterface::TIPO_BLOQUEIA,
            'origem_componente_id' => '',
            'alvo_tipo' => RuleInterface::ALVO_TIPO_COMPONENTE,
            'alvo_componente' => '',
            'alvo_acabamento' => '',
            'mensagem' => '',
            'ativo' => 1,
        ];

        if ($ruleId !== null) {
            $rule = $this->ruleRepository->getById($ruleId);

            $values = [
                'regra_id' => $rule->getRegraId(),
                'codigo' => $rule->getCodigo(),
                'tipo' => $rule->getTipo(),
                'origem_componente_id' => $rule->getOrigemComponenteId(),
                'alvo_tipo' => $rule->getAlvoTipo(),
                'alvo_componente' =>
                    $rule->getAlvoTipo() === RuleInterface::ALVO_TIPO_COMPONENTE
                        ? $rule->getAlvo()
                        : '',
                'alvo_acabamento' =>
                    $rule->getAlvoTipo() === RuleInterface::ALVO_TIPO_ACABAMENTO
                        ? $rule->getAlvo()
                        : '',
                'mensagem' => $rule->getMensagem(),
                'ativo' => (int) $rule->isAtivo(),
            ];
        }

        $persisted = $this->dataPersistor->get('nuvem_atelie_rule');

        if (is_array($persisted) && $persisted !== []) {
            $persistedId = $persisted['regra_id'] ?? null;

            $sameRule = $ruleId === null
                ? ($persistedId === null || $persistedId === '')
                : (
                    is_scalar($persistedId)
                    && (string) $persistedId === (string) $ruleId
                );

            if ($sameRule) {
                $values = array_replace(
                    $values,
                    array_intersect_key($persisted, $values)
                );
            }

            $this->dataPersistor->clear('nuvem_atelie_rule');
        }

        $this->loadedData = [
            $ruleId ?? '' => $values,
        ];

        return $this->loadedData;
    }

    public function count(): int
    {
        return count($this->getData());
    }
}