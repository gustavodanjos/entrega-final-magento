<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProductionOutbox extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('nuvem_atelie_production_outbox', 'order_item_id');
    }

    public function enqueue(int $orderId, int $orderItemId): void
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            ['order_item_id' => $orderItemId, 'order_id' => $orderId],
            ['order_item_id']
        );
    }

    public function getPending(int $limit = 100): array
    {
        return $this->getConnection()->fetchAll(
            $this->getConnection()->select()
                ->from($this->getMainTable())
                ->where('published_at IS NULL')
                ->where('next_attempt_at <= ?', gmdate('Y-m-d H:i:s'))
                ->order(['next_attempt_at ASC', 'order_item_id ASC'])
                ->limit($limit)
        );
    }

    public function markPublished(int $orderItemId): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            [
                'published_at' => gmdate('Y-m-d H:i:s'),
                'attempts' => new \Zend_Db_Expr('attempts + 1'),
                'last_error' => null,
            ],
            ['order_item_id = ?' => $orderItemId, 'published_at IS NULL']
        );
    }

    public function markFailed(int $orderItemId, int $previousAttempts, string $error): void
    {
        $delay = min(3600, 60 * (2 ** min($previousAttempts, 6)));
        $this->getConnection()->update(
            $this->getMainTable(),
            [
                'attempts' => new \Zend_Db_Expr('attempts + 1'),
                'last_error' => mb_substr($error, 0, 1000),
                'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            ],
            ['order_item_id = ?' => $orderItemId, 'published_at IS NULL']
        );
    }
}
