<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\QuoteManagement;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

class CompensateFailedSubmission
{
    /**
     * @param QuoteStockReservation $reservation
     */
    public function __construct(private readonly QuoteStockReservation $reservation)
    {
    }

    /**
     * Core dispatches submit_before outside its failure handler, so later observers can strand reservations.
     *
     * @param QuoteManagement $subject
     * @param callable $proceed
     * @param Quote $quote
     * @param array $orderData
     * @return mixed
     * @throws \Throwable
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSubmit(QuoteManagement $subject, callable $proceed, Quote $quote, array $orderData = [])
    {
        $attemptId = $this->reservation->beginAttempt($quote);
        try {
            $result = $proceed($quote, $orderData);
            $this->reservation->completeAttempt($quote, $attemptId);
            return $result;
        } catch (\Throwable $exception) {
            $this->reservation->handleFailure($quote, $attemptId, $exception);
            throw $exception;
        } finally {
            $this->reservation->endAttempt($quote, $attemptId);
        }
    }
}
