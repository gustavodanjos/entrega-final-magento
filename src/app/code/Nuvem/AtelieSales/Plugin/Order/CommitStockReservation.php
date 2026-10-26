<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\OrderService;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

class CommitStockReservation
{
    /**
     * @param QuoteStockReservation $reservation
     */
    public function __construct(private readonly QuoteStockReservation $reservation)
    {
    }

    /**
     * @param OrderService $subject
     * @param callable $proceed
     * @param OrderInterface $order
     * @return OrderInterface
     * @throws \Throwable
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundPlace(OrderService $subject, callable $proceed, OrderInterface $order): OrderInterface
    {
        $attemptId = $order instanceof Order ? $order->getData(QuoteStockReservation::ORDER_ATTEMPT_ID) : null;
        if (!is_string($attemptId) || $attemptId === '') {
            return $proceed($order);
        }
        try {
            $result = $proceed($order);
            $this->reservation->commitSubmission((int) $order->getQuoteId(), $attemptId);
            return $result;
        } catch (\Throwable $exception) {
            $this->reservation->rollbackSubmission($attemptId);
            throw $exception;
        }
    }
}
