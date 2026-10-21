<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\OrderItemConfigurationReader;
use Nuvem\AtelieSales\Model\OrderItemStockReturner;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderItemStockReturnerTest extends TestCase
{
    private ComponentStockInterface&MockObject $componentStock;
    private Movimentacao&MockObject $stockLedger;
    private OrderItemConfigurationReader&MockObject $configurationReader;
    private AdapterInterface&MockObject $connection;
    private OrderItemStockReturner $returner;
    private array $movements = [];
    private array $components = ['SOL-LUA-HOLO' => 1];

    protected function setUp(): void
    {
        $this->componentStock = $this->createMock(ComponentStockInterface::class);
        $this->stockLedger = $this->createMock(Movimentacao::class);
        $this->configurationReader = $this->createMock(OrderItemConfigurationReader::class);
        $resource = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $this->configurationReader->method('getComponentQuantities')
            ->willReturnCallback(fn (): array => $this->components);
        $this->stockLedger->method('getReturnedQuantitiesByOrderItemId')
            ->willReturnCallback(function (int $itemId, ?string $note = null): array {
                $totals = [];
                foreach ($this->movements as $movement) {
                    if ($movement['itemId'] === $itemId && ($note === null || $movement['note'] === $note)) {
                        $code = $movement['code'];
                        $totals[$code] = ($totals[$code] ?? 0) + $movement['quantity'];
                    }
                }
                return $totals;
            });
        $this->componentStock->method('return')->willReturnCallback(
            function (string $code, int $quantity, ?int $orderId, ?int $itemId, ?string $note): void {
                $this->movements[] = compact('code', 'quantity', 'orderId', 'itemId', 'note');
            }
        );
        $this->returner = new OrderItemStockReturner(
            $this->componentStock,
            $this->stockLedger,
            $this->configurationReader,
            $resource
        );
    }

    public function testCancellationReturnsAllSevenComponentsOnce(): void
    {
        $this->components = array_fill_keys([
            'CAB-TRICO-LAVANDA', 'BIQ-LISA-CEU', 'CAL-ACOLCHOADO-CEU', 'CAD-ELASTICO-MENTA',
            'EMB-NUVEM-PESSEGO', 'ENT-EVA-NUVEM', 'SOL-LUA-HOLO',
        ], 1);
        $order = $this->order($this->item(2, 2));
        $this->stockLedger->expects($this->exactly(2))->method('lockOrder')->with(101);
        $this->returner->returnOrder($order);
        $this->returner->returnOrder($order);
        self::assertCount(7, $this->movements);
        foreach ($this->movements as $movement) {
            self::assertSame(2, $movement['quantity']);
            self::assertSame(101, $movement['orderId']);
            self::assertSame(202, $movement['itemId']);
            self::assertSame('cancel:101', $movement['note']);
        }
    }

    public function testPartialCancellationKeepsInvoicedUnitsReserved(): void
    {
        $this->returner->returnOrder($this->order($this->item(3, 1)));
        self::assertSame([1], array_column($this->movements, 'quantity'));
    }

    public function testLaterCancellationOnlyReturnsItsIncrement(): void
    {
        $this->returner->returnOrder($this->order($this->item(3, 1)));
        $this->returner->returnOrder($this->order($this->item(3, 2)));
        self::assertSame([1, 1], array_column($this->movements, 'quantity'));
    }

    public function testUncancelledOrderDoesNotStartStockTransaction(): void
    {
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->returner->returnOrder($this->order($this->item(3, 0)));
    }

    public function testStandardProductDoesNotReturnComponents(): void
    {
        $this->components = [];
        $this->returner->returnOrder($this->order($this->item(1, 1)));
        self::assertSame([], $this->movements);
    }

    public function testAllItemsRollBackOnThrowable(): void
    {
        $this->configurationReader->method('getComponentQuantities')->willThrowException(new \Error('Failure'));
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->expectException(\Error::class);
        $this->returner->returnOrder($this->order($this->item(1, 1)));
    }

    public function testRepeatedComponentUsesItsActualDemand(): void
    {
        $this->components = ['SOL-LUA-HOLO' => 2];
        $order = $this->order($this->item(2, 2));
        $this->returner->returnOrder($order);
        $this->returner->returnOrder($order);
        self::assertSame([4], array_column($this->movements, 'quantity'));
    }

    private function item(int $ordered, int $canceled = 0): OrderItemInterface
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getItemId')->willReturn(202);
        $item->method('getProductType')->willReturn('atelie');
        $item->method('getQtyOrdered')->willReturn($ordered);
        $item->method('getQtyCanceled')->willReturn($canceled);
        return $item;
    }

    private function order(OrderItemInterface $item): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn(101);
        $order->method('getAllItems')->willReturn([$item]);
        return $order;
    }


}
