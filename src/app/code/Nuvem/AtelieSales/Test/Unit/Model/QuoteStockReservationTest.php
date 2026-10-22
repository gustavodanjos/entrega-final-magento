<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteStockReservationTest extends TestCase
{
    private QuoteComponentStockInterface&MockObject $stock;
    private Movimentacao&MockObject $ledger;
    private AdapterInterface&MockObject $connection;
    private QuoteItemConfigurationReader&MockObject $reader;
    private OrderSearchResultInterface&MockObject $orders;
    private QuoteStockReservation $reservation;
    private Quote $quote;

    protected function setUp(): void
    {
        $this->stock = $this->createMock(QuoteComponentStockInterface::class);
        $this->ledger = $this->createMock(Movimentacao::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->ledger->method('getConnection')->willReturn($this->connection);
        $this->reader = $this->createMock(QuoteItemConfigurationReader::class);
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $this->orders = $this->createMock(OrderSearchResultInterface::class);
        $repository->method('getList')->willReturn($this->orders);
        $builder = $this->createMock(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->with('quote_id', 99)->willReturnSelf();
        $builder->method('setPageSize')->willReturnSelf();
        $builder->method('create')->willReturn($this->createMock(SearchCriteria::class));
        $this->reservation = new QuoteStockReservation($this->stock, $this->reader, $this->ledger, $repository, $builder);
        $this->quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getAllItems', 'getId'])->getMock();
        $this->quote->method('getId')->willReturn(99);
        $this->quote->method('getAllItems')->willReturn([$this->createMock(Item::class)]);
    }

    public function testLocksQuoteAndReservesInConsistentOrder(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 2, 'CAB' => 3]);
        $calls = [];
        $this->ledger->expects($this->once())->method('lockQuote')->with(99)->willReturnCallback(
            static function () use (&$calls): void { $calls[] = 'lock'; }
        );
        $this->stock->method('reserveForQuote')->willReturnCallback(
            static function (string $code, int $qty, int $quoteId) use (&$calls): bool {
                $calls[] = [$code, $qty, $quoteId];
                return true;
            }
        );
        $this->connection->expects($this->once())->method('commit');
        $this->reservation->reserve($this->quote);
        $this->assertSame(['lock', ['CAB', 3, 99], ['SOL', 2, 99]], $calls);
        $this->assertTrue($this->reservation->hasReservation($this->quote));
    }

    public function testPartialReservationFailureRollsBackAndDoesNotOwnTheAttempt(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['CAB' => 1, 'SOL' => 1]);
        $this->stock->method('reserveForQuote')->willReturnCallback(static function (string $code): bool {
            if ($code === 'SOL') {
                throw new \RuntimeException('ledger failed');
            }
            return true;
        });
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        try {
            $this->reservation->reserve($this->quote);
            $this->fail('Expected reservation failure');
        } catch (\RuntimeException $exception) {
            $this->assertFalse($this->reservation->hasReservation($this->quote));
        }
    }

    public function testDuplicateSubmissionCannotReserveOrOwnExistingStock(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturn([['codigo' => 'SOL', 'net' => -1]]);
        $this->stock->expects($this->never())->method('reserveForQuote');
        $this->connection->expects($this->once())->method('rollBack');
        $this->expectException(LocalizedException::class);
        $this->reservation->reserve($this->quote);
    }

    public function testQuoteWithoutAtelieItemsDoesNotOpenTransaction(): void
    {
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->reservation->reserve($this->quote);
        $this->assertFalse($this->reservation->hasReservation($this->quote));
    }

    public function testCompensationLocksBeforeReadingAndOnlyReleasesNegativeBalances(): void
    {
        $locked = false;
        $this->ledger->method('lockQuote')->willReturnCallback(static function () use (&$locked): void {
            $locked = true;
        });
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturnCallback(function () use (&$locked): array {
            $this->assertTrue($locked);
            return [['codigo' => 'SOL', 'net' => -2], ['codigo' => 'CAB', 'net' => 0]];
        });
        $this->stock->expects($this->once())->method('compensateForQuote')->with('SOL', 2, 99);
        $this->reservation->compensate($this->quote);
    }

    public function testPersistedOrderNeverReleasesStockEvenAfterSubmissionFailure(): void
    {
        $this->orders->method('getTotalCount')->willReturn(1);
        $this->ledger->expects($this->never())->method('getNetQuantitiesByQuoteId');
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->reservation->compensate($this->quote);
    }

    public function testBalancedLedgerDoesNotReleaseStock(): void
    {
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturn([['codigo' => 'SOL', 'net' => 0]]);
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->reservation->compensate($this->quote);
    }

    public function testPartialCompensationFailureRollsBackAllComponents(): void
    {
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturn([
            ['codigo' => 'CAB', 'net' => -1], ['codigo' => 'SOL', 'net' => -1],
        ]);
        $this->stock->method('compensateForQuote')->willReturnCallback(static function (string $code): void {
            if ($code === 'SOL') {
                throw new \Error('stock failure');
            }
        });
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->expectException(\Error::class);
        $this->reservation->compensate($this->quote);
    }
}
