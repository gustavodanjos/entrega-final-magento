<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Observer\ReserveStockOnQuoteSubmitBefore;
use PHPUnit\Framework\TestCase;

class ReserveStockOnQuoteSubmitBeforeTest extends TestCase
{
    public function testMissingQuoteDoesNotMoveStock(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->never())->method("reserve");
        (new ReserveStockOnQuoteSubmitBefore($reservation))->execute(new Observer(['event' => new Event()]));
    }

    public function testSubmitsTheEventQuoteToTheReservationService(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(99);
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('getAttemptId')->willReturn('owned');
        $reservation->expects($this->once())->method("reserve")->with($quote, 'owned');
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        (new ReserveStockOnQuoteSubmitBefore($reservation))->execute(new Observer([
            'event' => new Event(['quote' => $quote, 'order' => $order]),
        ]));
        $this->assertSame('owned', $order->getData(QuoteStockReservation::ORDER_ATTEMPT_ID));
    }
}
