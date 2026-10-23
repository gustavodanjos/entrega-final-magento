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
        $attemptId = $this->reservation->beginAttempt($quote);
        try {
            $result = $proceed($quote, $orderData);
            $this->reservation->completeAttempt($quote, $attemptId);
            return $result;
        } catch (\Throwable $exception) {
            $this->handleFailure($quote, $attemptId, $exception);
            throw $exception;
        } finally {
            $this->reservation->endAttempt($quote, $attemptId);
        }
    }

    /**
     * Persist failure separately so a failed compensation remains recoverable.
     *
     * @param Quote $quote
     * @param string $attemptId
     * @param \Throwable $exception
     * @return void
     */
    public function handleFailure(Quote $quote, string $attemptId, \Throwable $exception): void
    {
        try {
            $this->reservation->markFailed($quote, $attemptId);
            $this->reservation->compensate($quote, $attemptId);
        } catch (\Throwable $compensationException) {
            try {
                $this->logger->critical('Failed to compensate component stock.', [
                    'quote_id' => $quote->getId(),
                    'attempt_id' => $attemptId,
                    'exception' => $compensationException,
                    'submission_exception' => $exception,
                ]);
            } catch (\Throwable) {
                // Logging failures must not replace the checkout exception.
                return;
            }
        }
    }
}
