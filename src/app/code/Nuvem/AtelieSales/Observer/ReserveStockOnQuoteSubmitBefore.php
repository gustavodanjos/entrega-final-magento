<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

class ReserveStockOnQuoteSubmitBefore implements ObserverInterface
{
    /**
     * @param QuoteStockReservation $reservation
     */
    public function __construct(private readonly QuoteStockReservation $reservation)
    {
    }

    /**
     * Require ownership acquired at the submit boundary before reserving.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if ($quote === null || !$quote->getId()) {
            return;
        }
        $attemptId = $this->reservation->getAttemptId($quote);
        if ($attemptId === null) {
            throw new \Magento\Framework\Exception\LocalizedException(__('A tentativa de checkout não foi iniciada.'));
        }
        $order = $observer->getEvent()->getOrder();
        if ($order instanceof Order) {
            $order->setData(QuoteStockReservation::ORDER_ATTEMPT_ID, $attemptId);
        }
        $this->reservation->reserve($quote, $attemptId);
    }
}
