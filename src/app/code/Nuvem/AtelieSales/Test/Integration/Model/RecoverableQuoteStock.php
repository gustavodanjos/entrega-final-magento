<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Integration\Model;

use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use RuntimeException;

class RecoverableQuoteStock implements QuoteComponentStockInterface
{
    /** @var bool */
    public bool $failCompensation = true;

    public function __construct(private readonly QuoteComponentStockInterface $stock)
    {
    }

    public function reserveForQuote(string $componentCode, int $qty, int $quoteId): bool
    {
        return $this->stock->reserveForQuote($componentCode, $qty, $quoteId);
    }

    public function compensateForQuote(string $componentCode, int $qty, int $quoteId): void
    {
        if ($this->failCompensation) {
            throw new RuntimeException('F2.3 integration: compensation unavailable.');
        }
        $this->stock->compensateForQuote($componentCode, $qty, $quoteId);
    }
}
