<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;
use Nuvem\AtelieSales\Observer\ReserveStockOnQuoteSubmitBefore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReserveStockOnQuoteSubmitBeforeTest extends TestCase
{
    /**
     * @var ComponentStockInterface|MockObject
     */
    private MockObject $componentStockMock;

    /**
     * @var QuoteItemConfigurationReader|MockObject
     */
    private MockObject $configurationReaderMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private MockObject $connectionMock;

    /**
     * @var Quote|MockObject
     */
    private MockObject $quoteMock;

    private ReserveStockOnQuoteSubmitBefore $observer;

    protected function setUp(): void
    {
        $this->componentStockMock = $this->createMock(ComponentStockInterface::class);
        $this->configurationReaderMock = $this->createMock(QuoteItemConfigurationReader::class);
        $resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);

        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getId')->willReturn(99);

        $this->observer = new ReserveStockOnQuoteSubmitBefore(
            $this->componentStockMock,
            $this->configurationReaderMock,
            $resourceConnectionMock
        );
    }

    private function createObserverMockWithQuote(): Observer
    {
        return new Observer(
            ['event' => new \Magento\Framework\Event(['quote' => $this->quoteMock])]
        );
    }

    public function testAggregatesDemandAndReservesPerComponent(): void
    {
        $itemOneMock = $this->createMock(Item::class);
        $itemTwoMock = $this->createMock(Item::class);
        $this->quoteMock->method('getAllItems')->willReturn([$itemOneMock, $itemTwoMock]);
        $this->configurationReaderMock->method('getComponentQuantities')->willReturnMap([
            [$itemOneMock, ['SOL-LUA-HOLO' => 1]],
            [$itemTwoMock, ['SOL-LUA-HOLO' => 1, 'CAB-TRICO-LAVANDA' => 3]],
        ]);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');

        $reserved = [];
        $this->componentStockMock->method('reserve')->willReturnCallback(
            static function (string $componentCode, int $qty, ?int $orderId, ?int $orderItemId, ?int $quoteId)
                use (&$reserved): bool {
                $reserved[] = [$componentCode, $qty, $quoteId];
                return true;
            }
        );

        $this->observer->execute($this->createObserverMockWithQuote());

        $this->assertSame(
            [
                ['SOL-LUA-HOLO', 2, 99],
                ['CAB-TRICO-LAVANDA', 3, 99],
            ],
            $reserved
        );
    }

    public function testRollsBackAndRethrowsWhenReservationFails(): void
    {
        $itemMock = $this->createMock(Item::class);
        $this->quoteMock->method('getAllItems')->willReturn([$itemMock]);
        $this->configurationReaderMock->method('getComponentQuantities')
            ->willReturn(['SOL-LUA-HOLO' => 1]);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');
        $this->componentStockMock->method('reserve')
            ->willThrowException(new LocalizedException(__('O componente esgotou.')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('O componente esgotou.');

        $this->observer->execute($this->createObserverMockWithQuote());
    }

    public function testDoesNothingWhenQuoteHasNoAtelieItems(): void
    {
        $itemMock = $this->createMock(Item::class);
        $this->quoteMock->method('getAllItems')->willReturn([$itemMock]);
        $this->configurationReaderMock->method('getComponentQuantities')->willReturn([]);

        $this->connectionMock->expects($this->never())->method('beginTransaction');
        $this->componentStockMock->expects($this->never())->method('reserve');

        $this->observer->execute($this->createObserverMockWithQuote());
    }
}
