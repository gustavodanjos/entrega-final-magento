<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;

class CompensateStockOnQuoteSubmitFailure implements ObserverInterface
{
    /**
     * @var ComponentStockInterface
     */
    private ComponentStockInterface $componentStock;

    /**
     * @var Movimentacao
     */
    private Movimentacao $movimentacaoResource;

    /**
     * @param ComponentStockInterface $componentStock
     * @param Movimentacao $movimentacaoResource
     */
    public function __construct(
        ComponentStockInterface $componentStock,
        Movimentacao $movimentacaoResource
    ) {
        $this->componentStock = $componentStock;
        $this->movimentacaoResource = $movimentacaoResource;
    }

    /**
     * Compensate the reservations of the failed quote
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if ($quote === null || !$quote->getId()) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        $orderId = $order !== null && $order->getId() ? (int) $order->getId() : null;
        $quoteId = (int) $quote->getId();

        foreach ($this->movimentacaoResource->getNetQuantitiesByQuoteId($quoteId) as $movement) {
            if ($movement['net'] >= 0) {
                continue;
            }

            $this->componentStock->compensate($movement['codigo'], abs($movement['net']), $orderId, $quoteId);
        }
    }
}
