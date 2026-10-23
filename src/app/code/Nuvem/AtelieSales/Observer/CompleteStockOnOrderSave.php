<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

class CompleteStockOnOrderSave implements ObserverInterface
{
    /**
     * @param QuoteStockReservation $reservation
     */
    public function __construct(private readonly QuoteStockReservation $reservation)
    {
    }

    /**
     * The order and completed ownership must share the order resource transaction.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order || !$order->getQuoteId()) {
            return;
        }
        $attemptId = $order->getData(QuoteStockReservation::ORDER_ATTEMPT_ID);
        if (is_string($attemptId) && $attemptId !== '') {
            $this->reservation->completeOrder((int) $order->getQuoteId(), $attemptId);
        }
    }
}
