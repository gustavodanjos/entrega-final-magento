<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Observer\CompensateStockOnQuoteSubmitFailure;
use PHPUnit\Framework\TestCase;
use Magento\Sales\Model\Order;

class CompensateStockOnQuoteSubmitFailureTest extends TestCase
{
    public function testMissingQuoteDoesNotMoveStock(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->never())->method('handleFailure');
        (new CompensateStockOnQuoteSubmitFailure($reservation))->execute(new Observer(['event' => new Event()]));
    }

    public function testFailureUsesOriginalOrderTokenEvenWhenTheQuoteIsReused(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(99);
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->never())->method('getAttemptId');
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $order->setQuoteId(99)->setData(QuoteStockReservation::ORDER_ATTEMPT_ID, 'old-owner');
        $failure = new \RuntimeException('checkout failed');
        $reservation->expects($this->once())->method('handleFailure')->with($quote, 'old-owner', $failure);
        (new CompensateStockOnQuoteSubmitFailure($reservation))->execute(new Observer([
            'event' => new Event(['quote' => $quote, 'order' => $order, 'exception' => $failure]),
        ]));
    }
}
