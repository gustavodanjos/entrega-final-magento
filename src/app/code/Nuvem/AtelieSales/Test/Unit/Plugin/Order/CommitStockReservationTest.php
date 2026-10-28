<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Order;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\OrderService;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Plugin\Order\CommitStockReservation;
use PHPUnit\Framework\TestCase;

class CommitStockReservationTest extends TestCase
{
    public function testSavedOrderCommitsBeforeReturningToQuoteManagement(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $order = $this->order('owned');
        $saved = false;
        $reservation->expects($this->once())->method('commitSubmission')->with(99, 'owned')
            ->willReturnCallback(function () use (&$saved): void {
                $this->assertTrue($saved);
            });
        $reservation->expects($this->never())->method('rollbackSubmission');
        $result = (new CommitStockReservation($reservation))->aroundPlace(
            $this->createMock(OrderService::class),
            static function ($order) use (&$saved) {
                $saved = true;
                return $order;
            },
            $order
        );
        $this->assertSame($order, $result);
    }

    public function testFailedOrderSaveRollsBackUsingTheOriginalToken(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->once())->method('rollbackSubmission')->with('owned');
        $reservation->expects($this->never())->method('commitSubmission');
        $order = $this->order('owned');
        $failure = new \Error('order save failed');
        try {
            (new CommitStockReservation($reservation))->aroundPlace($this->createMock(OrderService::class),
                static function ($order) use ($failure): never {
                    $order->setData(QuoteStockReservation::ORDER_ATTEMPT_ID, 'stale');
                    throw $failure;
                }, $order);
            $this->fail('Expected the original order save failure.');
        } catch (\Error $actual) {
            $this->assertSame($failure, $actual);
        }
    }

    public function testOrderWithoutCheckoutTokenUsesTheOriginalFlow(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->never())->method('commitSubmission');
        $reservation->expects($this->never())->method('rollbackSubmission');
        $order = $this->order(null);
        $this->assertSame($order, (new CommitStockReservation($reservation))->aroundPlace(
            $this->createMock(OrderService::class), static fn ($order) => $order, $order));
    }

    private function order(?string $attemptId): Order
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $order->setQuoteId(99)->setData(QuoteStockReservation::ORDER_ATTEMPT_ID, $attemptId);
        return $order;
    }
}
