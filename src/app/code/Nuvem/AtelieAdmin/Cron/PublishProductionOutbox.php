<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Cron;

use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Nuvem\AtelieAdmin\Model\Queue\ProductionConsumer;
use Nuvem\AtelieAdmin\Model\ResourceModel\ProductionOutbox;
use Psr\Log\LoggerInterface;

class PublishProductionOutbox
{
    private const LOCK_NAME = 'nuvem_production_outbox_publish';

    public function __construct(
        private readonly ProductionOutbox $outbox,
        private readonly PublisherInterface $publisher,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            return;
        }

        try {
            foreach ($this->outbox->getPending() as $entry) {
                $orderItemId = (int) $entry['order_item_id'];

                try {
                    $this->publisher->publish(ProductionConsumer::TOPIC, (string) $orderItemId);
                    $this->outbox->markPublished($orderItemId);
                } catch (\Throwable $exception) {
                    $this->outbox->markFailed(
                        $orderItemId,
                        (int) $entry['attempts'],
                        $exception->getMessage()
                    );
                    $this->logger->error(
                        'Atelie: production publication failed; the outbox retains the item for retry.',
                        ['order_item_id' => $orderItemId, 'exception' => $exception]
                    );
                    break;
                }
            }
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }
}
