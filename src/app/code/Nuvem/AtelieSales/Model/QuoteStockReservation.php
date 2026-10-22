<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;

class QuoteStockReservation
{
    private const RESERVED_FLAG = 'atelie_stock_reserved';

    /**
     * @param QuoteComponentStockInterface $componentStock
     * @param QuoteItemConfigurationReader $configurationReader
     * @param Movimentacao $movimentacaoResource
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly QuoteComponentStockInterface $componentStock,
        private readonly QuoteItemConfigurationReader $configurationReader,
        private readonly Movimentacao $movimentacaoResource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
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
        $connection = $this->movimentacaoResource->getConnection();
        $connection->beginTransaction();
        try {
            $this->movimentacaoResource->lockQuote($quoteId);
            if ($this->hasOrder($quoteId) || $this->hasPendingReservation($quoteId)) {
                throw new LocalizedException(__('Este carrinho já está sendo finalizado. Atualize a página.'));
            }
            foreach ($demand as $code => $qty) {
                $this->componentStock->reserveForQuote($code, $qty, $quoteId);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $quote->setData(self::RESERVED_FLAG, true);
    }

    /**
     * @param Quote $quote
     * @return void
     */
    public function compensate(Quote $quote): void
    {
        $quoteId = (int) $quote->getId();
        $connection = $this->movimentacaoResource->getConnection();
        $connection->beginTransaction();
        try {
            $this->movimentacaoResource->lockQuote($quoteId);
            // Core also dispatches submit_failure after a persisted order's success observer fails.
            if (!$this->hasOrder($quoteId)) {
                $movements = $this->movimentacaoResource->getNetQuantitiesByQuoteId($quoteId);
                usort($movements, static fn (array $left, array $right): int => $left['codigo'] <=> $right['codigo']);
                foreach ($movements as $movement) {
                    if ($movement['net'] < 0) {
                        $this->componentStock->compensateForQuote($movement['codigo'], -$movement['net'], $quoteId);
                    }
                }
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $this->clearAttempt($quote);
    }

    /**
     * The flag is transient and belongs only to this submission attempt.
     *
     * @param Quote $quote
     * @return bool
     */
    public function hasReservation(Quote $quote): bool
    {
        return (bool) $quote->getData(self::RESERVED_FLAG);
    }

    /**
     * @param Quote $quote
     * @return void
     */
    public function clearAttempt(Quote $quote): void
    {
        $quote->unsetData(self::RESERVED_FLAG);
    }

    /**
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
     * @param int $quoteId
     * @return bool
     */
    private function hasOrder(int $quoteId): bool
    {
        $criteria = $this->searchCriteriaBuilder->addFilter('quote_id', $quoteId)->setPageSize(1)->create();
        return $this->orderRepository->getList($criteria)->getTotalCount() > 0;
    }
}
