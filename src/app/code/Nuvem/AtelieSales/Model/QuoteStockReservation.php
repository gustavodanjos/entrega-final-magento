<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use Psr\Log\LoggerInterface;

class QuoteStockReservation
{
    private const MAX_TRANSACTION_ATTEMPTS = 10;
    private const DEADLOCK_RETRY_DELAY_MICROSECONDS = 10000;
    private const MAX_DEADLOCK_RETRY_DELAY_MICROSECONDS = 250000;

    public const STATE_ACTIVE = 'active';
    public const STATE_PENDING = 'compensation_pending';
    public const STATE_COMPENSATED = 'compensated';
    public const STATE_COMPLETED = 'completed';
    public const ORDER_ATTEMPT_ID = 'atelie_stock_attempt_id';

    /** @var \WeakMap<Quote, string> */
    private \WeakMap $activeSubmissions;

    /**
     * Dependencies for transactional stock orchestration.
     *
     * @param QuoteComponentStockInterface $componentStock
     * @param QuoteItemConfigurationReader $configurationReader
     * @param Movimentacao $movimentacaoResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly QuoteComponentStockInterface $componentStock,
        private readonly QuoteItemConfigurationReader $configurationReader,
        private readonly Movimentacao $movimentacaoResource,
        private readonly LoggerInterface $logger
    ) {
        $this->activeSubmissions = new \WeakMap();
    }

    /**
     * Acquire ownership; only a persisted failure permits recovery.
     *
     * @param Quote $quote
     * @return string
     */
    public function beginAttempt(Quote $quote): string
    {
        if (isset($this->activeSubmissions[$quote])) {
            $this->rejectSubmission();
        }
        $attemptId = bin2hex(random_bytes(16));
        if ($quote->getId()) {
            $quoteId = (int) $quote->getId();
            $this->executeTransaction($quoteId, function () use ($quoteId, $attemptId): void {
                $attempt = $this->movimentacaoResource->getAttempt($quoteId);
                if (in_array($attempt['state'], [self::STATE_ACTIVE, self::STATE_COMPLETED], true)) {
                    $this->rejectSubmission();
                }
                if ($this->movimentacaoResource->hasOrder($quoteId)) {
                    $this->rejectSubmission();
                }
                if ($attempt['state'] === self::STATE_PENDING) {
                    if (!$attempt['id']) {
                        $this->rejectSubmission();
                    }
                    $this->releaseStock($quoteId);
                }
                if ($this->hasPendingReservation($quoteId)) {
                    $this->rejectSubmission();
                }
                $this->movimentacaoResource->saveAttempt($quoteId, $attemptId, self::STATE_ACTIVE);
            });
        }
        $this->activeSubmissions[$quote] = $attemptId;
        return $attemptId;
    }

    /**
     * Return the invocation identity, never infer ownership from a pending balance.
     *
     * @param Quote $quote
     * @return string|null
     */
    public function getAttemptId(Quote $quote): ?string
    {
        return $this->activeSubmissions[$quote] ?? null;
    }

    /**
     * Release the local context only for its owning invocation.
     *
     * @param Quote $quote
     * @param string $attemptId
     * @return void
     */
    public function endAttempt(Quote $quote, string $attemptId): void
    {
        if ($this->getAttemptId($quote) === $attemptId) {
            unset($this->activeSubmissions[$quote]);
        }
    }

    /**
     * Persist failure before attempting stock recovery.
     *
     * @param Quote $quote
     * @param string $attemptId
     * @return void
     */
    public function markFailed(Quote $quote, string $attemptId): void
    {
        if (!$quote->getId()) {
            return;
        }
        $quoteId = (int) $quote->getId();
        $this->executeTransaction($quoteId, function () use ($quoteId, $attemptId): void {
            $attempt = $this->movimentacaoResource->getAttempt($quoteId);
            if ($attempt['id'] !== $attemptId || $attempt['state'] !== self::STATE_ACTIVE) {
                return;
            }
            $state = $this->movimentacaoResource->hasOrder($quoteId) ? self::STATE_COMPLETED : self::STATE_PENDING;
            $this->movimentacaoResource->saveAttempt($quoteId, $attemptId, $state);
        });
    }

    /**
     * Resolve ownership even when core returns without placing an order.
     *
     * @param Quote $quote
     * @param string $attemptId
     * @return void
     */
    public function completeAttempt(Quote $quote, string $attemptId): void
    {
        $this->markFailed($quote, $attemptId);
        $this->compensate($quote, $attemptId);
    }

    /**
     * Finalize consumption inside the transaction that saves the order.
     *
     * @param int $quoteId
     * @param string $attemptId
     * @return void
     */
    public function completeOrder(int $quoteId, string $attemptId): void
    {
        if ($this->movimentacaoResource->getConnection()->getTransactionLevel() === 0) {
            throw new \LogicException('Stock completion requires the order save transaction.');
        }
        $this->executeTransaction($quoteId, function () use ($quoteId, $attemptId): void {
            $attempt = $this->movimentacaoResource->getAttempt($quoteId);
            if ($attempt['id'] !== $attemptId
                || !in_array($attempt['state'], [self::STATE_ACTIVE, self::STATE_COMPLETED], true)
                || !$this->movimentacaoResource->hasOrder($quoteId)
            ) {
                $this->rejectSubmission();
            }
            if ($attempt['state'] === self::STATE_COMPLETED) {
                return;
            }
            $this->movimentacaoResource->saveAttempt($quoteId, $attemptId, self::STATE_COMPLETED);
        });
    }

    /**
     * Keep failed compensation recoverable without replacing the checkout error.
     *
     * @param Quote $quote
     * @param string $attemptId
     * @param \Throwable $exception
     * @return void
     */
    public function handleFailure(Quote $quote, string $attemptId, \Throwable $exception): void
    {
        try {
            $this->markFailed($quote, $attemptId);
            $this->compensate($quote, $attemptId);
        } catch (\Throwable $compensationException) {
            try {
                $this->logger->critical('Failed to compensate component stock.', [
                    'quote_id' => $quote->getId(),
                    'attempt_id' => $attemptId,
                    'exception' => $compensationException,
                    'submission_exception' => $exception,
                ]);
            } catch (\Throwable) {
                // Logging failures must not replace the checkout exception.
                return;
            }
        }
    }

    /**
     * Acquire the quote lock before changing component stock and its ledger.
     *
     * @param Quote $quote
     * @param string $attemptId
     * @return void
     * @throws LocalizedException
     */
    public function reserve(Quote $quote, string $attemptId): void
    {
        $quoteId = (int) $quote->getId();
        $this->executeTransaction($quoteId, function () use ($quote, $quoteId, $attemptId): void {
            $attempt = $this->movimentacaoResource->getAttempt($quoteId);
            if ($attempt['id'] !== $attemptId || $attempt['state'] !== self::STATE_ACTIVE) {
                $this->rejectSubmission();
            }
            if ($this->movimentacaoResource->hasOrder($quoteId)) {
                $this->rejectSubmission();
            }
            $demand = [];
            foreach ($quote->getAllItems() as $item) {
                foreach ($this->configurationReader->getComponentQuantities($item) as $code => $qty) {
                    $demand[$code] = ($demand[$code] ?? 0) + $qty;
                }
            }
            ksort($demand);
            $balances = $this->getNetQuantitiesByComponent($quoteId);
            if ($balances !== []) {
                $expected = [];
                foreach ($demand as $code => $qty) {
                    $expected[$code] = -$qty;
                }
                if ($balances === $expected) {
                    return;
                }
                $this->rejectSubmission();
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
     * @param string $attemptId
     * @return void
     */
    public function compensate(Quote $quote, string $attemptId): void
    {
        if (!$quote->getId()) {
            return;
        }
        $quoteId = (int) $quote->getId();
        $this->executeTransaction($quoteId, function () use ($quoteId, $attemptId): void {
            $attempt = $this->movimentacaoResource->getAttempt($quoteId);
            if ($attempt['id'] !== $attemptId || $attempt['state'] !== self::STATE_PENDING) {
                return;
            }
            $state = self::STATE_COMPLETED;
            if (!$this->movimentacaoResource->hasOrder($quoteId)) {
                $this->releaseStock($quoteId);
                $state = self::STATE_COMPENSATED;
            }
            $this->movimentacaoResource->saveAttempt($quoteId, $attemptId, $state);
        });
    }

    /**
     * Balance the ledger while the caller holds the quote lock.
     *
     * @param int $quoteId
     * @return void
     */
    private function releaseStock(int $quoteId): void
    {
        $movements = $this->movimentacaoResource->getNetQuantitiesByQuoteId($quoteId);
        usort($movements, static fn (array $left, array $right): int => $left['codigo'] <=> $right['codigo']);
        foreach ($movements as $movement) {
            if ($movement['net'] < 0) {
                $this->componentStock->compensateForQuote($movement['codigo'], -$movement['net'], $quoteId);
            }
        }
    }

    /**
     * Reject attempts that cannot safely acquire stock ownership.
     *
     * @return never
     */
    private function rejectSubmission(): never
    {
        throw new LocalizedException(__('Este carrinho já está sendo finalizado. Atualize a página.'));
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
                $delay = min(
                    self::MAX_DEADLOCK_RETRY_DELAY_MICROSECONDS,
                    self::DEADLOCK_RETRY_DELAY_MICROSECONDS * (2 ** ($attempt - 1))
                );
                usleep(random_int((int) ($delay / 2), $delay));
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
        return $this->getNetQuantitiesByComponent($quoteId) !== [];
    }

    /**
     * Return nonzero ledger balances indexed by component code.
     *
     * @param int $quoteId
     * @return array<string, int>
     */
    private function getNetQuantitiesByComponent(int $quoteId): array
    {
        $balances = [];
        foreach ($this->movimentacaoResource->getNetQuantitiesByQuoteId($quoteId) as $movement) {
            if ($movement['net'] !== 0) {
                $balances[$movement['codigo']] = $movement['net'];
            }
        }
        ksort($balances);
        return $balances;
    }
}
