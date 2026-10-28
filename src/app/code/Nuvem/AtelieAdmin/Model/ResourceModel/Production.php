<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Production extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('nuvem_atelie_ficha', 'ficha_id');
    }

    public function findById(int $sheetId): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()
                ->from($this->getMainTable())
                ->where('ficha_id = ?', $sheetId)
        );
        return $row === false ? null : $row;
    }

    public function updateStatuses(array $sheetIds, string $status): int
    {
        if (!$sheetIds) {
            return 0;
        }
        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => $status],
            ['ficha_id IN (?)' => $sheetIds]
        );
    }

    public function findByOrderItemId(int $orderItemId): ?array
    {
        $select = $this->getConnection()->select()
            ->from($this->getMainTable())
            ->where('order_item_id = ?', $orderItemId);
        $row = $this->getConnection()->fetchRow($select);
        return $row === false ? null : $row;
    }

    public function createSheet(int $orderId, int $orderItemId, string $content): array
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'status' => 'fila',
                'conteudo' => $content,
            ],
            ['order_item_id']
        );

        $sheet = $this->findByOrderItemId($orderItemId);
        if ($sheet === null) {
            throw new \RuntimeException('The production sheet could not be loaded after saving.');
        }
        return $sheet;
    }

    public function markEmailSent(int $sheetId): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['email_sent_at' => gmdate('Y-m-d H:i:s')],
            ['ficha_id = ?' => $sheetId]
        );
    }
}
