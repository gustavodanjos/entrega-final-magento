<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Creditmemo;
use Nuvem\AtelieSales\Model\OrderItemStockReturner;
use Nuvem\AtelieSales\Observer\ReturnStockOnCreditmemoRefund;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReturnStockOnCreditmemoRefundTest extends TestCase
{
    /**
     * @var OrderItemStockReturner|MockObject
     */
    private MockObject $stockReturnerMock;

    private ReturnStockOnCreditmemoRefund $observer;

    protected function setUp(): void
    {
        $this->stockReturnerMock = $this->createMock(OrderItemStockReturner::class);
        $this->observer = new ReturnStockOnCreditmemoRefund($this->stockReturnerMock);
    }

    public function testDelegatesCreditmemoReturnToStockReturner(): void
    {
        $creditmemoMock = $this->createMock(Creditmemo::class);

        $observer = new Observer([
            'event' => new Event(['creditmemo' => $creditmemoMock]),
        ]);

        $this->stockReturnerMock->expects($this->once())
            ->method('returnCreditmemo')
            ->with($creditmemoMock);

        $this->observer->execute($observer);
    }

    public function testDoesNothingWhenCreditmemoIsNotPresent(): void
    {
        $observer = new Observer([
            'event' => new Event([]),
        ]);

        $this->stockReturnerMock->expects($this->never())->method('returnCreditmemo');

        $this->observer->execute($observer);
    }
}
