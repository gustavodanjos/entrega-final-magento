<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Api;

/**
 * Component stock service: reserve, compensate, return, adjust and availability.
 *
 * Every movement is recorded in the stock ledger, stock never goes negative
 * and quantities are never exposed to the storefront.
 *
 * @api
 */
interface ComponentStockInterface
{
    /**
     * Reserve units of a component for an order.
     *
     * On error nothing changes: a failed reservation does not touch stock or ledger.
     *
     * @param string $componentCode component code (e.g. "SOL-LUA-HOLO")
     * @param int $qty units to reserve, must be positive
     * @param int|null $orderId
     * @param int|null $orderItemId
     * @param int|null $quoteId quote the reservation belongs to, recorded in the ledger so a failed submission can be compensated
     * @return bool true when the reservation succeeded
     * @throws \Magento\Framework\Exception\LocalizedException when the component does not exist, the quantity is not positive or the available stock is insufficient
     */
    public function reserve(
        string $componentCode,
        int $qty,
        ?int $orderId = null,
        ?int $orderItemId = null,
        ?int $quoteId = null
    ): bool;

    /**
     * Release a reservation when the order fails after the reservation.
     *
     * On error nothing changes: stock is only released for real components with
     * a positive quantity.
     *
     * With a quote ID, compensation is capped to its outstanding ledger balance under a quote lock.
     * Without a quote ID, the caller is responsible for preventing duplicate compensation.
     *
     * @param string $componentCode component code (e.g. "SOL-LUA-HOLO")
     * @param int $qty units to release, must be positive
     * @param int|null $orderId
     * @param int|null $quoteId quote whose outstanding reservation will be released
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException when the component does not exist or the quantity is not positive
     */
    public function compensate(string $componentCode, int $qty, ?int $orderId = null, ?int $quoteId = null): void;

    /**
     * Give units back to stock after a cancellation or a refund.
     *
     * On error nothing changes: stock is only increased for real components
     * with a positive quantity.
     *
     * @param string $componentCode component code (e.g. "SOL-LUA-HOLO")
     * @param int $qty units to return, must be positive
     * @param int|null $orderId
     * @param int|null $orderItemId
     * @param string|null $note stable return document identity recorded in the ledger
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException when the component does not exist or the quantity is not positive
     */
    public function return(
        string $componentCode,
        int $qty,
        ?int $orderId = null,
        ?int $orderItemId = null,
        ?string $note = null
    ): void;

    /**
     * Set the stock of a component to an absolute quantity.
     *
     * On error nothing changes: the adjustment is refused as a whole.
     *
     * @param string $componentCode component code (e.g. "SOL-LUA-HOLO")
     * @param int $newQty new absolute quantity, cannot be negative
     * @param string $note reason recorded in the ledger
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException when the component does not exist or the quantity is negative
     */
    public function adjust(string $componentCode, int $newQty, string $note = ''): void;

    /**
     * Report which components of a product are available right now.
     *
     * Never exposes how many units exist: only availability and the last-units
     * flag (threshold comes from configuration).
     *
     * On error no availability is guessed: the call fails loudly so the studio
     * never shows an empty availability list for a real product.
     *
     * @param string $sku
     * @return array<int, array{component: string, available: bool, last_units: bool}>
     * @throws \Magento\Framework\Exception\NoSuchEntityException when the SKU does not exist or is not an atelie product
     */
    public function availability(string $sku): array;
}
