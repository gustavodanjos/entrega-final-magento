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
    /** @dataProvider failureOwnership */
    public function testFailureOnlyCompensatesTheAttemptThatReserved(bool $ownsReservation): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $clearAttemptCount = 0;
        $reservation->method('hasReservation')->willReturn($ownsReservation);
        $reservation->expects($ownsReservation ? $this->once() : $this->never())
            ->method('compensate')
            ->willReturnCallback(function (Quote $quote) use ($reservation): void {
                $reservation->clearAttempt($quote);
            });
        $reservation->expects($ownsReservation ? $this->once() : $this->never())
            ->method('clearAttempt')
            ->willReturnCallback(function () use (&$clearAttemptCount): void {
                $clearAttemptCount++;
            });
        $plugin = new CompensateFailedSubmission($reservation, $this->createMock(LoggerInterface::class));
        $failure = new \Error('later submit_before observer failed');
        $this->expectException(\Error::class);
        $this->expectExceptionMessage($failure->getMessage());
        $quote = $this->createMock(Quote::class);
        $plugin->aroundSubmit(
            $this->createMock(QuoteManagement::class),
            function () use ($failure, &$clearAttemptCount): never {
                $this->assertSame(0, $clearAttemptCount);
                throw $failure;
            },
            $quote
        );
    }

    public static function failureOwnership(): array
    {
        return [[true], [false]];
    }

    public function testCompensationFailureIsLoggedWithoutReplacingOriginalError(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('hasReservation')->willReturn(true);
        $reservation->method('compensate')->willThrowException(new \RuntimeException('database unavailable'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');
        $reservation->expects($this->never())->method('clearAttempt');
        $plugin = new CompensateFailedSubmission($reservation, $logger);
        $failure = new \RuntimeException('order failed');
        $this->expectExceptionObject($failure);
        $plugin->aroundSubmit(
            $this->createMock(QuoteManagement::class),
            static function () use ($failure): never { throw $failure; },
            $this->createMock(Quote::class)
        );
    }

    public function testSuccessfulSubmissionPreservesTheOrderAndClearsTheAttempt(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $clearAttemptCount = 0;
        $reservation->expects($this->never())->method('compensate');
        $reservation->expects($this->once())
            ->method('clearAttempt')
            ->willReturnCallback(function () use (&$clearAttemptCount): void {
                $clearAttemptCount++;
            });
        $plugin = new CompensateFailedSubmission($reservation, $this->createMock(LoggerInterface::class));
        $order = new \stdClass();
        $this->assertSame($order, $plugin->aroundSubmit(
            $this->createMock(QuoteManagement::class),
            function () use ($order, &$clearAttemptCount) {
                $this->assertSame(0, $clearAttemptCount);
                return $order;
            },
            $this->createMock(Quote::class)
        ));
        $this->assertSame(1, $clearAttemptCount);
    }
}
