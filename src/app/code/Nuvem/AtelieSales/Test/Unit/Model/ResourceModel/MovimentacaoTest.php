<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use PHPUnit\Framework\TestCase;

class MovimentacaoTest extends TestCase
{
    /** @dataProvider persistedOrders */
    public function testOrderLookupUsesCurrentReadForTheLockedQuote(int|false $orderId): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->expects($this->once())->method('from')->with('sales_order', 'entity_id')->willReturnSelf();
        $select->expects($this->once())->method('where')->with('quote_id = ?', 99)->willReturnSelf();
        $select->expects($this->once())->method('limit')->with(1)->willReturnSelf();
        $select->expects($this->once())->method('forUpdate')->with(true)->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->expects($this->once())->method('fetchOne')->with($select)->willReturn($orderId);
        $ledger = $this->getMockBuilder(Movimentacao::class)->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTable'])->getMock();
        $ledger->method('getConnection')->willReturn($connection);
        $ledger->method('getTable')->willReturnArgument(0);

        $this->assertSame((bool) $orderId, $ledger->hasOrder(99));
    }

    public static function persistedOrders(): array
    {
        return [[123], [false]];
    }

    public function testQuoteBalancesUseCurrentReadAndIncludeCompensations(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        foreach (['from', 'join', 'where'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->expects($this->once())->method('forUpdate')->with(true)->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->with($select)->willReturn([
            ['componente_id' => '1', 'codigo' => 'SOL-LUA-HOLO', 'quantidade' => '-2'],
            ['componente_id' => '2', 'codigo' => 'CAB-TRICO', 'quantidade' => '-1'],
            ['componente_id' => '1', 'codigo' => 'SOL-LUA-HOLO', 'quantidade' => '1'],
            ['componente_id' => '2', 'codigo' => 'CAB-TRICO', 'quantidade' => '1'],
        ]);
        $ledger = $this->getMockBuilder(Movimentacao::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable', 'getTable'])
            ->getMock();
        $ledger->method('getConnection')->willReturn($connection);
        $ledger->method('getMainTable')->willReturn('nuvem_atelie_movimentacao');
        $ledger->method('getTable')->willReturnArgument(0);

        $this->assertSame([
            ['componente_id' => 1, 'codigo' => 'SOL-LUA-HOLO', 'net' => -1],
            ['componente_id' => 2, 'codigo' => 'CAB-TRICO', 'net' => 0],
        ], $ledger->getNetQuantitiesByQuoteId(123));
    }
}
