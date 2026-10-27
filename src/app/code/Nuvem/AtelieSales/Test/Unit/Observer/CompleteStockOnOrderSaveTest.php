<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Observer\CompleteStockOnOrderSave;
use PHPUnit\Framework\TestCase;

class CompleteStockOnOrderSaveTest extends TestCase
{
    public function testOrderSaveUsesTheTokenCapturedBeforeReservation(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $order->setQuoteId(99)->setData(QuoteStockReservation::ORDER_ATTEMPT_ID, 'owned');
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->once())->method('completeOrder')->with(99, 'owned');
        (new CompleteStockOnOrderSave($reservation))->execute(new Observer([
            'event' => new Event(['order' => $order]),
        ]));
    }

    public function testOrdersWithoutAReservationTokenDoNotChangeOwnership(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $order->setQuoteId(99);
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->never())->method('completeOrder');
        (new CompleteStockOnOrderSave($reservation))->execute(new Observer([
            'event' => new Event(['order' => $order]),
        ]));
    }
}
