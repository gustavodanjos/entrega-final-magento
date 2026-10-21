<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Extracts component demand of an order item from stored product options.
 *
 * Items without atelie configuration are standard catalog products and consume nothing.
 * Corrupted atelie configurations fail loudly to prevent partial or untracked stock operations.
 */
class OrderItemConfigurationReader
{
    public const OPTION_CODE = 'atelie_configuracao';

    private const OPTION_CODE_ALT = 'configuracao';

    /**
     * Extract the selected atelie component codes for an order item.
     *
     * @param OrderItemInterface $item
     * @return string[] list of component codes
     * @throws LocalizedException when the atelie configuration is corrupted
     */
    public function getComponentCodes(OrderItemInterface $item): array
    {
        $configuration = $this->extractConfiguration($item);
        if ($configuration === null) {
            return [];
        }

        $selections = $configuration['selecoes'] ?? $configuration['selections'] ?? null;
        if (!is_array($selections)) {
            throw new LocalizedException(
                __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
            );
        }

        $codes = [];
        foreach ($selections as $selection) {
            $componentCode = $selection['componente'] ?? $selection['component'] ?? null;
            if (!is_string($componentCode) || $componentCode === '') {
                throw new LocalizedException(
                    __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
                );
            }
            $codes[] = $componentCode;
        }

        return $codes;
    }

    /**
     * Calculate component units demanded by the given order item quantity.
     *
     * @param OrderItemInterface $item
     * @param int|null $qty units of the configured product, defaults to qty_ordered
     * @return array<string, int> component code => units consumed
     * @throws LocalizedException when the configuration is corrupted
     */
    public function getComponentQuantities(OrderItemInterface $item, ?int $qty = null): array
    {
        $componentCodes = $this->getComponentCodes($item);
        if ($componentCodes === []) {
            return [];
        }

        $effectiveQty = $qty ?? (int) $item->getQtyOrdered();
        if ($effectiveQty < 1) {
            return [];
        }

        $quantities = [];
        foreach ($componentCodes as $code) {
            $quantities[$code] = ($quantities[$code] ?? 0) + $effectiveQty;
        }

        return $quantities;
    }

    /**
     * Resolve configuration array from order item product options.
     *
     * @param OrderItemInterface $item
     * @return array|null
     * @throws LocalizedException
     */
    private function extractConfiguration(OrderItemInterface $item): ?array
    {
        $options = $item->getProductOptions();
        if (!is_array($options)) {
            return null;
        }

        $raw = $options[self::OPTION_CODE] ?? $options[self::OPTION_CODE_ALT] ?? null;

        if ($raw === null && isset($options['info_buyRequest']) && is_array($options['info_buyRequest'])) {
            $buyRequest = $options['info_buyRequest'];
            $raw = $buyRequest['atelie'] ?? $buyRequest[self::OPTION_CODE] ?? null;
        }

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw)) {
            throw new LocalizedException(
                __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
            );
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LocalizedException(
                __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
            );
        }

        if (!is_array($decoded)) {
            throw new LocalizedException(
                __('A configuração do item "%1" está corrompida e não pôde ser lida.', $item->getSku())
            );
        }

        return $decoded;
    }
}
