<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\QuoteManagement;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Plugin\QuoteManagement\CompensateFailedSubmission;
use PHPUnit\Framework\TestCase;

class CompensateFailedSubmissionTest extends TestCase
{
    public function testRejectedAcquisitionNeverRunsOrCompensatesSubmission(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $failure = new \RuntimeException('active attempt');
        $reservation->method('beginSubmission')->willThrowException($failure);
        foreach (['handleFailure', 'endAttempt'] as $method) {
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
        $reservation->method('beginSubmission')->willReturn('owned');
        $quote = $this->createMock(Quote::class);
        $reservation->expects($this->once())->method('completeAttempt')->with($quote, 'owned');
        $reservation->expects($this->once())->method('endAttempt')->with($quote, 'owned');
        $reservation->expects($this->never())->method('handleFailure');
        $this->assertSame('order', $this->submit($reservation, static fn () => 'order', $quote));
    }

    public function testFailureUsesItsOriginalAttemptAndPreservesOriginalError(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('beginSubmission')->willReturn('owned');
        $quote = $this->createMock(Quote::class);
        $reservation->expects($this->once())->method('endAttempt')->with($quote, 'owned');
        $failure = new \Error('submit_before failure');
        $reservation->expects($this->once())->method('handleFailure')->with($quote, 'owned', $failure);
        try {
            $this->submit($reservation, static function () use ($failure): never {
                throw $failure;
            }, $quote);
            $this->fail('Expected checkout failure.');
        } catch (\Error $actual) {
            $this->assertSame($failure, $actual);
        }
    }

    public function testOrdinaryQuoteBypassesStockLifecycle(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('beginSubmission')->willReturn(null);
        foreach (['completeAttempt', 'handleFailure', 'endAttempt'] as $method) {
            $reservation->expects($this->never())->method($method);
        }
        $this->assertSame('ordinary order', $this->submit($reservation, static fn () => 'ordinary order'));
    }

    private function submit(
        QuoteStockReservation $reservation,
        callable $proceed,
        ?Quote $quote = null
    ): mixed {
        $plugin = new CompensateFailedSubmission($reservation);
        return $plugin->aroundSubmit($this->createMock(QuoteManagement::class), $proceed,
            $quote ?? $this->createMock(Quote::class));
    }
}
