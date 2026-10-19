<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api;

/**
 * Everything the studio needs about one SKU, in a single call.
 *
 * @api
 */
interface AtelieCatalogInterface
{
    /**
     * Get the product data, the groups with their components and the catalog rules of a SKU.
     *
     * Structure of the returned array:
     * - sku: string
     * - nome: string
     * - preco_base: float
     * - tamanho_min: int
     * - tamanho_max: int
     * - prazo_producao_dias: int
     * - gravacao: array{preco: float, limite: int}|null, null when the model does not allow engraving
     * - grupos: list of array{codigo: string, nome: string, ordem: int, camada_svg: string,
     *   componentes: list of array{codigo: string, nome: string, cor_hex: string, acabamento: string,
     *   preco_adicional: float, padrao: bool}}, ordered by ordem
     * - regras: list of array{codigo: string, tipo: string, origem: string, alvo_tipo: string,
     *   alvo: string, mensagem: string}
     *
     * On error the call does not return an empty array: it fails loudly, so the
     * caller never renders an empty studio.
     *
     * @param string $sku
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException when the SKU does not exist or is not an atelie product
     */
    public function getBySku(string $sku): array;
}
