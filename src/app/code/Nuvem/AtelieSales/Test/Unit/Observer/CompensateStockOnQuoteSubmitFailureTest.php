<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Observer\CompensateStockOnQuoteSubmitFailure;
use PHPUnit\Framework\TestCase;
use Nuvem\AtelieSales\Plugin\QuoteManagement\CompensateFailedSubmission;

class CompensateStockOnQuoteSubmitFailureTest extends TestCase
{
    public function testMissingQuoteDoesNotMoveStock(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $submission = $this->createMock(CompensateFailedSubmission::class);
        $submission->expects($this->never())->method("handleFailure");
        (new CompensateStockOnQuoteSubmitFailure($reservation, $submission))->execute(new Observer(['event' => new Event()]));
    }

    public function testSubmitsTheEventQuoteToTheReservationService(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(99);
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('getAttemptId')->willReturn('owned');
        $failure = new \RuntimeException('checkout failed');
        $submission = $this->createMock(CompensateFailedSubmission::class);
        $submission->expects($this->once())->method('handleFailure')->with($quote, 'owned', $failure);
        (new CompensateStockOnQuoteSubmitFailure($reservation, $submission))->execute(new Observer(['event' => new Event(['quote' => $quote, 'exception' => $failure])]));
    }
}
