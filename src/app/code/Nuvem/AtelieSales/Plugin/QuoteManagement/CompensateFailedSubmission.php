<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\QuoteManagement;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Psr\Log\LoggerInterface;

class CompensateFailedSubmission
{
    /**
     * @param QuoteStockReservation $reservation
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly QuoteStockReservation $reservation,
        private readonly LoggerInterface $logger
    ) {
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
        $this->reservation->beginAttempt($quote);
        try {
            $this->recoverPendingReservation($quote);
            try {
                $result = $proceed($quote, $orderData);
            } catch (\Throwable $exception) {
                $this->compensateFailedSubmission($quote, $exception);
                throw $exception;
            }

            $this->reservation->clearAttempt($quote);
            return $result;
        } finally {
            $this->reservation->endAttempt($quote);
        }
    }

    /**
     * Pending compensation must finish before another submission reserves stock.
     *
     * @param Quote $quote
     * @return void
     * @throws \Throwable
     */
    private function recoverPendingReservation(Quote $quote): void
    {
        if (!$this->reservation->hasReservation($quote)) {
            return;
        }
        try {
            $this->reservation->compensate($quote);
        } catch (\Throwable $exception) {
            $this->logger->critical('Failed to recover pending component stock reservation.', [
                'quote_id' => $quote->getId(),
                'exception' => $exception,
            ]);
            throw $exception;
        }
    }

    /**
     * Preserve the submission exception when stock recovery also fails.
     *
     * @param Quote $quote
     * @param \Throwable $exception
     * @return void
     */
    private function compensateFailedSubmission(Quote $quote, \Throwable $exception): void
    {
        if (!$this->reservation->hasReservation($quote)) {
            return;
        }
        try {
            $this->reservation->compensate($quote);
        } catch (\Throwable $compensationException) {
            $this->logger->critical('Failed to compensate component stock.', [
                'quote_id' => $quote->getId(),
                'exception' => $compensationException,
                'submission_exception' => $exception,
            ]);
        }
    }
}
