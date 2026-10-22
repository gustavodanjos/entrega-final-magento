<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
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
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if ($quote === null || !$quote->getId() || !$this->reservation->hasReservation($quote)) {
            return;
        }
        $this->reservation->compensate($quote);
    }
}
