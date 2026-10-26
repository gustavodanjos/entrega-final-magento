<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Import;

use Nuvem\Atelie\Api\Data\CatalogImportRowErrorInterface;

/**
 * Class ComponentRowValidator
 *
 * Validates individual rows from a component import file.
 */
class ComponentRowValidator
{
    /**
     * Validate a single row
     *
     * @param array<string, mixed> $row
     * @param array<string, int> $availableGroups Map of lowercase code/name/id to grupo_id
     * @param array<string, int> $seenCodes Map of code to line number where first seen
     * @return array{errors: CatalogImportRowErrorInterface[], data: array<string, mixed>|null}
     */
    public function validate(array $row, array $availableGroups, array $seenCodes): array
    {
        $lineNumber = (int) ($row['_line_number'] ?? 0);
        $code = trim((string) ($row['codigo'] ?? ''));
        $errors = [];

        // 1. Código
        if ($code === '') {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                null,
                'O código do componente é obrigatório.'
            );
        } elseif (mb_strlen($code, 'UTF-8') > 40) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code,
                'O código do componente deve ter no máximo 40 caracteres.'
            );
        } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $code)) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code,
                'O código do componente deve conter apenas letras, números, hífens ou sublinhados.'
            );
        } elseif (isset($seenCodes[$code])) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code,
                sprintf('Código duplicado no arquivo: "%s" já foi informado na linha %d.', $code, $seenCodes[$code])
            );
        }

        // 2. Grupo
        $groupRaw = trim((string) ($row['grupo'] ?? ''));
        $groupId = null;
        if ($groupRaw === '') {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                'O grupo do componente é obrigatório.'
            );
        } else {
            $groupKey = mb_strtolower($groupRaw, 'UTF-8');
            if (isset($availableGroups[$groupKey])) {
                $groupId = $availableGroups[$groupKey];
            } else {
                $errors[] = new CatalogImportRowError(
                    $lineNumber,
                    $code ?: null,
                    sprintf('O grupo "%s" não existe no catálogo.', $groupRaw)
                );
            }
        }

        // 3. Nome
        $name = trim((string) ($row['nome'] ?? ''));
        if ($name === '') {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                'O nome do componente é obrigatório.'
            );
        } elseif (mb_strlen($name, 'UTF-8') > 80) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                'O nome do componente deve ter no máximo 80 caracteres.'
            );
        }

        // 4. Cor Hexadecimal
        $corHex = trim((string) ($row['cor_hex'] ?? ''));
        if ($corHex === '') {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                'A cor hexadecimal é obrigatória.'
            );
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $corHex)) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                sprintf('A cor "%s" é inválida. O formato esperado é hexadecimal #RRGGBB.', $corHex)
            );
        }

        // 5. Acabamento
        $acabamento = trim((string) ($row['acabamento'] ?? ''));
        if ($acabamento === '') {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                'O acabamento é obrigatório.'
            );
        } elseif (mb_strlen($acabamento, 'UTF-8') > 32) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                'O acabamento deve ter no máximo 32 caracteres.'
            );
        }

        // 6. Preço Adicional
        $priceRaw = trim((string) ($row['preco_adicional'] ?? '0.00'));
        $priceNormalized = str_replace(['R$', ' ', '$'], '', $priceRaw);
        $priceNormalized = str_replace(',', '.', $priceNormalized);
        $price = null;
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/D', $priceNormalized) || (float) $priceNormalized > 99999999.9999) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                sprintf('O preço adicional "%s" é inválido. Informe um valor numérico não negativo.', $priceRaw)
            );
        } else {
            $price = (float) $priceNormalized;
        }

        // 7. Estoque
        $stockRaw = trim((string) ($row['estoque'] ?? '0'));
        $stock = filter_var($stockRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 2147483647]
        ]);
        if ($stock === false) {
            $errors[] = new CatalogImportRowError(
                $lineNumber,
                $code ?: null,
                sprintf('O estoque "%s" é inválido. Informe um número inteiro entre 0 e 2147483647.', $stockRaw)
            );
        }

        // 8. Padrão
        $padraoRaw = strtolower(trim((string) ($row['padrao'] ?? '0')));
        $padrao = in_array($padraoRaw, ['1', 'true', 'sim', 'yes'], true) ? 1 : 0;

        // 9. Ativo
        $ativoRaw = strtolower(trim((string) ($row['ativo'] ?? '1')));
        $ativo = in_array($ativoRaw, ['0', 'false', 'nao', 'não', 'no'], true) ? 0 : 1;

        // 10. Ordem
        $ordemRaw = isset($row['ordem']) && trim((string) $row['ordem']) !== '' ? trim((string) $row['ordem']) : null;
        $ordem = null;
        if ($ordemRaw !== null) {
            $validatedOrdem = filter_var($ordemRaw, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => -32768, 'max_range' => 32767]
            ]);
            if ($validatedOrdem === false) {
                $errors[] = new CatalogImportRowError(
                    $lineNumber,
                    $code ?: null,
                    sprintf('A ordem "%s" deve ser um inteiro entre -32768 e 32767.', $ordemRaw)
                );
            } else {
                $ordem = $validatedOrdem;
            }
        }

        if (!empty($errors)) {
            return [
                'errors' => $errors,
                'data' => null,
            ];
        }

        return [
            'errors' => [],
            'data' => [
                'line_number' => $lineNumber,
                'codigo' => $code,
                'grupo_id' => $groupId,
                'nome' => $name,
                'cor_hex' => strtoupper($corHex),
                'acabamento' => $acabamento,
                'preco_adicional' => $price,
                'estoque' => $stock,
                'padrao' => $padrao,
                'ativo' => $ativo,
                'ordem' => $ordem,
            ],
        ];
    }
}
