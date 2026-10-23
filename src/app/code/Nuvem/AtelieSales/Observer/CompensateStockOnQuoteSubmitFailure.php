<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Plugin\QuoteManagement\CompensateFailedSubmission;

class CompensateStockOnQuoteSubmitFailure implements ObserverInterface
{
    /**
     * Share failure handling with the submit boundary.
     *
     * @param QuoteStockReservation $reservation
     * @param CompensateFailedSubmission $submission
     */
    public function __construct(
        private readonly QuoteStockReservation $reservation,
        private readonly CompensateFailedSubmission $submission
    ) {
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
        if ($quote === null || !$quote->getId()) {
            return;
        }
        $attemptId = $this->reservation->getAttemptId($quote);
        if ($attemptId !== null) {
            $this->submission->handleFailure($quote, $attemptId, $observer->getEvent()->getException());
        }
    }
}
