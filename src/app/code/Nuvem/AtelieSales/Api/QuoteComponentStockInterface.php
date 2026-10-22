<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Api;

/**
 * Stock movements owned by a quote, with compensation capped to its outstanding reservation.
 *
 * Implementing this contract enables quote checkout without changing ComponentStockInterface.
 *
 * @api
 */
interface QuoteComponentStockInterface
{
    /**
     * Reserve units and record the owning quote in the ledger under a quote lock.
     *
     * @param string $componentCode
     * @param int $qty
     * @param int $quoteId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function reserveForQuote(string $componentCode, int $qty, int $quoteId): bool;

    /**
     * Release at most the outstanding reservation of this quote under a quote lock.
     *
     * @param string $componentCode
     * @param int $qty
     * @param int $quoteId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function compensateForQuote(string $componentCode, int $qty, int $quoteId): void;
}
