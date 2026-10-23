<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\QuoteManagement;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Plugin\QuoteManagement\CompensateFailedSubmission;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CompensateFailedSubmissionTest extends TestCase
{
    public function testRejectedAcquisitionNeverRunsOrCompensatesSubmission(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $failure = new \RuntimeException('active attempt');
        $reservation->method('beginAttempt')->willThrowException($failure);
        foreach (['markFailed', 'compensate', 'endAttempt'] as $method) {
            $reservation->expects($this->never())->method($method);
        }
        $this->expectExceptionObject($failure);
        $this->submit($reservation, static function (): never {
            throw new \LogicException('Rejected submission must not run.');
        });
    }

    public function testSuccessfulSubmissionFinalizesAndReleasesItsContext(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('beginAttempt')->willReturn('owned');
        $quote = $this->createMock(Quote::class);
        $reservation->expects($this->once())->method('completeAttempt')->with($quote, 'owned');
        $reservation->expects($this->once())->method('endAttempt')->with($quote, 'owned');
        $reservation->expects($this->never())->method('compensate');
        $this->assertSame('order', $this->submit($reservation, static fn () => 'order', $quote));
    }

    public function testFailureMarksBeforeCompensatingAndPreservesOriginalError(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('beginAttempt')->willReturn('owned');
        $quote = $this->createMock(Quote::class);
        $calls = [];
        $reservation->expects($this->once())->method('markFailed')->with($quote, 'owned')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'pending';
            });
        $reservation->expects($this->once())->method('compensate')->with($quote, 'owned')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'compensate';
            });
        $reservation->expects($this->once())->method('endAttempt')->with($quote, 'owned');
        $failure = new \Error('submit_before failure');
        try {
            $this->submit($reservation, static function () use ($failure): never {
                throw $failure;
            }, $quote);
            $this->fail('Expected checkout failure.');
        } catch (\Error $actual) {
            $this->assertSame($failure, $actual);
            $this->assertSame(['pending', 'compensate'], $calls);
        }
    }

    /** @dataProvider failedRecoverySteps */
    public function testRecoveryAndLoggerFailuresDoNotReplaceCheckoutException(string $step): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('beginAttempt')->willReturn('owned');
        $reservation->method($step)->willThrowException(new \RuntimeException('database unavailable'));
        if ($step === 'markFailed') {
            $reservation->expects($this->never())->method('compensate');
        }
        $reservation->expects($this->once())->method('endAttempt');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical')->willThrowException(new \Error('logger unavailable'));
        $failure = new \RuntimeException('original checkout failure');
        $this->expectExceptionObject($failure);
        $this->submit($reservation, static function () use ($failure): never {
            throw $failure;
        }, logger: $logger);
    }

    public static function failedRecoverySteps(): array
    {
        return [['markFailed'], ['compensate']];
    }

    private function submit(
        QuoteStockReservation $reservation,
        callable $proceed,
        ?Quote $quote = null,
        ?LoggerInterface $logger = null
    ): mixed {
        $plugin = new CompensateFailedSubmission($reservation, $logger ?? $this->createMock(LoggerInterface::class));
        return $plugin->aroundSubmit($this->createMock(QuoteManagement::class), $proceed,
            $quote ?? $this->createMock(Quote::class));
    }
}
