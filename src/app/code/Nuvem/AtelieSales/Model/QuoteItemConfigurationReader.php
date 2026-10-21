<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;

/**
 * Class QuoteItemConfigurationReader
 *
 * Extracts the component demand of a quote item from the "atelie_configuracao"
 * custom option, written by the atelie product type in the format produced by
 * the configuration serializer. Items without the option are not atelie items
 * and consume nothing.
 *
 * A corrupted configuration aborts the order instead of being ignored: selling
 * without reserving would be worse than failing loudly.
 */
class QuoteItemConfigurationReader
{
    public const OPTION_CODE = 'atelie_configuracao';

    /**
     * Component demand of one quote item: component code => units consumed.
     *
     * @param Item $item
     * @return array<string, int>
     * @throws LocalizedException when the configuration of an atelie item cannot be read
     */
    public function getComponentQuantities(Item $item): array
    {
        $option = $item->getOptionByCode(self::OPTION_CODE);
        if ($option === null || $option->getValue() === null) {
            return [];
        }

        try {
            $configuration = json_decode((string) $option->getValue(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LocalizedException(
                __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
            );
        }

        if (!is_array($configuration) || !is_array($configuration['selecoes'] ?? null)) {
            throw new LocalizedException(
                __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
            );
        }

        $qty = (int) $item->getQty();
        if ($qty < 1) {
            return [];
        }

        $demand = [];
        foreach ($configuration['selecoes'] as $selection) {
            $componentCode = $selection['componente'] ?? null;
            if (!is_string($componentCode) || $componentCode === '') {
                throw new LocalizedException(
                    __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
                );
            }
            $demand[$componentCode] = ($demand[$componentCode] ?? 0) + $qty;
        }

        return $demand;
    }
}
