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
use Psr\Log\LoggerInterface;

class QuoteStockReservationTest extends TestCase
{
    private QuoteComponentStockInterface&MockObject $stock;
    private Movimentacao&MockObject $ledger;
    private AdapterInterface&MockObject $connection;
    private QuoteItemConfigurationReader&MockObject $reader;
    private QuoteStockReservation $reservation;
    private Quote $quote;
    private array $attempt = ['id' => null, 'state' => null];
    private array $balances = [];
    private bool $hasOrder = false;
    private int $transactionLevel = 0;
    private array $snapshots = [];
    private bool $failAfterCommit = false;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->stock = $this->createMock(QuoteComponentStockInterface::class);
        $this->ledger = $this->createMock(Movimentacao::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->ledger->method('getConnection')->willReturn($this->connection);
        $this->connection->method('getTransactionLevel')->willReturnCallback(fn () => $this->transactionLevel);
        $this->connection->method('beginTransaction')->willReturnCallback(function () {
            $this->snapshots[] = [$this->attempt, $this->balances];
            $this->transactionLevel++;
            return $this->connection;
        });
        $this->connection->method('commit')->willReturnCallback(function () {
            array_pop($this->snapshots);
            $this->transactionLevel--;
            if ($this->failAfterCommit && $this->transactionLevel === 0) {
                throw new \Error('after commit callback failed');
            }
            return $this->connection;
        });
        $this->connection->method('rollBack')->willReturnCallback(function () {
            [$this->attempt, $this->balances] = array_pop($this->snapshots);
            $this->transactionLevel--;
            return $this->connection;
        });
        $this->ledger->method('getAttempt')->willReturnCallback(fn () => $this->attempt);
        $this->ledger->method('saveAttempt')->willReturnCallback(function ($quoteId, $id, $state): void {
            $this->attempt = ['id' => $id, 'state' => $state];
        });
        $this->ledger->method('hasOrder')->willReturnCallback(fn () => $this->hasOrder);
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturnCallback(fn () => $this->balances);
        $this->reader = $this->createMock(QuoteItemConfigurationReader::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->reservation = $this->newService();
        $this->quote = $this->createMock(Quote::class);
        $this->quote->method('getId')->willReturn(99);
        $this->quote->method('getAllItems')->willReturn([$this->createMock(Item::class)]);
    }

    public function testActiveAttemptRejectsAnotherServiceWithoutReleasingStock(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->stock->expects($this->never())->method('compensateForQuote');
        try {
            $this->newService()->beginAttempt(clone $this->quote);
            $this->fail('Expected concurrent submission rejection.');
        } catch (LocalizedException) {
            $this->assertSame(['id' => $id, 'state' => 'active'], $this->attempt);
            $this->assertTrue($this->reservation->hasReservation($this->quote));
        }
    }

    public function testInvocationTokenIsNotSharedWithAnotherQuoteObject(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $otherQuote = clone $this->quote;
        $this->assertNull($this->reservation->getAttemptId($otherQuote));
        $this->assertSame($id, $this->reservation->getAttemptId($this->quote));
        $this->reservation->handleFailure($this->quote, $id, new \RuntimeException('checkout failed'));
        $this->reservation->endAttempt($this->quote, $id);
        $newId = $this->reservation->beginAttempt($otherQuote);
        $this->assertNull($this->reservation->getAttemptId($this->quote));
        $this->assertSame($newId, $this->reservation->getAttemptId($otherQuote));
    }

    public function testOrderAndCompletionAreRolledBackTogether(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->connection->beginTransaction();
        $this->hasOrder = true;
        $this->reservation->completeOrder(99, $id);
        $this->assertSame('completed', $this->attempt['state']);
        $this->assertSame(1, $this->transactionLevel);

        $this->connection->rollBack();
        $this->hasOrder = false;
        $this->assertSame(['id' => $id, 'state' => 'active'], $this->attempt);
        $this->reservation->handleFailure($this->quote, $id, new \RuntimeException('order save failed'));
        $this->assertSame('compensated', $this->attempt['state']);
    }

    public function testCommittedOrderCompletionIsIdempotentAndNeverCompensates(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->connection->beginTransaction();
        $this->hasOrder = true;
        $this->reservation->completeOrder(99, $id);
        $this->reservation->completeOrder(99, $id);
        $this->connection->commit();
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->reservation->handleFailure($this->quote, $id, new \RuntimeException('after commit'));
        $this->assertSame('completed', $this->attempt['state']);
    }

    public function testOrderCompletionRequiresItsResourceTransaction(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->expectException(\LogicException::class);
        $this->reservation->completeOrder(99, $id);
    }

    public function testOrderFromAnOldAttemptCannotFinalizeANewOwner(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->hasOrder = true;
        $this->connection->beginTransaction();
        try {
            $this->reservation->completeOrder(99, 'old-owner');
            $this->fail('Expected stale order rejection.');
        } catch (LocalizedException) {
            $this->assertSame(['id' => $id, 'state' => 'active'], $this->attempt);
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testCompensationAndLoggingFailuresLeaveADurablePendingAttempt(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->stock->method('compensateForQuote')->willThrowException(new \RuntimeException('stock unavailable'));
        $this->logger->expects($this->once())->method('critical')->willThrowException(new \Error('logger unavailable'));
        $this->reservation->handleFailure($this->quote, $id, new \RuntimeException('original checkout failure'));
        $this->assertSame(['id' => $id, 'state' => 'compensation_pending'], $this->attempt);
        $this->assertSame([['codigo' => 'SOL', 'net' => -1]], $this->balances);
        $this->assertSame(0, $this->transactionLevel);
    }

    public function testRejectedReentryCannotClearOuterContext(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        try {
            $this->reservation->beginAttempt(clone $this->quote);
            $this->fail('Expected reentry rejection.');
        } catch (LocalizedException) {
            $this->reservation->endAttempt($this->quote, 'another-attempt');
            $this->assertSame($id, $this->reservation->getAttemptId($this->quote));
        }
    }

    public function testLegacyBalanceWithoutOwnershipBlocksCheckout(): void
    {
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->expectException(LocalizedException::class);
        $this->reservation->beginAttempt($this->quote);
    }

    public function testReservationRequiresMatchingActiveOwnerAndSortsComponents(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 2, 'CAB' => 3]);
        $calls = [];
        $this->stock->method('reserveForQuote')->willReturnCallback(
            static function ($code, $qty, $quoteId) use (&$calls): bool {
                $calls[] = [$code, $qty, $quoteId];
                return true;
            }
        );
        $this->reservation->reserve($this->quote, $id);
        $this->assertSame([['CAB', 3, 99], ['SOL', 2, 99]], $calls);
        $this->expectException(LocalizedException::class);
        $this->reservation->reserve($this->quote, 'wrong-owner');
    }

    public function testRepeatedReservationForSameAttemptIsAnIdempotentNoOp(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->reader->method('getComponentQuantities')->willReturn(['CAB' => 2, 'SOL' => 1]);
        $this->balances = [['codigo' => 'SOL', 'net' => -1], ['codigo' => 'CAB', 'net' => -2]];
        $this->stock->expects($this->never())->method('reserveForQuote');
        $this->reservation->reserve($this->quote, $id);
    }

    public function testRepeatedReservationWithDifferentDemandIsRejected(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 2]);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->stock->expects($this->never())->method('reserveForQuote');

        $this->expectException(LocalizedException::class);
        $this->reservation->reserve($this->quote, $id);
    }

    public function testCompensationRequiresPersistedFailureAndIsIdempotent(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -2], ['codigo' => 'CAB', 'net' => 0]];
        $this->reservation->compensate($this->quote, $id);
        $this->assertSame('active', $this->attempt['state']);
        $this->stock->expects($this->once())->method('compensateForQuote')->with('SOL', 2, 99)
            ->willReturnCallback(function (): void {
                $this->balances = [['codigo' => 'SOL', 'net' => 0]];
            });
        $this->reservation->markFailed($this->quote, $id);
        $this->newService()->compensate(clone $this->quote, $id);
        $this->reservation->compensate($this->quote, $id);
        $this->assertSame('compensated', $this->attempt['state']);
        $this->assertFalse($this->reservation->hasReservation($this->quote));
    }

    public function testRecoveryWorksAfterServiceAndQuoteReload(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->reservation->markFailed($this->quote, $id);
        $this->stock->expects($this->once())->method('compensateForQuote')->with('SOL', 1, 99)
            ->willReturnCallback(function (): void {
                $this->balances = [];
            });
        $newId = $this->newService()->beginAttempt(clone $this->quote);
        $this->assertNotSame($id, $newId);
        $this->assertSame(['id' => $newId, 'state' => 'active'], $this->attempt);
    }

    public function testFailedRecoveryKeepsPendingOwnershipAndBlocksNewAttempt(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->reservation->markFailed($this->quote, $id);
        $failure = new \RuntimeException('compensation unavailable');
        $this->stock->method('compensateForQuote')->willThrowException($failure);
        try {
            $this->newService()->beginAttempt(clone $this->quote);
            $this->fail('Expected recovery failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($failure, $exception);
            $this->assertSame(['id' => $id, 'state' => 'compensation_pending'], $this->attempt);
        }
    }

    public function testDelayedFailureCannotCompensateNewAttempt(): void
    {
        $oldId = $this->reservation->beginAttempt($this->quote);
        $this->reservation->markFailed($this->quote, $oldId);
        $this->reservation->compensate($this->quote, $oldId);
        $this->reservation->endAttempt($this->quote, $oldId);
        $newId = $this->reservation->beginAttempt($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->reservation->markFailed($this->quote, $oldId);
        $this->reservation->compensate($this->quote, $oldId);
        $this->assertSame(['id' => $newId, 'state' => 'active'], $this->attempt);
    }

    public function testPersistedOrderIsPreservedDuringPendingCompensation(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->reservation->markFailed($this->quote, $id);
        $this->hasOrder = true;
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->reservation->compensate($this->quote, $id);
        $this->assertSame('completed', $this->attempt['state']);
    }

    public function testCompletionPreservesPersistedOrder(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->hasOrder = true;
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->reservation->completeAttempt($this->quote, $id);
        $this->assertSame('completed', $this->attempt['state']);
    }

    public function testReturnWithoutOrderRestoresOutstandingStock(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->stock->expects($this->once())->method('compensateForQuote')->with('SOL', 1, 99);
        $this->reservation->completeAttempt($this->quote, $id);
        $this->assertSame('compensated', $this->attempt['state']);
    }

    public function testPartialCompensationFailureRollsBackBalancesAndKeepsPending(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->reservation->markFailed($this->quote, $id);
        $this->balances = [['codigo' => 'CAB', 'net' => -1], ['codigo' => 'SOL', 'net' => -1]];
        $before = $this->balances;
        $this->stock->method('compensateForQuote')->willReturnCallback(function ($code): void {
            if ($code === 'SOL') {
                throw new \Error('stock unavailable');
            }
            $this->balances[0]['net'] = 0;
        });
        try {
            $this->reservation->compensate($this->quote, $id);
            $this->fail('Expected compensation failure.');
        } catch (\Error) {
            $this->assertSame($before, $this->balances);
            $this->assertSame('compensation_pending', $this->attempt['state']);
        }
    }

    public function testDeadlockRetriesWholeReservation(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->reader->method('getComponentQuantities')->willReturn(['CAB' => 1, 'SOL' => 1]);
        $calls = [];
        $this->stock->expects($this->exactly(4))->method('reserveForQuote')->willReturnCallback(
            static function ($code) use (&$calls): bool {
                $calls[] = $code;
                if (count($calls) === 2) {
                    throw new DeadlockException('conflict');
                }
                return true;
            }
        );
        $this->reservation->reserve($this->quote, $id);
        $this->assertSame(['CAB', 'SOL', 'CAB', 'SOL'], $calls);
    }

    public function testDeadlockRetriesAreBounded(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $this->stock->expects($this->exactly(10))->method('reserveForQuote')
            ->willThrowException(new DeadlockException('conflict'));
        $this->expectException(DeadlockException::class);
        $this->reservation->reserve($this->quote, $id);
    }

    public function testNestedTransactionDeadlockIsNotRetried(): void
    {
        $id = $this->reservation->beginAttempt($this->quote);
        $this->transactionLevel = 1;
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $this->stock->expects($this->once())->method('reserveForQuote')
            ->willThrowException(new DeadlockException('outer transaction'));
        $this->expectException(DeadlockException::class);
        $this->reservation->reserve($this->quote, $id);
    }

    public function testSubmissionKeepsReservationOpenUntilTheOrderIsSaved(): void
    {
        $this->connection->expects($this->once())->method('query')
            ->with('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        $this->reservation->reserve($this->quote, $id);
        $this->assertSame(1, $this->transactionLevel);
        $this->assertSame('active', $this->attempt['state']);
        $this->hasOrder = true;
        $this->connection->beginTransaction();
        $this->reservation->completeOrder(99, $id);
        $this->connection->commit();
        $this->assertSame(1, $this->transactionLevel);
        $this->reservation->commitSubmission(99, $id);
        $this->assertSame(0, $this->transactionLevel);
        $this->assertSame('completed', $this->attempt['state']);
        $this->reservation->endAttempt($this->quote, $id);
        $this->assertSame('completed', $this->attempt['state']);
    }

    public function testFailedOrderRollsBackTheWholeSubmissionAndOwnership(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        $this->stock->method('reserveForQuote')->willReturnCallback(function (): bool {
            $this->balances = [['codigo' => 'SOL', 'net' => -1]];
            return true;
        });
        $this->reservation->reserve($this->quote, $id);
        $this->reservation->rollbackSubmission($id);
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->reservation->handleFailure($this->quote, $id, new \RuntimeException('order save failed'));
        $this->assertSame(['id' => null, 'state' => null], $this->attempt);
        $this->assertSame([], $this->balances);
        $this->assertSame(0, $this->transactionLevel);
    }

    public function testFailureBeforeOrderPlacementCommitsABalancedCompensation(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        $this->balances = [['codigo' => 'SOL', 'net' => -1]];
        $this->stock->expects($this->once())->method('compensateForQuote')->willReturnCallback(function (): void {
            $this->balances = [['codigo' => 'SOL', 'net' => 0]];
        });
        $this->reservation->handleFailure($this->quote, $id, new \RuntimeException('submit_before'));
        $this->assertSame('compensated', $this->attempt['state']);
        $this->assertSame(0, $this->balances[0]['net']);
        $this->assertSame(0, $this->transactionLevel);
    }

    public function testReentryDoesNotRollbackTheOwnersOpenTransaction(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        try {
            $this->reservation->beginSubmission(clone $this->quote);
            $this->fail('Expected reentry rejection.');
        } catch (LocalizedException) {
            $this->assertSame(1, $this->transactionLevel);
            $this->assertSame(['id' => $id, 'state' => 'active'], $this->attempt);
        } finally {
            $this->reservation->endAttempt($this->quote, $id);
        }
        $this->assertSame(0, $this->transactionLevel);
    }

    public function testSubmissionCannotCommitBeforeTheOrderExists(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        try {
            $this->reservation->commitSubmission(99, $id);
            $this->fail('Expected missing order rejection.');
        } catch (LocalizedException) {
            $this->assertSame(1, $this->transactionLevel);
        } finally {
            $this->reservation->endAttempt($this->quote, $id);
        }
    }

    public function testSubmissionRollbackLeavesTheCallersTransactionOpen(): void
    {
        $this->connection->expects($this->never())->method('query');
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $this->connection->beginTransaction();
        $id = $this->reservation->beginSubmission($this->quote);
        $this->reservation->rollbackSubmission($id);
        $this->assertSame(1, $this->transactionLevel);
        $this->connection->rollBack();
    }

    public function testOrdinaryQuoteDoesNotOpenATransaction(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn([]);
        $this->assertNull($this->reservation->beginSubmission($this->quote));
        $this->assertSame(0, $this->transactionLevel);
        $this->assertSame(['id' => null, 'state' => null], $this->attempt);
    }

    public function testCallbackFailureAfterCommitNeverRollsBackOrCompensatesTheOrder(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        $this->hasOrder = true;
        $this->reservation->completeOrder(99, $id);
        $this->failAfterCommit = true;
        try {
            $this->reservation->commitSubmission(99, $id);
            $this->fail('Expected commit callback failure.');
        } catch (\Error $exception) {
            $this->failAfterCommit = false;
            $this->stock->expects($this->never())->method('compensateForQuote');
            $this->reservation->rollbackSubmission($id);
            $this->reservation->handleFailure($this->quote, $id, $exception);
            $this->assertSame('completed', $this->attempt['state']);
            $this->assertSame(0, $this->transactionLevel);
        }
    }

    public function testMissingOrderCommitBoundaryCannotBeSilentlyConfirmedAtTheEndOfSubmit(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        $this->hasOrder = true;
        $this->reservation->completeOrder(99, $id);
        try {
            $this->reservation->completeAttempt($this->quote, $id);
            $this->fail('Expected missing commit boundary rejection.');
        } catch (\LogicException $exception) {
            $this->reservation->handleFailure($this->quote, $id, $exception);
            $this->assertSame(['id' => null, 'state' => null], $this->attempt);
            $this->assertSame(0, $this->transactionLevel);
        }
    }

    public function testFailureForAnotherQuoteCannotCloseTheOwnersTransaction(): void
    {
        $this->reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $id = $this->reservation->beginSubmission($this->quote);
        $otherQuote = $this->createMock(Quote::class);
        $otherQuote->method('getId')->willReturn(100);
        $this->reservation->handleFailure($otherQuote, $id, new \RuntimeException('unrelated quote'));
        $this->assertSame(1, $this->transactionLevel);
        $this->assertSame(['id' => $id, 'state' => 'active'], $this->attempt);
        $this->reservation->endAttempt($this->quote, $id);
    }

    private function newService(): QuoteStockReservation
    {
        return new QuoteStockReservation($this->stock, $this->reader, $this->ledger, $this->logger);
    }
}
