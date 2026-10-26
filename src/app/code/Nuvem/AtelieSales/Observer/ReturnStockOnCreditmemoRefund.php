<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Nuvem\AtelieSales\Model\OrderItemStockReturner;

/**
 * Return atelie component stock for items marked back to stock in a creditmemo.
 */
class ReturnStockOnCreditmemoRefund implements ObserverInterface
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
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo instanceof Creditmemo) {
            return;
        }

        $this->stockReturner->returnCreditmemo($creditmemo);
    }
}
