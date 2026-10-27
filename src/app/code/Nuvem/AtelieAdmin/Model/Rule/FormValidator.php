<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Rule;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;

class FormValidator
{
    public function __construct(
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function validate(
        array $input,
        ?int $ruleId = null
    ): array {
        $data = [];

        foreach ([
            'codigo',
            'tipo',
            'origem_componente_id',
            'alvo_tipo',
            'alvo_componente',
            'alvo_acabamento',
            'mensagem',
            'ativo',
        ] as $field) {
            $value = $input[$field] ?? '';

            if (!is_scalar($value) && $value !== null) {
                throw new LocalizedException(
                    __('O campo "%1" possui um formato inválido.', $field)
                );
            }

            $data[$field] = trim((string) $value);
        }

        if (
            $data['codigo'] === ''
            || mb_strlen($data['codigo'], 'UTF-8') > 16
        ) {
            throw new LocalizedException(
                __('Informe um código de até 16 caracteres.')
            );
        }

        if (!in_array($data['tipo'], [
            RuleInterface::TIPO_BLOQUEIA,
            RuleInterface::TIPO_EXIGE,
        ], true)) {
            throw new LocalizedException(
                __('Selecione um tipo de regra válido.')
            );
        }

        if (!in_array($data['alvo_tipo'], [
            RuleInterface::ALVO_TIPO_COMPONENTE,
            RuleInterface::ALVO_TIPO_ACABAMENTO,
            RuleInterface::ALVO_TIPO_GRAVACAO,
        ], true)) {
            throw new LocalizedException(
                __('Selecione um tipo de alvo válido.')
            );
        }

        $originId = filter_var(
            $data['origem_componente_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($originId === false) {
            throw new LocalizedException(
                __('Selecione um componente de origem válido.')
            );
        }

        try {
            $this->componentRepository->getById($originId);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(
                __('O componente de origem não existe.')
            );
        }

        $target = match ($data['alvo_tipo']) {
            RuleInterface::ALVO_TIPO_COMPONENTE =>
                $data['alvo_componente'],
            RuleInterface::ALVO_TIPO_ACABAMENTO =>
                $data['alvo_acabamento'],
            RuleInterface::ALVO_TIPO_GRAVACAO => '*',
        };

        if ($data['alvo_tipo'] !== RuleInterface::ALVO_TIPO_GRAVACAO) {
            if (
                $target === ''
                || mb_strlen($target, 'UTF-8') > 40
                || !preg_match('/\A[A-Za-z0-9_-]+\z/', $target)
            ) {
                throw new LocalizedException(
                    __('Selecione um alvo válido.')
                );
            }
        }

        if (
            $data['alvo_tipo'] === RuleInterface::ALVO_TIPO_COMPONENTE
        ) {
            try {
                $this->componentRepository->getByCodigo($target);
            } catch (NoSuchEntityException $exception) {
                throw new LocalizedException(
                    __('O componente alvo não existe.')
                );
            }
        }

        if (
            $data['alvo_tipo'] === RuleInterface::ALVO_TIPO_ACABAMENTO
        ) {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('acabamento', $target, 'eq')
                ->setPageSize(1)
                ->setCurrentPage(1)
                ->create();

            $result = $this->componentRepository->getList($criteria);

            if ($result->getTotalCount() === 0) {
                throw new LocalizedException(
                    __('O acabamento selecionado não existe.')
                );
            }
        }

        if (
            $data['mensagem'] === ''
            || mb_strlen($data['mensagem'], 'UTF-8') > 255
        ) {
            throw new LocalizedException(
                __('Informe uma mensagem de até 255 caracteres.')
            );
        }

        if (preg_match('/[<>]/', $data['mensagem'])) {
            throw new LocalizedException(
                __('A mensagem da regra não pode conter HTML.')
            );
        }

        if (!in_array($data['ativo'], ['0', '1'], true)) {
            throw new LocalizedException(
                __('Selecione Sim ou Não para o status da regra.')
            );
        }

        try {
            $existing = $this->ruleRepository->getByCodigo(
                $data['codigo']
            );
        } catch (NoSuchEntityException $exception) {
            $existing = null;
        }

        if (
            $existing !== null
            && $existing->getRegraId() !== $ruleId
        ) {
            throw new LocalizedException(
                __('Já existe uma regra com o código "%1".', $data['codigo'])
            );
        }

        return [
            'codigo' => $data['codigo'],
            'tipo' => $data['tipo'],
            'origem_componente_id' => $originId,
            'alvo_tipo' => $data['alvo_tipo'],
            'alvo' => $target,
            'mensagem' => $data['mensagem'],
            'ativo' => (int) $data['ativo'],
        ];
    }
}