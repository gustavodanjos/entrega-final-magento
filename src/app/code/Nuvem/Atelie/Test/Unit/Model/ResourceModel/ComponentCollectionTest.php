<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\ResourceModel;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Nuvem\Atelie\Model\ResourceModel\Component\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

class ComponentCollectionTest extends TestCase
{
    /**
     * @var EntityFactoryInterface|MockObject
     */
    private MockObject $entityFactoryMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private MockObject $loggerMock;

    /**
     * @var FetchStrategyInterface|MockObject
     */
    private MockObject $fetchStrategyMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private MockObject $eventManagerMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private MockObject $connectionMock;

    /**
     * @var AbstractDb|MockObject
     */
    private MockObject $resourceMock;

    /**
     * @var Select|MockObject
     */
    private MockObject $selectMock;

    /**
     * @var Collection
     */
    private Collection $collection;

    protected function setUp(): void
    {
        $this->entityFactoryMock = $this->createMock(EntityFactoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->fetchStrategyMock = $this->createMock(FetchStrategyInterface::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->resourceMock = $this->createMock(AbstractDb::class);
        $this->selectMock = $this->createMock(Select::class);

        $this->connectionMock->method('select')->willReturn($this->selectMock);
        $this->connectionMock->method('quoteIdentifier')->willReturnCallback(
            fn(string $id) => '`' . $id . '`'
        );
        $this->resourceMock->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceMock->method('getMainTable')->willReturn('nuvem_atelie_componente');
        $this->resourceMock->method('getTable')->willReturnCallback(
            fn(string $tableName) => $tableName
        );

        $this->collection = new Collection(
            $this->entityFactoryMock,
            $this->loggerMock,
            $this->fetchStrategyMock,
            $this->eventManagerMock,
            $this->connectionMock,
            $this->resourceMock
        );
    }

    public function testAddFieldToFilterWithGrupoStringJoinsTable(): void
    {
        $this->selectMock->expects($this->once())
            ->method('joinInner')
            ->with(
                ['grupo_table' => 'nuvem_atelie_grupo'],
                'main_table.grupo_id = grupo_table.grupo_id',
                []
            )
            ->willReturnSelf();

        $this->connectionMock->expects($this->once())
            ->method('prepareSqlCondition')
            ->with('`grupo_table.codigo`', 'cabedal')
            ->willReturn("`grupo_table.codigo` = 'cabedal'");

        $this->selectMock->expects($this->once())
            ->method('where')
            ->with("`grupo_table.codigo` = 'cabedal'")
            ->willReturnSelf();

        $result = $this->collection->addFieldToFilter('grupo', 'cabedal');
        $this->assertSame($this->collection, $result);
    }

    public function testAddFieldToFilterWithGrupoNumericUsesGrupoId(): void
    {
        $this->selectMock->expects($this->never())->method('joinInner');

        $this->connectionMock->expects($this->once())
            ->method('prepareSqlCondition')
            ->with('`main_table.grupo_id`', 5)
            ->willReturn("`main_table.grupo_id` = 5");

        $this->selectMock->expects($this->once())
            ->method('where')
            ->with("`main_table.grupo_id` = 5")
            ->willReturnSelf();

        $result = $this->collection->addFieldToFilter('grupo', 5);
        $this->assertSame($this->collection, $result);
    }

    public function testAddFieldToFilterWithTableColumnDisambiguates(): void
    {
        $this->connectionMock->expects($this->once())
            ->method('prepareSqlCondition')
            ->with('`main_table.ativo`', 1)
            ->willReturn("`main_table.ativo` = 1");

        $this->selectMock->expects($this->once())
            ->method('where')
            ->with("`main_table.ativo` = 1")
            ->willReturnSelf();

        $result = $this->collection->addFieldToFilter('ativo', 1);
        $this->assertSame($this->collection, $result);
    }

    public function testAddOrderDisambiguates(): void
    {
        $this->collection->addOrder('ordem', 'ASC');

        $reflection = new ReflectionProperty($this->collection, '_orders');
        $orders = $reflection->getValue($this->collection);

        $this->assertArrayHasKey('main_table.ordem', $orders);
        $this->assertSame('ASC', $orders['main_table.ordem']);
    }

    public function testAddFieldToFilterWithAcabamentoDisambiguates(): void
    {
        $this->connectionMock->expects($this->once())
            ->method('prepareSqlCondition')
            ->with('`main_table.acabamento`', 'tela')
            ->willReturn("`main_table.acabamento` = 'tela'");

        $this->selectMock->expects($this->once())
            ->method('where')
            ->with("`main_table.acabamento` = 'tela'")
            ->willReturnSelf();

        $result = $this->collection->addFieldToFilter('acabamento', 'tela');
        $this->assertSame($this->collection, $result);
    }

    public function testAddFieldToFilterWithCombinationGrupoAndAtivo(): void
    {
        $this->selectMock->expects($this->once())
            ->method('joinInner')
            ->with(
                ['grupo_table' => 'nuvem_atelie_grupo'],
                'main_table.grupo_id = grupo_table.grupo_id',
                []
            )
            ->willReturnSelf();

        $this->connectionMock->expects($this->exactly(2))
            ->method('prepareSqlCondition')
            ->willReturnMap([
                ['`grupo_table.codigo`', 'cabedal', "`grupo_table.codigo` = 'cabedal'"],
                ['`main_table.ativo`', 1, "`main_table.ativo` = 1"],
            ]);

        $this->selectMock->expects($this->exactly(2))
            ->method('where')
            ->willReturnCallback(function (string $condition) {
                return $this->selectMock;
            });

        $this->collection->addFieldToFilter('grupo', 'cabedal');
        $this->collection->addFieldToFilter('ativo', 1);
    }

    public function testPagingCurrentPageAndPageSize(): void
    {
        $this->collection->setCurPage(2);
        $this->collection->setPageSize(5);

        $this->assertSame(2, $this->collection->getCurPage());
        $this->assertSame(5, $this->collection->getPageSize());
    }
}
