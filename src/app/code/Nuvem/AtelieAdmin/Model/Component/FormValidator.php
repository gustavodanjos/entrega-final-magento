<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Component;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;

class FormValidator
{
    public function __construct(
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly GroupRepositoryInterface $groupRepository
    ) {
    }

    public function validate(
        array $input,
        ?int $componentId = null
    ): array {
        $data = [];

        $fields = [
            'codigo',
            'nome',
            'grupo_id',
            'cor_hex',
            'acabamento',
            'preco_adicional',
            'ordem',
            'ativo',
            'padrao',
        ];

        foreach ($fields as $field) {
            $value = $input[$field] ?? '';

            if (!is_scalar($value) && $value !== null) {
                throw new LocalizedException(
                    __('O campo "%1" possui um formato inválido.', $field)
                );
            }

            $data[$field] = trim((string) $value);
        }

        $this->validateText($data['codigo'], 'Código', 40);
        $this->validateText($data['nome'], 'Nome', 80);
        $this->validateText($data['acabamento'], 'Acabamento', 32);

        if (!preg_match('/\A#[0-9a-fA-F]{6}\z/', $data['cor_hex'])) {
            throw new LocalizedException(
                __('Informe a cor no formato hexadecimal #RRGGBB.')
            );
        }

        $data['cor_hex'] = strtoupper($data['cor_hex']);

        /*
         * O banco aceita até 8 dígitos inteiros e 4 decimais.
         * Aceitamos ponto ou vírgula como separador decimal,
         * sem separador de milhares.
         */
        $price = str_replace(',', '.', $data['preco_adicional']);

        if (!preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,4})?\z/', $price)) {
            throw new LocalizedException(
                __(
                    'Informe um preço adicional não negativo, '
                    . 'com até 8 dígitos inteiros e 4 casas decimais.'
                )
            );
        }

        $data['preco_adicional'] = (float) $price;

        $groupId = filter_var(
            $data['grupo_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($groupId === false) {
            throw new LocalizedException(
                __('Selecione um grupo válido.')
            );
        }

        try {
            $this->groupRepository->getById($groupId);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(
                __('O grupo selecionado não existe.')
            );
        }

        $data['grupo_id'] = $groupId;

        $order = filter_var(
            $data['ordem'] === '' ? '0' : $data['ordem'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => -32768, 'max_range' => 32767]]
        );

        if ($order === false) {
            throw new LocalizedException(
                __('A ordem deve ser um inteiro entre -32768 e 32767.')
            );
        }

        $data['ordem'] = $order;

        foreach (['ativo', 'padrao'] as $field) {
            if (!in_array($data[$field], ['0', '1'], true)) {
                throw new LocalizedException(
                    __('Selecione Sim ou Não para o campo "%1".', $field)
                );
            }

            $data[$field] = (int) $data[$field];
        }

        $this->validateUniqueCode($data['codigo'], $componentId);

        return $data;
    }

    private function validateText(
        string $value,
        string $label,
        int $maxLength
    ): void {
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new LocalizedException(
                __(
                    'O campo "%1" é obrigatório e deve ter até %2 caracteres.',
                    __($label),
                    $maxLength
                )
            );
        }
    }

    private function validateUniqueCode(
        string $code,
        ?int $componentId
    ): void {
        try {
            $existing = $this->componentRepository->getByCodigo($code);
        } catch (NoSuchEntityException $exception) {
            return;
        }

        if ($existing->getComponenteId() !== $componentId) {
            throw new LocalizedException(
                __('Já existe um componente com o código "%1".', $code)
            );
        }
    }
}