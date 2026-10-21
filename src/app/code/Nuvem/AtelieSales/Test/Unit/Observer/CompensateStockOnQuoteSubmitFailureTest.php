<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use Nuvem\AtelieSales\Observer\CompensateStockOnQuoteSubmitFailure;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CompensateStockOnQuoteSubmitFailureTest extends TestCase
{
    /**
     * @var ComponentStockInterface|MockObject
     */
    private MockObject $componentStockMock;

    /**
     * @var Movimentacao|MockObject
     */
    private MockObject $movimentacaoResourceMock;

    /**
     * @var Quote|MockObject
     */
    private MockObject $quoteMock;

    private CompensateStockOnQuoteSubmitFailure $observer;

    protected function setUp(): void
    {
        $this->componentStockMock = $this->createMock(ComponentStockInterface::class);
        $this->movimentacaoResourceMock = $this->createMock(Movimentacao::class);

        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getId')->willReturn(99);

        $this->observer = new CompensateStockOnQuoteSubmitFailure(
            $this->componentStockMock,
            $this->movimentacaoResourceMock
        );
    }

    private function createObserverMockWithQuote(?Order $order): Observer
    {
        return new Observer(
            ['event' => new \Magento\Framework\Event(['quote' => $this->quoteMock, 'order' => $order])]
        );
    }

    public function testCompensatesOnlyComponentsStillOwingStock(): void
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(123);

        $this->movimentacaoResourceMock->method('getNetQuantitiesByQuoteId')
            ->with(99)
            ->willReturn([
                ['componente_id' => 7, 'codigo' => 'SOL-LUA-HOLO', 'net' => -2],
                ['componente_id' => 8, 'codigo' => 'CAB-TRICO-LAVANDA', 'net' => 0],
            ]);

        $this->componentStockMock
            ->expects($this->once())
            ->method('compensate')
            ->with('SOL-LUA-HOLO', 2, 123, 99);

        $this->observer->execute($this->createObserverMockWithQuote($orderMock));
    }

    public function testCompensatesNothingWhenLedgerIsBalanced(): void
    {
        $this->movimentacaoResourceMock->method('getNetQuantitiesByQuoteId')
            ->with(99)
            ->willReturn([['componente_id' => 7, 'codigo' => 'SOL-LUA-HOLO', 'net' => 0]]);

        $this->componentStockMock->expects($this->never())->method('compensate');

        $this->observer->execute($this->createObserverMockWithQuote(null));
    }

    public function testSkipsWhenQuoteIsMissing(): void
    {
        $observerMock = new Observer(
            ['event' => new \Magento\Framework\Event([])]
        );

        $this->movimentacaoResourceMock->expects($this->never())->method('getNetQuantitiesByQuoteId');
        $this->componentStockMock->expects($this->never())->method('compensate');

        $this->observer->execute($observerMock);
    }
}
