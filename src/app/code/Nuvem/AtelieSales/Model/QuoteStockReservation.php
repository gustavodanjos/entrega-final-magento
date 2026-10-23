<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;

class QuoteStockReservation
{
    private const MAX_TRANSACTION_ATTEMPTS = 5;
    private const DEADLOCK_RETRY_DELAY_MICROSECONDS = 10000;

    /** @var array<string, true> */
    private array $activeSubmissions = [];

    /**
     * @param QuoteComponentStockInterface $componentStock
     * @param QuoteItemConfigurationReader $configurationReader
     * @param Movimentacao $movimentacaoResource
     */
    public function __construct(
        private readonly QuoteComponentStockInterface $componentStock,
        private readonly QuoteItemConfigurationReader $configurationReader,
        private readonly Movimentacao $movimentacaoResource
    ) {
    }

    /**
     * Reentrant submissions must not acquire or compensate another invocation's reservation.
     *
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function beginAttempt(Quote $quote): void
    {
        $key = $this->getAttemptKey($quote);
        if (isset($this->activeSubmissions[$key])) {
            throw new LocalizedException(__('Este carrinho já está sendo finalizado. Atualize a página.'));
        }
        $this->activeSubmissions[$key] = true;
    }

    /**
     * Release only the execution guard so failed compensation remains recoverable.
     *
     * @param Quote $quote
     * @return void
     */
    public function endAttempt(Quote $quote): void
    {
        unset($this->activeSubmissions[$this->getAttemptKey($quote)]);
    }

    /**
     * Acquire the quote lock before changing component stock and its ledger.
     *
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function reserve(Quote $quote): void
    {
        $demand = [];
        foreach ($quote->getAllItems() as $item) {
            foreach ($this->configurationReader->getComponentQuantities($item) as $code => $qty) {
                $demand[$code] = ($demand[$code] ?? 0) + $qty;
            }
        }
        if ($demand === []) {
            return;
        }
        ksort($demand);
        $quoteId = (int) $quote->getId();
        $this->executeTransaction($quoteId, function () use ($quoteId, $demand): void {
            if ($this->movimentacaoResource->hasOrder($quoteId) || $this->hasPendingReservation($quoteId)) {
                throw new LocalizedException(__('Este carrinho já está sendo finalizado. Atualize a página.'));
            }
            foreach ($demand as $code => $qty) {
                $this->componentStock->reserveForQuote($code, $qty, $quoteId);
            }
        });
    }

    /**
     * Restore outstanding stock only when the quote has no persisted order.
     *
     * @param Quote $quote
     * @return void
     */
    public function compensate(Quote $quote): void
    {
        $quoteId = (int) $quote->getId();
        $this->executeTransaction($quoteId, function () use ($quoteId): void {
            // Core also dispatches submit_failure after a persisted order's success observer fails.
            if (!$this->movimentacaoResource->hasOrder($quoteId)) {
                $movements = $this->movimentacaoResource->getNetQuantitiesByQuoteId($quoteId);
                usort($movements, static fn (array $left, array $right): int => $left['codigo'] <=> $right['codigo']);
                foreach ($movements as $movement) {
                    if ($movement['net'] < 0) {
                        $this->componentStock->compensateForQuote($movement['codigo'], -$movement['net'], $quoteId);
                    }
                }
            }
        });
        $this->clearAttempt($quote);
    }

    /**
     * Checks if the quote has an outstanding negative balance in the ledger and no order.
     *
     * @param Quote $quote
     * @return bool
     */
    public function hasReservation(Quote $quote): bool
    {
        $quoteId = (int) $quote->getId();
        if (!$quoteId) {
            return false;
        }

        if ($this->movimentacaoResource->hasOrder($quoteId)) {
            return false;
        }

        return $this->hasPendingReservation($quoteId);
    }

    /**
     * Clear attempt is kept for backward compatibility with callers.
     *
     * @param Quote $quote
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function clearAttempt(Quote $quote): void
    {
        // No-op: ledger balance and hasOrder in database are the single source of truth.
    }

    /**
     * InnoDB gap locks can deadlock even for different quotes; only an owned root transaction is safe to retry.
     *
     * @param int $quoteId
     * @param callable $operation
     * @return void
     * @throws \Throwable
     */
    private function executeTransaction(int $quoteId, callable $operation): void
    {
        $connection = $this->movimentacaoResource->getConnection();
        $canRetry = $connection->getTransactionLevel() === 0;
        for ($attempt = 1;; $attempt++) {
            $connection->beginTransaction();
            try {
                $this->movimentacaoResource->lockQuote($quoteId);
                $operation();
                $connection->commit();
                return;
            } catch (\Throwable $exception) {
                $connection->rollBack();
                $retry = $canRetry && $exception instanceof DeadlockException
                    && $attempt < self::MAX_TRANSACTION_ATTEMPTS;
                if (!$retry) {
                    throw $exception;
                }
                usleep(self::DEADLOCK_RETRY_DELAY_MICROSECONDS * $attempt);
            }
        }
    }

    /**
     * Detect reservations committed by another submission of the same quote.
     *
     * @param int $quoteId
     * @return bool
     */
    private function hasPendingReservation(int $quoteId): bool
    {
        foreach ($this->movimentacaoResource->getNetQuantitiesByQuoteId($quoteId) as $movement) {
            if ($movement['net'] < 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Persisted identity survives reloads without conflating unrelated unsaved quotes.
     *
     * @param Quote $quote
     * @return string
     */
    private function getAttemptKey(Quote $quote): string
    {
        return $quote->getId() ? 'quote:' . $quote->getId() : 'object:' . spl_object_id($quote);
    }
}
