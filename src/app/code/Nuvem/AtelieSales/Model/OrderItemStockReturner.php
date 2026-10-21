<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;

/**
 * Handles idempotent return of atelie components to stock on cancellation or refund.
 *
 * Uses the stock ledger (nuvem_atelie_movimentacao) as memory to guarantee that no
 * order item can ever return more component units than originally ordered.
 */
class OrderItemStockReturner
{
    /**
     * @param ComponentStockInterface $componentStock
     * @param Movimentacao $movimentacaoResource
     * @param OrderItemConfigurationReader $configurationReader
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ComponentStockInterface $componentStock,
        private readonly Movimentacao $movimentacaoResource,
        private readonly OrderItemConfigurationReader $configurationReader,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Return remaining unreturned components of an order when it is cancelled.
     *
     * @param OrderInterface $order
     * @return void
     * @throws Exception
     */
    public function returnOrder(OrderInterface $order): void
    {
        $items = method_exists($order, 'getAllItems') ? $order->getAllItems() : $order->getItems();
        if (empty($items)) {
            return;
        }

        $orderId = $order->getEntityId() !== null ? (int) $order->getEntityId() : null;
        $connection = $this->resourceConnection->getConnection();

        $connection->beginTransaction();
        try {
            foreach ($items as $item) {
                $this->processOrderItemReturn($item, null, $orderId);
            }
            $connection->commit();
        } catch (Exception $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * Return components for items marked "back to stock" in a creditmemo refund.
     *
     * @param CreditmemoInterface $creditmemo
     * @return void
     * @throws Exception
     */
    public function returnCreditmemo(CreditmemoInterface $creditmemo): void
    {
        $items = method_exists($creditmemo, 'getAllItems') ? $creditmemo->getAllItems() : $creditmemo->getItems();
        if (empty($items)) {
            return;
        }

        $orderId = $creditmemo->getOrderId() !== null ? (int) $creditmemo->getOrderId() : null;
        $connection = $this->resourceConnection->getConnection();

        $connection->beginTransaction();
        try {
            foreach ($items as $creditmemoItem) {
                if (!$this->isBackToStock($creditmemoItem)) {
                    continue;
                }

                $orderItem = method_exists($creditmemoItem, 'getOrderItem')
                    ? $creditmemoItem->getOrderItem()
                    : null;

                if ($orderItem === null && method_exists($creditmemo, 'getOrder') && $creditmemo->getOrder() !== null) {
                    $orderItem = $creditmemo->getOrder()->getItemById($creditmemoItem->getOrderItemId());
                }

                if ($orderItem === null) {
                    continue;
                }

                $qtyToRefund = (int) $creditmemoItem->getQty();
                if ($qtyToRefund <= 0) {
                    continue;
                }

                $this->processOrderItemReturn($orderItem, $qtyToRefund, $orderId);
            }
            $connection->commit();
        } catch (Exception $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * Return components for a single order item with ledger-based idempotency.
     *
     * @param OrderItemInterface $orderItem
     * @param int|null $requestedQty units to return (null means all remaining from qty_ordered)
     * @param int|null $orderId
     * @return void
     * @throws LocalizedException
     */
    private function processOrderItemReturn(
        OrderItemInterface $orderItem,
        ?int $requestedQty,
        ?int $orderId
    ): void {
        $orderItemId = $orderItem->getItemId() !== null ? (int) $orderItem->getItemId() : null;
        if ($orderItemId === null) {
            return;
        }

        $componentCodes = $this->configurationReader->getComponentCodes($orderItem);
        if ($componentCodes === []) {
            return;
        }

        $qtyOrdered = (int) $orderItem->getQtyOrdered();
        if ($qtyOrdered <= 0) {
            return;
        }

        $returnedQuantities = $this->movimentacaoResource->getReturnedQuantitiesByOrderItemId($orderItemId);
        $resolvedOrderId = $orderId ?? ($orderItem->getOrderId() !== null ? (int) $orderItem->getOrderId() : null);

        foreach ($componentCodes as $code) {
            $alreadyReturned = $returnedQuantities[$code] ?? 0;
            $maxAvailableToReturn = max(0, $qtyOrdered - $alreadyReturned);

            if ($maxAvailableToReturn <= 0) {
                continue;
            }

            $unitsToReturn = $requestedQty !== null
                ? min($requestedQty, $maxAvailableToReturn)
                : $maxAvailableToReturn;

            if ($unitsToReturn <= 0) {
                continue;
            }

            $this->componentStock->return($code, $unitsToReturn, $resolvedOrderId, $orderItemId);
        }
    }

    /**
     * Check whether a creditmemo item is flagged to return to stock.
     *
     * @param CreditmemoItemInterface $creditmemoItem
     * @return bool
     */
    private function isBackToStock(CreditmemoItemInterface $creditmemoItem): bool
    {
        if (method_exists($creditmemoItem, 'getBackToStock')) {
            return (bool) $creditmemoItem->getBackToStock();
        }

        if ($creditmemoItem instanceof \Magento\Framework\DataObject) {
            return (bool) $creditmemoItem->getData('back_to_stock');
        }

        return false;
    }
}
