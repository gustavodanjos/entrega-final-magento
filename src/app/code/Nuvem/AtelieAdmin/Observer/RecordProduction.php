<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieAdmin\Model\ResourceModel\ProductionOutbox;

class RecordProduction implements ObserverInterface
{
    public function __construct(
        private readonly ProductionOutbox $outbox
    ) {
    }

    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getItem();
        if (!$item instanceof OrderItemInterface
            || $item->getProductType() !== Atelie::TYPE_CODE
            || $item->getParentItemId()
            || !$item->getItemId()
            || !$item->getOrderId()
        ) {
            return;
        }

        $this->outbox->enqueue((int) $item->getOrderId(), (int) $item->getItemId());
    }
}
