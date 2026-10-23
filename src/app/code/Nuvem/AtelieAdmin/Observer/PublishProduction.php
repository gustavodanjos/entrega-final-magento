<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieAdmin\Model\Queue\ProductionConsumer;
use Psr\Log\LoggerInterface;

class PublishProduction implements ObserverInterface
{
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface || !$order->getEntityId()) {
            return;
        }

        foreach ($order->getItems() ?? [] as $item) {
            if ($item->getProductType() !== Atelie::TYPE_CODE
                || $item->getParentItemId()
                || !$item->getItemId()
            ) {
                continue;
            }

            try {
                $this->publisher->publish(
                    ProductionConsumer::TOPIC,
                    (string) $item->getItemId()
                );
            } catch (\Throwable $exception) {
                $this->logger->critical(
                    'Atelie: failed to enqueue production. Republish this order item after restoring the broker.',
                    [
                        'order_id' => $order->getEntityId(),
                        'order_item_id' => $item->getItemId(),
                        'exception' => $exception,
                    ]
                );
            }
        }
    }
}
