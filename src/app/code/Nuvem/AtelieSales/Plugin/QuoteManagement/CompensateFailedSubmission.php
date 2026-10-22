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
     * submit_before runs outside core's failure handler, so a later observer can strand reservations.
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
        $this->reservation->clearAttempt($quote);
        try {
            return $proceed($quote, $orderData);
        } catch (\Throwable $exception) {
            if ($this->reservation->hasReservation($quote)) {
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
            throw $exception;
        } finally {
            $this->reservation->clearAttempt($quote);
        }
    }
}
