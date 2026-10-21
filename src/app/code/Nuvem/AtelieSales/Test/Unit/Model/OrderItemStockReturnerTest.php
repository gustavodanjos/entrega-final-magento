<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\OrderItemConfigurationReader;
use Nuvem\AtelieSales\Model\OrderItemStockReturner;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderItemStockReturnerTest extends TestCase
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
     * @var OrderItemConfigurationReader|MockObject
     */
    private MockObject $configurationReaderMock;

    /**
     * @var ResourceConnection|MockObject
     */
    private MockObject $resourceConnectionMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private MockObject $connectionMock;

    private OrderItemStockReturner $returner;

    private const SAMPLE_SEVEN_COMPONENTS = [
        'CAB-TRICO-LAVANDA',
        'FOR-ESPUMA-BRANCO',
        'CAD-ALGODAO-BRANCO',
        'PAL-ANAT-NATURAL',
        'SOL-LUA-HOLO',
        'ENT-EVA-BRANCO',
        'TAL-SUEDE-ROSA',
    ];

    protected function setUp(): void
    {
        $this->componentStockMock = $this->createMock(ComponentStockInterface::class);
        $this->movimentacaoResourceMock = $this->createMock(Movimentacao::class);
        $this->configurationReaderMock = $this->createMock(OrderItemConfigurationReader::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $this->resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);

        $this->returner = new OrderItemStockReturner(
            $this->componentStockMock,
            $this->movimentacaoResourceMock,
            $this->configurationReaderMock,
            $this->resourceConnectionMock
        );
    }

    public function testReturnOrderReturnsAllSevenComponents(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn(101);

        $orderItemMock = $this->createMock(OrderItemInterface::class);
        $orderItemMock->method('getItemId')->willReturn(202);
        $orderItemMock->method('getQtyOrdered')->willReturn(1.0);
        $orderItemMock->method('getOrderId')->willReturn(101);

        $orderMock->method('getItems')->willReturn([$orderItemMock]);

        $this->configurationReaderMock->method('getComponentCodes')
            ->with($orderItemMock)
            ->willReturn(self::SAMPLE_SEVEN_COMPONENTS);

        $this->movimentacaoResourceMock->method('getReturnedQuantitiesByOrderItemId')
            ->with(202)
            ->willReturn([]);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');

        $this->componentStockMock->expects($this->exactly(7))
            ->method('return')
            ->willReturnCallback(function (string $code, int $qty, ?int $orderId, ?int $orderItemId) {
                $this->assertContains($code, self::SAMPLE_SEVEN_COMPONENTS);
                $this->assertSame(1, $qty);
                $this->assertSame(101, $orderId);
                $this->assertSame(202, $orderItemId);
            });

        $this->returner->returnOrder($orderMock);
    }

    public function testReturnOrderIsIdempotentWhenAlreadyReturned(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn(101);

        $orderItemMock = $this->createMock(OrderItemInterface::class);
        $orderItemMock->method('getItemId')->willReturn(202);
        $orderItemMock->method('getQtyOrdered')->willReturn(1.0);

        $orderMock->method('getItems')->willReturn([$orderItemMock]);

        $this->configurationReaderMock->method('getComponentCodes')
            ->with($orderItemMock)
            ->willReturn(self::SAMPLE_SEVEN_COMPONENTS);

        $alreadyReturned = [];
        foreach (self::SAMPLE_SEVEN_COMPONENTS as $code) {
            $alreadyReturned[$code] = 1;
        }

        $this->movimentacaoResourceMock->method('getReturnedQuantitiesByOrderItemId')
            ->with(202)
            ->willReturn($alreadyReturned);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->componentStockMock->expects($this->never())->method('return');

        $this->returner->returnOrder($orderMock);
    }

    public function testReturnOrderSkipsNonAtelieItems(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn(101);

        $regularItemMock = $this->createMock(OrderItemInterface::class);
        $regularItemMock->method('getItemId')->willReturn(303);

        $orderMock->method('getItems')->willReturn([$regularItemMock]);

        $this->configurationReaderMock->method('getComponentCodes')
            ->with($regularItemMock)
            ->willReturn([]);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->componentStockMock->expects($this->never())->method('return');

        $this->returner->returnOrder($orderMock);
    }

    public function testReturnOrderRollsBackOnException(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn(101);

        $orderItemMock = $this->createMock(OrderItemInterface::class);
        $orderItemMock->method('getItemId')->willReturn(202);
        $orderItemMock->method('getQtyOrdered')->willReturn(1.0);

        $orderMock->method('getItems')->willReturn([$orderItemMock]);

        $this->configurationReaderMock->method('getComponentCodes')
            ->with($orderItemMock)
            ->willReturn(['SOL-LUA-HOLO']);

        $this->movimentacaoResourceMock->method('getReturnedQuantitiesByOrderItemId')
            ->willReturn([]);

        $this->componentStockMock->method('return')
            ->willThrowException(new Exception('Database deadlock'));

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database deadlock');

        $this->returner->returnOrder($orderMock);
    }

    public function testReturnCreditmemoReturnsWhenBackToStockIsTrue(): void
    {
        $creditmemoMock = $this->createMock(CreditmemoInterface::class);
        $creditmemoMock->method('getOrderId')->willReturn(101);

        $creditmemoItemMock = $this->getMockBuilder(CreditmemoItemInterface::class)
            ->addMethods(['getBackToStock', 'getOrderItem'])
            ->getMockForAbstractClass();
        $creditmemoItemMock->method('getBackToStock')->willReturn(true);
        $creditmemoItemMock->method('getQty')->willReturn(1.0);

        $orderItemMock = $this->createMock(OrderItemInterface::class);
        $orderItemMock->method('getItemId')->willReturn(202);
        $orderItemMock->method('getQtyOrdered')->willReturn(2.0);
        $creditmemoItemMock->method('getOrderItem')->willReturn($orderItemMock);

        $creditmemoMock->method('getItems')->willReturn([$creditmemoItemMock]);

        $this->configurationReaderMock->method('getComponentCodes')
            ->with($orderItemMock)
            ->willReturn(['SOL-LUA-HOLO']);

        $this->movimentacaoResourceMock->method('getReturnedQuantitiesByOrderItemId')
            ->with(202)
            ->willReturn([]);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');

        $this->componentStockMock->expects($this->once())
            ->method('return')
            ->with('SOL-LUA-HOLO', 1, 101, 202);

        $this->returner->returnCreditmemo($creditmemoMock);
    }

    public function testReturnCreditmemoIgnoresItemsWithoutBackToStock(): void
    {
        $creditmemoMock = $this->createMock(CreditmemoInterface::class);
        $creditmemoItemMock = $this->getMockBuilder(CreditmemoItemInterface::class)
            ->addMethods(['getBackToStock', 'getOrderItem'])
            ->getMockForAbstractClass();
        $creditmemoItemMock->method('getBackToStock')->willReturn(false);

        $creditmemoMock->method('getItems')->willReturn([$creditmemoItemMock]);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->componentStockMock->expects($this->never())->method('return');

        $this->returner->returnCreditmemo($creditmemoMock);
    }

    public function testReturnCreditmemoCapsToRemainingUnreturnedQty(): void
    {
        $creditmemoMock = $this->createMock(CreditmemoInterface::class);
        $creditmemoMock->method('getOrderId')->willReturn(101);

        $creditmemoItemMock = $this->getMockBuilder(CreditmemoItemInterface::class)
            ->addMethods(['getBackToStock', 'getOrderItem'])
            ->getMockForAbstractClass();
        $creditmemoItemMock->method('getBackToStock')->willReturn(true);
        $creditmemoItemMock->method('getQty')->willReturn(2.0);

        $orderItemMock = $this->createMock(OrderItemInterface::class);
        $orderItemMock->method('getItemId')->willReturn(202);
        $orderItemMock->method('getQtyOrdered')->willReturn(2.0);
        $creditmemoItemMock->method('getOrderItem')->willReturn($orderItemMock);

        $creditmemoMock->method('getItems')->willReturn([$creditmemoItemMock]);

        $this->configurationReaderMock->method('getComponentCodes')
            ->with($orderItemMock)
            ->willReturn(['SOL-LUA-HOLO']);

        // 1 was already returned previously, so only 1 remains available out of 2 requested
        $this->movimentacaoResourceMock->method('getReturnedQuantitiesByOrderItemId')
            ->with(202)
            ->willReturn(['SOL-LUA-HOLO' => 1]);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');

        $this->componentStockMock->expects($this->once())
            ->method('return')
            ->with('SOL-LUA-HOLO', 1, 101, 202);

        $this->returner->returnCreditmemo($creditmemoMock);
    }
}
