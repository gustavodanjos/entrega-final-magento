<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\ViewModel\Order;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Nuvem\AtelieSales\Model\Order\ItemOptionsReader;

class ItemOptions implements ArgumentInterface
{
    public function __construct(private readonly ItemOptionsReader $reader)
    {
    }

    /**
     * Get unescaped historical rows for the email template.
     *
     * @param Item|InvoiceItem|CreditmemoItem $item
     * @return array
     */
    public function getRows(Item|InvoiceItem|CreditmemoItem $item): array
    {
        return $this->reader->read($item);
    }
}
