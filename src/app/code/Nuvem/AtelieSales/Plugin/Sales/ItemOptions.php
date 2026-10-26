<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Sales;

use Magento\Sales\Block\Adminhtml\Items\Column\DefaultColumn;
use Magento\Sales\Block\Order\Item\Renderer\DefaultRenderer;
use Magento\Sales\Model\Order\Item;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieSales\Model\Order\ItemOptionsReader;

class ItemOptions
{
    public function __construct(private readonly ItemOptionsReader $reader)
    {
    }

    /**
     * Read the original order item for account order, invoice and credit memo renderers.
     *
     * @param DefaultRenderer $subject
     * @param array $result
     * @return array
     */
    public function afterGetItemOptions(DefaultRenderer $subject, array $result): array
    {
        $item = $subject->getOrderItem();
        return $item instanceof Item && $item->getProductType() === Atelie::TYPE_CODE
            ? $this->reader->read($item)
            : $result;
    }

    /**
     * Keep native admin columns while normalizing the Atelie options.
     *
     * @param DefaultColumn $subject
     * @param array $result
     * @return array
     */
    public function afterGetOrderOptions(DefaultColumn $subject, array $result): array
    {
        $item = $subject->getItem();
        return $item instanceof Item && $item->getProductType() === Atelie::TYPE_CODE
            ? $this->reader->read($item)
            : $result;
    }
}
