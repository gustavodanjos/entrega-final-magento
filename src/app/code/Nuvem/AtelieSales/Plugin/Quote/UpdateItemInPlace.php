<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Quote;

use Magento\Framework\DataObject;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\Quote;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieSales\Model\Cart\ItemUpdater;

class UpdateItemInPlace
{
    public function __construct(
        private readonly ItemUpdater $updater,
        private readonly RequestInterface $request
    ) {
    }

    public function aroundUpdateItem(Quote $subject, callable $proceed, $itemId, $buyRequest, $params = null)
    {
        if (strtolower($this->request->getFullActionName()) !== 'checkout_cart_updateitemoptions') {
            return $proceed($itemId, $buyRequest, $params);
        }
        $item = $subject->getItemById($itemId);
        if (!$item || $item->getProductType() !== Atelie::TYPE_CODE) {
            return $proceed($itemId, $buyRequest, $params);
        }
        return $this->updater->update(
            $subject,
            $item,
            $buyRequest instanceof DataObject ? $buyRequest : new DataObject((array) $buyRequest)
        );
    }
}
