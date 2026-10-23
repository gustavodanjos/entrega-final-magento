<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteStockReservationTest extends TestCase
{
    /** @var QuoteComponentStockInterface&MockObject */
    private QuoteComponentStockInterface&MockObject $stock;

    /** @var Movimentacao&MockObject */
    private Movimentacao&MockObject $ledger;

    /** @var AdapterInterface&MockObject */
    private AdapterInterface&MockObject $connection;

    /** @var QuoteItemConfigurationReader&MockObject */
    private QuoteItemConfigurationReader&MockObject $reader;

    /** @var QuoteStockReservation */
    private QuoteStockReservation $reservation;

    /** @var Quote */
    private Quote $quote;

    /** @var int */
    private int $outerTransactionLevel = 0;

    protected function setUp(): void
    {
        $this->stock = $this->createMock(QuoteComponentStockInterface::class);
        $this->ledger = $this->createMock(Movimentacao::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('getTransactionLevel')
            ->willReturnCallback(fn (): int => $this->outerTransactionLevel);
        $this->ledger->method('getConnection')->willReturn($this->connection);
        $this->reader = $this->createMock(QuoteItemConfigurationReader::class);
        $this->reservation = new QuoteStockReservation($this->stock, $this->reader, $this->ledger);
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
            static function () use (&$calls): void {
                $calls[] = 'lock';
            }
        );
        $this->stock->method('reserveForQuote')->willReturnCallback(
            static function (string $code, int $qty, int $quoteId) use (&$calls): bool {
                $calls[] = [$code, $qty, $quoteId];
                return true;
            }
        );
        // First call: inside reserve() transaction for hasPendingReservation check → empty.
        // Second call: hasReservation() after commit → reservation exists.
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturnOnConsecutiveCalls(
            [],
            [['codigo' => 'CAB', 'net' => -3], ['codigo' => 'SOL', 'net' => -2]]
        );
        $this->reservation->reserve($this->quote);
        $this->assertSame(['lock', ['CAB', 3, 99], ['SOL', 2, 99]], $calls);
        $this->assertTrue($this->reservation->hasReservation($this->quote));
    }


    public function testReservationOwnershipSurvivesReloadingTheSameQuote(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        // First call: inside reserve() transaction for duplicate check → empty.
        // Second call: in hasReservation() after reserve commit → negative.
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturnOnConsecutiveCalls(
            [],
            [['codigo' => 'SOL', 'net' => -1]]
        );
        $this->reservation->reserve($this->quote);
        $reloaded = $this->createMock(Quote::class);
        $reloaded->method('getId')->willReturn(99);
        $this->assertTrue($this->reservation->hasReservation($reloaded));
    }

    public function testCompensationMakesLedgerBalancedSoHasReservationReturnsFalse(): void
    {
        $reloaded = $this->createMock(Quote::class);
        $reloaded->method('getId')->willReturn(99);
        // First call: inside compensate() → pending reservation.
        // Second call: hasReservation() before compensate → pending.
        // Third call: inside compensate() after compensateForQuote is called → still in same TX read.
        // After compensate() completes, hasReservation() calls ledger → empty.
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturnOnConsecutiveCalls(
            [['codigo' => 'SOL', 'net' => -1]], // hasReservation() before compensate
            [['codigo' => 'SOL', 'net' => -1]], // inside compensate() transaction
            []                                   // hasReservation() after compensate
        );
        $this->stock->method('compensateForQuote')->willReturnCallback(static function (): void {});
        $this->assertTrue($this->reservation->hasReservation($reloaded));
        $this->reservation->compensate($reloaded);
        $this->assertFalse($this->reservation->hasReservation($reloaded));
    }


    public function testRejectedReentryDoesNotReleaseTheOuterGuard(): void
    {
        $this->reservation->beginAttempt($this->quote);
        $reloaded = $this->createMock(Quote::class);
        $reloaded->method('getId')->willReturn(99);
        foreach ([$this->quote, $reloaded] as $quote) {
            try {
                $this->reservation->beginAttempt($quote);
                $this->fail('Expected reentry rejection.');
            } catch (LocalizedException $exception) {
                $this->assertStringContainsString('já está sendo finalizado', $exception->getMessage());
            }
        }
        $this->reservation->endAttempt($this->quote);
        $this->reservation->beginAttempt($reloaded);
        $this->reservation->endAttempt($reloaded);
    }

    public function testDifferentUnsavedQuotesDoNotShareAnAttemptGuard(): void
    {
        $first = $this->createMock(Quote::class);
        $second = $this->createMock(Quote::class);
        $this->reservation->beginAttempt($first);
        $this->reservation->beginAttempt($second);
        $this->reservation->endAttempt($first);
        $this->reservation->endAttempt($second);
        $this->assertFalse($this->reservation->hasReservation($first));
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

    public function testDeadlockRetriesTheEntireOwnedReservationAfterRollback(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['CAB' => 1, 'SOL' => 1]);
        $calls = [];
        $this->stock->expects($this->exactly(4))->method('reserveForQuote')->willReturnCallback(
            static function (string $code) use (&$calls): bool {
                $calls[] = $code;
                if (count($calls) === 2) {
                    throw new DeadlockException('gap lock conflict');
                }
                return true;
            }
        );
        $this->connection->expects($this->exactly(2))->method('beginTransaction');
        $this->ledger->expects($this->exactly(2))->method('lockQuote');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->once())->method('commit');
        // Two calls inside reserve() transactions (duplicate check), one call in hasReservation() after commit.
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturnOnConsecutiveCalls(
            [], // first attempt: no pending reservation
            [], // second attempt (after deadlock retry): no pending reservation
            [['codigo' => 'CAB', 'net' => -1], ['codigo' => 'SOL', 'net' => -1]] // hasReservation() after success
        );
        $this->reservation->reserve($this->quote);
        $this->assertSame(['CAB', 'SOL', 'CAB', 'SOL'], $calls);
        $this->assertTrue($this->reservation->hasReservation($this->quote));
    }

    public function testDeadlockRetriesAreBoundedAndNeverClaimFailedStock(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $deadlock = new DeadlockException('persistent lock conflict');
        $this->stock->expects($this->exactly(5))->method('reserveForQuote')->willThrowException($deadlock);
        $this->connection->expects($this->exactly(5))->method('beginTransaction');
        $this->connection->expects($this->exactly(5))->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturn([]);
        try {
            $this->reservation->reserve($this->quote);
            $this->fail('Expected exhausted deadlock retries.');
        } catch (DeadlockException $exception) {
            $this->assertSame($deadlock, $exception);
            $this->assertFalse($this->reservation->hasReservation($this->quote));
        }
    }

    public function testDeadlockInsideCallerTransactionIsNeverRetried(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $this->outerTransactionLevel = 1;
        $deadlock = new DeadlockException('outer transaction must be restarted by its owner');
        $this->stock->expects($this->once())->method('reserveForQuote')->willThrowException($deadlock);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->expectExceptionObject($deadlock);
        $this->reservation->reserve($this->quote);
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
        $this->ledger->method('hasOrder')->with(99)->willReturn(true);
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
        $this->reader->method('getComponentQuantities')->willReturn(['CAB' => 1, 'SOL' => 1]);
        $this->reservation->reserve($this->quote);
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
        try {
            $this->reservation->compensate($this->quote);
            $this->fail('Expected compensation failure.');
        } catch (\Error $exception) {
            $this->assertTrue($this->reservation->hasReservation($this->quote));
        }
    }
}
