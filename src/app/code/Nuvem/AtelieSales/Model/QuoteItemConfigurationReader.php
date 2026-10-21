<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class QuoteItemConfigurationReader
{
    public const OPTION_CODE = Atelie::CUSTOM_OPTION_CONFIGURACAO;

    /**
     * @param Item $item
     * @return array<string, int>
     * @throws LocalizedException
     */
    public function getComponentQuantities(Item $item): array
    {
        if ($item->getProductType() !== Atelie::TYPE_CODE) {
            return [];
        }
        $option = $item->getOptionByCode(self::OPTION_CODE);
        try {
            $configuration = json_decode((string) $option?->getValue(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $this->invalidConfiguration($item);
        }
        if (!is_array($configuration)
            || !is_int($configuration['tamanho'] ?? null)
            || !array_key_exists('gravacao', $configuration)
            || ($configuration['gravacao'] !== null && !is_string($configuration['gravacao']))
            || !is_array($configuration['selecoes'] ?? null)
            || !array_is_list($configuration['selecoes'])
            || $configuration['selecoes'] === []
        ) {
            throw $this->invalidConfiguration($item);
        }
        $qty = (float) $item->getQty();
        if (!is_finite($qty) || $qty < 1 || floor($qty) !== $qty || $qty >= PHP_INT_MAX) {
            throw new LocalizedException(__('A quantidade do item "%1" deve ser um inteiro positivo.', $item->getSku()));
        }
        $demand = [];
        $groups = [];
        foreach ($configuration['selecoes'] as $selection) {
            if (!is_array($selection)) {
                throw $this->invalidConfiguration($item);
            }
            $group = $selection['grupo'] ?? null;
            $componentCode = $selection['componente'] ?? null;
            if (!is_string($group) || trim($group) === '' || isset($groups[$group])
                || !is_string($componentCode) || trim($componentCode) === ''
            ) {
                throw $this->invalidConfiguration($item);
            }
            $groups[$group] = true;
            $demand[$componentCode] = ($demand[$componentCode] ?? 0) + (int) $qty;
        }
        return $demand;
    }

    /**
     * @param Item $item
     * @return LocalizedException
     */
    private function invalidConfiguration(Item $item): LocalizedException
    {
        return new LocalizedException(
            __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
        );
    }
}
