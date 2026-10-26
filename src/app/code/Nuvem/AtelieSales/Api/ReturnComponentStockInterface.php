<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Api;

/**
 * Component returns with a stable document identity for cancellation and refund reconciliation.
 *
 * @api
 */
interface ReturnComponentStockInterface
{
    /**
     * Return units and record the originating document in the stock ledger.
     *
     * @param string $componentCode
     * @param int $qty
     * @param int|null $orderId
     * @param int|null $orderItemId
     * @param string|null $note stable return document identity
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function return(
        string $componentCode,
        int $qty,
        ?int $orderId = null,
        ?int $orderItemId = null,
        ?string $note = null
    ): void;
}
