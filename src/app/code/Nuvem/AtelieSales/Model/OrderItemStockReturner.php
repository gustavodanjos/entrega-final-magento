<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;

class OrderItemStockReturner
{
    /**
     * @param ComponentStockInterface $componentStock
     * @param Movimentacao $stockLedger
     * @param OrderItemConfigurationReader $configurationReader
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ComponentStockInterface $componentStock,
        private readonly Movimentacao $stockLedger,
        private readonly OrderItemConfigurationReader $configurationReader,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Called within order persistence so cancellation and stock share the transaction.
     *
     * @param Order $order
     * @return void
     */
    public function returnOrder(Order $order): void
    {
        $returns = [];
        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() !== Atelie::TYPE_CODE) {
                continue;
            }
            $quantity = $this->quantity($item->getQtyCanceled());
            if ($quantity > 0) {
                $returns[] = ['item' => $item, 'qty' => $quantity];
            }
        }
        $orderId = (int) $order->getEntityId();
        $this->returnItems($orderId, $returns, 'cancel:' . $orderId);
    }

    /**
     * @param Creditmemo $creditmemo
     * @return void
     */
    public function returnCreditmemo(Creditmemo $creditmemo): void
    {
        if (!$creditmemo->getEntityId() || (int) $creditmemo->getState() !== Creditmemo::STATE_REFUNDED) {
            return;
        }
        $returns = [];
        foreach ($creditmemo->getAllItems() as $item) {
            if (!$item->getBackToStock()) {
                continue;
            }
            $orderItem = $item->getOrderItem();
            if ($orderItem->getProductType() !== Atelie::TYPE_CODE) {
                continue;
            }
            $quantity = $this->quantity($item->getQty());
            if ($quantity > 0) {
                $returns[] = ['item' => $orderItem, 'qty' => $quantity];
            }
        }
        $this->returnItems(
            (int) $creditmemo->getOrderId(),
            $returns,
            'creditmemo:' . $creditmemo->getEntityId()
        );
    }

    /**
     * @param int $orderId
     * @param array<int, array{item: OrderItemInterface, qty: int}> $returns
     * @param string $note
     * @return void
     */
    private function returnItems(int $orderId, array $returns, string $note): void
    {
        if ($orderId <= 0 || $returns === []) {
            return;
        }
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $this->stockLedger->lockOrder($orderId);
            foreach ($returns as $return) {
                $this->returnItem($return['item'], $return['qty'], $orderId, $note);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @param OrderItemInterface $item
     * @param int $requestedQuantity cumulative quantity for this return document
     * @param int $orderId
     * @param string $note
     * @return void
     */
    private function returnItem(OrderItemInterface $item, int $requestedQuantity, int $orderId, string $note): void
    {
        $itemId = (int) $item->getItemId();
        $componentQuantities = $this->configurationReader->getComponentQuantities($item, 1);
        if ($itemId <= 0 || $componentQuantities === []) {
            return;
        }
        $orderedQuantity = $this->quantity($item->getQtyOrdered());
        $returned = $this->stockLedger->getReturnedQuantitiesByOrderItemId($itemId, null, $orderId);
        $returnedForDocument = $this->stockLedger->getReturnedQuantitiesByOrderItemId($itemId, $note, $orderId);
        ksort($componentQuantities);
        foreach ($componentQuantities as $code => $unitsPerItem) {
            $remaining = $orderedQuantity * $unitsPerItem - ($returned[$code] ?? 0);
            $requested = $requestedQuantity * $unitsPerItem - ($returnedForDocument[$code] ?? 0);
            $quantity = min($remaining, $requested);
            if ($quantity > 0) {
                $this->componentStock->return($code, $quantity, $orderId, $itemId, $note);
            }
        }
    }

    /**
     * @param mixed $value
     * @return int
     * @throws LocalizedException
     */
    private function quantity(mixed $value): int
    {
        $quantity = (float) $value;
        if (!is_finite($quantity) || $quantity < 0 || floor($quantity) !== $quantity || $quantity >= PHP_INT_MAX) {
            throw new LocalizedException(__('A quantidade de componentes a devolver deve ser um inteiro não negativo.'));
        }
        return (int) $quantity;
    }
}
