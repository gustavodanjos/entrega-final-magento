<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;

class ReserveStockOnQuoteSubmitBefore implements ObserverInterface
{
    /**
     * @var ComponentStockInterface
     */
    private ComponentStockInterface $componentStock;

    /**
     * @var QuoteItemConfigurationReader
     */
    private QuoteItemConfigurationReader $configurationReader;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @param ComponentStockInterface $componentStock
     * @param QuoteItemConfigurationReader $configurationReader
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ComponentStockInterface $componentStock,
        QuoteItemConfigurationReader $configurationReader,
        ResourceConnection $resourceConnection
    ) {
        $this->componentStock = $componentStock;
        $this->configurationReader = $configurationReader;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Reserve stock for all atelie components of the quote
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

        $demand = [];
        foreach ($quote->getAllItems() as $item) {
            foreach ($this->configurationReader->getComponentQuantities($item) as $componentCode => $qty) {
                $demand[$componentCode] = ($demand[$componentCode] ?? 0) + $qty;
            }
        }

        if ($demand === []) {
            return;
        }

        $quoteId = (int) $quote->getId();
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($demand as $componentCode => $qty) {
                $this->componentStock->reserve($componentCode, $qty, null, null, $quoteId);
            }
            $connection->commit();
        } catch (\Exception $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
