<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\Config;
use Nuvem\AtelieSales\Model\ComponentStock;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ComponentStockTest extends TestCase
{
    /**
     * @var ComponentRepositoryInterface|MockObject
     */
    private MockObject $componentRepositoryMock;

    /**
     * @var Movimentacao|MockObject
     */
    private MockObject $movimentacaoResourceMock;

    /**
     * @var ResourceConnection|MockObject
     */
    private MockObject $resourceConnectionMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private MockObject $connectionMock;

    /**
     * @var AtelieCatalogInterface|MockObject
     */
    private MockObject $atelieCatalogMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private MockObject $scopeConfigMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private MockObject $eventManagerMock;

    /**
     * @var Component|MockObject
     */
    private MockObject $componentMock;

    /**
     * @var Select|MockObject
     */
    private MockObject $selectMock;

    private ComponentStock $componentStock;

    private array $commitCallbacks = [];
    private bool $deferCallbacks = false;

    protected function setUp(): void
    {
        $this->componentRepositoryMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->movimentacaoResourceMock = $this->createMock(Movimentacao::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->atelieCatalogMock = $this->createMock(AtelieCatalogInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);

        $this->movimentacaoResourceMock->method('getMainTable')->willReturn('nuvem_atelie_movimentacao');
        $this->resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceConnectionMock->method('getTableName')->willReturnArgument(0);
        $this->movimentacaoResourceMock->method('getReservedQuantity')->willReturn(2);
        $this->movimentacaoResourceMock->method('addCommitCallback')->willReturnCallback(
            function (callable $callback): Movimentacao {
                $this->commitCallbacks[] = $callback;
                return $this->movimentacaoResourceMock;
            }
        );
        $this->connectionMock->method('commit')->willReturnCallback(function (): void {
            if ($this->deferCallbacks) {
                return;
            }
            foreach ($this->commitCallbacks as $callback) {
                $callback();
            }
            $this->commitCallbacks = [];
        });

        $this->selectMock = $this->createMock(Select::class);
        $this->selectMock->method('from')->willReturnSelf();
        $this->selectMock->method('where')->willReturnSelf();
        $this->selectMock->method('forUpdate')->willReturnSelf();
        $this->connectionMock->method('select')->willReturn($this->selectMock);

        $this->componentMock = $this->createMock(Component::class);
        $this->componentMock->method('getComponenteId')->willReturn(7);
        $this->componentMock->method('getNome')->willReturn('Sola Lua');
        $this->componentMock->method('getEstoque')->willReturn(10);
        $this->componentRepositoryMock->method('getByCodigo')->willReturn($this->componentMock);

        $this->componentStock = new ComponentStock(
            $this->componentRepositoryMock,
            $this->movimentacaoResourceMock,
            $this->resourceConnectionMock,
            $this->atelieCatalogMock,
            $this->scopeConfigMock,
            $this->eventManagerMock
        );
    }

    public function testReserveDecrementsStockAndRecordsLedgerMovement(): void
    {
        $this->connectionMock->expects($this->once())->method('update')->with(
            'nuvem_atelie_componente',
            $this->callback(static fn (array $data): bool => (string) $data['estoque'] === 'estoque - 2'),
            ['componente_id = ?' => 7, 'estoque >= ?' => 2]
        )->willReturn(1);
        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->connectionMock->expects($this->never())->method('rollBack');
        $this->connectionMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                'nuvem_atelie_movimentacao',
                [
                    'componente_id' => 7,
                    'tipo' => 'reserva',
                    'quantidade' => -2,
                    'quote_id' => 99,
                    'order_id' => null,
                    'order_item_id' => null,
                    'observacao' => null,
                ]
            );
        $this->eventManagerMock->expects($this->never())->method('dispatch');

        $this->assertTrue($this->componentStock->reserveForQuote('SOL-LUA-HOLO', 2, 99));
    }

    public function testReserveRefusesWhenStockIsInsufficient(): void
    {
        $this->connectionMock->method('update')->willReturn(0);
        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');
        $this->connectionMock->expects($this->never())->method('insert');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('O componente "Sola Lua" esgotou enquanto você finalizava.');

        $this->componentStock->reserve('SOL-LUA-HOLO', 2);
    }

    public function testOriginalReservePreservesOrderMetadataWithoutQuoteOwnership(): void
    {
        $this->connectionMock->method('update')->willReturn(1);
        $this->movimentacaoResourceMock->expects($this->never())->method('lockQuote');
        $this->connectionMock->expects($this->once())->method('insert')->with(
            'nuvem_atelie_movimentacao',
            $this->callback(static fn (array $row): bool => $row['order_id'] === 11
                && $row['order_item_id'] === 12 && $row['quote_id'] === null && $row['quantidade'] === -2)
        );

        $this->assertTrue($this->componentStock->reserve('SOL-LUA-HOLO', 2, 11, 12));
    }

    public function testOriginalCompensationPreservesOrderMetadataWithoutQuoteOwnership(): void
    {
        $this->movimentacaoResourceMock->expects($this->never())->method('lockQuote');
        $this->movimentacaoResourceMock->expects($this->never())->method('getReservedQuantity');
        $this->connectionMock->expects($this->once())->method('insert')->with(
            'nuvem_atelie_movimentacao',
            $this->callback(static fn (array $row): bool => $row['order_id'] === 11
                && $row['quote_id'] === null && $row['quantidade'] === 2)
        );

        $this->componentStock->compensate('SOL-LUA-HOLO', 2, 11);
    }

    public function testReserveWrapsUnknownComponentIntoLocalizedMessage(): void
    {
        $this->componentRepositoryMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->componentRepositoryMock->method('getByCodigo')
            ->willThrowException(new NoSuchEntityException(__('No such component.')));
        $this->componentStock = new ComponentStock(
            $this->componentRepositoryMock,
            $this->movimentacaoResourceMock,
            $this->resourceConnectionMock,
            $this->atelieCatalogMock,
            $this->scopeConfigMock,
            $this->eventManagerMock
        );

        $this->connectionMock->expects($this->never())->method('update');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('O componente "SOL-LUA-HOLO" não existe.');

        $this->componentStock->reserve('SOL-LUA-HOLO', 2);
    }

    public function testReserveRefusesNonPositiveQuantity(): void
    {
        $this->componentRepositoryMock->expects($this->never())->method('getByCodigo');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A quantidade deve ser maior que zero.');

        $this->componentStock->reserve('SOL-LUA-HOLO', 0);
    }

    public function testReserveInvalidatesComponentCacheAfterCommit(): void
    {
        $this->connectionMock->method('update')->willReturn(1);
        $this->connectionMock->method('fetchOne')->willReturn('10');
        $this->scopeConfigMock->method('getValue')->willReturn('5');
        $this->componentMock->expects($this->once())->method('cleanModelCache');
        $this->eventManagerMock
            ->expects($this->once())
            ->method('dispatch')
            ->with('clean_cache_by_tags', ['object' => $this->componentMock]);

        $this->componentStock->reserve('SOL-LUA-HOLO', 5);
    }

    public function testReserveDoesNotInvalidateCacheWhenAvailabilityFlagsStayTheSame(): void
    {
        $this->connectionMock->method('update')->willReturn(1);
        $this->connectionMock->method('fetchOne')->willReturn('10');
        $this->scopeConfigMock->method('getValue')->willReturn('5');
        $this->componentMock->expects($this->never())->method('cleanModelCache');
        $this->eventManagerMock->expects($this->never())->method('dispatch');
        $this->movimentacaoResourceMock->expects($this->never())->method('addCommitCallback');

        $this->componentStock->reserve('SOL-LUA-HOLO', 1);
    }

    public function testReservationAndCompensationKeepUnchangedAvailabilityCached(): void
    {
        $this->connectionMock->method('update')->willReturn(1);
        $this->connectionMock->method('fetchOne')->willReturnOnConsecutiveCalls('2', '0');
        $this->scopeConfigMock->method('getValue')->willReturn('5');
        $this->eventManagerMock->expects($this->never())->method('dispatch');
        $this->deferCallbacks = true;
        $this->componentStock->reserveForQuote('SOL-LUA-HOLO', 2, 99);
        $this->componentStock->compensateForQuote('SOL-LUA-HOLO', 2, 99);
        $this->assertCount(1, $this->commitCallbacks);
        $this->deferCallbacks = false;
        $this->connectionMock->commit();
    }

    public function testCompensateReturnsStockAndRecordsLedgerMovement(): void
    {
        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('update');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->connectionMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                'nuvem_atelie_movimentacao',
                [
                    'componente_id' => 7,
                    'tipo' => 'compensacao',
                    'quantidade' => 2,
                    'quote_id' => 99,
                    'order_id' => null,
                    'order_item_id' => null,
                    'observacao' => null,
                ]
            );

        $this->componentStock->compensateForQuote('SOL-LUA-HOLO', 2, 99);
    }

    public function testCompensationInvalidatesCacheWhenAvailabilityReturns(): void
    {
        $this->connectionMock->method('fetchOne')->willReturn('0');
        $this->scopeConfigMock->method('getValue')->willReturn('5');
        $this->componentMock->expects($this->once())->method('cleanModelCache');
        $this->eventManagerMock->expects($this->once())->method('dispatch');

        $this->componentStock->compensateForQuote('SOL-LUA-HOLO', 2, 99);
    }

    public function testReturnRecordsDevolutionLedgerMovement(): void
    {
        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->connectionMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                'nuvem_atelie_movimentacao',
                [
                    'componente_id' => 7,
                    'tipo' => 'devolucao',
                    'quantidade' => 2,
                    'quote_id' => null,
                    'order_id' => 11,
                    'order_item_id' => 12,
                    'observacao' => null,
                ]
            );

        $this->componentStock->return('SOL-LUA-HOLO', 2, 11, 12);
    }

    public function testAdjustSetsAbsoluteStockAndRecordsDelta(): void
    {
        $this->connectionMock->method('fetchOne')->willReturn('10');
        $this->selectMock->expects($this->once())->method('forUpdate')->with(true);
        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock
            ->expects($this->once())
            ->method('update')
            ->with('nuvem_atelie_componente', ['estoque' => 7], ['componente_id = ?' => 7]);
        $this->connectionMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                'nuvem_atelie_movimentacao',
                [
                    'componente_id' => 7,
                    'tipo' => 'ajuste',
                    'quantidade' => -3,
                    'quote_id' => null,
                    'order_id' => null,
                    'order_item_id' => null,
                    'observacao' => 'recebimento de lote',
                ]
            );
        $this->connectionMock->expects($this->once())->method('commit');

        $this->componentStock->adjust('SOL-LUA-HOLO', 7, 'recebimento de lote');
    }

    public function testAdjustRecordsNoMovementWhenQuantityDoesNotChange(): void
    {
        $this->connectionMock->method('fetchOne')->willReturn('10');
        $this->connectionMock->expects($this->once())->method('update');
        $this->connectionMock->expects($this->never())->method('insert');
        $this->connectionMock->expects($this->once())->method('commit');

        $this->componentStock->adjust('SOL-LUA-HOLO', 10);
    }

    public function testAdjustToZeroInvalidatesComponentCache(): void
    {
        $this->connectionMock->method('fetchOne')->willReturn('3');
        $this->scopeConfigMock->method('getValue')->willReturn('5');
        $this->connectionMock->expects($this->once())->method('update');
        $this->componentMock->expects($this->once())->method('cleanModelCache');
        $this->eventManagerMock
            ->expects($this->once())
            ->method('dispatch')
            ->with('clean_cache_by_tags', ['object' => $this->componentMock]);

        $this->componentStock->adjust('SOL-LUA-HOLO', 0);
    }

    public function testAdjustDoesNotInvalidateCacheWhenAvailabilityFlagsStayTheSame(): void
    {
        $this->connectionMock->method('fetchOne')->willReturn('10');
        $this->scopeConfigMock->method('getValue')->willReturn('5');
        $this->eventManagerMock->expects($this->never())->method('dispatch');
        $this->movimentacaoResourceMock->expects($this->never())->method('addCommitCallback');

        $this->componentStock->adjust('SOL-LUA-HOLO', 8);
    }

    public function testAdjustRefusesNegativeQuantity(): void
    {
        $this->connectionMock->expects($this->never())->method('update');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A quantidade em estoque não pode ser negativa.');

        $this->componentStock->adjust('SOL-LUA-HOLO', -1);
    }

    /** @dataProvider movementMethods */
    public function testLedgerFailureRollsBackEveryMovement(string $method): void
    {
        $this->connectionMock->method('update')->willReturn(1);
        $this->connectionMock->method('fetchOne')->willReturn('10');
        $failure = new \RuntimeException('ledger unavailable');
        $this->connectionMock->method('insert')->willThrowException($failure);
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');
        $this->expectExceptionObject($failure);

        $this->componentStock->$method('SOL-LUA-HOLO', 2);
    }

    public static function movementMethods(): array
    {
        return [['reserve'], ['compensate'], ['return'], ['adjust']];
    }

    public function testCompensationWithNoPendingReservationDoesNotCreateStock(): void
    {
        $resource = $this->createMock(Movimentacao::class);
        $resource->method('getReservedQuantity')->willReturn(0);
        $resource->expects($this->once())->method('lockQuote')->with(99);
        $service = new ComponentStock(
            $this->componentRepositoryMock,
            $resource,
            $this->resourceConnectionMock,
            $this->atelieCatalogMock,
            $this->scopeConfigMock,
            $this->eventManagerMock
        );
        $this->connectionMock->expects($this->never())->method('update');
        $this->connectionMock->expects($this->never())->method('insert');
        $service->compensateForQuote('SOL-LUA-HOLO', 10, 99);
    }

    public function testCompensationCannotExceedOutstandingReservation(): void
    {
        $this->connectionMock->expects($this->once())->method('update')->with(
            'nuvem_atelie_componente',
            $this->callback(static fn (array $data): bool => (string) $data['estoque'] === 'estoque + 2'),
            ['componente_id = ?' => 7]
        );
        $this->componentStock->compensateForQuote('SOL-LUA-HOLO', 10, 99);
    }

    public function testAvailabilityReportsFlagsWithoutQuantities(): void
    {
        $this->atelieCatalogMock->method('getBySku')->willReturn([
            'sku' => 'NUVEM-RUNNER',
            'grupos' => [
                [
                    'codigo' => 'solado',
                    'componentes' => [
                        ['codigo' => 'SOL-LUA-HOLO'],
                        ['codigo' => 'SOL-BORRACHA-LAVANDA'],
                        ['codigo' => 'SOL-BORRACHA-GRAFITE'],
                        ['codigo' => 'SOL-LIMIT'],
                        ['codigo' => 'SOL-ONE'],
                    ],
                ],
            ],
        ]);

        $this->connectionMock->method('fetchPairs')->willReturn([
            'SOL-LUA-HOLO' => 4,
            'SOL-BORRACHA-LAVANDA' => 0,
            'SOL-BORRACHA-GRAFITE' => 30,
            'SOL-LIMIT' => 5,
            'SOL-ONE' => 1,
        ]);
        $this->scopeConfigMock->method('getValue')
            ->with(Config::XML_PATH_STOCK_LAST_UNITS, ScopeInterface::SCOPE_STORE)
            ->willReturn('5');

        $this->assertSame(
            [
                ['component' => 'SOL-LUA-HOLO', 'available' => true, 'last_units' => true],
                ['component' => 'SOL-BORRACHA-LAVANDA', 'available' => false, 'last_units' => false],
                ['component' => 'SOL-BORRACHA-GRAFITE', 'available' => true, 'last_units' => false],
                ['component' => 'SOL-LIMIT', 'available' => true, 'last_units' => true],
                ['component' => 'SOL-ONE', 'available' => true, 'last_units' => true],
            ],
            $this->componentStock->availability('NUVEM-RUNNER')
        );
    }

    public function testZeroThresholdDisablesLastUnitsWarning(): void
    {
        $this->atelieCatalogMock->method('getBySku')->willReturn([
            'grupos' => [['componentes' => [['codigo' => 'SOL-LUA-HOLO']]]],
        ]);
        $this->connectionMock->method('fetchPairs')->willReturn(['SOL-LUA-HOLO' => 1]);
        $this->scopeConfigMock->expects($this->once())->method('getValue')
            ->with(Config::XML_PATH_STOCK_LAST_UNITS, ScopeInterface::SCOPE_STORE)
            ->willReturn('0');

        $this->assertSame([
            ['component' => 'SOL-LUA-HOLO', 'available' => true, 'last_units' => false],
        ], $this->componentStock->availability('NUVEM-RUNNER'));
    }

    public function testAvailabilityThrowsForUnknownSku(): void
    {
        $this->atelieCatalogMock->method('getBySku')
            ->willThrowException(new NoSuchEntityException(__('No such product.')));

        $this->expectException(NoSuchEntityException::class);

        $this->componentStock->availability('NUVEM-INEXISTENTE');
    }
}
