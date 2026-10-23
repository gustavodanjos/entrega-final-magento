<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\QuoteManagement;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use Nuvem\AtelieSales\Plugin\QuoteManagement\CompensateFailedSubmission;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CompensateFailedSubmissionTest extends TestCase
{
    /** @var QuoteComponentStockInterface&MockObject */
    private QuoteComponentStockInterface&MockObject $stock;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface&MockObject $logger;

    /** @var QuoteStockReservation */
    private QuoteStockReservation $reservation;

    /** @var CompensateFailedSubmission */
    private CompensateFailedSubmission $plugin;

    /** @var Quote */
    private Quote $quote;

    /** @var int */
    private int $net = 0;

    /** @var Movimentacao&MockObject */
    private Movimentacao&MockObject $ledger;

    /** @var bool */
    private bool $hasOrder = false;

    protected function setUp(): void
    {
        $this->stock = $this->createMock(QuoteComponentStockInterface::class);
        $connection = $this->createMock(AdapterInterface::class);
        $this->ledger = $this->createMock(Movimentacao::class);
        $this->ledger->method('getConnection')->willReturn($connection);
        $this->ledger->method('getNetQuantitiesByQuoteId')->willReturnCallback(
            fn (): array => $this->net < 0 ? [['codigo' => 'SOL', 'net' => $this->net]] : []
        );
        $this->ledger->method('hasOrder')->willReturnCallback(fn (): bool => $this->hasOrder);
        $reader = $this->createMock(QuoteItemConfigurationReader::class);
        $reader->method('getComponentQuantities')->willReturn(['SOL' => 1]);
        $this->reservation = new QuoteStockReservation($this->stock, $reader, $this->ledger);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->plugin = new CompensateFailedSubmission($this->reservation, $this->logger);
        $this->quote = $this->createQuote();
    }

    public function testFailureBeforeReservationDoesNotCompensate(): void
    {
        $this->stock->expects($this->never())->method('reserveForQuote');
        $this->stock->expects($this->never())->method('compensateForQuote');
        $failure = new \Error('validation failed');
        $this->assertFailure(fn () => $this->submit(static function () use ($failure): never {
            throw $failure;
        }), $failure);
        $this->assertFalse($this->reservation->hasReservation($this->quote));
    }

    public function testFailureAfterReservationCompensatesAndPreservesOriginalError(): void
    {
        $this->expectReservations(1);
        $this->expectCompensations(1);
        $failure = new \Error('later submit_before observer failed');
        $this->assertFailure(fn () => $this->submit(function () use ($failure): never {
            $this->reservation->reserve($this->quote);
            throw $failure;
        }), $failure);
        $this->assertSame(0, $this->net);
        $this->assertFalse($this->reservation->hasReservation($this->quote));
    }

    public function testCoreFailureObserverAndPluginOnlyCompensateOnce(): void
    {
        $this->expectReservations(1);
        $this->expectCompensations(1);
        $failure = new \RuntimeException('order failed');
        $this->assertFailure(fn () => $this->submit(function () use ($failure): never {
            $this->reservation->reserve($this->quote);
            $this->reservation->compensate($this->quote);
            throw $failure;
        }), $failure);
        $this->assertSame(0, $this->net);
    }

    public function testSuccessfulSubmissionHasNoReservationBecauseOrderExists(): void
    {
        $this->expectReservations(1);
        $this->stock->expects($this->never())->method('compensateForQuote');
        $order = new \stdClass();
        $this->assertSame($order, $this->submit(function () use ($order) {
            $this->reservation->reserve($this->quote);
            $this->assertTrue($this->reservation->hasReservation($this->quote));
            $this->hasOrder = true;
            return $order;
        }));
        $this->assertFalse($this->reservation->hasReservation($this->quote));
        $this->assertSame(-1, $this->net);
    }

    /** @dataProvider quoteReloads */
    public function testCaughtReentryCannotCompensateOrClearTheOuterAttempt(bool $reload): void
    {
        $this->expectReservations(1);
        $this->stock->expects($this->never())->method('compensateForQuote');
        $innerQuote = $reload ? $this->createQuote() : $this->quote;
        $innerRan = false;
        $order = new \stdClass();
        $result = $this->submit(function () use ($innerQuote, &$innerRan, $order) {
            $this->reservation->reserve($this->quote);
            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $this->submit(function () use (&$innerRan): void {
                        $innerRan = true;
                    }, $innerQuote);
                    $this->fail('Expected reentry rejection.');
                } catch (LocalizedException $exception) {
                    $this->assertStringContainsString('já está sendo finalizado', $exception->getMessage());
                }
                $this->assertTrue($this->reservation->hasReservation($innerQuote));
            }
            $this->hasOrder = true;
            return $order;
        });
        $this->assertSame($order, $result);
        $this->assertFalse($innerRan);
        $this->assertSame(-1, $this->net);
        $this->assertFalse($this->reservation->hasReservation($innerQuote));
    }

    public static function quoteReloads(): array
    {
        return [[false], [true]];
    }

    public function testUncaughtReentryIsCompensatedOnlyByTheOuterAttempt(): void
    {
        $this->expectReservations(1);
        $this->expectCompensations(1);
        try {
            $this->submit(function (): void {
                $this->reservation->reserve($this->quote);
                $this->submit(static function (): never {
                    throw new \LogicException('Inner submit must not run.');
                }, $this->createQuote());
            });
            $this->fail('Expected reentry rejection.');
        } catch (LocalizedException $exception) {
            $this->assertSame(0, $this->net);
            $this->assertFalse($this->reservation->hasReservation($this->quote));
        }
    }

    public function testPendingCompensationIsRecoveredBeforeReservingAgain(): void
    {
        $this->expectReservations(2);
        $compensations = 0;
        $this->stock->expects($this->exactly(2))->method('compensateForQuote')->willReturnCallback(
            function () use (&$compensations): void {
                if (++$compensations === 1) {
                    throw new \RuntimeException('database unavailable');
                }
                $this->net = 0;
            }
        );
        $this->logger->expects($this->once())->method('critical');
        $failure = new \RuntimeException('checkout failed');
        $this->assertFailure(fn () => $this->submit(function () use ($failure): never {
            $this->reservation->reserve($this->quote);
            throw $failure;
        }), $failure);
        $this->assertTrue($this->reservation->hasReservation($this->quote));
        $reloaded = $this->createQuote();
        $order = new \stdClass();
        $this->assertSame($order, $this->submit(function () use ($reloaded, $order) {
            $this->assertSame(0, $this->net);
            $this->assertFalse($this->reservation->hasReservation($reloaded));
            $this->reservation->reserve($reloaded);
            $this->hasOrder = true;
            return $order;
        }, $reloaded));
        $this->assertSame(-1, $this->net);
        $this->assertFalse($this->reservation->hasReservation($this->quote));
    }

    public function testFailedRecoveryBlocksNewSubmissionAndPreservesPendingReservation(): void
    {
        $this->expectReservations(1);
        $compensationFailure = new \RuntimeException('database unavailable');
        $this->stock->expects($this->exactly(2))->method('compensateForQuote')
            ->willThrowException($compensationFailure);
        $this->logger->expects($this->exactly(2))->method('critical');
        $failure = new \RuntimeException('checkout failed');
        $this->assertFailure(fn () => $this->submit(function () use ($failure): never {
            $this->reservation->reserve($this->quote);
            throw $failure;
        }), $failure);
        $this->assertFailure(fn () => $this->submit(static function (): never {
            throw new \LogicException('New submission must not run before recovery.');
        }, $this->createQuote()), $compensationFailure);
        $this->assertTrue($this->reservation->hasReservation($this->quote));
        $this->assertSame(-1, $this->net);
        $this->reservation->beginAttempt($this->quote);
        $this->reservation->endAttempt($this->quote);
    }

    public function testReusingSuccessfulQuoteDoesNotCompensatePersistedOrder(): void
    {
        $this->expectReservations(1);
        $this->stock->expects($this->never())->method('compensateForQuote');
        $this->submit(function (): void {
            $this->reservation->reserve($this->quote);
            $this->hasOrder = true;
        });
        $failure = new \RuntimeException('duplicate order');
        $this->assertFailure(fn () => $this->submit(static function () use ($failure): never {
            throw $failure;
        }, $this->createQuote()), $failure);
        $this->assertSame(-1, $this->net);
    }

    private function expectReservations(int $count): void
    {
        $this->stock->expects($this->exactly($count))->method('reserveForQuote')->willReturnCallback(function (): bool {
            $this->net--;
            return true;
        });
    }

    private function expectCompensations(int $count): void
    {
        $this->stock->expects($this->exactly($count))->method('compensateForQuote')
            ->willReturnCallback(function (): void {
                $this->net = 0;
            });
    }

    private function createQuote(): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getAllItems'])->getMock();
        $quote->method('getId')->willReturn(99);
        $quote->method('getAllItems')->willReturn([$this->createMock(Item::class)]);
        return $quote;
    }

    private function submit(callable $proceed, ?Quote $quote = null): mixed
    {
        return $this->plugin->aroundSubmit($this->createMock(QuoteManagement::class), $proceed, $quote ?? $this->quote);
    }

    private function assertFailure(callable $action, \Throwable $expected): void
    {
        try {
            $action();
        } catch (\Throwable $actual) {
            $this->assertSame($expected, $actual);
            return;
        }
        $this->fail('Expected submission failure.');
    }
}
