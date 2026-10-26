<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

class CompensateStockOnQuoteSubmitFailure implements ObserverInterface
{
    /**
     * @param QuoteStockReservation $reservation
     */
    public function __construct(private readonly QuoteStockReservation $reservation)
    {
    }

    /**
     * Compensate only the invocation that received the core failure event.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();
        if ($quote === null || !$quote->getId() || !$order instanceof Order
            || (int) $order->getQuoteId() !== (int) $quote->getId()
        ) {
            return;
        }
        $attemptId = $order->getData(QuoteStockReservation::ORDER_ATTEMPT_ID);
        if (is_string($attemptId) && $attemptId !== '') {
            $this->reservation->handleFailure($quote, $attemptId, $observer->getEvent()->getException());
        }
    }
}
