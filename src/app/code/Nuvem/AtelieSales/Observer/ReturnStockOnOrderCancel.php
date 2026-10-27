<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Model\OrderItemStockReturner;

/**
 * Return reserved atelie component stock when an order is cancelled.
 */
class ReturnStockOnOrderCancel implements ObserverInterface
{
    /**
     * @param OrderItemStockReturner $stockReturner
     */
    public function __construct(
        private readonly OrderItemStockReturner $stockReturner
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order) {
            return;
        }

        $this->stockReturner->returnOrder($order);
    }
}
