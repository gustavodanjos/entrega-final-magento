<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Quote;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Nuvem\AtelieSales\Plugin\Quote\ProtectStockAttempt;
use PHPUnit\Framework\TestCase;

class ProtectStockAttemptTest extends TestCase
{
    public function testStaleQuoteCannotWriteOwnershipButKeepsNormalFields(): void
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $quote->setData([
            'atelie_stock_attempt_id' => 'old-owner',
            'atelie_stock_attempt_state' => 'active',
            'customer_email' => 'customer@example.test',
        ]);
        $result = (new ProtectStockAttempt())->beforeSave($this->createMock(QuoteResource::class), $quote);
        $this->assertSame([$quote], $result);
        $this->assertSame(['customer_email' => 'customer@example.test'], $quote->getData());
    }
}
