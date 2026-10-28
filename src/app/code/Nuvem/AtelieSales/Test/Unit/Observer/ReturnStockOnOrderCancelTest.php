<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Model\OrderItemStockReturner;
use Nuvem\AtelieSales\Observer\ReturnStockOnOrderCancel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReturnStockOnOrderCancelTest extends TestCase
{
    /**
     * @var OrderItemStockReturner|MockObject
     */
    private MockObject $stockReturnerMock;

    private ReturnStockOnOrderCancel $observer;

    protected function setUp(): void
    {
        $this->stockReturnerMock = $this->createMock(OrderItemStockReturner::class);
        $this->observer = new ReturnStockOnOrderCancel($this->stockReturnerMock);
    }

    public function testDelegatesOrderReturnToStockReturner(): void
    {
        $orderMock = $this->createMock(Order::class);

        $observer = new Observer([
            'event' => new Event(['order' => $orderMock]),
        ]);

        $this->stockReturnerMock->expects($this->once())
            ->method('returnOrder')
            ->with($orderMock);

        $this->observer->execute($observer);
    }

    public function testDoesNothingWhenOrderIsNotPresent(): void
    {
        $observer = new Observer([
            'event' => new Event([]),
        ]);

        $this->stockReturnerMock->expects($this->never())->method('returnOrder');

        $this->observer->execute($observer);
    }
}
