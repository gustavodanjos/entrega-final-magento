<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Observer\CompensateStockOnQuoteSubmitFailure;
use PHPUnit\Framework\TestCase;

class CompensateStockOnQuoteSubmitFailureTest extends TestCase
{
    public function testMissingQuoteDoesNotMoveStock(): void
    {
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->expects($this->never())->method("compensate");
        (new CompensateStockOnQuoteSubmitFailure($reservation))->execute(new Observer(['event' => new Event()]));
    }

    public function testSubmitsTheEventQuoteToTheReservationService(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(99);
        $reservation = $this->createMock(QuoteStockReservation::class);
        $reservation->method('hasReservation')->willReturn(true);
        $reservation->expects($this->once())->method("compensate")->with($quote);
        (new CompensateStockOnQuoteSubmitFailure($reservation))->execute(new Observer(['event' => new Event(['quote' => $quote])]));
    }
}
